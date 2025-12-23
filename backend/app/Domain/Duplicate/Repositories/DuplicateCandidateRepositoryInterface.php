<?php

declare(strict_types=1);

namespace App\Domain\Duplicate\Repositories;

use App\Domain\Duplicate\Entities\DuplicateCandidate;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface DuplicateCandidateRepositoryInterface
{
    /**
     * Find a candidate entity by ID.
     */
    public function findById(int $id): ?DuplicateCandidate;

    /**
     * Save a duplicate candidate entity.
     */
    public function save(DuplicateCandidate $candidate): DuplicateCandidate;

    /**
     * Find a candidate by ID (backward compatibility).
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find all candidates.
     */
    public function findAll(): array;

    /**
     * List candidates with filtering and pagination.
     */
    public function listCandidates(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get candidates for a specific record.
     */
    public function getCandidatesForRecord(int $recordId): array;

    /**
     * Count candidates by status for a module.
     */
    public function countByStatus(int $moduleId, string $status): int;

    /**
     * Count pending candidates for a module.
     */
    public function countPendingForModule(int $moduleId): int;

    /**
     * Count high confidence candidates for a module.
     */
    public function countHighConfidence(int $moduleId, float $threshold = 0.9): int;

    /**
     * Get average match score for a module.
     */
    public function getAverageScore(int $moduleId, ?string $status = null): float;

    /**
     * Get counts by module for pending candidates.
     */
    public function countByModule(): array;

    /**
     * Check if a candidate exists.
     */
    public function exists(int $moduleId, int $recordIdA, int $recordIdB): ?array;

    /**
     * Create a new candidate.
     */
    public function create(array $data): array;

    /**
     * Update a candidate.
     */
    public function update(int $id, array $data): ?array;

    /**
     * Mark candidate as merged.
     */
    public function markAsMerged(int $id, int $userId): bool;

    /**
     * Mark candidate as dismissed.
     */
    public function markAsDismissed(int $id, int $userId, ?string $reason = null): bool;

    /**
     * Bulk update candidates.
     */
    public function bulkUpdate(array $ids, array $data): int;

    /**
     * Delete a candidate.
     */
    public function delete(int $id): bool;
}
