<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration;

use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebhookController extends Controller
{
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

    public function __construct(
        private readonly WebhookRepositoryInterface $webhookRepository
    ) {
    }
    /**
     * List all webhooks for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'user_id' => Auth::id(),
        ];

        if ($request->boolean('active_only')) {
            $filters['is_active'] = true;
        }

        $perPage = $request->integer('per_page', 1000);
        $page = $request->integer('page', 1);

        $result = $this->webhookRepository->listWebhooks($filters, $perPage, $page);

        return response()->json([
            'data' => $result->items(),
            'total' => $result->total(),
            'per_page' => $result->perPage(),
            'current_page' => $result->currentPage(),
            'available_events' => $this->webhookRepository->getAvailableEvents(),
        ]);
    }

    /**
     * Create a new webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'url' => ['required', 'url', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
            'module_id' => ['nullable', 'exists:modules,id'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string'],
            'verify_ssl' => ['boolean'],
            'timeout' => ['integer', 'min:1', 'max:60'],
            'retry_count' => ['integer', 'min:0', 'max:5'],
            'retry_delay' => ['integer', 'min:10', 'max:3600'],
        ]);

        $data = [
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'url' => $validated['url'],
            'events' => $validated['events'],
            'module_id' => $validated['module_id'] ?? null,
            'headers' => $validated['headers'] ?? null,
            'is_active' => true,
            'verify_ssl' => $validated['verify_ssl'] ?? true,
            'timeout' => $validated['timeout'] ?? 30,
            'retry_count' => $validated['retry_count'] ?? 3,
            'retry_delay' => $validated['retry_delay'] ?? 60,
        ];

        $webhook = $this->webhookRepository->createWebhook($data);

        // Get the secret for the newly created webhook
        $secret = $this->webhookRepository->regenerateSecret($webhook['id']);

        return response()->json([
            'message' => 'Webhook created successfully',
            'webhook' => $webhook,
            'secret' => $secret, // Show once
            'warning' => 'Store this secret securely for signature verification.',
        ], 201);
    }

    /**
     * Get a specific webhook.
     */
    public function show(int $id): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($id);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        // Get webhook stats
        $stats = $this->webhookRepository->getWebhookStats($id);

        return response()->json([
            'webhook' => $webhook,
            'stats' => $stats,
        ]);
    }

    /**
     * Update a webhook.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($id);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'url' => ['sometimes', 'url', 'max:2000'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string'],
            'module_id' => ['nullable', 'exists:modules,id'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
            'verify_ssl' => ['sometimes', 'boolean'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'retry_count' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'retry_delay' => ['sometimes', 'integer', 'min:10', 'max:3600'],
        ]);

        $updatedWebhook = $this->webhookRepository->updateWebhook($id, $validated);

        return response()->json([
            'message' => 'Webhook updated successfully',
            'webhook' => $updatedWebhook,
        ]);
    }

    /**
     * Delete a webhook.
     */
    public function destroy(int $id): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($id);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        $this->webhookRepository->deleteWebhook($id);

        return response()->json([
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Rotate webhook secret.
     */
    public function rotateSecret(int $id): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($id);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        $newSecret = $this->webhookRepository->regenerateSecret($id);

        return response()->json([
            'message' => 'Webhook secret rotated successfully',
            'secret' => $newSecret,
            'warning' => 'Update your integration with the new secret.',
        ]);
    }

    /**
     * Test webhook by sending a test payload.
     */
    public function test(int $id): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($id);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        $testPayload = [
            'message' => 'This is a test webhook from VRTX CRM',
            'webhook_id' => $webhook['id'],
            'webhook_name' => $webhook['name'],
        ];

        // Create a test delivery
        $delivery = $this->webhookRepository->queueDelivery($id, 'webhook.test', $testPayload);

        // Dispatch job to send
        \App\Jobs\SendWebhookJob::dispatch($delivery);

        return response()->json([
            'message' => 'Test webhook queued for delivery',
            'delivery_id' => $delivery['id'],
        ]);
    }

    /**
     * Get delivery details.
     */
    public function getDelivery(int $webhookId, int $deliveryId): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($webhookId);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        $delivery = $this->webhookRepository->getDelivery($deliveryId);

        if (!$delivery || $delivery['webhook_id'] !== $webhookId) {
            abort(404, 'Delivery not found');
        }

        return response()->json([
            'delivery' => $delivery,
        ]);
    }

    /**
     * Retry a failed delivery.
     */
    public function retryDelivery(int $webhookId, int $deliveryId): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($webhookId);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        $delivery = $this->webhookRepository->getDelivery($deliveryId);

        if (!$delivery || $delivery['webhook_id'] !== $webhookId) {
            abort(404, 'Delivery not found');
        }

        if ($delivery['status'] !== self::STATUS_FAILED) {
            abort(400, 'Only failed deliveries can be retried');
        }

        // Reset and queue for retry
        $updatedDelivery = $this->webhookRepository->updateDeliveryStatus($deliveryId, [
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'next_retry_at' => null,
        ]);

        \App\Jobs\SendWebhookJob::dispatch($updatedDelivery);

        return response()->json([
            'message' => 'Delivery queued for retry',
        ]);
    }

    /**
     * List webhook deliveries.
     */
    public function deliveries(Request $request, int $id): JsonResponse
    {
        $webhook = $this->webhookRepository->getWebhook($id);

        if (!$webhook || $webhook['user_id'] !== Auth::id()) {
            abort(404, 'Webhook not found');
        }

        $perPage = $request->integer('per_page', 25);
        $page = $request->integer('page', 1);

        $result = $this->webhookRepository->getDeliveryHistory($id, $perPage, $page);

        return response()->json([
            'data' => $result->items(),
            'total' => $result->total(),
            'per_page' => $result->perPage(),
            'current_page' => $result->currentPage(),
        ]);
    }

}
