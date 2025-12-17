<?php

declare(strict_types=1);

namespace App\Application\Services\KnowledgeBase;

use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use App\Models\KbArticle;
use App\Models\KbArticleFeedback;
use App\Models\KbCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeBaseApplicationService
{
    public function __construct(
        private KbArticleRepositoryInterface $repository,
    ) {}

    // ==========================================
    // CATEGORY QUERY USE CASES
    // ==========================================

    /**
     * List all categories.
     */
    public function listCategories(array $filters = []): Collection
    {
        $query = KbCategory::query()
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

        return $query->orderBy('display_order')->orderBy('name')->get();
    }

    /**
     * Get category tree.
     */
    public function getCategoryTree(bool $publicOnly = false): Collection
    {
        $query = KbCategory::with(['children' => function ($q) use ($publicOnly) {
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

        return $query->orderBy('display_order')->orderBy('name')->get();
    }

    /**
     * Get a single category.
     */
    public function getCategory(int $id): ?KbCategory
    {
        return KbCategory::with(['parent', 'children'])
            ->withCount('articles')
            ->find($id);
    }

    /**
     * Get category by slug.
     */
    public function getCategoryBySlug(string $slug): ?KbCategory
    {
        return KbCategory::where('slug', $slug)
            ->with(['parent', 'children'])
            ->withCount('articles')
            ->first();
    }

    /**
     * Get category with articles.
     */
    public function getCategoryWithArticles(int $id, bool $publishedOnly = false, int $perPage = 15): array
    {
        $category = KbCategory::findOrFail($id);

        $query = $category->articles()
            ->with('author')
            ->orderBy('title');

        if ($publishedOnly) {
            $query->published();
        }

        return [
            'category' => $category,
            'articles' => $query->paginate($perPage),
        ];
    }

    // ==========================================
    // CATEGORY COMMAND USE CASES
    // ==========================================

    /**
     * Create a category.
     */
    public function createCategory(array $data): KbCategory
    {
        return KbCategory::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'is_public' => $data['is_public'] ?? true,
        ]);
    }

    /**
     * Update a category.
     */
    public function updateCategory(int $id, array $data): KbCategory
    {
        $category = KbCategory::findOrFail($id);

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

        return $category->fresh();
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(int $id, ?int $moveArticlesToCategoryId = null): void
    {
        $category = KbCategory::findOrFail($id);

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

    /**
     * Reorder categories.
     */
    public function reorderCategories(array $categoryIds): void
    {
        foreach ($categoryIds as $order => $categoryId) {
            KbCategory::where('id', $categoryId)
                ->update(['display_order' => $order]);
        }
    }

    // ==========================================
    // ARTICLE QUERY USE CASES
    // ==========================================

    /**
     * List articles with filtering and pagination.
     */
    public function listArticles(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = KbArticle::query()
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

        return $query->paginate($perPage);
    }

    /**
     * Get a single article.
     */
    public function getArticle(int $id): ?KbArticle
    {
        return KbArticle::with(['category', 'author'])->find($id);
    }

    /**
     * Get article by slug.
     */
    public function getArticleBySlug(string $slug): ?KbArticle
    {
        return KbArticle::where('slug', $slug)
            ->with(['category', 'author'])
            ->first();
    }

    /**
     * Get article for public viewing (increments view count).
     */
    public function getArticleForViewing(int $id): ?KbArticle
    {
        $article = KbArticle::with(['category', 'author'])
            ->published()
            ->find($id);

        if ($article) {
            $article->incrementViews();
        }

        return $article;
    }

    /**
     * Search articles.
     */
    public function searchArticles(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = KbArticle::query()
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

        return $search->paginate($perPage);
    }

    /**
     * Get popular articles.
     */
    public function getPopularArticles(int $limit = 10, bool $publishedOnly = true): Collection
    {
        $query = KbArticle::query()
            ->with(['category', 'author'])
            ->orderBy('view_count', 'desc')
            ->limit($limit);

        if ($publishedOnly) {
            $query->published();
        }

        return $query->get();
    }

    /**
     * Get recent articles.
     */
    public function getRecentArticles(int $limit = 10, bool $publishedOnly = true): Collection
    {
        $query = KbArticle::query()
            ->with(['category', 'author'])
            ->orderBy('published_at', 'desc')
            ->limit($limit);

        if ($publishedOnly) {
            $query->published()->whereNotNull('published_at');
        }

        return $query->get();
    }

    /**
     * Get related articles.
     */
    public function getRelatedArticles(int $articleId, int $limit = 5): Collection
    {
        $article = KbArticle::findOrFail($articleId);

        return KbArticle::query()
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
            ->limit($limit)
            ->get();
    }

    /**
     * Get all unique tags.
     */
    public function getAllTags(): array
    {
        $articles = KbArticle::whereNotNull('tags')->get();
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

    // ==========================================
    // ARTICLE COMMAND USE CASES
    // ==========================================

    /**
     * Create an article.
     */
    public function createArticle(array $data): KbArticle
    {
        return KbArticle::create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'content' => $data['content'],
            'excerpt' => $data['excerpt'] ?? $this->generateExcerpt($data['content']),
            'category_id' => $data['category_id'],
            'status' => $data['status'] ?? 'draft',
            'author_id' => $data['author_id'] ?? Auth::id(),
            'tags' => $data['tags'] ?? [],
            'is_public' => $data['is_public'] ?? true,
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);
    }

    /**
     * Update an article.
     */
    public function updateArticle(int $id, array $data): KbArticle
    {
        $article = KbArticle::findOrFail($id);

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

        return $article->fresh();
    }

    /**
     * Publish an article.
     */
    public function publishArticle(int $id): KbArticle
    {
        $article = KbArticle::findOrFail($id);
        $article->publish();
        return $article->fresh();
    }

    /**
     * Unpublish an article.
     */
    public function unpublishArticle(int $id): KbArticle
    {
        $article = KbArticle::findOrFail($id);
        $article->unpublish();
        return $article->fresh();
    }

    /**
     * Archive an article.
     */
    public function archiveArticle(int $id): KbArticle
    {
        $article = KbArticle::findOrFail($id);
        $article->update(['status' => 'archived']);
        return $article->fresh();
    }

    /**
     * Delete an article.
     */
    public function deleteArticle(int $id): void
    {
        $article = KbArticle::findOrFail($id);

        DB::transaction(function () use ($article) {
            $article->feedback()->delete();
            $article->delete();
        });
    }

    /**
     * Duplicate an article.
     */
    public function duplicateArticle(int $id): KbArticle
    {
        $original = KbArticle::findOrFail($id);

        return KbArticle::create([
            'title' => $original->title . ' (Copy)',
            'slug' => $original->slug . '-copy-' . time(),
            'content' => $original->content,
            'excerpt' => $original->excerpt,
            'category_id' => $original->category_id,
            'status' => 'draft',
            'author_id' => Auth::id(),
            'tags' => $original->tags,
            'is_public' => $original->is_public,
        ]);
    }

    /**
     * Move article to another category.
     */
    public function moveArticle(int $id, int $categoryId): KbArticle
    {
        $article = KbArticle::findOrFail($id);
        $article->update(['category_id' => $categoryId]);
        return $article->fresh(['category']);
    }

    // ==========================================
    // FEEDBACK USE CASES
    // ==========================================

    /**
     * Submit feedback for an article.
     */
    public function submitFeedback(int $articleId, array $data): KbArticleFeedback
    {
        $article = KbArticle::findOrFail($articleId);

        $feedback = KbArticleFeedback::create([
            'article_id' => $articleId,
            'is_helpful' => $data['is_helpful'],
            'comment' => $data['comment'] ?? null,
            'user_id' => Auth::id(),
            'portal_user_id' => $data['portal_user_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);

        // Update article counts
        if ($data['is_helpful']) {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }

        return $feedback;
    }

    /**
     * Get feedback for an article.
     */
    public function getArticleFeedback(int $articleId, int $perPage = 20): LengthAwarePaginator
    {
        return KbArticleFeedback::where('article_id', $articleId)
            ->with(['user', 'portalUser'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get feedback summary for an article.
     */
    public function getArticleFeedbackSummary(int $articleId): array
    {
        $article = KbArticle::findOrFail($articleId);

        return [
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
            'total_feedback' => $article->helpful_count + $article->not_helpful_count,
            'helpfulness_percentage' => $article->getHelpfulnessPercentage(),
            'recent_comments' => KbArticleFeedback::where('article_id', $articleId)
                ->whereNotNull('comment')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->pluck('comment'),
        ];
    }

    // ==========================================
    // ANALYTICS USE CASES
    // ==========================================

    /**
     * Get knowledge base statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_articles' => KbArticle::count(),
            'published_articles' => KbArticle::published()->count(),
            'draft_articles' => KbArticle::draft()->count(),
            'total_categories' => KbCategory::count(),
            'total_views' => KbArticle::sum('view_count'),
            'total_feedback' => KbArticleFeedback::count(),
            'helpful_feedback' => KbArticleFeedback::where('is_helpful', true)->count(),
            'avg_helpfulness' => KbArticle::whereRaw('helpful_count + not_helpful_count > 0')
                ->selectRaw('AVG(helpful_count::float / (helpful_count + not_helpful_count) * 100) as avg')
                ->value('avg'),
        ];
    }

    /**
     * Get articles needing review (low helpfulness).
     */
    public function getArticlesNeedingReview(float $threshold = 50, int $minFeedback = 5): Collection
    {
        return KbArticle::query()
            ->with(['category', 'author'])
            ->published()
            ->whereRaw('helpful_count + not_helpful_count >= ?', [$minFeedback])
            ->whereRaw('(helpful_count::float / (helpful_count + not_helpful_count) * 100) < ?', [$threshold])
            ->orderByRaw('helpful_count::float / (helpful_count + not_helpful_count)')
            ->limit(20)
            ->get();
    }

    /**
     * Get top performing articles.
     */
    public function getTopPerformingArticles(int $limit = 10): Collection
    {
        return KbArticle::query()
            ->with(['category', 'author'])
            ->published()
            ->whereRaw('helpful_count + not_helpful_count >= 5')
            ->orderByRaw('helpful_count::float / (helpful_count + not_helpful_count) DESC')
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($article) {
                $article->helpfulness = $article->getHelpfulnessPercentage();
                return $article;
            });
    }

    /**
     * Get articles by view trend.
     */
    public function getViewTrends(string $period = 'month'): array
    {
        $dateFrom = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            default => now()->subMonth(),
        };

        // Get daily views
        $views = KbArticle::query()
            ->selectRaw("DATE(updated_at) as date, SUM(view_count) as views")
            ->where('updated_at', '>=', $dateFrom)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('views', 'date')
            ->toArray();

        // Get top viewed in period
        $topViewed = KbArticle::query()
            ->with('category')
            ->published()
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'view_count', 'category_id']);

        return [
            'period' => $period,
            'date_from' => $dateFrom->toIso8601String(),
            'daily_views' => $views,
            'top_viewed' => $topViewed,
        ];
    }

    /**
     * Get category performance.
     */
    public function getCategoryPerformance(): Collection
    {
        return KbCategory::query()
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
                $total = ($category->articles_sum_helpful_count ?? 0) + ($category->articles_sum_not_helpful_count ?? 0);
                $category->helpfulness = $total > 0
                    ? round(($category->articles_sum_helpful_count / $total) * 100, 1)
                    : null;
                return $category;
            });
    }

    /**
     * Get search analytics.
     */
    public function getSearchAnalytics(): array
    {
        // This would typically pull from a search log table
        // For now, return tag-based insights
        $tagUsage = [];
        $articles = KbArticle::whereNotNull('tags')->get();

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
            'articles_without_tags' => KbArticle::published()
                ->where(function ($q) {
                    $q->whereNull('tags')->orWhere('tags', '[]');
                })
                ->count(),
        ];
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Generate excerpt from content.
     */
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
}
