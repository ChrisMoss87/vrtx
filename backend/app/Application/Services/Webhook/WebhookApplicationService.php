<?php

declare(strict_types=1);

namespace App\Application\Services\Webhook;

use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use Illuminate\Support\Facades\Http;

class WebhookApplicationService
{
    public function __construct(
        private WebhookRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - OUTGOING WEBHOOKS
    // =========================================================================

    /**
     * List outgoing webhooks with filtering and pagination.
     */
    public function listWebhooks(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listWebhooks($filters, $perPage, $page);
    }

    /**
     * Get a webhook by ID.
     */
    public function getWebhook(int $id): ?array
    {
        return $this->repository->getWebhook($id);
    }

    /**
     * Get webhook delivery history.
     */
    public function getDeliveryHistory(int $webhookId, int $perPage = 50): PaginatedResult
    {
        return $this->repository->getDeliveryHistory($webhookId, $perPage);
    }

    /**
     * Get webhook statistics.
     */
    public function getWebhookStats(int $webhookId): array
    {
        return $this->repository->getWebhookStats($webhookId);
    }

    /**
     * Get available webhook events.
     */
    public function getAvailableEvents(): array
    {
        return $this->repository->getAvailableEvents();
    }

    // =========================================================================
    // COMMAND USE CASES - OUTGOING WEBHOOKS
    // =========================================================================

    /**
     * Create a new outgoing webhook.
     */
    public function createWebhook(array $data): array
    {
        $data['user_id'] = $this->authContext->userId();
        return $this->repository->createWebhook($data);
    }

    /**
     * Update a webhook.
     */
    public function updateWebhook(int $id, array $data): array
    {
        return $this->repository->updateWebhook($id, $data);
    }

    /**
     * Delete a webhook.
     */
    public function deleteWebhook(int $id): bool
    {
        return $this->repository->deleteWebhook($id);
    }

    /**
     * Regenerate webhook secret.
     */
    public function regenerateSecret(int $id): string
    {
        return $this->repository->regenerateSecret($id);
    }

    /**
     * Toggle webhook active status.
     */
    public function toggleActive(int $id): array
    {
        return $this->repository->toggleActive($id);
    }

    /**
     * Test a webhook by sending a test payload.
     */
    public function testWebhook(int $id): array
    {
        $webhook = $this->repository->getWebhook($id);

        if (!$webhook) {
            throw new \InvalidArgumentException('Webhook not found');
        }

        $testPayload = [
            'event' => 'test',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'This is a test webhook delivery',
                'webhook_id' => $webhook['id'],
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
        $webhooks = $this->repository->getWebhooksForEvent($event, $moduleId);

        $results = [];

        foreach ($webhooks as $webhook) {
            $results[$webhook['id']] = $this->queueDelivery($webhook['id'], $event, $payload);
        }

        return $results;
    }

    /**
     * Queue a webhook delivery.
     */
    public function queueDelivery(int $webhookId, string $event, array $payload): array
    {
        return $this->repository->queueDelivery($webhookId, $event, $payload);
    }

    /**
     * Process pending deliveries.
     */
    public function processPendingDeliveries(int $limit = 100): array
    {
        $deliveries = $this->repository->getPendingDeliveries($limit);

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
    public function executeDelivery(array $delivery): array
    {
        $webhook = $delivery['webhook'] ?? null;

        if (!$webhook) {
            $webhook = $this->repository->getWebhookForDelivery($delivery['id']);
        }

        if (!$webhook) {
            throw new \InvalidArgumentException('Webhook not found for delivery');
        }

        $startTime = microtime(true);

        try {
            $signature = $this->signPayload($delivery['payload'], $webhook['secret']);

            $headers = array_merge($webhook['headers'] ?? [], [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'User-Agent' => 'CRM-Webhook/1.0',
            ]);

            $response = Http::withHeaders($headers)
                ->timeout($webhook['timeout'])
                ->withOptions(['verify' => $webhook['verify_ssl']])
                ->post($webhook['url'], $delivery['payload']);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $this->repository->updateDeliveryStatus($delivery['id'], [
                    'status' => 'success',
                    'response_code' => $response->status(),
                    'response_body' => $response->body() ? substr($response->body(), 0, 10000) : null,
                    'response_time_ms' => $responseTimeMs,
                    'delivered_at' => now(),
                    'next_retry_at' => null,
                ]);

                return ['success' => true, 'response_code' => $response->status()];
            } else {
                $this->repository->updateDeliveryStatus($delivery['id'], [
                    'status' => 'failed',
                    'attempts' => $delivery['attempts'] + 1,
                    'response_code' => $response->status(),
                    'error_message' => "HTTP {$response->status()}: " . substr($response->body(), 0, 500),
                    'response_time_ms' => $responseTimeMs,
                ]);

                return ['success' => false, 'response_code' => $response->status()];
            }
        } catch (\Exception $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->repository->updateDeliveryStatus($delivery['id'], [
                'status' => 'failed',
                'attempts' => $delivery['attempts'] + 1,
                'error_message' => $e->getMessage(),
                'response_time_ms' => $responseTimeMs,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sign a payload with the webhook secret.
     */
    private function signPayload(array $payload, string $secret): string
    {
        $jsonPayload = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Deliver payload directly (synchronous).
     */
    public function deliverPayload(array $webhook, string $event, array $payload): array
    {
        $delivery = $this->queueDelivery($webhook['id'], $event, $payload);
        return $this->executeDelivery($delivery);
    }

    /**
     * Retry a failed delivery.
     */
    public function retryDelivery(int $deliveryId): array
    {
        $delivery = $this->repository->getDelivery($deliveryId);

        if (!$delivery) {
            throw new \InvalidArgumentException('Delivery not found');
        }

        if ($delivery['status'] !== 'failed') {
            throw new \InvalidArgumentException('Only failed deliveries can be retried');
        }

        $this->repository->updateDeliveryStatus($deliveryId, [
            'status' => 'pending',
            'next_retry_at' => null,
        ]);

        $delivery = $this->repository->getDelivery($deliveryId);
        return $this->executeDelivery($delivery);
    }

    // =========================================================================
    // QUERY USE CASES - INCOMING WEBHOOKS
    // =========================================================================

    /**
     * List incoming webhooks.
     */
    public function listIncomingWebhooks(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listIncomingWebhooks($filters, $perPage, $page);
    }

    /**
     * Get an incoming webhook by ID.
     */
    public function getIncomingWebhook(int $id): ?array
    {
        return $this->repository->getIncomingWebhook($id);
    }

    /**
     * Get incoming webhook logs.
     */
    public function getIncomingWebhookLogs(int $webhookId, int $perPage = 50): PaginatedResult
    {
        return $this->repository->getIncomingWebhookLogs($webhookId, $perPage);
    }

    // =========================================================================
    // COMMAND USE CASES - INCOMING WEBHOOKS
    // =========================================================================

    /**
     * Create an incoming webhook.
     */
    public function createIncomingWebhook(array $data): array
    {
        $data['user_id'] = $this->authContext->userId();
        return $this->repository->createIncomingWebhook($data);
    }

    /**
     * Update an incoming webhook.
     */
    public function updateIncomingWebhook(int $id, array $data): array
    {
        return $this->repository->updateIncomingWebhook($id, $data);
    }

    /**
     * Delete an incoming webhook.
     */
    public function deleteIncomingWebhook(int $id): bool
    {
        return $this->repository->deleteIncomingWebhook($id);
    }

    /**
     * Regenerate incoming webhook token.
     */
    public function regenerateIncomingToken(int $id): string
    {
        return $this->repository->regenerateIncomingToken($id);
    }

    /**
     * Process an incoming webhook request.
     */
    public function processIncomingWebhook(string $token, array $payload, array $headers = []): array
    {
        $webhook = $this->repository->findIncomingWebhookByToken($token);

        if (!$webhook) {
            return ['success' => false, 'error' => 'Invalid or inactive webhook token'];
        }

        // Log the incoming request
        $log = $this->repository->createIncomingWebhookLog([
            'incoming_webhook_id' => $webhook['id'],
            'payload' => $payload,
            'headers' => $headers,
            'ip_address' => request()->ip(),
            'status' => 'processing',
        ]);

        try {
            // Map the data
            $mappedData = $this->mapData($payload, $webhook['field_mapping'] ?? []);

            if (empty($mappedData)) {
                throw new \InvalidArgumentException('No fields mapped from payload');
            }

            $result = match ($webhook['action']) {
                'create' => $this->createRecordFromWebhook($webhook, $mappedData),
                'update' => $this->updateRecordFromWebhook($webhook, $mappedData, $payload),
                'upsert' => $this->upsertRecordFromWebhook($webhook, $mappedData, $payload),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->repository->updateIncomingWebhookLog($log['id'], [
                'status' => 'success',
                'result' => $result,
            ]);

            $this->repository->recordIncomingWebhookReceived($webhook['id']);

            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            $this->repository->updateIncomingWebhookLog($log['id'], [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Map incoming data to module fields.
     */
    private function mapData(array $data, array $fieldMapping): array
    {
        $mapped = [];

        foreach ($fieldMapping as $incomingField => $moduleField) {
            if ($moduleField && isset($data[$incomingField])) {
                $mapped[$moduleField] = $data[$incomingField];
            }
        }

        return $mapped;
    }

    /**
     * Create record from webhook data.
     */
    private function createRecordFromWebhook(array $webhook, array $data): array
    {
        $record = $this->repository->createModuleRecord(
            $webhook['module_id'],
            $data,
            $webhook['user_id']
        );

        return ['action' => 'created', 'record_id' => $record['id']];
    }

    /**
     * Update record from webhook data.
     */
    private function updateRecordFromWebhook(array $webhook, array $data, array $payload): array
    {
        $lookupField = $webhook['upsert_field'];
        $lookupValue = $payload[$lookupField] ?? null;

        if (!$lookupValue) {
            throw new \InvalidArgumentException("Missing lookup field: {$lookupField}");
        }

        $record = $this->repository->findModuleRecordByField(
            $webhook['module_id'],
            $lookupField,
            $lookupValue
        );

        if (!$record) {
            throw new \InvalidArgumentException("Record not found for {$lookupField}: {$lookupValue}");
        }

        $this->repository->updateModuleRecord($record['id'], $data);

        return ['action' => 'updated', 'record_id' => $record['id']];
    }

    /**
     * Upsert record from webhook data.
     */
    private function upsertRecordFromWebhook(array $webhook, array $data, array $payload): array
    {
        $lookupField = $webhook['upsert_field'];
        $lookupValue = $payload[$lookupField] ?? null;

        if (!$lookupValue) {
            // Create if no lookup value
            return $this->createRecordFromWebhook($webhook, $data);
        }

        $record = $this->repository->findModuleRecordByField(
            $webhook['module_id'],
            $lookupField,
            $lookupValue
        );

        if ($record) {
            $this->repository->updateModuleRecord($record['id'], $data);
            return ['action' => 'updated', 'record_id' => $record['id']];
        } else {
            return $this->createRecordFromWebhook($webhook, $data);
        }
    }
}
