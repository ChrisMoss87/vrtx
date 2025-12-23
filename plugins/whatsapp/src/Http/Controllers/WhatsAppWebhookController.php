<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Plugins\WhatsApp\Application\Services\WhatsAppApplicationService;
use Plugins\WhatsApp\Domain\Repositories\WhatsAppRepositoryInterface;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppApplicationService $whatsAppService,
        private readonly WhatsAppRepositoryInterface $repository,
    ) {}

    /**
     * Verify webhook subscription.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Get the expected verify token from active connection
        $connection = $this->repository->getActiveConnection();
        $expectedToken = $connection['webhook_verify_token'] ?? null;

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $expectedToken,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('WhatsApp webhook received', [
            'type' => $payload['entry'][0]['changes'][0]['field'] ?? 'unknown',
        ]);

        try {
            $changes = $payload['entry'][0]['changes'][0] ?? null;

            if (!$changes) {
                return response()->json(['status' => 'ignored']);
            }

            $field = $changes['field'] ?? null;
            $value = $changes['value'] ?? [];

            match ($field) {
                'messages' => $this->handleMessages($value),
                'message_status' => $this->handleMessageStatus($value),
                default => Log::info("Unhandled webhook field: {$field}"),
            };

            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            // Return 200 to prevent webhook retries
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Handle incoming messages.
     */
    private function handleMessages(array $value): void
    {
        $messages = $value['messages'] ?? [];
        $contacts = $value['contacts'] ?? [];
        $metadata = $value['metadata'] ?? [];

        foreach ($messages as $message) {
            $contactInfo = $this->findContactInfo($message['from'], $contacts);

            $this->whatsAppService->handleIncomingMessage([
                'entry' => [[
                    'changes' => [[
                        'value' => [
                            'messages' => [$message],
                            'contacts' => $contacts,
                            'metadata' => $metadata,
                        ],
                    ]],
                ]],
            ]);

            Log::info('WhatsApp message received', [
                'from' => $message['from'],
                'type' => $message['type'],
                'message_id' => $message['id'],
            ]);
        }
    }

    /**
     * Handle message status updates.
     */
    private function handleMessageStatus(array $value): void
    {
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $status) {
            $this->whatsAppService->handleStatusUpdate([
                'entry' => [[
                    'changes' => [[
                        'value' => [
                            'statuses' => [$status],
                        ],
                    ]],
                ]],
            ]);

            Log::info('WhatsApp message status updated', [
                'message_id' => $status['id'],
                'status' => $status['status'],
            ]);
        }
    }

    /**
     * Find contact info from contacts array.
     */
    private function findContactInfo(string $phoneNumber, array $contacts): array
    {
        foreach ($contacts as $contact) {
            if ($contact['wa_id'] === $phoneNumber) {
                return $contact;
            }
        }

        return ['wa_id' => $phoneNumber, 'profile' => ['name' => null]];
    }
}
