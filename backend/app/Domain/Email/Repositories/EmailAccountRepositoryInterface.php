<?php

declare(strict_types=1);

namespace App\Domain\Email\Repositories;

use App\Domain\Email\Entities\EmailAccount;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface EmailAccountRepositoryInterface
{
    public function findById(int $id): ?EmailAccount;

    public function findByUserId(int $userId): array;

    public function findActiveByUserId(int $userId): array;

    public function findDefaultForUser(int $userId): ?EmailAccount;

    public function findByEmail(string $email): ?EmailAccount;

    public function findAll(): array;

    /**
     * Get paginated accounts.
     *
     * @param int $page
     * @param int $perPage
     * @param array $filters Optional filters (user_id, is_active, provider)
     * @param string $sortBy
     * @param string $sortDirection
     * @return PaginatedResult
     */
    public function paginate(
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): PaginatedResult;

    /**
     * Convert entity to array representation.
     *
     * @param EmailAccount $account
     * @return array
     */
    public function toArray(EmailAccount $account): array;

    public function save(EmailAccount $account): EmailAccount;

    public function delete(int $id): bool;
}
