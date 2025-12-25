<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Webhook;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class DbWebhookRepository implements WebhookRepositoryInterface
{
    private const TABLE_WEBHOOKS = 'webhooks';
    private const TABLE_WEBHOOK_DELIVERIES = 'webhook_deliveries';
    private const TABLE_INCOMING_WEBHOOKS = 'incoming_webhooks';
    private const TABLE_INCOMING_WEBHOOK_LOGS = 'incoming_webhook_logs';
    private const TABLE_MODULE_RECORDS = 'module_records';
    private const TABLE_USERS = 'users';
    private const TABLE_MODULES = 'modules';

    // Webhook Event Constants
    private const EVENT_RECORD_CREATED = 'record.created';
    private const EVENT_RECORD_UPDATED = 'record.updated';
    private const EVENT_RECORD_DELETED = 'record.deleted';
    private const EVENT_DEAL_STAGE_CHANGED = 'deal.stage_changed';
    private const EVENT_DEAL_WON = 'deal.won';
    private const EVENT_DEAL_LOST = 'deal.lost';
    private const EVENT_EMAIL_RECEIVED = 'email.received';
    private const EVENT_EMAIL_OPENED = 'email.opened';
    private const EVENT_EMAIL_CLICKED = 'email.clicked';
    private const EVENT_WORKFLOW_TRIGGERED = 'workflow.triggered';
    private const EVENT_IMPORT_COMPLETED = 'import.completed';
    private const EVENT_EXPORT_COMPLETED = 'export.completed';

    // Webhook Delivery Status Constants
    private const STATUS_PENDING = 'pending';
    private const STATUS_SUCCESS = 'success';
    private const STATUS_FAILED = 'failed';

    // Incoming Webhook Action Constants
    private const ACTION_CREATE = 'create';
    private const ACTION_UPDATE = 'update';
    private const ACTION_UPSERT = 'upsert';

    // Incoming Webhook Log Status Constants
    private const LOG_STATUS_SUCCESS = 'success';
    private const LOG_STATUS_FAILED = 'failed';
    private const LOG_STATUS_INVALID = 'invalid';

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Convert stdClass to array recursively.
     */
    private function toArray(stdClass|array|null $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return json_decode(json_encode($data), true);
    }

    /**
     * Generate a webhook secret.
     */
    private function generateSecret(): string
    {
        return 'whsec_' . Str::random(32);
    }

    /**
     * Generate an incoming webhook token.
     */
    private function generateToken(): string
    {
        return 'iwh_' . Str::random(32);
    }

    /**
     * Get all available events.
     */
    public function getAvailableEvents(): array
    {
        return [
            'Record Events' => [
                self::EVENT_RECORD_CREATED => 'Record Created',
                self::EVENT_RECORD_UPDATED => 'Record Updated',
                self::EVENT_RECORD_DELETED => 'Record Deleted',
            ],
            'Deal Events' => [
                self::EVENT_DEAL_STAGE_CHANGED => 'Deal Stage Changed',
                self::EVENT_DEAL_WON => 'Deal Won',
                self::EVENT_DEAL_LOST => 'Deal Lost',
            ],
            'Email Events' => [
                self::EVENT_EMAIL_RECEIVED => 'Email Received',
                self::EVENT_EMAIL_OPENED => 'Email Opened',
                self::EVENT_EMAIL_CLICKED => 'Email Link Clicked',
            ],
            'System Events' => [
                self::EVENT_WORKFLOW_TRIGGERED => 'Workflow Triggered',
                self::EVENT_IMPORT_COMPLETED => 'Import Completed',
                self::EVENT_EXPORT_COMPLETED => 'Export Completed',
            ],
        ];
    }

    /**
     * Load user relation for webhook.
     */
    private function loadUserRelation(stdClass $webhook): array
    {
        $data = $this->toArray($webhook);

        if ($webhook->user_id) {
            $user = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $webhook->user_id)
                ->first();

            $data['user'] = $user ? $this->toArray($user) : null;
        }

        return $data;
    }

    /**
     * Load module relation for webhook.
     */
    private function loadModuleRelation(array $data, ?int $moduleId, bool $includeApiName = false): array
    {
        if ($moduleId) {
            $select = ['id', 'name'];
            if ($includeApiName) {
                $select[] = 'api_name';
            }

            $module = DB::table(self::TABLE_MODULES)
                ->select($select)
                ->where('id', $moduleId)
                ->first();

            $data['module'] = $module ? $this->toArray($module) : null;
        }

        return $data;
    }

    /**
     * Decode JSON fields for webhook.
     */
    private function decodeWebhookJsonFields(stdClass $webhook): stdClass
    {
        if (isset($webhook->events) && is_string($webhook->events)) {
            $webhook->events = json_decode($webhook->events, true);
        }
        if (isset($webhook->headers) && is_string($webhook->headers)) {
            $webhook->headers = json_decode($webhook->headers, true);
        }
        return $webhook;
    }

    /**
     * Decode JSON fields for incoming webhook.
     */
    private function decodeIncomingWebhookJsonFields(stdClass $webhook): stdClass
    {
        if (isset($webhook->field_mapping) && is_string($webhook->field_mapping)) {
            $webhook->field_mapping = json_decode($webhook->field_mapping, true);
        }
        return $webhook;
    }

    // =========================================================================
    // OUTGOING WEBHOOKS - QUERY METHODS
    // =========================================================================

    public function listWebhooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_WEBHOOKS)->whereNull('deleted_at');

        // Filter by status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by event (JSON contains)
        if (!empty($filters['event'])) {
            $query->whereRaw("JSON_CONTAINS(events, ?)", [json_encode($filters['event'])]);
        }

        // Filter by module (null or specific module)
        if (!empty($filters['module_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('module_id')
                    ->orWhere('module_id', $filters['module_id']);
            });
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

        // Count total
        $total = $query->count();

        // Get paginated results
        $webhooks = $query->orderBy('created_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        // Load relations and decode JSON
        $items = [];
        foreach ($webhooks as $webhook) {
            $webhook = $this->decodeWebhookJsonFields($webhook);
            $data = $this->loadUserRelation($webhook);
            $data = $this->loadModuleRelation($data, $webhook->module_id ?? null);
            unset($data['secret']); // Hide secret
            $items[] = $data;
        }

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getWebhook(int $id): ?array
    {
        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            return null;
        }

        $webhook = $this->decodeWebhookJsonFields($webhook);
        $data = $this->loadUserRelation($webhook);
        $data = $this->loadModuleRelation($data, $webhook->module_id ?? null);

        // Load recent deliveries
        $deliveries = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('webhook_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $data['deliveries'] = array_map(function ($delivery) {
            $delivery = $this->decodeDeliveryJsonFields($delivery);
            return $this->toArray($delivery);
        }, $deliveries->all());

        unset($data['secret']); // Hide secret

        return $data;
    }

    /**
     * Decode JSON fields for delivery.
     */
    private function decodeDeliveryJsonFields(stdClass $delivery): stdClass
    {
        if (isset($delivery->payload) && is_string($delivery->payload)) {
            $delivery->payload = json_decode($delivery->payload, true);
        }
        return $delivery;
    }

    public function getDeliveryHistory(int $webhookId, int $perPage = 50, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('webhook_id', $webhookId);

        $total = $query->count();

        $deliveries = $query->orderBy('created_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        $items = array_map(function ($delivery) {
            $delivery = $this->decodeDeliveryJsonFields($delivery);
            return $this->toArray($delivery);
        }, $deliveries->all());

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getWebhookStats(int $webhookId): array
    {
        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $webhookId)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Webhook not found");
        }

        $webhook = $this->decodeWebhookJsonFields($webhook);

        $totalDeliveries = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('webhook_id', $webhookId)
            ->count();

        $sevenDaysAgo = now()->subDays(7)->toDateTimeString();

        $recentDeliveriesCount = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('webhook_id', $webhookId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        $recentFailuresCount = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('webhook_id', $webhookId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->where('status', self::STATUS_FAILED)
            ->count();

        $avgResponseTime = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('webhook_id', $webhookId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->where('status', self::STATUS_SUCCESS)
            ->avg('response_time_ms') ?? 0;

        $data = $this->toArray($webhook);
        unset($data['secret']);

        $successCount = $webhook->success_count ?? 0;
        $failureCount = $webhook->failure_count ?? 0;

        return [
            'webhook' => $data,
            'total_deliveries' => $totalDeliveries,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'success_rate' => ($successCount + $failureCount) > 0
                ? round(($successCount / ($successCount + $failureCount)) * 100, 1)
                : 0,
            'recent_deliveries' => $recentDeliveriesCount,
            'recent_failures' => $recentFailuresCount,
            'avg_response_time' => $avgResponseTime,
            'last_triggered_at' => $webhook->last_triggered_at ?? null,
        ];
    }

    public function getWebhooksForEvent(string $event, ?int $moduleId = null): array
    {
        $query = DB::table(self::TABLE_WEBHOOKS)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw("JSON_CONTAINS(events, ?)", [json_encode($event)]);

        // Apply module filter (null or specific module)
        if ($moduleId !== null) {
            $query->where(function ($q) use ($moduleId) {
                $q->whereNull('module_id')
                    ->orWhere('module_id', $moduleId);
            });
        }

        $webhooks = $query->get();

        return array_map(function ($webhook) {
            $webhook = $this->decodeWebhookJsonFields($webhook);
            $data = $this->toArray($webhook);
            unset($data['secret']);
            return $data;
        }, $webhooks->all());
    }

    // =========================================================================
    // OUTGOING WEBHOOKS - COMMAND METHODS
    // =========================================================================

    public function createWebhook(array $data): array
    {
        $now = now()->toDateTimeString();

        $id = DB::table(self::TABLE_WEBHOOKS)->insertGetId([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'],
            'secret' => $this->generateSecret(),
            'events' => json_encode($data['events'] ?? []),
            'module_id' => $data['module_id'] ?? null,
            'headers' => json_encode($data['headers'] ?? []),
            'is_active' => $data['is_active'] ?? true,
            'verify_ssl' => $data['verify_ssl'] ?? true,
            'timeout' => $data['timeout'] ?? 30,
            'retry_count' => $data['retry_count'] ?? 3,
            'retry_delay' => $data['retry_delay'] ?? 60,
            'success_count' => 0,
            'failure_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $webhook = DB::table(self::TABLE_WEBHOOKS)->where('id', $id)->first();
        $webhook = $this->decodeWebhookJsonFields($webhook);
        $result = $this->toArray($webhook);
        unset($result['secret']);

        return $result;
    }

    public function updateWebhook(int $id, array $data): array
    {
        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Webhook not found");
        }

        $webhook = $this->decodeWebhookJsonFields($webhook);

        $updateData = [
            'name' => $data['name'] ?? $webhook->name,
            'description' => $data['description'] ?? $webhook->description,
            'url' => $data['url'] ?? $webhook->url,
            'events' => json_encode($data['events'] ?? $webhook->events),
            'module_id' => $data['module_id'] ?? $webhook->module_id,
            'headers' => json_encode($data['headers'] ?? $webhook->headers),
            'is_active' => $data['is_active'] ?? $webhook->is_active,
            'verify_ssl' => $data['verify_ssl'] ?? $webhook->verify_ssl,
            'timeout' => $data['timeout'] ?? $webhook->timeout,
            'retry_count' => $data['retry_count'] ?? $webhook->retry_count,
            'retry_delay' => $data['retry_delay'] ?? $webhook->retry_delay,
            'updated_at' => now()->toDateTimeString(),
        ];

        DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->update($updateData);

        $updated = DB::table(self::TABLE_WEBHOOKS)->where('id', $id)->first();
        $updated = $this->decodeWebhookJsonFields($updated);
        $result = $this->toArray($updated);
        unset($result['secret']);

        return $result;
    }

    public function deleteWebhook(int $id): bool
    {
        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Webhook not found");
        }

        // Soft delete
        DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->update([
                'deleted_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

        return true;
    }

    public function regenerateSecret(int $id): string
    {
        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Webhook not found");
        }

        $newSecret = $this->generateSecret();

        DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->update([
                'secret' => $newSecret,
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $newSecret;
    }

    public function toggleActive(int $id): array
    {
        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Webhook not found");
        }

        DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $id)
            ->update([
                'is_active' => !$webhook->is_active,
                'updated_at' => now()->toDateTimeString(),
            ]);

        $updated = DB::table(self::TABLE_WEBHOOKS)->where('id', $id)->first();
        $updated = $this->decodeWebhookJsonFields($updated);
        $result = $this->toArray($updated);
        unset($result['secret']);

        return $result;
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

        $now = now()->toDateTimeString();

        $id = DB::table(self::TABLE_WEBHOOK_DELIVERIES)->insertGetId([
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => json_encode($fullPayload),
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $delivery = DB::table(self::TABLE_WEBHOOK_DELIVERIES)->where('id', $id)->first();
        $delivery = $this->decodeDeliveryJsonFields($delivery);

        return $this->toArray($delivery);
    }

    public function getPendingDeliveries(int $limit = 100): array
    {
        $now = now()->toDateTimeString();

        $deliveries = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('status', self::STATUS_PENDING)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', $now);
            })
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($deliveries as $delivery) {
            $delivery = $this->decodeDeliveryJsonFields($delivery);
            $data = $this->toArray($delivery);

            // Load webhook relation
            $webhook = DB::table(self::TABLE_WEBHOOKS)
                ->where('id', $delivery->webhook_id)
                ->first();

            if ($webhook) {
                $webhook = $this->decodeWebhookJsonFields($webhook);
                $data['webhook'] = $this->toArray($webhook);
            }

            $result[] = $data;
        }

        return $result;
    }

    public function getDelivery(int $deliveryId): ?array
    {
        $delivery = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('id', $deliveryId)
            ->first();

        if (!$delivery) {
            return null;
        }

        $delivery = $this->decodeDeliveryJsonFields($delivery);
        return $this->toArray($delivery);
    }

    public function updateDeliveryStatus(int $deliveryId, array $data): array
    {
        $delivery = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('id', $deliveryId)
            ->first();

        if (!$delivery) {
            throw new \RuntimeException("Delivery not found");
        }

        $updateData = $data;
        $updateData['updated_at'] = now()->toDateTimeString();

        // Encode payload if present
        if (isset($updateData['payload']) && is_array($updateData['payload'])) {
            $updateData['payload'] = json_encode($updateData['payload']);
        }

        DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('id', $deliveryId)
            ->update($updateData);

        $updated = DB::table(self::TABLE_WEBHOOK_DELIVERIES)->where('id', $deliveryId)->first();
        $updated = $this->decodeDeliveryJsonFields($updated);

        return $this->toArray($updated);
    }

    public function getWebhookForDelivery(int $deliveryId): ?array
    {
        $delivery = DB::table(self::TABLE_WEBHOOK_DELIVERIES)
            ->where('id', $deliveryId)
            ->first();

        if (!$delivery) {
            return null;
        }

        $webhook = DB::table(self::TABLE_WEBHOOKS)
            ->where('id', $delivery->webhook_id)
            ->first();

        if (!$webhook) {
            return null;
        }

        $webhook = $this->decodeWebhookJsonFields($webhook);
        return $this->toArray($webhook);
    }

    // =========================================================================
    // INCOMING WEBHOOKS - QUERY METHODS
    // =========================================================================

    public function listIncomingWebhooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_INCOMING_WEBHOOKS)->whereNull('deleted_at');

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        $total = $query->count();

        $webhooks = $query->orderBy('created_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        $items = [];
        foreach ($webhooks as $webhook) {
            $webhook = $this->decodeIncomingWebhookJsonFields($webhook);
            $data = $this->loadUserRelation($webhook);
            $data = $this->loadModuleRelation($data, $webhook->module_id ?? null, true);
            unset($data['token']); // Hide token
            $items[] = $data;
        }

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getIncomingWebhook(int $id): ?array
    {
        $webhook = DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            return null;
        }

        $webhook = $this->decodeIncomingWebhookJsonFields($webhook);
        $data = $this->loadUserRelation($webhook);
        $data = $this->loadModuleRelation($data, $webhook->module_id ?? null);

        // Load recent logs
        $logs = DB::table(self::TABLE_INCOMING_WEBHOOK_LOGS)
            ->where('incoming_webhook_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $data['logs'] = array_map(function ($log) {
            $log = $this->decodeIncomingWebhookLogJsonFields($log);
            return $this->toArray($log);
        }, $logs->all());

        unset($data['token']); // Hide token

        return $data;
    }

    /**
     * Decode JSON fields for incoming webhook log.
     */
    private function decodeIncomingWebhookLogJsonFields(stdClass $log): stdClass
    {
        if (isset($log->payload) && is_string($log->payload)) {
            $log->payload = json_decode($log->payload, true);
        }
        if (isset($log->headers) && is_string($log->headers)) {
            $log->headers = json_decode($log->headers, true);
        }
        return $log;
    }

    public function getIncomingWebhookLogs(int $webhookId, int $perPage = 50, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_INCOMING_WEBHOOK_LOGS)
            ->where('incoming_webhook_id', $webhookId);

        $total = $query->count();

        $logs = $query->orderBy('created_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        $items = array_map(function ($log) {
            $log = $this->decodeIncomingWebhookLogJsonFields($log);
            return $this->toArray($log);
        }, $logs->all());

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findIncomingWebhookByToken(string $token): ?array
    {
        $webhook = DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('token', $token)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            return null;
        }

        $webhook = $this->decodeIncomingWebhookJsonFields($webhook);
        return $this->toArray($webhook);
    }

    // =========================================================================
    // INCOMING WEBHOOKS - COMMAND METHODS
    // =========================================================================

    public function createIncomingWebhook(array $data): array
    {
        $now = now()->toDateTimeString();

        $id = DB::table(self::TABLE_INCOMING_WEBHOOKS)->insertGetId([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'token' => $this->generateToken(),
            'module_id' => $data['module_id'],
            'field_mapping' => json_encode($data['field_mapping'] ?? []),
            'is_active' => $data['is_active'] ?? true,
            'action' => $data['action'] ?? self::ACTION_CREATE,
            'upsert_field' => $data['upsert_field'] ?? null,
            'received_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $webhook = DB::table(self::TABLE_INCOMING_WEBHOOKS)->where('id', $id)->first();
        $webhook = $this->decodeIncomingWebhookJsonFields($webhook);
        $result = $this->toArray($webhook);
        unset($result['token']);

        return $result;
    }

    public function updateIncomingWebhook(int $id, array $data): array
    {
        $webhook = DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Incoming webhook not found");
        }

        $webhook = $this->decodeIncomingWebhookJsonFields($webhook);

        $updateData = [
            'name' => $data['name'] ?? $webhook->name,
            'description' => $data['description'] ?? $webhook->description,
            'module_id' => $data['module_id'] ?? $webhook->module_id,
            'field_mapping' => json_encode($data['field_mapping'] ?? $webhook->field_mapping),
            'is_active' => $data['is_active'] ?? $webhook->is_active,
            'action' => $data['action'] ?? $webhook->action,
            'upsert_field' => $data['upsert_field'] ?? $webhook->upsert_field,
            'updated_at' => now()->toDateTimeString(),
        ];

        DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $id)
            ->update($updateData);

        $updated = DB::table(self::TABLE_INCOMING_WEBHOOKS)->where('id', $id)->first();
        $updated = $this->decodeIncomingWebhookJsonFields($updated);
        $result = $this->toArray($updated);
        unset($result['token']);

        return $result;
    }

    public function deleteIncomingWebhook(int $id): bool
    {
        $webhook = DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Incoming webhook not found");
        }

        // Soft delete
        DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $id)
            ->update([
                'deleted_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

        return true;
    }

    public function regenerateIncomingToken(int $id): string
    {
        $webhook = DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Incoming webhook not found");
        }

        $newToken = $this->generateToken();

        DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $id)
            ->update([
                'token' => $newToken,
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $newToken;
    }

    public function createIncomingWebhookLog(array $data): array
    {
        $now = now()->toDateTimeString();

        $id = DB::table(self::TABLE_INCOMING_WEBHOOK_LOGS)->insertGetId([
            'incoming_webhook_id' => $data['incoming_webhook_id'],
            'payload' => json_encode($data['payload']),
            'headers' => isset($data['headers']) ? json_encode($data['headers']) : json_encode([]),
            'ip_address' => $data['ip_address'] ?? null,
            'status' => $data['status'] ?? 'processing',
            'created_at' => $now,
        ]);

        $log = DB::table(self::TABLE_INCOMING_WEBHOOK_LOGS)->where('id', $id)->first();
        $log = $this->decodeIncomingWebhookLogJsonFields($log);

        return $this->toArray($log);
    }

    public function updateIncomingWebhookLog(int $logId, array $data): array
    {
        $log = DB::table(self::TABLE_INCOMING_WEBHOOK_LOGS)
            ->where('id', $logId)
            ->first();

        if (!$log) {
            throw new \RuntimeException("Incoming webhook log not found");
        }

        $updateData = $data;

        // Encode arrays to JSON
        if (isset($updateData['payload']) && is_array($updateData['payload'])) {
            $updateData['payload'] = json_encode($updateData['payload']);
        }
        if (isset($updateData['headers']) && is_array($updateData['headers'])) {
            $updateData['headers'] = json_encode($updateData['headers']);
        }

        DB::table(self::TABLE_INCOMING_WEBHOOK_LOGS)
            ->where('id', $logId)
            ->update($updateData);

        $updated = DB::table(self::TABLE_INCOMING_WEBHOOK_LOGS)->where('id', $logId)->first();
        $updated = $this->decodeIncomingWebhookLogJsonFields($updated);

        return $this->toArray($updated);
    }

    public function recordIncomingWebhookReceived(int $webhookId): void
    {
        $webhook = DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $webhookId)
            ->whereNull('deleted_at')
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Incoming webhook not found");
        }

        DB::table(self::TABLE_INCOMING_WEBHOOKS)
            ->where('id', $webhookId)
            ->update([
                'last_received_at' => now()->toDateTimeString(),
                'received_count' => ($webhook->received_count ?? 0) + 1,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    // =========================================================================
    // MODULE RECORD METHODS
    // =========================================================================

    public function createModuleRecord(int $moduleId, array $data, int $userId): array
    {
        $now = now()->toDateTimeString();

        $id = DB::table(self::TABLE_MODULE_RECORDS)->insertGetId([
            'module_id' => $moduleId,
            'data' => json_encode($data),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $record = DB::table(self::TABLE_MODULE_RECORDS)->where('id', $id)->first();

        if (isset($record->data) && is_string($record->data)) {
            $record->data = json_decode($record->data, true);
        }

        return $this->toArray($record);
    }

    public function updateModuleRecord(int $recordId, array $data): array
    {
        $record = DB::table(self::TABLE_MODULE_RECORDS)
            ->where('id', $recordId)
            ->first();

        if (!$record) {
            throw new \RuntimeException("Module record not found");
        }

        // Decode existing data
        $existingData = [];
        if (isset($record->data) && is_string($record->data)) {
            $existingData = json_decode($record->data, true) ?? [];
        }

        // Merge with new data
        $mergedData = array_merge($existingData, $data);

        DB::table(self::TABLE_MODULE_RECORDS)
            ->where('id', $recordId)
            ->update([
                'data' => json_encode($mergedData),
                'updated_at' => now()->toDateTimeString(),
            ]);

        $updated = DB::table(self::TABLE_MODULE_RECORDS)->where('id', $recordId)->first();

        if (isset($updated->data) && is_string($updated->data)) {
            $updated->data = json_decode($updated->data, true);
        }

        return $this->toArray($updated);
    }

    public function findModuleRecordByField(int $moduleId, string $field, mixed $value): ?array
    {
        $record = DB::table(self::TABLE_MODULE_RECORDS)
            ->where('module_id', $moduleId)
            ->whereRaw("data->>? = ?", [$field, $value])
            ->first();

        if (!$record) {
            return null;
        }

        if (isset($record->data) && is_string($record->data)) {
            $record->data = json_decode($record->data, true);
        }

        return $this->toArray($record);
    }
}
