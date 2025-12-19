<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebhookController extends Controller
{
    /**
     * List all webhooks for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Webhook::where('user_id', Auth::id())
            ->with(['module:id,name,api_name'])
            ->orderBy('created_at', 'desc');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $webhooks = $query->get()->map(fn ($webhook) => $this->formatWebhook($webhook));

        return response()->json([
            'data' => $webhooks,
            'available_events' => Webhook::getAvailableEvents(),
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

        $webhook = Webhook::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'url' => $validated['url'],
            'secret' => Webhook::generateSecret(),
            'events' => $validated['events'],
            'module_id' => $validated['module_id'] ?? null,
            'headers' => $validated['headers'] ?? null,
            'is_active' => true,
            'verify_ssl' => $validated['verify_ssl'] ?? true,
            'timeout' => $validated['timeout'] ?? 30,
            'retry_count' => $validated['retry_count'] ?? 3,
            'retry_delay' => $validated['retry_delay'] ?? 60,
        ]);

        return response()->json([
            'message' => 'Webhook created successfully',
            'webhook' => $this->formatWebhook($webhook),
            'secret' => $webhook->secret, // Show once
            'warning' => 'Store this secret securely for signature verification.',
        ], 201);
    }

    /**
     * Get a specific webhook.
     */
    public function show(int $id): JsonResponse
    {
        $webhook = Webhook::where('user_id', Auth::id())
            ->with(['module:id,name,api_name'])
            ->findOrFail($id);

        // Get recent deliveries
        $recentDeliveries = $webhook->deliveries()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($delivery) => $this->formatDelivery($delivery));

        // Get delivery stats
        $stats = [
            'total' => $webhook->deliveries()->count(),
            'success' => $webhook->deliveries()->where('status', WebhookDelivery::STATUS_SUCCESS)->count(),
            'failed' => $webhook->deliveries()->where('status', WebhookDelivery::STATUS_FAILED)->count(),
            'pending' => $webhook->deliveries()->where('status', WebhookDelivery::STATUS_PENDING)->count(),
        ];

        return response()->json([
            'webhook' => $this->formatWebhook($webhook),
            'recent_deliveries' => $recentDeliveries,
            'delivery_stats' => $stats,
        ]);
    }

    /**
     * Update a webhook.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::where('user_id', Auth::id())
            ->findOrFail($id);

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

        $webhook->update($validated);

        return response()->json([
            'message' => 'Webhook updated successfully',
            'webhook' => $this->formatWebhook($webhook->fresh(['module:id,name,api_name'])),
        ]);
    }

    /**
     * Delete a webhook.
     */
    public function destroy(int $id): JsonResponse
    {
        $webhook = Webhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $webhook->delete();

        return response()->json([
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Rotate webhook secret.
     */
    public function rotateSecret(int $id): JsonResponse
    {
        $webhook = Webhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $newSecret = Webhook::generateSecret();
        $webhook->update(['secret' => $newSecret]);

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
        $webhook = Webhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $testPayload = [
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'This is a test webhook from VRTX CRM',
                'webhook_id' => $webhook->id,
                'webhook_name' => $webhook->name,
            ],
        ];

        // Create a test delivery
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => 'webhook.test',
            'payload' => $testPayload,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        // Dispatch job to send
        \App\Jobs\SendWebhookJob::dispatch($delivery);

        return response()->json([
            'message' => 'Test webhook queued for delivery',
            'delivery_id' => $delivery->id,
        ]);
    }

    /**
     * Get delivery details.
     */
    public function getDelivery(int $webhookId, int $deliveryId): JsonResponse
    {
        $webhook = Webhook::where('user_id', Auth::id())
            ->findOrFail($webhookId);

        $delivery = $webhook->deliveries()->findOrFail($deliveryId);

        return response()->json([
            'delivery' => $this->formatDelivery($delivery, true),
        ]);
    }

    /**
     * Retry a failed delivery.
     */
    public function retryDelivery(int $webhookId, int $deliveryId): JsonResponse
    {
        $webhook = Webhook::where('user_id', Auth::id())
            ->findOrFail($webhookId);

        $delivery = $webhook->deliveries()
            ->where('status', WebhookDelivery::STATUS_FAILED)
            ->findOrFail($deliveryId);

        // Reset and queue for retry
        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
            'next_retry_at' => null,
        ]);

        \App\Jobs\SendWebhookJob::dispatch($delivery);

        return response()->json([
            'message' => 'Delivery queued for retry',
        ]);
    }

    /**
     * List webhook deliveries.
     */
    public function deliveries(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $query = $webhook->deliveries()
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $deliveries = $query->paginate($request->integer('per_page', 25));

        $deliveries->getCollection()->transform(fn ($d) => $this->formatDelivery($d));

        return response()->json($deliveries);
    }

    /**
     * Format webhook for response.
     */
    protected function formatWebhook(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'description' => $webhook->description,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'module' => $webhook->module ? [
                'id' => $webhook->module->id,
                'name' => $webhook->module->name,
                'api_name' => $webhook->module->api_name,
            ] : null,
            'headers' => $webhook->headers,
            'is_active' => $webhook->is_active,
            'verify_ssl' => $webhook->verify_ssl,
            'timeout' => $webhook->timeout,
            'retry_count' => $webhook->retry_count,
            'retry_delay' => $webhook->retry_delay,
            'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
            'last_status' => $webhook->last_status,
            'success_count' => $webhook->success_count,
            'failure_count' => $webhook->failure_count,
            'created_at' => $webhook->created_at->toIso8601String(),
            'updated_at' => $webhook->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format delivery for response.
     */
    protected function formatDelivery(WebhookDelivery $delivery, bool $includePayload = false): array
    {
        $data = [
            'id' => $delivery->id,
            'event' => $delivery->event,
            'status' => $delivery->status,
            'attempts' => $delivery->attempts,
            'response_code' => $delivery->response_code,
            'response_time_ms' => $delivery->response_time_ms,
            'error_message' => $delivery->error_message,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'created_at' => $delivery->created_at->toIso8601String(),
        ];

        if ($includePayload) {
            $data['payload'] = $delivery->payload;
            $data['response_body'] = $delivery->response_body;
        }

        return $data;
    }
}
