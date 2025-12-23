<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration\Webhooks;

use App\Http\Controllers\Controller;
use App\Infrastructure\Services\Integration\QuickBooks\QuickBooksSyncService;
use App\Models\IntegrationConnection;
use App\Models\IntegrationWebhook;
use App\Models\IntegrationWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuickBooksWebhookController extends Controller
{
    private const PROVIDER = 'quickbooks';

    public function __construct(
        private readonly QuickBooksSyncService $syncService,
    ) {}

    /**
     * Handle incoming QuickBooks webhook notifications
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('intuit-signature');

        // Log incoming webhook
        Log::info('QuickBooks webhook received', [
            'payload' => $payload,
            'signature' => $signature,
        ]);

        // Verify signature
        if (!$this->verifySignature($request->getContent(), $signature)) {
            Log::warning('QuickBooks webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Process each notification
        $eventNotifications = $payload['eventNotifications'] ?? [];

        foreach ($eventNotifications as $notification) {
            $this->processNotification($notification);
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * Verify the webhook signature from QuickBooks
     */
    private function verifySignature(string $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $webhookVerifierToken = config('services.quickbooks.webhook_verifier_token');

        if (!$webhookVerifierToken) {
            // If no verifier token is configured, skip verification in development
            return config('app.env') !== 'production';
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookVerifierToken, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process a single webhook notification
     */
    private function processNotification(array $notification): void
    {
        $realmId = $notification['realmId'] ?? null;
        $dataChangeEvent = $notification['dataChangeEvent'] ?? null;

        if (!$realmId || !$dataChangeEvent) {
            return;
        }

        // Find the connection for this realm
        $connection = IntegrationConnection::where('provider', self::PROVIDER)
            ->where('status', 'active')
            ->whereRaw("JSON_EXTRACT(settings, '$.realm_id') = ?", [$realmId])
            ->orWhereRaw("JSON_EXTRACT(credentials, '$.realm_id') = ?", [$realmId])
            ->first();

        if (!$connection) {
            Log::warning('QuickBooks webhook: No connection found for realm', ['realm_id' => $realmId]);
            return;
        }

        // Log the webhook event
        $webhookLog = IntegrationWebhookLog::create([
            'integration_connection_id' => $connection->id,
            'event_type' => 'data_change',
            'payload' => $notification,
            'status' => 'processing',
            'received_at' => now(),
        ]);

        try {
            $entities = $dataChangeEvent['entities'] ?? [];

            foreach ($entities as $entity) {
                $this->processEntityChange($connection, $entity);
            }

            $webhookLog->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('QuickBooks webhook processing failed', [
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);

            $webhookLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    /**
     * Process an entity change from QuickBooks
     */
    private function processEntityChange(IntegrationConnection $connection, array $entity): void
    {
        $entityName = $entity['name'] ?? null;
        $entityId = $entity['id'] ?? null;
        $operation = $entity['operation'] ?? null;
        $lastUpdated = $entity['lastUpdated'] ?? null;

        if (!$entityName || !$entityId) {
            return;
        }

        Log::info('QuickBooks entity change', [
            'entity' => $entityName,
            'id' => $entityId,
            'operation' => $operation,
        ]);

        // Map QuickBooks entity names to our entity types
        $entityType = match (strtolower($entityName)) {
            'customer' => 'contacts',
            'invoice' => 'invoices',
            'payment' => 'payments',
            'vendor' => 'vendors',
            'bill' => 'bills',
            'item' => 'products',
            default => null,
        };

        if (!$entityType) {
            return;
        }

        // Queue a sync for this specific entity
        // For now, we'll trigger an incremental sync
        // In production, you might want to queue this as a job
        match ($entityType) {
            'contacts' => $this->syncService->syncContacts($connection),
            'invoices' => $this->syncService->syncInvoices($connection),
            default => null,
        };
    }

    /**
     * Verify webhook endpoint (QuickBooks verification request)
     */
    public function verify(Request $request): JsonResponse
    {
        // QuickBooks sends a challenge for verification
        $challenge = $request->input('challenge');

        if ($challenge) {
            return response()->json(['challenge' => $challenge]);
        }

        return response()->json(['status' => 'ok']);
    }
}
