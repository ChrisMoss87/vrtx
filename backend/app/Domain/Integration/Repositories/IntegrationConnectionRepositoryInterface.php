<?php

declare(strict_types=1);

namespace App\Domain\Integration\Repositories;

use App\Domain\Integration\ValueObjects\ConnectionStatus;
use App\Domain\Integration\ValueObjects\IntegrationCategory;
use App\Domain\Integration\ValueObjects\IntegrationProvider;
use App\Domain\Integration\ValueObjects\SyncStatus;

interface IntegrationConnectionRepositoryInterface
{
    // Basic CRUD
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function findByProvider(IntegrationProvider $provider): ?array;
    public function getAll(): array;
    public function getActive(): array;

    // Filtered queries
    public function getByCategory(IntegrationCategory $category): array;
    public function getByStatus(ConnectionStatus $status): array;
    public function getBySyncStatus(SyncStatus $status): array;

    // Connection management
    public function create(array $data): array;
    public function update(int $id, array $data): array;
    public function delete(int $id): bool;

    // Status management
    public function updateStatus(int $id, ConnectionStatus $status, ?string $errorMessage = null): bool;
    public function updateSyncStatus(int $id, SyncStatus $status): bool;
    public function markAsError(int $id, string $errorMessage): bool;
    public function markAsExpired(int $id): bool;

    // Credential management
    public function updateCredentials(int $id, array $credentials): bool;
    public function updateTokens(int $id, string $accessToken, ?string $refreshToken, ?\DateTimeInterface $expiresAt): bool;
    public function getCredentials(int $id): ?array;
    public function hasValidToken(int $id): bool;

    // Settings
    public function updateSettings(int $id, array $settings): bool;
    public function getSetting(int $id, string $key, mixed $default = null): mixed;

    // Sync tracking
    public function updateLastSyncAt(int $id): bool;
    public function getConnectionsNeedingTokenRefresh(int $minutesBeforeExpiry = 5): array;

    // Sync logs
    public function createSyncLog(int $connectionId, array $data): array;
    public function updateSyncLog(int $logId, array $data): bool;
    public function completeSyncLog(int $logId, array $summary): bool;
    public function getSyncLogs(int $connectionId, int $limit = 50): array;
    public function getLatestSyncLog(int $connectionId, ?string $entityType = null): ?array;

    // Field mappings
    public function getFieldMappings(int $connectionId, ?string $crmEntity = null): array;
    public function createFieldMapping(int $connectionId, array $data): array;
    public function updateFieldMapping(int $mappingId, array $data): bool;
    public function deleteFieldMapping(int $mappingId): bool;
    public function setFieldMappings(int $connectionId, string $crmEntity, array $mappings): bool;

    // Entity mappings (CRM record <-> External record)
    public function findEntityMapping(int $connectionId, string $crmEntity, int $crmRecordId): ?array;
    public function findEntityMappingByExternalId(int $connectionId, string $externalEntity, string $externalId): ?array;
    public function createEntityMapping(int $connectionId, array $data): array;
    public function updateEntityMapping(int $mappingId, array $data): bool;
    public function deleteEntityMapping(int $mappingId): bool;
    public function getEntityMappings(int $connectionId, string $crmEntity, array $crmRecordIds): array;

    // Webhooks
    public function getWebhooks(int $connectionId): array;
    public function createWebhook(int $connectionId, array $data): array;
    public function updateWebhook(int $webhookId, array $data): bool;
    public function deleteWebhook(int $webhookId): bool;
    public function findWebhookByExternalId(int $connectionId, string $externalWebhookId): ?array;

    // Webhook logs
    public function createWebhookLog(int $webhookId, array $data): array;
    public function updateWebhookLog(int $logId, array $data): bool;
    public function getWebhookLogs(int $webhookId, int $limit = 100): array;

    // Statistics
    public function getConnectionStats(): array;
    public function getSyncStats(int $connectionId, int $days = 30): array;
}
