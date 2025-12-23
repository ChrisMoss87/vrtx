<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Webhook;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use App\Models\IncomingWebhook;
use App\Models\IncomingWebhookLog;
use App\Models\ModuleRecord;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

class EloquentWebhookRepository implements WebhookRepositoryInterface
{
    // =========================================================================
    // OUTGOING WEBHOOKS - QUERY METHODS
    // =========================================================================

    public function listWebhooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = Webhook::query()->with(['user:id,name,email', 'module:id,name']);

        // Filter by status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by event
        if (!empty($filters['event'])) {
            $query->forEvent($filters['event']);
        }

        // Filter by module
        if (!empty($filters['module_id'])) {
            $query->forModule($filters['module_id']);
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function getWebhook(int $id): ?array
    {
        $webhook = Webhook::with(['user:id,name,email', 'module', 'deliveries' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(10);
        }])->find($id);

        return $webhook ? $webhook->toArray() : null;
    }

    public function getDeliveryHistory(int $webhookId, int $perPage = 50, int $page = 1): PaginatedResult
    {
        $paginator = WebhookDelivery::where('webhook_id', $webhookId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function getWebhookStats(int $webhookId): array
    {
        $webhook = Webhook::findOrFail($webhookId);

        $deliveries = $webhook->deliveries();
        $recentDeliveries = (clone $deliveries)->recent(7);

        return [
            'webhook' => $webhook->toArray(),
            'total_deliveries' => $deliveries->count(),
            'success_count' => $webhook->success_count,
            'failure_count' => $webhook->failure_count,
            'success_rate' => ($webhook->success_count + $webhook->failure_count) > 0
                ? round(($webhook->success_count / ($webhook->success_count + $webhook->failure_count)) * 100, 1)
                : 0,
            'recent_deliveries' => $recentDeliveries->count(),
            'recent_failures' => (clone $recentDeliveries)->failed()->count(),
            'avg_response_time' => (clone $recentDeliveries)
                ->where('status', WebhookDelivery::STATUS_SUCCESS)
                ->avg('response_time_ms') ?? 0,
            'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
        ];
    }

    public function getWebhooksForEvent(string $event, ?int $moduleId = null): array
    {
        $webhooks = Webhook::active()
            ->forEvent($event)
            ->forModule($moduleId)
            ->get();

        return $webhooks->map(fn($webhook) => $webhook->toArray())->toArray();
    }

    public function getAvailableEvents(): array
    {
        return Webhook::getAvailableEvents();
    }

    // =========================================================================
    // OUTGOING WEBHOOKS - COMMAND METHODS
    // =========================================================================

    public function createWebhook(array $data): array
    {
        $webhook = Webhook::create([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'],
            'secret' => Webhook::generateSecret(),
            'events' => $data['events'] ?? [],
            'module_id' => $data['module_id'] ?? null,
            'headers' => $data['headers'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'verify_ssl' => $data['verify_ssl'] ?? true,
            'timeout' => $data['timeout'] ?? 30,
            'retry_count' => $data['retry_count'] ?? 3,
            'retry_delay' => $data['retry_delay'] ?? 60,
        ]);

        return $webhook->toArray();
    }

    public function updateWebhook(int $id, array $data): array
    {
        $webhook = Webhook::findOrFail($id);

        $webhook->update([
            'name' => $data['name'] ?? $webhook->name,
            'description' => $data['description'] ?? $webhook->description,
            'url' => $data['url'] ?? $webhook->url,
            'events' => $data['events'] ?? $webhook->events,
            'module_id' => $data['module_id'] ?? $webhook->module_id,
            'headers' => $data['headers'] ?? $webhook->headers,
            'is_active' => $data['is_active'] ?? $webhook->is_active,
            'verify_ssl' => $data['verify_ssl'] ?? $webhook->verify_ssl,
            'timeout' => $data['timeout'] ?? $webhook->timeout,
            'retry_count' => $data['retry_count'] ?? $webhook->retry_count,
            'retry_delay' => $data['retry_delay'] ?? $webhook->retry_delay,
        ]);

        return $webhook->fresh()->toArray();
    }

    public function deleteWebhook(int $id): bool
    {
        $webhook = Webhook::findOrFail($id);
        return $webhook->delete();
    }

    public function regenerateSecret(int $id): string
    {
        $webhook = Webhook::findOrFail($id);
        $newSecret = Webhook::generateSecret();

        $webhook->update(['secret' => $newSecret]);

        return $newSecret;
    }

    public function toggleActive(int $id): array
    {
        $webhook = Webhook::findOrFail($id);
        $webhook->update(['is_active' => !$webhook->is_active]);
        return $webhook->fresh()->toArray();
    }

    // =========================================================================
    // WEBHOOK DELIVERY METHODS
    // =========================================================================

    public function queueDelivery(int $webhookId, string $event, array $payload): array
    {
        $fullPayload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'webhook_id' => $webhookId,
            'data' => $payload,
        ];

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => $fullPayload,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        return $delivery->toArray();
    }

    public function getPendingDeliveries(int $limit = 100): array
    {
        $deliveries = WebhookDelivery::readyForRetry()
            ->with('webhook')
            ->limit($limit)
            ->get();

        return $deliveries->map(fn($delivery) => $delivery->toArray())->toArray();
    }

    public function getDelivery(int $deliveryId): ?array
    {
        $delivery = WebhookDelivery::find($deliveryId);
        return $delivery ? $delivery->toArray() : null;
    }

    public function updateDeliveryStatus(int $deliveryId, array $data): array
    {
        $delivery = WebhookDelivery::findOrFail($deliveryId);
        $delivery->update($data);
        return $delivery->fresh()->toArray();
    }

    public function getWebhookForDelivery(int $deliveryId): ?array
    {
        $delivery = WebhookDelivery::with('webhook')->find($deliveryId);
        return $delivery && $delivery->webhook ? $delivery->webhook->toArray() : null;
    }

    // =========================================================================
    // INCOMING WEBHOOKS - QUERY METHODS
    // =========================================================================

    public function listIncomingWebhooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = IncomingWebhook::query()->with(['user:id,name,email', 'module:id,name,api_name']);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function getIncomingWebhook(int $id): ?array
    {
        $webhook = IncomingWebhook::with(['user:id,name,email', 'module', 'logs' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(20);
        }])->find($id);

        return $webhook ? $webhook->toArray() : null;
    }

    public function getIncomingWebhookLogs(int $webhookId, int $perPage = 50, int $page = 1): PaginatedResult
    {
        $paginator = IncomingWebhookLog::where('incoming_webhook_id', $webhookId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function findIncomingWebhookByToken(string $token): ?array
    {
        $webhook = IncomingWebhook::findByToken($token);
        return $webhook ? $webhook->toArray() : null;
    }

    // =========================================================================
    // INCOMING WEBHOOKS - COMMAND METHODS
    // =========================================================================

    public function createIncomingWebhook(array $data): array
    {
        $webhook = IncomingWebhook::create([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'token' => IncomingWebhook::generateToken(),
            'module_id' => $data['module_id'],
            'field_mapping' => $data['field_mapping'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'action' => $data['action'] ?? IncomingWebhook::ACTION_CREATE,
            'upsert_field' => $data['upsert_field'] ?? null,
        ]);

        return $webhook->toArray();
    }

    public function updateIncomingWebhook(int $id, array $data): array
    {
        $webhook = IncomingWebhook::findOrFail($id);

        $webhook->update([
            'name' => $data['name'] ?? $webhook->name,
            'description' => $data['description'] ?? $webhook->description,
            'module_id' => $data['module_id'] ?? $webhook->module_id,
            'field_mapping' => $data['field_mapping'] ?? $webhook->field_mapping,
            'is_active' => $data['is_active'] ?? $webhook->is_active,
            'action' => $data['action'] ?? $webhook->action,
            'upsert_field' => $data['upsert_field'] ?? $webhook->upsert_field,
        ]);

        return $webhook->fresh()->toArray();
    }

    public function deleteIncomingWebhook(int $id): bool
    {
        $webhook = IncomingWebhook::findOrFail($id);
        return $webhook->delete();
    }

    public function regenerateIncomingToken(int $id): string
    {
        $webhook = IncomingWebhook::findOrFail($id);
        $newToken = IncomingWebhook::generateToken();

        $webhook->update(['token' => $newToken]);

        return $newToken;
    }

    public function createIncomingWebhookLog(array $data): array
    {
        $log = IncomingWebhookLog::create([
            'incoming_webhook_id' => $data['incoming_webhook_id'],
            'payload' => $data['payload'],
            'headers' => $data['headers'] ?? [],
            'ip_address' => $data['ip_address'] ?? null,
            'status' => $data['status'] ?? 'processing',
            'created_at' => now(),
        ]);

        return $log->toArray();
    }

    public function updateIncomingWebhookLog(int $logId, array $data): array
    {
        $log = IncomingWebhookLog::findOrFail($logId);
        $log->update($data);
        return $log->fresh()->toArray();
    }

    public function recordIncomingWebhookReceived(int $webhookId): void
    {
        $webhook = IncomingWebhook::findOrFail($webhookId);
        $webhook->recordReceived();
    }

    // =========================================================================
    // MODULE RECORD METHODS
    // =========================================================================

    public function createModuleRecord(int $moduleId, array $data, int $userId): array
    {
        $record = ModuleRecord::create([
            'module_id' => $moduleId,
            'data' => $data,
            'created_by' => $userId,
        ]);

        return $record->toArray();
    }

    public function updateModuleRecord(int $recordId, array $data): array
    {
        $record = ModuleRecord::findOrFail($recordId);
        $record->update(['data' => array_merge($record->data ?? [], $data)]);
        return $record->fresh()->toArray();
    }

    public function findModuleRecordByField(int $moduleId, string $field, mixed $value): ?array
    {
        $record = ModuleRecord::where('module_id', $moduleId)
            ->whereRaw("data->>? = ?", [$field, $value])
            ->first();

        return $record ? $record->toArray() : null;
    }
}
