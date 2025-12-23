<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Integration;

use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domain\Integration\ValueObjects\ConnectionStatus;
use App\Domain\Integration\ValueObjects\IntegrationCategory;
use App\Domain\Integration\ValueObjects\IntegrationProvider;
use App\Domain\Integration\ValueObjects\SyncStatus;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEntityMapping;
use App\Models\IntegrationFieldMapping;
use App\Models\IntegrationSyncLog;
use App\Models\IntegrationWebhook;
use App\Models\IntegrationWebhookLog;

class EloquentIntegrationConnectionRepository implements IntegrationConnectionRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $connection = IntegrationConnection::find($id);
        return $connection?->toArray();
    }

    public function findBySlug(string $slug): ?array
    {
        $connection = IntegrationConnection::where('integration_slug', $slug)->first();
        return $connection?->toArray();
    }

    public function findByProvider(IntegrationProvider $provider): ?array
    {
        return $this->findBySlug($provider->value);
    }

    public function getAll(): array
    {
        return IntegrationConnection::orderBy('integration_slug')->get()->toArray();
    }

    public function getActive(): array
    {
        return IntegrationConnection::active()->orderBy('integration_slug')->get()->toArray();
    }

    public function getByCategory(IntegrationCategory $category): array
    {
        $providers = IntegrationProvider::getByCategory($category);
        $slugs = array_map(fn($p) => $p->value, $providers);

        return IntegrationConnection::whereIn('integration_slug', $slugs)
            ->orderBy('integration_slug')
            ->get()
            ->toArray();
    }

    public function getByStatus(ConnectionStatus $status): array
    {
        return IntegrationConnection::where('status', $status)
            ->orderBy('integration_slug')
            ->get()
            ->toArray();
    }

    public function getBySyncStatus(SyncStatus $status): array
    {
        return IntegrationConnection::where('sync_status', $status)
            ->orderBy('integration_slug')
            ->get()
            ->toArray();
    }

    public function create(array $data): array
    {
        $connection = IntegrationConnection::create($data);
        return $connection->toArray();
    }

    public function update(int $id, array $data): array
    {
        $connection = IntegrationConnection::findOrFail($id);
        $connection->update($data);
        return $connection->fresh()->toArray();
    }

    public function delete(int $id): bool
    {
        return IntegrationConnection::destroy($id) > 0;
    }

    public function updateStatus(int $id, ConnectionStatus $status, ?string $errorMessage = null): bool
    {
        return IntegrationConnection::where('id', $id)->update([
            'status' => $status,
            'error_message' => $errorMessage,
        ]) > 0;
    }

    public function updateSyncStatus(int $id, SyncStatus $status): bool
    {
        return IntegrationConnection::where('id', $id)->update([
            'sync_status' => $status,
        ]) > 0;
    }

    public function markAsError(int $id, string $errorMessage): bool
    {
        return $this->updateStatus($id, ConnectionStatus::ERROR, $errorMessage);
    }

    public function markAsExpired(int $id): bool
    {
        return $this->updateStatus($id, ConnectionStatus::EXPIRED, 'Token has expired');
    }

    public function updateCredentials(int $id, array $credentials): bool
    {
        $connection = IntegrationConnection::find($id);
        if (!$connection) {
            return false;
        }

        $connection->credentials = $credentials;
        return $connection->save();
    }

    public function updateTokens(int $id, string $accessToken, ?string $refreshToken, ?\DateTimeInterface $expiresAt): bool
    {
        $connection = IntegrationConnection::find($id);
        if (!$connection) {
            return false;
        }

        $credentials = $connection->credentials ?? [];
        $credentials['access_token'] = $accessToken;
        if ($refreshToken !== null) {
            $credentials['refresh_token'] = $refreshToken;
        }

        $connection->credentials = $credentials;
        $connection->token_expires_at = $expiresAt;
        $connection->status = ConnectionStatus::ACTIVE;
        $connection->error_message = null;

        return $connection->save();
    }

    public function getCredentials(int $id): ?array
    {
        $connection = IntegrationConnection::find($id);
        return $connection?->credentials;
    }

    public function hasValidToken(int $id): bool
    {
        $connection = IntegrationConnection::find($id);
        return $connection?->hasValidToken() ?? false;
    }

    public function updateSettings(int $id, array $settings): bool
    {
        return IntegrationConnection::where('id', $id)->update([
            'settings' => $settings,
        ]) > 0;
    }

    public function getSetting(int $id, string $key, mixed $default = null): mixed
    {
        $connection = IntegrationConnection::find($id);
        return $connection?->getSetting($key, $default) ?? $default;
    }

    public function updateLastSyncAt(int $id): bool
    {
        return IntegrationConnection::where('id', $id)->update([
            'last_sync_at' => now(),
        ]) > 0;
    }

    public function getConnectionsNeedingTokenRefresh(int $minutesBeforeExpiry = 5): array
    {
        return IntegrationConnection::needsTokenRefresh($minutesBeforeExpiry)
            ->get()
            ->toArray();
    }

    // Sync logs
    public function createSyncLog(int $connectionId, array $data): array
    {
        $log = IntegrationSyncLog::create(array_merge($data, [
            'connection_id' => $connectionId,
            'started_at' => now(),
            'status' => 'running',
        ]));
        return $log->toArray();
    }

    public function updateSyncLog(int $logId, array $data): bool
    {
        return IntegrationSyncLog::where('id', $logId)->update($data) > 0;
    }

    public function completeSyncLog(int $logId, array $summary): bool
    {
        $log = IntegrationSyncLog::find($logId);
        if (!$log) {
            return false;
        }

        $log->complete($summary);
        return true;
    }

    public function getSyncLogs(int $connectionId, int $limit = 50): array
    {
        return IntegrationSyncLog::where('connection_id', $connectionId)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getLatestSyncLog(int $connectionId, ?string $entityType = null): ?array
    {
        $query = IntegrationSyncLog::where('connection_id', $connectionId);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $log = $query->orderByDesc('started_at')->first();
        return $log?->toArray();
    }

    // Field mappings
    public function getFieldMappings(int $connectionId, ?string $crmEntity = null): array
    {
        $query = IntegrationFieldMapping::where('connection_id', $connectionId);

        if ($crmEntity) {
            $query->where('crm_entity', $crmEntity);
        }

        return $query->orderBy('sort_order')->get()->toArray();
    }

    public function createFieldMapping(int $connectionId, array $data): array
    {
        $mapping = IntegrationFieldMapping::create(array_merge($data, [
            'connection_id' => $connectionId,
        ]));
        return $mapping->toArray();
    }

    public function updateFieldMapping(int $mappingId, array $data): bool
    {
        return IntegrationFieldMapping::where('id', $mappingId)->update($data) > 0;
    }

    public function deleteFieldMapping(int $mappingId): bool
    {
        return IntegrationFieldMapping::destroy($mappingId) > 0;
    }

    public function setFieldMappings(int $connectionId, string $crmEntity, array $mappings): bool
    {
        // Delete existing mappings for this entity
        IntegrationFieldMapping::where('connection_id', $connectionId)
            ->where('crm_entity', $crmEntity)
            ->delete();

        // Create new mappings
        foreach ($mappings as $index => $mapping) {
            IntegrationFieldMapping::create(array_merge($mapping, [
                'connection_id' => $connectionId,
                'crm_entity' => $crmEntity,
                'sort_order' => $index,
            ]));
        }

        return true;
    }

    // Entity mappings
    public function findEntityMapping(int $connectionId, string $crmEntity, int $crmRecordId): ?array
    {
        $mapping = IntegrationEntityMapping::where('connection_id', $connectionId)
            ->forCrmRecord($crmEntity, $crmRecordId)
            ->first();
        return $mapping?->toArray();
    }

    public function findEntityMappingByExternalId(int $connectionId, string $externalEntity, string $externalId): ?array
    {
        $mapping = IntegrationEntityMapping::where('connection_id', $connectionId)
            ->forExternalRecord($externalEntity, $externalId)
            ->first();
        return $mapping?->toArray();
    }

    public function createEntityMapping(int $connectionId, array $data): array
    {
        $mapping = IntegrationEntityMapping::create(array_merge($data, [
            'connection_id' => $connectionId,
            'last_synced_at' => now(),
        ]));
        return $mapping->toArray();
    }

    public function updateEntityMapping(int $mappingId, array $data): bool
    {
        return IntegrationEntityMapping::where('id', $mappingId)->update($data) > 0;
    }

    public function deleteEntityMapping(int $mappingId): bool
    {
        return IntegrationEntityMapping::destroy($mappingId) > 0;
    }

    public function getEntityMappings(int $connectionId, string $crmEntity, array $crmRecordIds): array
    {
        return IntegrationEntityMapping::where('connection_id', $connectionId)
            ->where('crm_entity', $crmEntity)
            ->whereIn('crm_record_id', $crmRecordIds)
            ->get()
            ->toArray();
    }

    // Webhooks
    public function getWebhooks(int $connectionId): array
    {
        return IntegrationWebhook::where('connection_id', $connectionId)->get()->toArray();
    }

    public function createWebhook(int $connectionId, array $data): array
    {
        $webhook = IntegrationWebhook::create(array_merge($data, [
            'connection_id' => $connectionId,
        ]));
        return $webhook->toArray();
    }

    public function updateWebhook(int $webhookId, array $data): bool
    {
        return IntegrationWebhook::where('id', $webhookId)->update($data) > 0;
    }

    public function deleteWebhook(int $webhookId): bool
    {
        return IntegrationWebhook::destroy($webhookId) > 0;
    }

    public function findWebhookByExternalId(int $connectionId, string $externalWebhookId): ?array
    {
        $webhook = IntegrationWebhook::where('connection_id', $connectionId)
            ->where('webhook_id', $externalWebhookId)
            ->first();
        return $webhook?->toArray();
    }

    // Webhook logs
    public function createWebhookLog(int $webhookId, array $data): array
    {
        $log = IntegrationWebhookLog::create(array_merge($data, [
            'webhook_id' => $webhookId,
            'received_at' => now(),
            'status' => 'received',
        ]));
        return $log->toArray();
    }

    public function updateWebhookLog(int $logId, array $data): bool
    {
        return IntegrationWebhookLog::where('id', $logId)->update($data) > 0;
    }

    public function getWebhookLogs(int $webhookId, int $limit = 100): array
    {
        return IntegrationWebhookLog::where('webhook_id', $webhookId)
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // Statistics
    public function getConnectionStats(): array
    {
        $connections = IntegrationConnection::all();

        return [
            'total' => $connections->count(),
            'active' => $connections->where('status', ConnectionStatus::ACTIVE)->count(),
            'inactive' => $connections->where('status', ConnectionStatus::INACTIVE)->count(),
            'error' => $connections->where('status', ConnectionStatus::ERROR)->count(),
            'expired' => $connections->where('status', ConnectionStatus::EXPIRED)->count(),
            'by_category' => $this->getStatsByCategory($connections),
        ];
    }

    public function getSyncStats(int $connectionId, int $days = 30): array
    {
        $logs = IntegrationSyncLog::where('connection_id', $connectionId)
            ->where('started_at', '>=', now()->subDays($days))
            ->get();

        return [
            'total_syncs' => $logs->count(),
            'successful' => $logs->where('status', 'completed')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'records_processed' => $logs->sum('records_processed'),
            'records_created' => $logs->sum('records_created'),
            'records_updated' => $logs->sum('records_updated'),
            'records_failed' => $logs->sum('records_failed'),
            'avg_duration_ms' => $logs->avg('duration_ms'),
        ];
    }

    private function getStatsByCategory($connections): array
    {
        $stats = [];

        foreach (IntegrationCategory::cases() as $category) {
            $providers = IntegrationProvider::getByCategory($category);
            $slugs = array_map(fn($p) => $p->value, $providers);

            $categoryConnections = $connections->whereIn('integration_slug', $slugs);

            $stats[$category->value] = [
                'total' => $categoryConnections->count(),
                'active' => $categoryConnections->where('status', ConnectionStatus::ACTIVE)->count(),
            ];
        }

        return $stats;
    }
}
