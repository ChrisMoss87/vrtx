<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\KnowledgeBase;

use App\Domain\KnowledgeBase\Entities\KbArticle;
use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DateTimeImmutable;
use stdClass;

class DbKbArticleRepository implements KbArticleRepositoryInterface
{
    private const TABLE_KB_ARTICLES = 'kb_articles';
    private const TABLE_KB_CATEGORIES = 'kb_categories';
    private const TABLE_KB_ARTICLE_FEEDBACK = 'kb_article_feedback';
    private const TABLE_USERS = 'users';
    private const TABLE_PORTAL_USERS = 'portal_users';

    public function findById(int $id): ?KbArticle
    {
        $model = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        if (!$model) {
            return null;
        }

        return KbArticle::reconstitute(
            id: $model->id,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at) : null,
        );
    }

    public function findAll(): array
    {
        $models = DB::table(self::TABLE_KB_ARTICLES)->get();

        return array_map(function ($model) {
            return KbArticle::reconstitute(
                id: $model->id,
                createdAt: $model->created_at ? new DateTimeImmutable($model->created_at) : null,
                updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at) : null,
            );
        }, $models->toArray());
    }

    public function save(KbArticle $entity): KbArticle
    {
        // TODO: Implement proper entity persistence
        return $entity;
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE_KB_ARTICLES)->where('id', $id)->delete() > 0;
    }

    // Article query methods
    public function listArticles(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_KB_ARTICLES);

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
            $query->where('status', 'published');
        }

        if (isset($filters['public_only']) && $filters['public_only']) {
            $query->where('is_public', true);
        }

        if (!empty($filters['tag'])) {
            $query->whereRaw("tags::jsonb @> ?", [json_encode($filters['tag'])]);
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('title', 'ilike', "%{$term}%")
                    ->orWhere('content', 'ilike', "%{$term}%");
            });
        }

        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        // Manual pagination
        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $itemsWithRelations = array_map(function ($item) {
            return $this->toArrayWithRelations($item);
        }, $items->toArray());

        return PaginatedResult::create(
            items: $itemsWithRelations,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function getArticle(int $id): ?array
    {
        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        return $article ? $this->toArrayWithRelations($article) : null;
    }

    public function getArticleBySlug(string $slug): ?array
    {
        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('slug', $slug)
            ->first();

        return $article ? $this->toArrayWithRelations($article) : null;
    }

    public function getArticleForViewing(int $id): ?array
    {
        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->where('status', 'published')
            ->first();

        if ($article) {
            DB::table(self::TABLE_KB_ARTICLES)
                ->where('id', $id)
                ->increment('view_count');

            $article = DB::table(self::TABLE_KB_ARTICLES)
                ->where('id', $id)
                ->first();

            return $this->toArrayWithRelations($article);
        }

        return null;
    }

    public function searchArticles(string $query, array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $search = DB::table(self::TABLE_KB_ARTICLES)
            ->where(function ($q) use ($query) {
                $q->where('title', 'ilike', "%{$query}%")
                    ->orWhere('content', 'ilike', "%{$query}%");
            });

        if (isset($filters['published_only']) && $filters['published_only']) {
            $search->where('status', 'published');
        }

        if (isset($filters['public_only']) && $filters['public_only']) {
            $search->where('is_public', true);
        }

        if (!empty($filters['category_id'])) {
            $search->where('category_id', $filters['category_id']);
        }

        // Order by relevance (title matches first)
        $search->orderByRaw("CASE WHEN title ILIKE ? THEN 0 ELSE 1 END", ["%{$query}%"])
            ->orderBy('view_count', 'desc');

        $total = $search->count();
        $items = $search->forPage($page, $perPage)->get();

        $itemsWithRelations = array_map(function ($item) {
            return $this->toArrayWithRelations($item);
        }, $items->toArray());

        return PaginatedResult::create(
            items: $itemsWithRelations,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function getPopularArticles(int $limit = 10, bool $publishedOnly = true): array
    {
        $query = DB::table(self::TABLE_KB_ARTICLES)
            ->orderBy('view_count', 'desc')
            ->limit($limit);

        if ($publishedOnly) {
            $query->where('status', 'published');
        }

        $items = $query->get();

        return array_map(function ($item) {
            return $this->toArrayWithRelations($item);
        }, $items->toArray());
    }

    public function getRecentArticles(int $limit = 10, bool $publishedOnly = true): array
    {
        $query = DB::table(self::TABLE_KB_ARTICLES)
            ->orderBy('published_at', 'desc')
            ->limit($limit);

        if ($publishedOnly) {
            $query->where('status', 'published')
                ->whereNotNull('published_at');
        }

        $items = $query->get();

        return array_map(function ($item) {
            return $this->toArrayWithRelations($item);
        }, $items->toArray());
    }

    public function getRelatedArticles(int $articleId, int $limit = 5): array
    {
        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $articleId)
            ->first();

        if (!$article) {
            throw new \Exception('Article not found');
        }

        $query = DB::table(self::TABLE_KB_ARTICLES)
            ->where('status', 'published')
            ->where('id', '!=', $articleId)
            ->where(function ($q) use ($article) {
                // Same category
                $q->where('category_id', $article->category_id);

                // Or matching tags
                $tags = json_decode($article->tags, true);
                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        $q->orWhereRaw("tags::jsonb @> ?", [json_encode($tag)]);
                    }
                }
            })
            ->orderBy('view_count', 'desc')
            ->limit($limit);

        $items = $query->get();

        return array_map(function ($item) {
            return $this->toArrayWithRelations($item);
        }, $items->toArray());
    }

    public function getAllTags(): array
    {
        $articles = DB::table(self::TABLE_KB_ARTICLES)
            ->whereNotNull('tags')
            ->get();

        $tags = [];

        foreach ($articles as $article) {
            $articleTags = json_decode($article->tags, true);
            foreach ($articleTags ?? [] as $tag) {
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
        $now = now();
        $status = $data['status'] ?? 'draft';

        $id = DB::table(self::TABLE_KB_ARTICLES)->insertGetId([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'content' => $data['content'],
            'excerpt' => $data['excerpt'] ?? $this->generateExcerpt($data['content']),
            'category_id' => $data['category_id'],
            'status' => $status,
            'author_id' => $data['author_id'],
            'tags' => json_encode($data['tags'] ?? []),
            'is_public' => $data['is_public'] ?? true,
            'view_count' => 0,
            'helpful_count' => 0,
            'not_helpful_count' => 0,
            'published_at' => $status === 'published' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        return $this->toArrayWithRelations($article);
    }

    public function updateArticle(int $id, array $data): array
    {
        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        if (!$article) {
            throw new \Exception('Article not found');
        }

        $wasPublished = $article->status === 'published';
        $willBePublished = ($data['status'] ?? $article->status) === 'published';

        $updateData = [
            'title' => $data['title'] ?? $article->title,
            'slug' => $data['slug'] ?? $article->slug,
            'content' => $data['content'] ?? $article->content,
            'excerpt' => $data['excerpt'] ?? (isset($data['content']) ? $this->generateExcerpt($data['content']) : $article->excerpt),
            'category_id' => $data['category_id'] ?? $article->category_id,
            'status' => $data['status'] ?? $article->status,
            'is_public' => $data['is_public'] ?? $article->is_public,
            'updated_at' => now(),
        ];

        if (isset($data['tags'])) {
            $updateData['tags'] = json_encode($data['tags']);
        }

        if (!$wasPublished && $willBePublished) {
            $updateData['published_at'] = now();
        }

        DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->update($updateData);

        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        return $this->toArrayWithRelations($article);
    }

    public function publishArticle(int $id): array
    {
        DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->update([
                'status' => 'published',
                'published_at' => now(),
                'updated_at' => now(),
            ]);

        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        return $this->toArrayWithRelations($article);
    }

    public function unpublishArticle(int $id): array
    {
        DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->update([
                'status' => 'draft',
                'updated_at' => now(),
            ]);

        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        return $this->toArrayWithRelations($article);
    }

    public function archiveArticle(int $id): array
    {
        DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);

        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        return $this->toArrayWithRelations($article);
    }

    public function deleteArticle(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table(self::TABLE_KB_ARTICLE_FEEDBACK)
                ->where('article_id', $id)
                ->delete();

            DB::table(self::TABLE_KB_ARTICLES)
                ->where('id', $id)
                ->delete();
        });
    }

    public function duplicateArticle(int $id, int $authorId): array
    {
        $original = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        if (!$original) {
            throw new \Exception('Article not found');
        }

        $now = now();

        $duplicateId = DB::table(self::TABLE_KB_ARTICLES)->insertGetId([
            'title' => $original->title . ' (Copy)',
            'slug' => $original->slug . '-copy-' . time(),
            'content' => $original->content,
            'excerpt' => $original->excerpt,
            'category_id' => $original->category_id,
            'status' => 'draft',
            'author_id' => $authorId,
            'tags' => $original->tags,
            'is_public' => $original->is_public,
            'view_count' => 0,
            'helpful_count' => 0,
            'not_helpful_count' => 0,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $duplicate = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $duplicateId)
            ->first();

        return $this->toArrayWithRelations($duplicate);
    }

    public function moveArticle(int $id, int $categoryId): array
    {
        DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->update([
                'category_id' => $categoryId,
                'updated_at' => now(),
            ]);

        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->first();

        return $this->toArrayWithRelations($article);
    }

    public function incrementArticleViews(int $id): void
    {
        DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $id)
            ->increment('view_count');
    }

    // Feedback methods
    public function submitFeedback(int $articleId, array $data): array
    {
        $now = now();

        $feedbackId = DB::table(self::TABLE_KB_ARTICLE_FEEDBACK)->insertGetId([
            'article_id' => $articleId,
            'is_helpful' => $data['is_helpful'],
            'comment' => $data['comment'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'portal_user_id' => $data['portal_user_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update article counts
        if ($data['is_helpful']) {
            DB::table(self::TABLE_KB_ARTICLES)
                ->where('id', $articleId)
                ->increment('helpful_count');
        } else {
            DB::table(self::TABLE_KB_ARTICLES)
                ->where('id', $articleId)
                ->increment('not_helpful_count');
        }

        $feedback = DB::table(self::TABLE_KB_ARTICLE_FEEDBACK)
            ->where('id', $feedbackId)
            ->first();

        return $this->toArray($feedback);
    }

    public function getArticleFeedback(int $articleId, int $perPage = 20, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_KB_ARTICLE_FEEDBACK)
            ->where('article_id', $articleId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $itemsWithRelations = array_map(function ($item) {
            $data = $this->toArray($item);

            // Load user relation
            if ($item->user_id) {
                $user = DB::table(self::TABLE_USERS)
                    ->where('id', $item->user_id)
                    ->first();
                $data['user'] = $user ? $this->toArray($user) : null;
            }

            // Load portal user relation
            if ($item->portal_user_id) {
                $portalUser = DB::table(self::TABLE_PORTAL_USERS)
                    ->where('id', $item->portal_user_id)
                    ->first();
                $data['portal_user'] = $portalUser ? $this->toArray($portalUser) : null;
            }

            return $data;
        }, $items->toArray());

        return PaginatedResult::create(
            items: $itemsWithRelations,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function getArticleFeedbackSummary(int $articleId): array
    {
        $article = DB::table(self::TABLE_KB_ARTICLES)
            ->where('id', $articleId)
            ->first();

        if (!$article) {
            throw new \Exception('Article not found');
        }

        $totalFeedback = $article->helpful_count + $article->not_helpful_count;
        $helpfulnessPercentage = $totalFeedback > 0
            ? round(($article->helpful_count / $totalFeedback) * 100, 1)
            : null;

        $recentComments = DB::table(self::TABLE_KB_ARTICLE_FEEDBACK)
            ->where('article_id', $articleId)
            ->whereNotNull('comment')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->pluck('comment')
            ->toArray();

        return [
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
            'total_feedback' => $totalFeedback,
            'helpfulness_percentage' => $helpfulnessPercentage,
            'recent_comments' => $recentComments,
        ];
    }

    // Analytics methods
    public function getStatistics(): array
    {
        $totalArticles = DB::table(self::TABLE_KB_ARTICLES)->count();
        $publishedArticles = DB::table(self::TABLE_KB_ARTICLES)
            ->where('status', 'published')
            ->count();
        $draftArticles = DB::table(self::TABLE_KB_ARTICLES)
            ->where('status', 'draft')
            ->count();
        $totalCategories = DB::table(self::TABLE_KB_CATEGORIES)->count();
        $totalViews = DB::table(self::TABLE_KB_ARTICLES)->sum('view_count');
        $totalFeedback = DB::table(self::TABLE_KB_ARTICLE_FEEDBACK)->count();
        $helpfulFeedback = DB::table(self::TABLE_KB_ARTICLE_FEEDBACK)
            ->where('is_helpful', true)
            ->count();
        $avgHelpfulness = DB::table(self::TABLE_KB_ARTICLES)
            ->whereRaw('helpful_count + not_helpful_count > 0')
            ->selectRaw('AVG(helpful_count::float / (helpful_count + not_helpful_count) * 100) as avg')
            ->value('avg');

        return [
            'total_articles' => $totalArticles,
            'published_articles' => $publishedArticles,
            'draft_articles' => $draftArticles,
            'total_categories' => $totalCategories,
            'total_views' => $totalViews,
            'total_feedback' => $totalFeedback,
            'helpful_feedback' => $helpfulFeedback,
            'avg_helpfulness' => $avgHelpfulness,
        ];
    }

    public function getArticlesNeedingReview(float $threshold = 50, int $minFeedback = 5): array
    {
        $items = DB::table(self::TABLE_KB_ARTICLES)
            ->where('status', 'published')
            ->whereRaw('helpful_count + not_helpful_count >= ?', [$minFeedback])
            ->whereRaw('(helpful_count::float / (helpful_count + not_helpful_count) * 100) < ?', [$threshold])
            ->orderByRaw('helpful_count::float / (helpful_count + not_helpful_count)')
            ->limit(20)
            ->get();

        return array_map(function ($item) {
            return $this->toArrayWithRelations($item);
        }, $items->toArray());
    }

    public function getTopPerformingArticles(int $limit = 10): array
    {
        $items = DB::table(self::TABLE_KB_ARTICLES)
            ->where('status', 'published')
            ->whereRaw('helpful_count + not_helpful_count >= 5')
            ->orderByRaw('helpful_count::float / (helpful_count + not_helpful_count) DESC')
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get();

        return array_map(function ($item) {
            $data = $this->toArrayWithRelations($item);
            $total = $item->helpful_count + $item->not_helpful_count;
            $data['helpfulness'] = $total > 0
                ? round(($item->helpful_count / $total) * 100, 1)
                : null;
            return $data;
        }, $items->toArray());
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
        $views = DB::table(self::TABLE_KB_ARTICLES)
            ->selectRaw("DATE(updated_at) as date, SUM(view_count) as views")
            ->where('updated_at', '>=', $dateFrom)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('views', 'date')
            ->toArray();

        // Get top viewed in period
        $topViewedItems = DB::table(self::TABLE_KB_ARTICLES)
            ->select(['id', 'title', 'view_count', 'category_id'])
            ->where('status', 'published')
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get();

        $topViewed = array_map(function ($item) {
            $data = $this->toArray($item);

            // Load category
            if ($item->category_id) {
                $category = DB::table(self::TABLE_KB_CATEGORIES)
                    ->where('id', $item->category_id)
                    ->first();
                $data['category'] = $category ? $this->toArray($category) : null;
            }

            return $data;
        }, $topViewedItems->toArray());

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
        $query = DB::table(self::TABLE_KB_CATEGORIES);

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

        $categories = $query->orderBy('display_order')->orderBy('name')->get();

        return array_map(function ($category) {
            $data = $this->toArray($category);

            // Add article counts
            $data['articles_count'] = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->count();

            $data['published_articles_count'] = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->where('status', 'published')
                ->count();

            return $data;
        }, $categories->toArray());
    }

    public function getCategoryTree(bool $publicOnly = false): array
    {
        $query = DB::table(self::TABLE_KB_CATEGORIES)
            ->whereNull('parent_id');

        if ($publicOnly) {
            $query->where('is_public', true);
        }

        $rootCategories = $query->orderBy('display_order')->orderBy('name')->get();

        return array_map(function ($category) use ($publicOnly) {
            $data = $this->toArray($category);

            // Load children
            $childrenQuery = DB::table(self::TABLE_KB_CATEGORIES)
                ->where('parent_id', $category->id);

            if ($publicOnly) {
                $childrenQuery->where('is_public', true);
            }

            $children = $childrenQuery->orderBy('display_order')->orderBy('name')->get();
            $data['children'] = array_map(fn($child) => $this->toArray($child), $children->toArray());

            // Add published articles count
            $data['published_articles_count'] = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->where('status', 'published')
                ->count();

            return $data;
        }, $rootCategories->toArray());
    }

    public function getCategory(int $id): ?array
    {
        $category = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('id', $id)
            ->first();

        if (!$category) {
            return null;
        }

        $data = $this->toArray($category);

        // Load parent
        if ($category->parent_id) {
            $parent = DB::table(self::TABLE_KB_CATEGORIES)
                ->where('id', $category->parent_id)
                ->first();
            $data['parent'] = $parent ? $this->toArray($parent) : null;
        }

        // Load children
        $children = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('parent_id', $category->id)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
        $data['children'] = array_map(fn($child) => $this->toArray($child), $children->toArray());

        // Add articles count
        $data['articles_count'] = DB::table(self::TABLE_KB_ARTICLES)
            ->where('category_id', $category->id)
            ->count();

        return $data;
    }

    public function getCategoryBySlug(string $slug): ?array
    {
        $category = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('slug', $slug)
            ->first();

        if (!$category) {
            return null;
        }

        $data = $this->toArray($category);

        // Load parent
        if ($category->parent_id) {
            $parent = DB::table(self::TABLE_KB_CATEGORIES)
                ->where('id', $category->parent_id)
                ->first();
            $data['parent'] = $parent ? $this->toArray($parent) : null;
        }

        // Load children
        $children = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('parent_id', $category->id)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
        $data['children'] = array_map(fn($child) => $this->toArray($child), $children->toArray());

        // Add articles count
        $data['articles_count'] = DB::table(self::TABLE_KB_ARTICLES)
            ->where('category_id', $category->id)
            ->count();

        return $data;
    }

    public function getCategoryWithArticles(int $id, bool $publishedOnly = false, int $perPage = 15, int $page = 1): array
    {
        $category = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('id', $id)
            ->first();

        if (!$category) {
            throw new \Exception('Category not found');
        }

        $query = DB::table(self::TABLE_KB_ARTICLES)
            ->where('category_id', $id)
            ->orderBy('title');

        if ($publishedOnly) {
            $query->where('status', 'published');
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $itemsWithRelations = array_map(function ($item) {
            $data = $this->toArray($item);

            // Load author
            if ($item->author_id) {
                $author = DB::table(self::TABLE_USERS)
                    ->where('id', $item->author_id)
                    ->first();
                $data['author'] = $author ? $this->toArray($author) : null;
            }

            return $data;
        }, $items->toArray());

        return [
            'category' => $this->toArray($category),
            'articles' => PaginatedResult::create(
                items: $itemsWithRelations,
                total: $total,
                perPage: $perPage,
                currentPage: $page,
            ),
        ];
    }

    public function createCategory(array $data): array
    {
        $now = now();

        $id = DB::table(self::TABLE_KB_CATEGORIES)->insertGetId([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'is_public' => $data['is_public'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $category = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('id', $id)
            ->first();

        return $this->toArray($category);
    }

    public function updateCategory(int $id, array $data): array
    {
        $category = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('id', $id)
            ->first();

        if (!$category) {
            throw new \Exception('Category not found');
        }

        // Prevent circular parent reference
        if (!empty($data['parent_id']) && $data['parent_id'] == $id) {
            throw new \InvalidArgumentException('A category cannot be its own parent');
        }

        $updateData = [
            'name' => $data['name'] ?? $category->name,
            'slug' => $data['slug'] ?? $category->slug,
            'description' => $data['description'] ?? $category->description,
            'icon' => $data['icon'] ?? $category->icon,
            'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id,
            'display_order' => $data['display_order'] ?? $category->display_order,
            'is_public' => $data['is_public'] ?? $category->is_public,
            'updated_at' => now(),
        ];

        DB::table(self::TABLE_KB_CATEGORIES)
            ->where('id', $id)
            ->update($updateData);

        $category = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('id', $id)
            ->first();

        return $this->toArray($category);
    }

    public function deleteCategory(int $id, ?int $moveArticlesToCategoryId = null): void
    {
        $category = DB::table(self::TABLE_KB_CATEGORIES)
            ->where('id', $id)
            ->first();

        if (!$category) {
            throw new \Exception('Category not found');
        }

        DB::transaction(function () use ($category, $id, $moveArticlesToCategoryId) {
            if ($moveArticlesToCategoryId) {
                // Move articles to another category
                DB::table(self::TABLE_KB_ARTICLES)
                    ->where('category_id', $id)
                    ->update(['category_id' => $moveArticlesToCategoryId]);
            } else {
                // Delete all articles in this category
                DB::table(self::TABLE_KB_ARTICLES)
                    ->where('category_id', $id)
                    ->delete();
            }

            // Move child categories to parent
            if ($category->parent_id) {
                DB::table(self::TABLE_KB_CATEGORIES)
                    ->where('parent_id', $id)
                    ->update(['parent_id' => $category->parent_id]);
            } else {
                DB::table(self::TABLE_KB_CATEGORIES)
                    ->where('parent_id', $id)
                    ->update(['parent_id' => null]);
            }

            DB::table(self::TABLE_KB_CATEGORIES)
                ->where('id', $id)
                ->delete();
        });
    }

    public function reorderCategories(array $categoryIds): void
    {
        foreach ($categoryIds as $order => $categoryId) {
            DB::table(self::TABLE_KB_CATEGORIES)
                ->where('id', $categoryId)
                ->update([
                    'display_order' => $order,
                    'updated_at' => now(),
                ]);
        }
    }

    public function getCategoryPerformance(): array
    {
        $categories = DB::table(self::TABLE_KB_CATEGORIES)->get();

        $performance = array_map(function ($category) {
            $data = $this->toArray($category);

            // Get article counts and sums
            $totalArticles = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->count();

            $publishedArticles = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->where('status', 'published')
                ->count();

            $viewCount = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->sum('view_count');

            $helpfulCount = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->sum('helpful_count');

            $notHelpfulCount = DB::table(self::TABLE_KB_ARTICLES)
                ->where('category_id', $category->id)
                ->sum('not_helpful_count');

            $data['total_articles'] = $totalArticles;
            $data['published_articles'] = $publishedArticles;
            $data['articles_sum_view_count'] = $viewCount;
            $data['articles_sum_helpful_count'] = $helpfulCount;
            $data['articles_sum_not_helpful_count'] = $notHelpfulCount;

            $total = ($helpfulCount ?? 0) + ($notHelpfulCount ?? 0);
            $data['helpfulness'] = $total > 0
                ? round(($helpfulCount / $total) * 100, 1)
                : null;

            return $data;
        }, $categories->toArray());

        // Sort by view count
        usort($performance, fn($a, $b) => ($b['articles_sum_view_count'] ?? 0) <=> ($a['articles_sum_view_count'] ?? 0));

        return $performance;
    }

    public function getSearchAnalytics(): array
    {
        // This would typically pull from a search log table
        // For now, return tag-based insights
        $tagUsage = [];
        $articles = DB::table(self::TABLE_KB_ARTICLES)
            ->whereNotNull('tags')
            ->get();

        foreach ($articles as $article) {
            $tags = json_decode($article->tags, true);
            foreach ($tags ?? [] as $tag) {
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

        $articlesWithoutTags = DB::table(self::TABLE_KB_ARTICLES)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('tags')->orWhere('tags', '[]');
            })
            ->count();

        return [
            'popular_tags' => array_slice($tagUsage, 0, 20, true),
            'articles_without_tags' => $articlesWithoutTags,
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

    private function toArray(?stdClass $object): array
    {
        if (!$object) {
            return [];
        }

        $array = json_decode(json_encode($object), true);

        // Decode JSON fields
        if (isset($array['tags']) && is_string($array['tags'])) {
            $array['tags'] = json_decode($array['tags'], true);
        }

        // Convert boolean fields
        if (isset($array['is_public'])) {
            $array['is_public'] = (bool) $array['is_public'];
        }

        if (isset($array['is_helpful'])) {
            $array['is_helpful'] = (bool) $array['is_helpful'];
        }

        return $array;
    }

    private function toArrayWithRelations(stdClass $article): array
    {
        $data = $this->toArray($article);

        // Load category relation
        if ($article->category_id) {
            $category = DB::table(self::TABLE_KB_CATEGORIES)
                ->where('id', $article->category_id)
                ->first();
            $data['category'] = $category ? $this->toArray($category) : null;
        }

        // Load author relation
        if ($article->author_id) {
            $author = DB::table(self::TABLE_USERS)
                ->where('id', $article->author_id)
                ->first();
            $data['author'] = $author ? $this->toArray($author) : null;
        }

        return $data;
    }
}
