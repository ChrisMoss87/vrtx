<?php

declare(strict_types=1);

namespace App\Domain\KnowledgeBase\Repositories;

use App\Domain\KnowledgeBase\Entities\KbArticle;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface KbArticleRepositoryInterface
{
    public function findById(int $id): ?KbArticle;

    public function findAll(): array;

    public function save(KbArticle $entity): KbArticle;

    public function delete(int $id): bool;

    // Article query methods
    public function listArticles(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    public function getArticle(int $id): ?array;

    public function getArticleBySlug(string $slug): ?array;

    public function getArticleForViewing(int $id): ?array;

    public function searchArticles(string $query, array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    public function getPopularArticles(int $limit = 10, bool $publishedOnly = true): array;

    public function getRecentArticles(int $limit = 10, bool $publishedOnly = true): array;

    public function getRelatedArticles(int $articleId, int $limit = 5): array;

    public function getAllTags(): array;

    // Article command methods
    public function createArticle(array $data): array;

    public function updateArticle(int $id, array $data): array;

    public function publishArticle(int $id): array;

    public function unpublishArticle(int $id): array;

    public function archiveArticle(int $id): array;

    public function deleteArticle(int $id): void;

    public function duplicateArticle(int $id, int $authorId): array;

    public function moveArticle(int $id, int $categoryId): array;

    public function incrementArticleViews(int $id): void;

    // Feedback methods
    public function submitFeedback(int $articleId, array $data): array;

    public function getArticleFeedback(int $articleId, int $perPage = 20, int $page = 1): PaginatedResult;

    public function getArticleFeedbackSummary(int $articleId): array;

    // Analytics methods
    public function getStatistics(): array;

    public function getArticlesNeedingReview(float $threshold = 50, int $minFeedback = 5): array;

    public function getTopPerformingArticles(int $limit = 10): array;

    public function getViewTrends(string $period = 'month'): array;

    // Category methods
    public function listCategories(array $filters = []): array;

    public function getCategoryTree(bool $publicOnly = false): array;

    public function getCategory(int $id): ?array;

    public function getCategoryBySlug(string $slug): ?array;

    public function getCategoryWithArticles(int $id, bool $publishedOnly = false, int $perPage = 15, int $page = 1): array;

    public function createCategory(array $data): array;

    public function updateCategory(int $id, array $data): array;

    public function deleteCategory(int $id, ?int $moveArticlesToCategoryId = null): void;

    public function reorderCategories(array $categoryIds): void;

    public function getCategoryPerformance(): array;

    public function getSearchAnalytics(): array;
}
