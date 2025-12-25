<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration\Webhooks;

use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Services\Integration\Xero\XeroSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XeroWebhookController extends Controller
{
    private const PROVIDER = 'xero';

    public function __construct(
        private readonly XeroSyncService $syncService,
        private readonly IntegrationConnectionRepositoryInterface $connectionRepository,
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
        // Using DB::table directly for JSON queries as repository doesn't support this specific query
        $connectionData = DB::table('integration_connections')
            ->where('integration_slug', self::PROVIDER)
            ->where('status', 'active')
            ->where(function ($query) use ($tenantId) {
                $query->whereRaw("JSON_EXTRACT(settings, '$.tenant_id') = ?", [$tenantId])
                    ->orWhereRaw("JSON_EXTRACT(credentials, '$.tenant_id') = ?", [$tenantId]);
            })
            ->first();

        if (!$connectionData) {
            Log::warning('Xero webhook: No connection found for tenant', ['tenant_id' => $tenantId]);
            return;
        }

        // Log the webhook event using DB::table
        $webhookLogId = DB::table('integration_webhook_logs')->insertGetId([
            'integration_connection_id' => $connectionData->id,
            'event_type' => $eventType,
            'payload' => json_encode($event),
            'status' => 'processing',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->processEventByType($connectionData->id, $event);

            DB::table('integration_webhook_logs')
                ->where('id', $webhookLogId)
                ->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::error('Xero webhook processing failed', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);

            DB::table('integration_webhook_logs')
                ->where('id', $webhookLogId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Process event based on its type
     */
    private function processEventByType(int $connectionId, array $event): void
    {
        $eventCategory = $event['eventCategory'] ?? null;
        $eventType = $event['eventType'] ?? null;

        Log::info('Processing Xero event', [
            'category' => $eventCategory,
            'type' => $eventType,
            'resource_id' => $event['resourceId'] ?? null,
        ]);

        // Get connection data from repository
        $connection = $this->connectionRepository->findById($connectionId);

        if (!$connection) {
            Log::error('Connection not found', ['connection_id' => $connectionId]);
            return;
        }

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
