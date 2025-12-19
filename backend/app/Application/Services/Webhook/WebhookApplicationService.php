<?php

declare(strict_types=1);

namespace App\Application\Services\Webhook;

use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use App\Models\IncomingWebhook;
use App\Models\IncomingWebhookLog;
use App\Models\ModuleRecord;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class WebhookApplicationService
{
    public function __construct(
        private WebhookRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - OUTGOING WEBHOOKS
    // =========================================================================

    /**
     * List outgoing webhooks with filtering and pagination.
     */
    public function listWebhooks(array $filters = [], int $perPage = 25): LengthAwarePaginator
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

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a webhook by ID.
     */
    public function getWebhook(int $id): ?Webhook
    {
        return Webhook::with(['user:id,name,email', 'module', 'deliveries' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(10);
        }])->find($id);
    }

    /**
     * Get webhook delivery history.
     */
    public function getDeliveryHistory(int $webhookId, int $perPage = 50): LengthAwarePaginator
    {
        return WebhookDelivery::where('webhook_id', $webhookId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get webhook statistics.
     */
    public function getWebhookStats(int $webhookId): array
    {
        $webhook = Webhook::findOrFail($webhookId);

        $deliveries = $webhook->deliveries();
        $recentDeliveries = (clone $deliveries)->recent(7);

        return [
            'webhook' => $webhook,
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
            'last_triggered_at' => $webhook->last_triggered_at,
        ];
    }

    /**
     * Get available webhook events.
     */
    public function getAvailableEvents(): array
    {
        return Webhook::getAvailableEvents();
    }

    // =========================================================================
    // COMMAND USE CASES - OUTGOING WEBHOOKS
    // =========================================================================

    /**
     * Create a new outgoing webhook.
     */
    public function createWebhook(array $data): Webhook
    {
        return Webhook::create([
            'user_id' => Auth::id(),
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
    }

    /**
     * Update a webhook.
     */
    public function updateWebhook(int $id, array $data): Webhook
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

        return $webhook->fresh();
    }

    /**
     * Delete a webhook.
     */
    public function deleteWebhook(int $id): bool
    {
        $webhook = Webhook::findOrFail($id);
        return $webhook->delete();
    }

    /**
     * Regenerate webhook secret.
     */
    public function regenerateSecret(int $id): string
    {
        $webhook = Webhook::findOrFail($id);
        $newSecret = Webhook::generateSecret();

        $webhook->update(['secret' => $newSecret]);

        return $newSecret;
    }

    /**
     * Toggle webhook active status.
     */
    public function toggleActive(int $id): Webhook
    {
        $webhook = Webhook::findOrFail($id);
        $webhook->update(['is_active' => !$webhook->is_active]);
        return $webhook->fresh();
    }

    /**
     * Test a webhook by sending a test payload.
     */
    public function testWebhook(int $id): array
    {
        $webhook = Webhook::findOrFail($id);

        $testPayload = [
            'event' => 'test',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'This is a test webhook delivery',
                'webhook_id' => $webhook->id,
            ],
        ];

        return $this->deliverPayload($webhook, 'test', $testPayload);
    }

    // =========================================================================
    // EVENT DISPATCHING
    // =========================================================================

    /**
     * Trigger webhooks for an event.
     */
    public function triggerEvent(string $event, array $payload, ?int $moduleId = null): array
    {
        $webhooks = Webhook::active()
            ->forEvent($event)
            ->forModule($moduleId)
            ->get();

        $results = [];

        foreach ($webhooks as $webhook) {
            $results[$webhook->id] = $this->queueDelivery($webhook, $event, $payload);
        }

        return $results;
    }

    /**
     * Queue a webhook delivery.
     */
    public function queueDelivery(Webhook $webhook, string $event, array $payload): WebhookDelivery
    {
        $fullPayload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'webhook_id' => $webhook->id,
            'data' => $payload,
        ];

        return WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $fullPayload,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * Process pending deliveries.
     */
    public function processPendingDeliveries(int $limit = 100): array
    {
        $deliveries = WebhookDelivery::readyForRetry()
            ->with('webhook')
            ->limit($limit)
            ->get();

        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

        foreach ($deliveries as $delivery) {
            $result = $this->executeDelivery($delivery);
            $results['processed']++;

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Execute a single delivery.
     */
    public function executeDelivery(WebhookDelivery $delivery): array
    {
        $webhook = $delivery->webhook;
        $startTime = microtime(true);

        try {
            $signature = $webhook->signPayload($delivery->payload);

            $headers = array_merge($webhook->headers ?? [], [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'User-Agent' => 'CRM-Webhook/1.0',
            ]);

            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout)
                ->withOptions(['verify' => $webhook->verify_ssl])
                ->post($webhook->url, $delivery->payload);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $delivery->markAsSuccess(
                    $response->status(),
                    $response->body(),
                    $responseTimeMs
                );

                return ['success' => true, 'response_code' => $response->status()];
            } else {
                $delivery->markAsFailed(
                    $delivery->attempts + 1,
                    $response->status(),
                    "HTTP {$response->status()}: " . substr($response->body(), 0, 500),
                    $responseTimeMs
                );

                return ['success' => false, 'response_code' => $response->status()];
            }
        } catch (\Exception $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $delivery->markAsFailed(
                $delivery->attempts + 1,
                null,
                $e->getMessage(),
                $responseTimeMs
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deliver payload directly (synchronous).
     */
    public function deliverPayload(Webhook $webhook, string $event, array $payload): array
    {
        $delivery = $this->queueDelivery($webhook, $event, $payload);
        return $this->executeDelivery($delivery);
    }

    /**
     * Retry a failed delivery.
     */
    public function retryDelivery(int $deliveryId): array
    {
        $delivery = WebhookDelivery::findOrFail($deliveryId);

        if ($delivery->status !== WebhookDelivery::STATUS_FAILED) {
            throw new \InvalidArgumentException('Only failed deliveries can be retried');
        }

        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_retry_at' => null,
        ]);

        return $this->executeDelivery($delivery);
    }

    // =========================================================================
    // QUERY USE CASES - INCOMING WEBHOOKS
    // =========================================================================

    /**
     * List incoming webhooks.
     */
    public function listIncomingWebhooks(array $filters = [], int $perPage = 25): LengthAwarePaginator
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

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get an incoming webhook by ID.
     */
    public function getIncomingWebhook(int $id): ?IncomingWebhook
    {
        return IncomingWebhook::with(['user:id,name,email', 'module', 'logs' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(20);
        }])->find($id);
    }

    /**
     * Get incoming webhook logs.
     */
    public function getIncomingWebhookLogs(int $webhookId, int $perPage = 50): LengthAwarePaginator
    {
        return IncomingWebhookLog::where('incoming_webhook_id', $webhookId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    // =========================================================================
    // COMMAND USE CASES - INCOMING WEBHOOKS
    // =========================================================================

    /**
     * Create an incoming webhook.
     */
    public function createIncomingWebhook(array $data): IncomingWebhook
    {
        $webhook = IncomingWebhook::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'token' => IncomingWebhook::generateToken(),
            'module_id' => $data['module_id'],
            'field_mapping' => $data['field_mapping'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'action' => $data['action'] ?? IncomingWebhook::ACTION_CREATE,
            'upsert_field' => $data['upsert_field'] ?? null,
        ]);

        return $webhook;
    }

    /**
     * Update an incoming webhook.
     */
    public function updateIncomingWebhook(int $id, array $data): IncomingWebhook
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

        return $webhook->fresh();
    }

    /**
     * Delete an incoming webhook.
     */
    public function deleteIncomingWebhook(int $id): bool
    {
        $webhook = IncomingWebhook::findOrFail($id);
        return $webhook->delete();
    }

    /**
     * Regenerate incoming webhook token.
     */
    public function regenerateIncomingToken(int $id): string
    {
        $webhook = IncomingWebhook::findOrFail($id);
        $newToken = IncomingWebhook::generateToken();

        $webhook->update(['token' => $newToken]);

        return $newToken;
    }

    /**
     * Process an incoming webhook request.
     */
    public function processIncomingWebhook(string $token, array $payload, array $headers = []): array
    {
        $webhook = IncomingWebhook::findByToken($token);

        if (!$webhook) {
            return ['success' => false, 'error' => 'Invalid or inactive webhook token'];
        }

        // Log the incoming request
        $log = IncomingWebhookLog::create([
            'incoming_webhook_id' => $webhook->id,
            'payload' => $payload,
            'headers' => $headers,
            'ip_address' => request()->ip(),
            'status' => 'processing',
        ]);

        try {
            // Map the data
            $mappedData = $webhook->mapData($payload);

            if (empty($mappedData)) {
                throw new \InvalidArgumentException('No fields mapped from payload');
            }

            $result = match ($webhook->action) {
                IncomingWebhook::ACTION_CREATE => $this->createRecordFromWebhook($webhook, $mappedData),
                IncomingWebhook::ACTION_UPDATE => $this->updateRecordFromWebhook($webhook, $mappedData, $payload),
                IncomingWebhook::ACTION_UPSERT => $this->upsertRecordFromWebhook($webhook, $mappedData, $payload),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $log->update([
                'status' => 'success',
                'result' => $result,
            ]);

            $webhook->recordReceived();

            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create record from webhook data.
     */
    private function createRecordFromWebhook(IncomingWebhook $webhook, array $data): array
    {
        $record = ModuleRecord::create([
            'module_id' => $webhook->module_id,
            'data' => $data,
            'created_by' => $webhook->user_id,
        ]);

        return ['action' => 'created', 'record_id' => $record->id];
    }

    /**
     * Update record from webhook data.
     */
    private function updateRecordFromWebhook(IncomingWebhook $webhook, array $data, array $payload): array
    {
        $lookupField = $webhook->upsert_field;
        $lookupValue = $payload[$lookupField] ?? null;

        if (!$lookupValue) {
            throw new \InvalidArgumentException("Missing lookup field: {$lookupField}");
        }

        $record = ModuleRecord::where('module_id', $webhook->module_id)
            ->whereRaw("data->>? = ?", [$lookupField, $lookupValue])
            ->first();

        if (!$record) {
            throw new \InvalidArgumentException("Record not found for {$lookupField}: {$lookupValue}");
        }

        $record->update(['data' => array_merge($record->data ?? [], $data)]);

        return ['action' => 'updated', 'record_id' => $record->id];
    }

    /**
     * Upsert record from webhook data.
     */
    private function upsertRecordFromWebhook(IncomingWebhook $webhook, array $data, array $payload): array
    {
        $lookupField = $webhook->upsert_field;
        $lookupValue = $payload[$lookupField] ?? null;

        if (!$lookupValue) {
            // Create if no lookup value
            return $this->createRecordFromWebhook($webhook, $data);
        }

        $record = ModuleRecord::where('module_id', $webhook->module_id)
            ->whereRaw("data->>? = ?", [$lookupField, $lookupValue])
            ->first();

        if ($record) {
            $record->update(['data' => array_merge($record->data ?? [], $data)]);
            return ['action' => 'updated', 'record_id' => $record->id];
        } else {
            return $this->createRecordFromWebhook($webhook, $data);
        }
    }
}
