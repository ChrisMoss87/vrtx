<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Integration;

use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domain\Integration\ValueObjects\ConnectionStatus;
use App\Domain\Integration\ValueObjects\IntegrationCategory;
use App\Domain\Integration\ValueObjects\IntegrationProvider;
use App\Domain\Integration\ValueObjects\SyncStatus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbIntegrationConnectionRepository implements IntegrationConnectionRepositoryInterface
{
    private const TABLE_CONNECTIONS = 'integration_connections';
    private const TABLE_SYNC_LOGS = 'integration_sync_logs';
    private const TABLE_FIELD_MAPPINGS = 'integration_field_mappings';
    private const TABLE_ENTITY_MAPPINGS = 'integration_entity_mappings';
    private const TABLE_WEBHOOKS = 'integration_webhooks';
    private const TABLE_WEBHOOK_LOGS = 'integration_webhook_logs';
    public function findById(int $id): ?array
    {
        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        return $connection ? $this->toArray($connection) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $connection = DB::table(self::TABLE_CONNECTIONS)
            ->where('integration_slug', $slug)
            ->first();
        return $connection ? $this->toArray($connection) : null;
    }

    public function findByProvider(IntegrationProvider $provider): ?array
    {
        return $this->findBySlug($provider->value);
    }

    public function getAll(): array
    {
        $connections = DB::table(self::TABLE_CONNECTIONS)
            ->orderBy('integration_slug')
            ->get();

        return array_map(fn($c) => $this->toArray($c), $connections->all());
    }

    public function getActive(): array
    {
        $connections = DB::table(self::TABLE_CONNECTIONS)
            ->where('status', ConnectionStatus::ACTIVE->value)
            ->orderBy('integration_slug')
            ->get();

        return array_map(fn($c) => $this->toArray($c), $connections->all());
    }

    public function getByCategory(IntegrationCategory $category): array
    {
        $providers = IntegrationProvider::getByCategory($category);
        $slugs = array_map(fn($p) => $p->value, $providers);

        $connections = DB::table(self::TABLE_CONNECTIONS)
            ->whereIn('integration_slug', $slugs)
            ->orderBy('integration_slug')
            ->get();

        return array_map(fn($c) => $this->toArray($c), $connections->all());
    }

    public function getByStatus(ConnectionStatus $status): array
    {
        $connections = DB::table(self::TABLE_CONNECTIONS)
            ->where('status', $status->value)
            ->orderBy('integration_slug')
            ->get();

        return array_map(fn($c) => $this->toArray($c), $connections->all());
    }

    public function getBySyncStatus(SyncStatus $status): array
    {
        $connections = DB::table(self::TABLE_CONNECTIONS)
            ->where('sync_status', $status->value)
            ->orderBy('integration_slug')
            ->get();

        return array_map(fn($c) => $this->toArray($c), $connections->all());
    }

    public function create(array $data): array
    {
        // Handle credentials encryption
        if (isset($data['credentials'])) {
            $data['credentials'] = Crypt::encryptString(json_encode($data['credentials']));
        }

        // Handle JSON fields
        if (isset($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }
        if (isset($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        // Handle enum values
        if (isset($data['status']) && $data['status'] instanceof ConnectionStatus) {
            $data['status'] = $data['status']->value;
        }
        if (isset($data['sync_status']) && $data['sync_status'] instanceof SyncStatus) {
            $data['sync_status'] = $data['sync_status']->value;
        }

        // Add timestamps
        $now = now();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $id = DB::table(self::TABLE_CONNECTIONS)->insertGetId($data);

        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        return $this->toArray($connection);
    }

    public function update(int $id, array $data): array
    {
        // Handle credentials encryption
        if (isset($data['credentials'])) {
            if ($data['credentials'] === null) {
                $data['credentials'] = null;
            } else {
                $data['credentials'] = Crypt::encryptString(json_encode($data['credentials']));
            }
        }

        // Handle JSON fields
        if (isset($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }
        if (isset($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        // Handle enum values
        if (isset($data['status']) && $data['status'] instanceof ConnectionStatus) {
            $data['status'] = $data['status']->value;
        }
        if (isset($data['sync_status']) && $data['sync_status'] instanceof SyncStatus) {
            $data['sync_status'] = $data['sync_status']->value;
        }

        // Update timestamp
        $data['updated_at'] = now();

        DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->update($data);

        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        return $this->toArray($connection);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->delete() > 0;
    }

    public function updateStatus(int $id, ConnectionStatus $status, ?string $errorMessage = null): bool
    {
        return DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->update([
            'status' => $status->value,
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]) > 0;
    }

    public function updateSyncStatus(int $id, SyncStatus $status): bool
    {
        return DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->update([
            'sync_status' => $status->value,
            'updated_at' => now(),
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
        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        if (!$connection) {
            return false;
        }

        return DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->update([
            'credentials' => Crypt::encryptString(json_encode($credentials)),
            'updated_at' => now(),
        ]) > 0;
    }

    public function updateTokens(int $id, string $accessToken, ?string $refreshToken, ?\DateTimeInterface $expiresAt): bool
    {
        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        if (!$connection) {
            return false;
        }

        // Get existing credentials
        $credentials = $this->decryptCredentials($connection->credentials);
        $credentials['access_token'] = $accessToken;
        if ($refreshToken !== null) {
            $credentials['refresh_token'] = $refreshToken;
        }

        return DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->update([
            'credentials' => Crypt::encryptString(json_encode($credentials)),
            'token_expires_at' => $expiresAt,
            'status' => ConnectionStatus::ACTIVE->value,
            'error_message' => null,
            'updated_at' => now(),
        ]) > 0;
    }

    public function getCredentials(int $id): ?array
    {
        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        if (!$connection) {
            return null;
        }

        return $this->decryptCredentials($connection->credentials);
    }

    public function hasValidToken(int $id): bool
    {
        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        if (!$connection) {
            return false;
        }

        // Check if connected
        if ($connection->status !== ConnectionStatus::ACTIVE->value) {
            return false;
        }

        // If no expiry, it's API key based
        if ($connection->token_expires_at === null) {
            return true;
        }

        // Check if token is still valid
        return now()->lessThan($connection->token_expires_at);
    }

    public function updateSettings(int $id, array $settings): bool
    {
        return DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->update([
            'settings' => json_encode($settings),
            'updated_at' => now(),
        ]) > 0;
    }

    public function getSetting(int $id, string $key, mixed $default = null): mixed
    {
        $connection = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        if (!$connection) {
            return $default;
        }

        $settings = json_decode($connection->settings ?? '[]', true);
        return data_get($settings, $key, $default);
    }

    public function updateLastSyncAt(int $id): bool
    {
        return DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->update([
            'last_sync_at' => now(),
            'updated_at' => now(),
        ]) > 0;
    }

    public function getConnectionsNeedingTokenRefresh(int $minutesBeforeExpiry = 5): array
    {
        $connections = DB::table(self::TABLE_CONNECTIONS)
            ->where('status', ConnectionStatus::ACTIVE->value)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addMinutes($minutesBeforeExpiry))
            ->get();

        return array_map(fn($c) => $this->toArray($c), $connections->all());
    }

    // Sync logs
    public function createSyncLog(int $connectionId, array $data): array
    {
        // Handle JSON fields
        if (isset($data['errors'])) {
            $data['errors'] = json_encode($data['errors']);
        }
        if (isset($data['summary'])) {
            $data['summary'] = json_encode($data['summary']);
        }

        // Handle enum values
        if (isset($data['direction']) && is_object($data['direction'])) {
            $data['direction'] = $data['direction']->value;
        }

        $now = now();
        $data['connection_id'] = $connectionId;
        $data['started_at'] = $now;
        $data['status'] = 'running';
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $id = DB::table(self::TABLE_SYNC_LOGS)->insertGetId($data);

        $log = DB::table(self::TABLE_SYNC_LOGS)->where('id', $id)->first();
        return $this->syncLogToArray($log);
    }

    public function updateSyncLog(int $logId, array $data): bool
    {
        // Handle JSON fields
        if (isset($data['errors'])) {
            $data['errors'] = json_encode($data['errors']);
        }
        if (isset($data['summary'])) {
            $data['summary'] = json_encode($data['summary']);
        }

        $data['updated_at'] = now();

        return DB::table(self::TABLE_SYNC_LOGS)->where('id', $logId)->update($data) > 0;
    }

    public function completeSyncLog(int $logId, array $summary): bool
    {
        $log = DB::table(self::TABLE_SYNC_LOGS)->where('id', $logId)->first();
        if (!$log) {
            return false;
        }

        $startedAt = now()->parse($log->started_at);
        $completedAt = now();
        $durationMs = $completedAt->diffInMilliseconds($startedAt);

        return DB::table(self::TABLE_SYNC_LOGS)->where('id', $logId)->update([
            'status' => 'completed',
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
            'summary' => json_encode($summary),
            'updated_at' => $completedAt,
        ]) > 0;
    }

    public function getSyncLogs(int $connectionId, int $limit = 50): array
    {
        $logs = DB::table(self::TABLE_SYNC_LOGS)
            ->where('connection_id', $connectionId)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();

        return array_map(fn($l) => $this->syncLogToArray($l), $logs->all());
    }

    public function getLatestSyncLog(int $connectionId, ?string $entityType = null): ?array
    {
        $query = DB::table(self::TABLE_SYNC_LOGS)
            ->where('connection_id', $connectionId);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $log = $query->orderByDesc('started_at')->first();
        return $log ? $this->syncLogToArray($log) : null;
    }

    // Field mappings
    public function getFieldMappings(int $connectionId, ?string $crmEntity = null): array
    {
        $query = DB::table(self::TABLE_FIELD_MAPPINGS)
            ->where('connection_id', $connectionId);

        if ($crmEntity) {
            $query->where('crm_entity', $crmEntity);
        }

        $mappings = $query->orderBy('sort_order')->get();
        return array_map(fn($m) => $this->fieldMappingToArray($m), $mappings->all());
    }

    public function createFieldMapping(int $connectionId, array $data): array
    {
        // Handle JSON fields
        if (isset($data['transformation_rules'])) {
            $data['transformation_rules'] = json_encode($data['transformation_rules']);
        }

        $now = now();
        $data['connection_id'] = $connectionId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $id = DB::table(self::TABLE_FIELD_MAPPINGS)->insertGetId($data);

        $mapping = DB::table(self::TABLE_FIELD_MAPPINGS)->where('id', $id)->first();
        return $this->fieldMappingToArray($mapping);
    }

    public function updateFieldMapping(int $mappingId, array $data): bool
    {
        // Handle JSON fields
        if (isset($data['transformation_rules'])) {
            $data['transformation_rules'] = json_encode($data['transformation_rules']);
        }

        $data['updated_at'] = now();

        return DB::table(self::TABLE_FIELD_MAPPINGS)->where('id', $mappingId)->update($data) > 0;
    }

    public function deleteFieldMapping(int $mappingId): bool
    {
        return DB::table(self::TABLE_FIELD_MAPPINGS)->where('id', $mappingId)->delete() > 0;
    }

    public function setFieldMappings(int $connectionId, string $crmEntity, array $mappings): bool
    {
        // Delete existing mappings for this entity
        DB::table(self::TABLE_FIELD_MAPPINGS)
            ->where('connection_id', $connectionId)
            ->where('crm_entity', $crmEntity)
            ->delete();

        // Create new mappings
        $now = now();
        foreach ($mappings as $index => $mapping) {
            // Handle JSON fields
            if (isset($mapping['transformation_rules'])) {
                $mapping['transformation_rules'] = json_encode($mapping['transformation_rules']);
            }

            DB::table(self::TABLE_FIELD_MAPPINGS)->insert(array_merge($mapping, [
                'connection_id' => $connectionId,
                'crm_entity' => $crmEntity,
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        return true;
    }

    // Entity mappings
    public function findEntityMapping(int $connectionId, string $crmEntity, int $crmRecordId): ?array
    {
        $mapping = DB::table(self::TABLE_ENTITY_MAPPINGS)
            ->where('connection_id', $connectionId)
            ->where('crm_entity', $crmEntity)
            ->where('crm_record_id', $crmRecordId)
            ->first();
        return $mapping ? $this->entityMappingToArray($mapping) : null;
    }

    public function findEntityMappingByExternalId(int $connectionId, string $externalEntity, string $externalId): ?array
    {
        $mapping = DB::table(self::TABLE_ENTITY_MAPPINGS)
            ->where('connection_id', $connectionId)
            ->where('external_entity', $externalEntity)
            ->where('external_id', $externalId)
            ->first();
        return $mapping ? $this->entityMappingToArray($mapping) : null;
    }

    public function createEntityMapping(int $connectionId, array $data): array
    {
        // Handle JSON fields
        if (isset($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        $now = now();
        $data['connection_id'] = $connectionId;
        $data['last_synced_at'] = $now;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $id = DB::table(self::TABLE_ENTITY_MAPPINGS)->insertGetId($data);

        $mapping = DB::table(self::TABLE_ENTITY_MAPPINGS)->where('id', $id)->first();
        return $this->entityMappingToArray($mapping);
    }

    public function updateEntityMapping(int $mappingId, array $data): bool
    {
        // Handle JSON fields
        if (isset($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        $data['updated_at'] = now();

        return DB::table(self::TABLE_ENTITY_MAPPINGS)->where('id', $mappingId)->update($data) > 0;
    }

    public function deleteEntityMapping(int $mappingId): bool
    {
        return DB::table(self::TABLE_ENTITY_MAPPINGS)->where('id', $mappingId)->delete() > 0;
    }

    public function getEntityMappings(int $connectionId, string $crmEntity, array $crmRecordIds): array
    {
        $mappings = DB::table(self::TABLE_ENTITY_MAPPINGS)
            ->where('connection_id', $connectionId)
            ->where('crm_entity', $crmEntity)
            ->whereIn('crm_record_id', $crmRecordIds)
            ->get();

        return array_map(fn($m) => $this->entityMappingToArray($m), $mappings->all());
    }

    // Webhooks
    public function getWebhooks(int $connectionId): array
    {
        $webhooks = DB::table(self::TABLE_WEBHOOKS)
            ->where('connection_id', $connectionId)
            ->get();

        return array_map(fn($w) => $this->webhookToArray($w), $webhooks->all());
    }

    public function createWebhook(int $connectionId, array $data): array
    {
        // Handle JSON fields
        if (isset($data['events'])) {
            $data['events'] = json_encode($data['events']);
        }
        if (isset($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }

        $now = now();
        $data['connection_id'] = $connectionId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $id = DB::table(self::TABLE_WEBHOOKS)->insertGetId($data);

        $webhook = DB::table(self::TABLE_WEBHOOKS)->where('id', $id)->first();
        return $this->webhookToArray($webhook);
    }

    public function updateWebhook(int $webhookId, array $data): bool
    {
        // Handle JSON fields
        if (isset($data['events'])) {
            $data['events'] = json_encode($data['events']);
        }
        if (isset($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }

        $data['updated_at'] = now();

        return DB::table(self::TABLE_WEBHOOKS)->where('id', $webhookId)->update($data) > 0;
    }

    public function deleteWebhook(int $webhookId): bool
    {
        return DB::table(self::TABLE_WEBHOOKS)->where('id', $webhookId)->delete() > 0;
    }

    public function findWebhookByExternalId(int $connectionId, string $externalWebhookId): ?array
    {
        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('connection_id', $connectionId)
            ->where('webhook_id', $externalWebhookId)
            ->first();
        return $webhook ? $this->webhookToArray($webhook) : null;
    }

    // Webhook logs
    public function createWebhookLog(int $webhookId, array $data): array
    {
        // Handle JSON fields
        if (isset($data['payload'])) {
            $data['payload'] = json_encode($data['payload']);
        }
        if (isset($data['headers'])) {
            $data['headers'] = json_encode($data['headers']);
        }
        if (isset($data['response'])) {
            $data['response'] = json_encode($data['response']);
        }

        $now = now();
        $data['webhook_id'] = $webhookId;
        $data['received_at'] = $now;
        $data['status'] = 'received';
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $id = DB::table(self::TABLE_WEBHOOK_LOGS)->insertGetId($data);

        $log = DB::table(self::TABLE_WEBHOOK_LOGS)->where('id', $id)->first();
        return $this->webhookLogToArray($log);
    }

    public function updateWebhookLog(int $logId, array $data): bool
    {
        // Handle JSON fields
        if (isset($data['response'])) {
            $data['response'] = json_encode($data['response']);
        }

        $data['updated_at'] = now();

        return DB::table(self::TABLE_WEBHOOK_LOGS)->where('id', $logId)->update($data) > 0;
    }

    public function getWebhookLogs(int $webhookId, int $limit = 100): array
    {
        $logs = DB::table(self::TABLE_WEBHOOK_LOGS)
            ->where('webhook_id', $webhookId)
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get();

        return array_map(fn($l) => $this->webhookLogToArray($l), $logs->all());
    }

    // Statistics
    public function getConnectionStats(): array
    {
        $connections = DB::table(self::TABLE_CONNECTIONS)->get();

        $total = $connections->count();
        $active = $connections->where('status', ConnectionStatus::ACTIVE->value)->count();
        $inactive = $connections->where('status', ConnectionStatus::INACTIVE->value)->count();
        $error = $connections->where('status', ConnectionStatus::ERROR->value)->count();
        $expired = $connections->where('status', ConnectionStatus::EXPIRED->value)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'error' => $error,
            'expired' => $expired,
            'by_category' => $this->getStatsByCategory($connections),
        ];
    }

    public function getSyncStats(int $connectionId, int $days = 30): array
    {
        $logs = DB::table(self::TABLE_SYNC_LOGS)
            ->where('connection_id', $connectionId)
            ->where('started_at', '>=', now()->subDays($days))
            ->get();

        $successful = $logs->where('status', 'completed')->count();
        $failed = $logs->where('status', 'failed')->count();
        $recordsProcessed = $logs->sum('records_processed');
        $recordsCreated = $logs->sum('records_created');
        $recordsUpdated = $logs->sum('records_updated');
        $recordsFailed = $logs->sum('records_failed');
        $avgDurationMs = $logs->avg('duration_ms');

        return [
            'total_syncs' => $logs->count(),
            'successful' => $successful,
            'failed' => $failed,
            'records_processed' => $recordsProcessed,
            'records_created' => $recordsCreated,
            'records_updated' => $recordsUpdated,
            'records_failed' => $recordsFailed,
            'avg_duration_ms' => $avgDurationMs,
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
                'active' => $categoryConnections->where('status', ConnectionStatus::ACTIVE->value)->count(),
            ];
        }

        return $stats;
    }

    // Helper methods to convert stdClass to array
    private function toArray(stdClass $connection): array
    {
        $array = (array) $connection;

        // Decode JSON fields
        if (isset($array['settings'])) {
            $array['settings'] = json_decode($array['settings'] ?? '[]', true);
        }
        if (isset($array['metadata'])) {
            $array['metadata'] = json_decode($array['metadata'] ?? '[]', true);
        }

        // Decrypt credentials
        if (isset($array['credentials'])) {
            $array['credentials'] = $this->decryptCredentials($array['credentials']);
        }

        return $array;
    }

    private function syncLogToArray(stdClass $log): array
    {
        $array = (array) $log;

        // Decode JSON fields
        if (isset($array['errors'])) {
            $array['errors'] = json_decode($array['errors'] ?? '[]', true);
        }
        if (isset($array['summary'])) {
            $array['summary'] = json_decode($array['summary'] ?? '[]', true);
        }

        return $array;
    }

    private function fieldMappingToArray(stdClass $mapping): array
    {
        $array = (array) $mapping;

        // Decode JSON fields
        if (isset($array['transformation_rules'])) {
            $array['transformation_rules'] = json_decode($array['transformation_rules'] ?? '[]', true);
        }

        return $array;
    }

    private function entityMappingToArray(stdClass $mapping): array
    {
        $array = (array) $mapping;

        // Decode JSON fields
        if (isset($array['metadata'])) {
            $array['metadata'] = json_decode($array['metadata'] ?? '[]', true);
        }

        return $array;
    }

    private function webhookToArray(stdClass $webhook): array
    {
        $array = (array) $webhook;

        // Decode JSON fields
        if (isset($array['events'])) {
            $array['events'] = json_decode($array['events'] ?? '[]', true);
        }
        if (isset($array['config'])) {
            $array['config'] = json_decode($array['config'] ?? '[]', true);
        }

        return $array;
    }

    private function webhookLogToArray(stdClass $log): array
    {
        $array = (array) $log;

        // Decode JSON fields
        if (isset($array['payload'])) {
            $array['payload'] = json_decode($array['payload'] ?? '[]', true);
        }
        if (isset($array['headers'])) {
            $array['headers'] = json_decode($array['headers'] ?? '[]', true);
        }
        if (isset($array['response'])) {
            $array['response'] = json_decode($array['response'] ?? '[]', true);
        }

        return $array;
    }

    private function decryptCredentials(?string $encryptedCredentials): ?array
    {
        if ($encryptedCredentials === null) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($encryptedCredentials), true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
