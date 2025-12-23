<?php

declare(strict_types=1);

namespace App\Application\Services\KnowledgeBase;

use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class KnowledgeBaseApplicationService
{
    public function __construct(
        private KbArticleRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // ==========================================
    // CATEGORY QUERY USE CASES
    // ==========================================

    /**
     * List all categories.
     */
    public function listCategories(array $filters = []): array
    {
        return $this->repository->listCategories($filters);
    }

    /**
     * Get category tree.
     */
    public function getCategoryTree(bool $publicOnly = false): array
    {
        return $this->repository->getCategoryTree($publicOnly);
    }

    /**
     * Get a single category.
     */
    public function getCategory(int $id): ?array
    {
        return $this->repository->getCategory($id);
    }

    /**
     * Get category by slug.
     */
    public function getCategoryBySlug(string $slug): ?array
    {
        return $this->repository->getCategoryBySlug($slug);
    }

    /**
     * Get category with articles.
     */
    public function getCategoryWithArticles(int $id, bool $publishedOnly = false, int $perPage = 15, int $page = 1): array
    {
        return $this->repository->getCategoryWithArticles($id, $publishedOnly, $perPage, $page);
    }

    // ==========================================
    // CATEGORY COMMAND USE CASES
    // ==========================================

    /**
     * Create a category.
     */
    public function createCategory(array $data): array
    {
        return $this->repository->createCategory($data);
    }

    /**
     * Update a category.
     */
    public function updateCategory(int $id, array $data): array
    {
        return $this->repository->updateCategory($id, $data);
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(int $id, ?int $moveArticlesToCategoryId = null): void
    {
        $this->repository->deleteCategory($id, $moveArticlesToCategoryId);
    }

    /**
     * Reorder categories.
     */
    public function reorderCategories(array $categoryIds): void
    {
        $this->repository->reorderCategories($categoryIds);
    }

    // ==========================================
    // ARTICLE QUERY USE CASES
    // ==========================================

    /**
     * List articles with filtering and pagination.
     */
    public function listArticles(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        return $this->repository->listArticles($filters, $perPage, $page);
    }

    /**
     * Get a single article.
     */
    public function getArticle(int $id): ?array
    {
        return $this->repository->getArticle($id);
    }

    /**
     * Get article by slug.
     */
    public function getArticleBySlug(string $slug): ?array
    {
        return $this->repository->getArticleBySlug($slug);
    }

    /**
     * Get article for public viewing (increments view count).
     */
    public function getArticleForViewing(int $id): ?array
    {
        return $this->repository->getArticleForViewing($id);
    }

    /**
     * Search articles.
     */
    public function searchArticles(string $query, array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        return $this->repository->searchArticles($query, $filters, $perPage, $page);
    }

    /**
     * Get popular articles.
     */
    public function getPopularArticles(int $limit = 10, bool $publishedOnly = true): array
    {
        return $this->repository->getPopularArticles($limit, $publishedOnly);
    }

    /**
     * Get recent articles.
     */
    public function getRecentArticles(int $limit = 10, bool $publishedOnly = true): array
    {
        return $this->repository->getRecentArticles($limit, $publishedOnly);
    }

    /**
     * Get related articles.
     */
    public function getRelatedArticles(int $articleId, int $limit = 5): array
    {
        return $this->repository->getRelatedArticles($articleId, $limit);
    }

    /**
     * Get all unique tags.
     */
    public function getAllTags(): array
    {
        return $this->repository->getAllTags();
    }

    // ==========================================
    // ARTICLE COMMAND USE CASES
    // ==========================================

    /**
     * Create an article.
     */
    public function createArticle(array $data): array
    {
        $data['author_id'] = $data['author_id'] ?? $this->authContext->userId();
        return $this->repository->createArticle($data);
    }

    /**
     * Update an article.
     */
    public function updateArticle(int $id, array $data): array
    {
        return $this->repository->updateArticle($id, $data);
    }

    /**
     * Publish an article.
     */
    public function publishArticle(int $id): array
    {
        return $this->repository->publishArticle($id);
    }

    /**
     * Unpublish an article.
     */
    public function unpublishArticle(int $id): array
    {
        return $this->repository->unpublishArticle($id);
    }

    /**
     * Archive an article.
     */
    public function archiveArticle(int $id): array
    {
        return $this->repository->archiveArticle($id);
    }

    /**
     * Delete an article.
     */
    public function deleteArticle(int $id): void
    {
        $this->repository->deleteArticle($id);
    }

    /**
     * Duplicate an article.
     */
    public function duplicateArticle(int $id): array
    {
        $authorId = $this->authContext->userId();
        if (!$authorId) {
            throw new \RuntimeException('User must be authenticated to duplicate an article');
        }
        return $this->repository->duplicateArticle($id, $authorId);
    }

    /**
     * Move article to another category.
     */
    public function moveArticle(int $id, int $categoryId): array
    {
        return $this->repository->moveArticle($id, $categoryId);
    }

    // ==========================================
    // FEEDBACK USE CASES
    // ==========================================

    /**
     * Submit feedback for an article.
     */
    public function submitFeedback(int $articleId, array $data): array
    {
        $data['user_id'] = $data['user_id'] ?? $this->authContext->userId();
        return $this->repository->submitFeedback($articleId, $data);
    }

    /**
     * Get feedback for an article.
     */
    public function getArticleFeedback(int $articleId, int $perPage = 20, int $page = 1): PaginatedResult
    {
        return $this->repository->getArticleFeedback($articleId, $perPage, $page);
    }

    /**
     * Get feedback summary for an article.
     */
    public function getArticleFeedbackSummary(int $articleId): array
    {
        return $this->repository->getArticleFeedbackSummary($articleId);
    }

    // ==========================================
    // ANALYTICS USE CASES
    // ==========================================

    /**
     * Get knowledge base statistics.
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     * Get articles needing review (low helpfulness).
     */
    public function getArticlesNeedingReview(float $threshold = 50, int $minFeedback = 5): array
    {
        return $this->repository->getArticlesNeedingReview($threshold, $minFeedback);
    }

    /**
     * Get top performing articles.
     */
    public function getTopPerformingArticles(int $limit = 10): array
    {
        return $this->repository->getTopPerformingArticles($limit);
    }

    /**
     * Get articles by view trend.
     */
    public function getViewTrends(string $period = 'month'): array
    {
        return $this->repository->getViewTrends($period);
    }

    /**
     * Get category performance.
     */
    public function getCategoryPerformance(): array
    {
        return $this->repository->getCategoryPerformance();
    }

    /**
     * Get search analytics.
     */
    public function getSearchAnalytics(): array
    {
        return $this->repository->getSearchAnalytics();
    }
}
