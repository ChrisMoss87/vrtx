<?php

declare(strict_types=1);

namespace App\Domain\Email\Repositories;

use App\Domain\Email\Entities\EmailTemplate;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface EmailTemplateRepositoryInterface
{
    public function findById(int $id): ?EmailTemplate;

    public function findByModuleId(int $moduleId): array;

    public function findShared(): array;

    public function findByUserId(int $userId): array;

    public function findActive(): array;

    public function findAll(): array;

    /**
     * Get paginated templates.
     *
     * @param int $page
     * @param int $perPage
     * @param array $filters Optional filters (module_id, is_shared, is_active, created_by)
     * @param string $sortBy
     * @param string $sortDirection
     * @return PaginatedResult
     */
    public function paginate(
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
        string $sortBy = 'name',
        string $sortDirection = 'asc'
    ): PaginatedResult;

    /**
     * Search templates by name or subject.
     *
     * @param string $search
     * @param int|null $userId
     * @return array
     */
    public function search(string $search, ?int $userId = null): array;

    /**
     * Convert entity to array representation.
     *
     * @param EmailTemplate $template
     * @return array
     */
    public function toArray(EmailTemplate $template): array;

    public function save(EmailTemplate $template): EmailTemplate;

    public function delete(int $id): bool;
}
