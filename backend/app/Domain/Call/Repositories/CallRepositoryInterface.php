<?php

declare(strict_types=1);

namespace App\Domain\Call\Repositories;

use App\Domain\Call\Entities\Call;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CallRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?Call;

    public function save(Call $call): Call;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByIdWithRelations(int $id): ?array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function delete(int $id): bool;

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    // =========================================================================
    // QUERY METHODS - CALLS
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult;

    public function findForContact(int $contactId, int $limit = 50): array;

    public function findToday(?int $userId = null): array;

    public function findRecent(?int $userId = null, int $limit = 20): array;

    public function getStats(?int $userId = null, string $period = 'today'): array;

    public function getHourlyDistribution(?int $userId = null, int $days = 7): array;

    public function getCallsByDay(?int $userId = null, int $days = 30): array;

    public function getAnalytics(array $filters = []): array;

    // =========================================================================
    // PROVIDER METHODS
    // =========================================================================

    public function findAllProviders(bool $activeOnly = false): array;

    public function findProviderById(int $id): ?array;

    public function findProviderByType(string $provider): ?array;

    public function createProvider(array $data): array;

    public function updateProvider(int $id, array $data): array;

    public function deleteProvider(int $id): bool;

    public function providerHasCalls(int $providerId): bool;

    // =========================================================================
    // TRANSCRIPTION METHODS
    // =========================================================================

    public function findTranscriptionByCallId(int $callId): ?array;

    public function findPendingTranscriptions(int $limit = 50): array;

    public function createTranscription(array $data): array;

    public function updateTranscription(int $id, array $data): array;

    public function searchTranscriptions(string $query, int $perPage = 25): PaginatedResult;

    public function callHasRecording(int $callId): bool;
}
