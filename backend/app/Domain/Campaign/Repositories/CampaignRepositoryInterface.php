<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Repositories;

use App\Domain\Campaign\Entities\Campaign;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CampaignRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?Campaign;

    public function save(Campaign $campaign): Campaign;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByIdWithRelations(int $id): ?array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function delete(int $id): bool;

    // =========================================================================
    // QUERY METHODS - CAMPAIGNS
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult;

    public function findActive(): array;

    public function findCampaignWithMetrics(int $id): ?array;

    public function getCampaignPerformance(int $campaignId): array;

    // =========================================================================
    // AUDIENCE METHODS
    // =========================================================================

    public function findAudiences(int $campaignId): array;

    public function findAudienceById(int $audienceId): ?array;

    public function createAudience(array $data): array;

    public function updateAudience(int $audienceId, array $data): array;

    public function deleteAudience(int $audienceId): bool;

    public function refreshAudienceCount(int $audienceId): array;

    public function addAudienceMembers(int $audienceId, array $recordIds): int;

    public function removeAudienceMembers(int $audienceId, array $recordIds): int;

    // =========================================================================
    // ASSET METHODS
    // =========================================================================

    public function findAssets(int $campaignId): array;

    public function findAssetById(int $assetId): ?array;

    public function createAsset(array $data): array;

    public function updateAsset(int $assetId, array $data): array;

    public function deleteAsset(int $assetId): bool;

    // =========================================================================
    // SEND METHODS
    // =========================================================================

    public function createSend(array $data): array;

    public function findPendingSends(int $limit = 100): array;

    public function updateSend(int $sendId, array $data): array;

    public function findSendById(int $sendId): ?array;

    public function sendExists(int $campaignId, int $recordId): bool;

    // =========================================================================
    // CLICK METHODS
    // =========================================================================

    public function createClick(array $data): array;

    // =========================================================================
    // UNSUBSCRIBE METHODS
    // =========================================================================

    public function createUnsubscribe(array $data): array;

    // =========================================================================
    // CONVERSION METHODS
    // =========================================================================

    public function createConversion(array $data): array;

    // =========================================================================
    // METRIC METHODS
    // =========================================================================

    public function getOrCreateMetricForDate(int $campaignId, string $date): array;

    public function incrementMetric(int $metricId, string $field, int $amount = 1): void;

    public function addRevenue(int $metricId, float $revenue): void;

    public function incrementCampaignSpent(int $campaignId, float $amount): void;

    public function findMetrics(int $campaignId, ?string $fromDate = null, ?string $toDate = null): array;

    public function getAggregateAnalytics(array $filters = []): array;

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function countAudiences(int $campaignId): int;

    public function countAssets(int $campaignId): int;

    public function canBeStarted(int $campaignId): bool;

    public function canBePaused(int $campaignId): bool;

    public function isDraft(int $campaignId): bool;

    public function isActive(int $campaignId): bool;

    public function getMatchingRecords(int $audienceId): array;
}
