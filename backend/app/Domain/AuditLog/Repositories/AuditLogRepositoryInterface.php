<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;

interface AuditLogRepositoryInterface
{
    /**
     * Find a single audit log by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Find a single audit log by ID with user relation.
     */
    public function findByIdWithUser(int $id): ?array;

    /**
     * List audit logs with filters and pagination.
     */
    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult;

    /**
     * Get audit trail for a specific auditable entity.
     */
    public function findForAuditable(string $auditableType, int $auditableId, int $limit = 50): array;

    /**
     * Get audit logs by user.
     */
    public function findByUser(int $userId, ?string $startDate = null, ?string $endDate = null, int $perPage = 25): PaginatedResult;

    /**
     * Get audit summary for an auditable entity.
     */
    public function getSummary(string $auditableType, int $auditableId): array;

    /**
     * Create an audit log entry.
     */
    public function create(array $data): array;

    // =========================================================================
    // EVENT CONSTANTS
    // =========================================================================

    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_RESTORED = 'restored';
    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_VIEWED = 'viewed';
    public const EVENT_EXPORTED = 'exported';
    public const EVENT_IMPORTED = 'imported';
}
