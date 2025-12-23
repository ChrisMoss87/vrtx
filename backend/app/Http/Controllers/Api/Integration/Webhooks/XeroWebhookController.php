<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration\Webhooks;

use App\Http\Controllers\Controller;
use App\Infrastructure\Services\Integration\Xero\XeroSyncService;
use App\Models\IntegrationConnection;
use App\Models\IntegrationWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class XeroWebhookController extends Controller
{
    private const PROVIDER = 'xero';

    public function __construct(
        private readonly XeroSyncService $syncService,
    ) {}

    /**
     * Handle incoming Xero webhook notifications
     *
     * Xero webhooks work differently - they use Intent to Receive (ITR) model
     */
    public function handle(Request $request): Response|JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('x-xero-signature');

        // Log incoming webhook
        Log::info('Xero webhook received', [
            'payload' => $payload,
            'headers' => $request->headers->all(),
        ]);

        // Verify signature
        if (!$this->verifySignature($request->getContent(), $signature)) {
            Log::warning('Xero webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Xero uses Intent to Receive - first request should be responded with 200
        // Check if this is a validation request (empty events)
        if (empty($payload['events'])) {
            return response('', 200);
        }

        // Process events
        foreach ($payload['events'] ?? [] as $event) {
            $this->processEvent($event);
        }

        return response('', 200);
    }

    /**
     * Verify the webhook signature from Xero
     */
    private function verifySignature(string $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $webhookKey = config('services.xero.webhook_key');

        if (!$webhookKey) {
            // If no webhook key is configured, skip verification in development
            return config('app.env') !== 'production';
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookKey, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process a single webhook event
     */
    private function processEvent(array $event): void
    {
        $tenantId = $event['tenantId'] ?? null;
        $eventType = $event['eventType'] ?? null;
        $eventCategory = $event['eventCategory'] ?? null;
        $resourceId = $event['resourceId'] ?? null;

        if (!$tenantId || !$eventType) {
            return;
        }

        // Find the connection for this tenant
        $connection = IntegrationConnection::where('provider', self::PROVIDER)
            ->where('status', 'active')
            ->whereRaw("JSON_EXTRACT(settings, '$.tenant_id') = ?", [$tenantId])
            ->orWhereRaw("JSON_EXTRACT(credentials, '$.tenant_id') = ?", [$tenantId])
            ->first();

        if (!$connection) {
            Log::warning('Xero webhook: No connection found for tenant', ['tenant_id' => $tenantId]);
            return;
        }

        // Log the webhook event
        $webhookLog = IntegrationWebhookLog::create([
            'integration_connection_id' => $connection->id,
            'event_type' => $eventType,
            'payload' => $event,
            'status' => 'processing',
            'received_at' => now(),
        ]);

        try {
            $this->processEventByType($connection, $event);

            $webhookLog->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Xero webhook processing failed', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);

            $webhookLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    /**
     * Process event based on its type
     */
    private function processEventByType(IntegrationConnection $connection, array $event): void
    {
        $eventCategory = $event['eventCategory'] ?? null;
        $eventType = $event['eventType'] ?? null;

        Log::info('Processing Xero event', [
            'category' => $eventCategory,
            'type' => $eventType,
            'resource_id' => $event['resourceId'] ?? null,
        ]);

        // Map Xero event categories to our sync actions
        match ($eventCategory) {
            'CONTACT' => $this->syncService->syncContacts($connection),
            'INVOICE' => $this->syncService->syncInvoices($connection),
            default => null,
        };
    }

    /**
     * Handle webhook subscription verification
     * Xero requires endpoints to respond to ITR (Intent to Receive) requests
     */
    public function verify(Request $request): Response
    {
        // For ITR verification, Xero sends a request and expects 200 OK
        // The signature must be verified
        $signature = $request->header('x-xero-signature');

        if (!$this->verifySignature($request->getContent(), $signature)) {
            return response('Unauthorized', 401);
        }

        return response('', 200);
    }
}
