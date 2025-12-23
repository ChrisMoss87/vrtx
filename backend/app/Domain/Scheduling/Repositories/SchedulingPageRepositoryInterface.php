<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Repositories;

use App\Domain\Scheduling\Entities\SchedulingPage;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Shared\ValueObjects\UserId;

/**
 * Repository interface for SchedulingPage aggregate root.
 */
interface SchedulingPageRepositoryInterface
{
    /**
     * Find a scheduling page by its ID.
     */
    public function findById(int $id): ?SchedulingPage;

    /**
     * Find a scheduling page by its ID and return as array.
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find a scheduling page by its slug.
     */
    public function findBySlug(string $slug): ?SchedulingPage;

    /**
     * Find a scheduling page by its slug and return as array.
     */
    public function findBySlugAsArray(string $slug): ?array;

    /**
     * Find all scheduling pages for a user.
     *
     * @return array<SchedulingPage>
     */
    public function findByUserId(UserId $userId): array;

    /**
     * Find active scheduling pages for a user.
     *
     * @return array<SchedulingPage>
     */
    public function findActiveByUserId(UserId $userId): array;

    /**
     * List scheduling pages with filtering and pagination.
     *
     * @param array $filters Array of filters (user_id, active, search, sort_by, sort_dir)
     * @param int $perPage Number of items per page
     * @param int $page Current page number
     * @return PaginatedResult
     */
    public function listPaginated(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Save a scheduling page (insert or update).
     */
    public function save(SchedulingPage $page): SchedulingPage;

    /**
     * Delete a scheduling page.
     */
    public function delete(int $id): bool;

    /**
     * Check if a slug exists (excluding a specific ID).
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool;
}
