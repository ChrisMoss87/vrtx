<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration\Webhooks;

use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Services\Integration\QuickBooks\QuickBooksSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuickBooksWebhookController extends Controller
{
    private const PROVIDER = 'quickbooks';

    public function __construct(
        private readonly QuickBooksSyncService $syncService,
        private readonly IntegrationConnectionRepositoryInterface $connectionRepository,
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
        // Using DB::table directly for JSON queries as repository doesn't support this specific query
        $connectionData = DB::table('integration_connections')
            ->where('integration_slug', self::PROVIDER)
            ->where('status', 'active')
            ->where(function ($query) use ($realmId) {
                $query->whereRaw("JSON_EXTRACT(settings, '$.realm_id') = ?", [$realmId])
                    ->orWhereRaw("JSON_EXTRACT(credentials, '$.realm_id') = ?", [$realmId]);
            })
            ->first();

        if (!$connectionData) {
            Log::warning('QuickBooks webhook: No connection found for realm', ['realm_id' => $realmId]);
            return;
        }

        // Log the webhook event using DB::table
        $webhookLogId = DB::table('integration_webhook_logs')->insertGetId([
            'integration_connection_id' => $connectionData->id,
            'event_type' => 'data_change',
            'payload' => json_encode($notification),
            'status' => 'processing',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $entities = $dataChangeEvent['entities'] ?? [];

            foreach ($entities as $entity) {
                $this->processEntityChange($connectionData->id, $entity);
            }

            DB::table('integration_webhook_logs')
                ->where('id', $webhookLogId)
                ->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::error('QuickBooks webhook processing failed', [
                'error' => $e->getMessage(),
                'notification' => $notification,
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
     * Process an entity change from QuickBooks
     */
    private function processEntityChange(int $connectionId, array $entity): void
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

        // Get connection data from repository
        $connection = $this->connectionRepository->findById($connectionId);

        if (!$connection) {
            Log::error('Connection not found', ['connection_id' => $connectionId]);
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
