<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\KnowledgeBase;

use App\Domain\KnowledgeBase\Entities\KbArticle;
use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\KbArticle as EloquentKbArticle;
use App\Models\KbArticleFeedback as EloquentKbArticleFeedback;
use App\Models\KbCategory as EloquentKbCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DateTimeImmutable;

class EloquentKbArticleRepository implements KbArticleRepositoryInterface
{
    public function findById(int $id): ?KbArticle
    {
        $model = EloquentKbArticle::find($id);
        if (!$model) {
            return null;
        }

        return KbArticle::reconstitute(
            id: $model->id,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at->toDateTimeString()) : null,
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );
    }

    public function findAll(): array
    {
        return EloquentKbArticle::all()->map(function ($model) {
            return KbArticle::reconstitute(
                id: $model->id,
                createdAt: $model->created_at ? new DateTimeImmutable($model->created_at->toDateTimeString()) : null,
                updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
            );
        })->toArray();
    }

    public function save(KbArticle $entity): KbArticle
    {
        // TODO: Implement proper entity persistence
        return $entity;
    }

    public function delete(int $id): bool
    {
        return EloquentKbArticle::destroy($id) > 0;
    }

    // Article query methods
    public function listArticles(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = EloquentKbArticle::query()
            ->with(['category', 'author']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['published_only']) && $filters['published_only']) {
            $query->published();
        }

        if (isset($filters['public_only']) && $filters['public_only']) {
            $query->public();
        }

        if (!empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items()->map(fn($item) => $this->modelToArray($item))->toArray(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
        );
    }

    public function getArticle(int $id): ?array
    {
        $article = EloquentKbArticle::with(['category', 'author'])->find($id);
        return $article ? $this->modelToArray($article) : null;
    }

    public function getArticleBySlug(string $slug): ?array
    {
        $article = EloquentKbArticle::where('slug', $slug)
            ->with(['category', 'author'])
            ->first();
        return $article ? $this->modelToArray($article) : null;
    }

    public function getArticleForViewing(int $id): ?array
    {
        $article = EloquentKbArticle::with(['category', 'author'])
            ->published()
            ->find($id);

        if ($article) {
            $article->incrementViews();
            return $this->modelToArray($article->fresh());
        }

        return null;
    }

    public function searchArticles(string $query, array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $search = EloquentKbArticle::query()
            ->with(['category', 'author'])
            ->search($query);

        if (isset($filters['published_only']) && $filters['published_only']) {
            $search->published();
        }

        if (isset($filters['public_only']) && $filters['public_only']) {
            $search->public();
        }

        if (!empty($filters['category_id'])) {
            $search->where('category_id', $filters['category_id']);
        }

        // Order by relevance (title matches first)
        $search->orderByRaw("CASE WHEN title ILIKE ? THEN 0 ELSE 1 END", ["%{$query}%"])
            ->orderBy('view_count', 'desc');

        $paginator = $search->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items()->map(fn($item) => $this->modelToArray($item))->toArray(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
        );
    }

    public function getPopularArticles(int $limit = 10, bool $publishedOnly = true): array
    {
        $query = EloquentKbArticle::query()
            ->with(['category', 'author'])
            ->orderBy('view_count', 'desc')
            ->limit($limit);

        if ($publishedOnly) {
            $query->published();
        }

        return $query->get()->map(fn($item) => $this->modelToArray($item))->toArray();
    }

    public function getRecentArticles(int $limit = 10, bool $publishedOnly = true): array
    {
        $query = EloquentKbArticle::query()
            ->with(['category', 'author'])
            ->orderBy('published_at', 'desc')
            ->limit($limit);

        if ($publishedOnly) {
            $query->published()->whereNotNull('published_at');
        }

        return $query->get()->map(fn($item) => $this->modelToArray($item))->toArray();
    }

    public function getRelatedArticles(int $articleId, int $limit = 5): array
    {
        $article = EloquentKbArticle::findOrFail($articleId);

        $query = EloquentKbArticle::query()
            ->with(['category', 'author'])
            ->published()
            ->where('id', '!=', $articleId)
            ->where(function ($q) use ($article) {
                // Same category
                $q->where('category_id', $article->category_id);

                // Or matching tags
                if (!empty($article->tags)) {
                    foreach ($article->tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                }
            })
            ->orderBy('view_count', 'desc')
            ->limit($limit);

        return $query->get()->map(fn($item) => $this->modelToArray($item))->toArray();
    }

    public function getAllTags(): array
    {
        $articles = EloquentKbArticle::whereNotNull('tags')->get();
        $tags = [];

        foreach ($articles as $article) {
            foreach ($article->tags ?? [] as $tag) {
                if (!in_array($tag, $tags)) {
                    $tags[] = $tag;
                }
            }
        }

        sort($tags);
        return $tags;
    }

    // Article command methods
    public function createArticle(array $data): array
    {
        $article = EloquentKbArticle::create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'content' => $data['content'],
            'excerpt' => $data['excerpt'] ?? $this->generateExcerpt($data['content']),
            'category_id' => $data['category_id'],
            'status' => $data['status'] ?? 'draft',
            'author_id' => $data['author_id'],
            'tags' => $data['tags'] ?? [],
            'is_public' => $data['is_public'] ?? true,
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);

        return $this->modelToArray($article->fresh(['category', 'author']));
    }

    public function updateArticle(int $id, array $data): array
    {
        $article = EloquentKbArticle::findOrFail($id);

        $wasPublished = $article->isPublished();
        $willBePublished = ($data['status'] ?? $article->status) === 'published';

        $article->update([
            'title' => $data['title'] ?? $article->title,
            'slug' => $data['slug'] ?? $article->slug,
            'content' => $data['content'] ?? $article->content,
            'excerpt' => $data['excerpt'] ?? ($data['content'] ? $this->generateExcerpt($data['content']) : $article->excerpt),
            'category_id' => $data['category_id'] ?? $article->category_id,
            'status' => $data['status'] ?? $article->status,
            'tags' => $data['tags'] ?? $article->tags,
            'is_public' => $data['is_public'] ?? $article->is_public,
            'published_at' => !$wasPublished && $willBePublished ? now() : $article->published_at,
        ]);

        return $this->modelToArray($article->fresh(['category', 'author']));
    }

    public function publishArticle(int $id): array
    {
        $article = EloquentKbArticle::findOrFail($id);
        $article->publish();
        return $this->modelToArray($article->fresh(['category', 'author']));
    }

    public function unpublishArticle(int $id): array
    {
        $article = EloquentKbArticle::findOrFail($id);
        $article->unpublish();
        return $this->modelToArray($article->fresh(['category', 'author']));
    }

    public function archiveArticle(int $id): array
    {
        $article = EloquentKbArticle::findOrFail($id);
        $article->update(['status' => 'archived']);
        return $this->modelToArray($article->fresh(['category', 'author']));
    }

    public function deleteArticle(int $id): void
    {
        $article = EloquentKbArticle::findOrFail($id);

        DB::transaction(function () use ($article) {
            $article->feedback()->delete();
            $article->delete();
        });
    }

    public function duplicateArticle(int $id, int $authorId): array
    {
        $original = EloquentKbArticle::findOrFail($id);

        $duplicate = EloquentKbArticle::create([
            'title' => $original->title . ' (Copy)',
            'slug' => $original->slug . '-copy-' . time(),
            'content' => $original->content,
            'excerpt' => $original->excerpt,
            'category_id' => $original->category_id,
            'status' => 'draft',
            'author_id' => $authorId,
            'tags' => $original->tags,
            'is_public' => $original->is_public,
        ]);

        return $this->modelToArray($duplicate->fresh(['category', 'author']));
    }

    public function moveArticle(int $id, int $categoryId): array
    {
        $article = EloquentKbArticle::findOrFail($id);
        $article->update(['category_id' => $categoryId]);
        return $this->modelToArray($article->fresh(['category', 'author']));
    }

    public function incrementArticleViews(int $id): void
    {
        $article = EloquentKbArticle::find($id);
        if ($article) {
            $article->incrementViews();
        }
    }

    // Feedback methods
    public function submitFeedback(int $articleId, array $data): array
    {
        $article = EloquentKbArticle::findOrFail($articleId);

        $feedback = EloquentKbArticleFeedback::create([
            'article_id' => $articleId,
            'is_helpful' => $data['is_helpful'],
            'comment' => $data['comment'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'portal_user_id' => $data['portal_user_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);

        // Update article counts
        if ($data['is_helpful']) {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }

        return $feedback->toArray();
    }

    public function getArticleFeedback(int $articleId, int $perPage = 20, int $page = 1): PaginatedResult
    {
        $paginator = EloquentKbArticleFeedback::where('article_id', $articleId)
            ->with(['user', 'portalUser'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items()->map(fn($item) => $item->toArray())->toArray(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
        );
    }

    public function getArticleFeedbackSummary(int $articleId): array
    {
        $article = EloquentKbArticle::findOrFail($articleId);

        return [
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
            'total_feedback' => $article->helpful_count + $article->not_helpful_count,
            'helpfulness_percentage' => $article->getHelpfulnessPercentage(),
            'recent_comments' => EloquentKbArticleFeedback::where('article_id', $articleId)
                ->whereNotNull('comment')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->pluck('comment')
                ->toArray(),
        ];
    }

    // Analytics methods
    public function getStatistics(): array
    {
        return [
            'total_articles' => EloquentKbArticle::count(),
            'published_articles' => EloquentKbArticle::published()->count(),
            'draft_articles' => EloquentKbArticle::draft()->count(),
            'total_categories' => EloquentKbCategory::count(),
            'total_views' => EloquentKbArticle::sum('view_count'),
            'total_feedback' => EloquentKbArticleFeedback::count(),
            'helpful_feedback' => EloquentKbArticleFeedback::where('is_helpful', true)->count(),
            'avg_helpfulness' => EloquentKbArticle::whereRaw('helpful_count + not_helpful_count > 0')
                ->selectRaw('AVG(helpful_count::float / (helpful_count + not_helpful_count) * 100) as avg')
                ->value('avg'),
        ];
    }

    public function getArticlesNeedingReview(float $threshold = 50, int $minFeedback = 5): array
    {
        return EloquentKbArticle::query()
            ->with(['category', 'author'])
            ->published()
            ->whereRaw('helpful_count + not_helpful_count >= ?', [$minFeedback])
            ->whereRaw('(helpful_count::float / (helpful_count + not_helpful_count) * 100) < ?', [$threshold])
            ->orderByRaw('helpful_count::float / (helpful_count + not_helpful_count)')
            ->limit(20)
            ->get()
            ->map(fn($item) => $this->modelToArray($item))
            ->toArray();
    }

    public function getTopPerformingArticles(int $limit = 10): array
    {
        return EloquentKbArticle::query()
            ->with(['category', 'author'])
            ->published()
            ->whereRaw('helpful_count + not_helpful_count >= 5')
            ->orderByRaw('helpful_count::float / (helpful_count + not_helpful_count) DESC')
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($article) {
                $data = $this->modelToArray($article);
                $data['helpfulness'] = $article->getHelpfulnessPercentage();
                return $data;
            })
            ->toArray();
    }

    public function getViewTrends(string $period = 'month'): array
    {
        $dateFrom = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            default => now()->subMonth(),
        };

        // Get daily views
        $views = EloquentKbArticle::query()
            ->selectRaw("DATE(updated_at) as date, SUM(view_count) as views")
            ->where('updated_at', '>=', $dateFrom)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('views', 'date')
            ->toArray();

        // Get top viewed in period
        $topViewed = EloquentKbArticle::query()
            ->with('category')
            ->published()
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'view_count', 'category_id'])
            ->map(fn($item) => $this->modelToArray($item))
            ->toArray();

        return [
            'period' => $period,
            'date_from' => $dateFrom->toIso8601String(),
            'daily_views' => $views,
            'top_viewed' => $topViewed,
        ];
    }

    // Category methods
    public function listCategories(array $filters = []): array
    {
        $query = EloquentKbCategory::query()
            ->withCount('articles')
            ->withCount(['articles as published_articles_count' => function ($q) {
                $q->where('status', 'published');
            }]);

        if (isset($filters['public_only']) && $filters['public_only']) {
            $query->where('is_public', true);
        }

        if (!empty($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        if (isset($filters['top_level']) && $filters['top_level']) {
            $query->whereNull('parent_id');
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['search']}%")
                    ->orWhere('description', 'ilike', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('display_order')->orderBy('name')->get()->map(fn($item) => $item->toArray())->toArray();
    }

    public function getCategoryTree(bool $publicOnly = false): array
    {
        $query = EloquentKbCategory::with(['children' => function ($q) use ($publicOnly) {
            if ($publicOnly) {
                $q->where('is_public', true);
            }
            $q->orderBy('display_order')->orderBy('name');
        }])
            ->withCount(['articles as published_articles_count' => function ($q) {
                $q->where('status', 'published');
            }])
            ->whereNull('parent_id');

        if ($publicOnly) {
            $query->where('is_public', true);
        }

        return $query->orderBy('display_order')->orderBy('name')->get()->map(fn($item) => $item->toArray())->toArray();
    }

    public function getCategory(int $id): ?array
    {
        $category = EloquentKbCategory::with(['parent', 'children'])
            ->withCount('articles')
            ->find($id);

        return $category ? $category->toArray() : null;
    }

    public function getCategoryBySlug(string $slug): ?array
    {
        $category = EloquentKbCategory::where('slug', $slug)
            ->with(['parent', 'children'])
            ->withCount('articles')
            ->first();

        return $category ? $category->toArray() : null;
    }

    public function getCategoryWithArticles(int $id, bool $publishedOnly = false, int $perPage = 15, int $page = 1): array
    {
        $category = EloquentKbCategory::findOrFail($id);

        $query = $category->articles()
            ->with('author')
            ->orderBy('title');

        if ($publishedOnly) {
            $query->published();
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'category' => $category->toArray(),
            'articles' => PaginatedResult::create(
                items: $paginator->items()->map(fn($item) => $this->modelToArray($item))->toArray(),
                total: $paginator->total(),
                perPage: $paginator->perPage(),
                currentPage: $paginator->currentPage(),
            ),
        ];
    }

    public function createCategory(array $data): array
    {
        $category = EloquentKbCategory::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'is_public' => $data['is_public'] ?? true,
        ]);

        return $category->toArray();
    }

    public function updateCategory(int $id, array $data): array
    {
        $category = EloquentKbCategory::findOrFail($id);

        // Prevent circular parent reference
        if (!empty($data['parent_id']) && $data['parent_id'] == $id) {
            throw new \InvalidArgumentException('A category cannot be its own parent');
        }

        $category->update([
            'name' => $data['name'] ?? $category->name,
            'slug' => $data['slug'] ?? $category->slug,
            'description' => $data['description'] ?? $category->description,
            'icon' => $data['icon'] ?? $category->icon,
            'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id,
            'display_order' => $data['display_order'] ?? $category->display_order,
            'is_public' => $data['is_public'] ?? $category->is_public,
        ]);

        return $category->fresh()->toArray();
    }

    public function deleteCategory(int $id, ?int $moveArticlesToCategoryId = null): void
    {
        $category = EloquentKbCategory::findOrFail($id);

        DB::transaction(function () use ($category, $moveArticlesToCategoryId) {
            if ($moveArticlesToCategoryId) {
                // Move articles to another category
                $category->articles()->update(['category_id' => $moveArticlesToCategoryId]);
            } else {
                // Delete all articles in this category
                $category->articles()->delete();
            }

            // Move child categories to parent
            if ($category->parent_id) {
                $category->children()->update(['parent_id' => $category->parent_id]);
            } else {
                $category->children()->update(['parent_id' => null]);
            }

            $category->delete();
        });
    }

    public function reorderCategories(array $categoryIds): void
    {
        foreach ($categoryIds as $order => $categoryId) {
            EloquentKbCategory::where('id', $categoryId)
                ->update(['display_order' => $order]);
        }
    }

    public function getCategoryPerformance(): array
    {
        return EloquentKbCategory::query()
            ->withCount(['articles as total_articles'])
            ->withCount(['articles as published_articles' => function ($q) {
                $q->where('status', 'published');
            }])
            ->withSum('articles', 'view_count')
            ->withSum('articles', 'helpful_count')
            ->withSum('articles', 'not_helpful_count')
            ->orderBy('articles_sum_view_count', 'desc')
            ->get()
            ->map(function ($category) {
                $data = $category->toArray();
                $total = ($category->articles_sum_helpful_count ?? 0) + ($category->articles_sum_not_helpful_count ?? 0);
                $data['helpfulness'] = $total > 0
                    ? round(($category->articles_sum_helpful_count / $total) * 100, 1)
                    : null;
                return $data;
            })
            ->toArray();
    }

    public function getSearchAnalytics(): array
    {
        // This would typically pull from a search log table
        // For now, return tag-based insights
        $tagUsage = [];
        $articles = EloquentKbArticle::whereNotNull('tags')->get();

        foreach ($articles as $article) {
            foreach ($article->tags ?? [] as $tag) {
                if (!isset($tagUsage[$tag])) {
                    $tagUsage[$tag] = [
                        'count' => 0,
                        'total_views' => 0,
                    ];
                }
                $tagUsage[$tag]['count']++;
                $tagUsage[$tag]['total_views'] += $article->view_count;
            }
        }

        // Sort by total views
        uasort($tagUsage, fn ($a, $b) => $b['total_views'] <=> $a['total_views']);

        return [
            'popular_tags' => array_slice($tagUsage, 0, 20, true),
            'articles_without_tags' => EloquentKbArticle::published()
                ->where(function ($q) {
                    $q->whereNull('tags')->orWhere('tags', '[]');
                })
                ->count(),
        ];
    }

    // Helper methods
    private function generateExcerpt(string $content, int $length = 200): string
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    private function modelToArray($model): array
    {
        $data = $model->toArray();

        // Ensure consistent array format for relationships
        if (isset($data['category']) && is_object($data['category'])) {
            $data['category'] = $data['category']->toArray();
        }
        if (isset($data['author']) && is_object($data['author'])) {
            $data['author'] = $data['author']->toArray();
        }

        return $data;
    }
}
