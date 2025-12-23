<?php

declare(strict_types=1);

namespace App\Domain\Email\Repositories;

use App\Domain\Email\Entities\EmailMessage;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface EmailMessageRepositoryInterface
{
    public function findById(int $id): ?EmailMessage;

    public function findByRecordId(int $moduleId, int $recordId): array;

    public function findByAccountId(int $accountId): array;

    public function findByThreadId(string $threadId): array;

    public function findByUserId(int $userId, ?string $folder = null): array;

    public function findDrafts(int $userId): array;

    public function findSent(int $userId): array;

    public function findInbox(int $userId): array;

    public function findUnread(int $userId): array;

    public function findStarred(int $userId): array;

    /**
     * Get paginated emails for a user.
     *
     * @param int $userId
     * @param int $page
     * @param int $perPage
     * @param array $filters Optional filters (folder, status, is_read, is_starred, etc.)
     * @param string $sortBy
     * @param string $sortDirection
     * @return PaginatedResult
     */
    public function paginate(
        int $userId,
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): PaginatedResult;

    /**
     * Get paginated emails for a specific account.
     *
     * @param int $accountId
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @param string $sortBy
     * @param string $sortDirection
     * @return PaginatedResult
     */
    public function paginateByAccount(
        int $accountId,
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): PaginatedResult;

    /**
     * Convert entity to array representation.
     *
     * @param EmailMessage $email
     * @return array
     */
    public function toArray(EmailMessage $email): array;

    public function save(EmailMessage $email): EmailMessage;

    public function delete(int $id): bool;
}
