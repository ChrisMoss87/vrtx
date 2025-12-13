<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private WhatsappService $whatsappService
    ) {}

    /**
     * Webhook verification (GET request from Meta)
     */
    public function verify(Request $request, int $connectionId): Response
    {
        $connection = WhatsappConnection::find($connectionId);

        if (!$connection) {
            return response('Connection not found', 404);
        }

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $connection->webhook_verify_token) {
            Log::info('WhatsApp webhook verified', ['connection_id' => $connectionId]);
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'connection_id' => $connectionId,
            'provided_token' => $token,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Webhook events (POST request from Meta)
     */
    public function handle(Request $request, int $connectionId): JsonResponse
    {
        $connection = WhatsappConnection::find($connectionId);

        if (!$connection) {
            return response()->json(['error' => 'Connection not found'], 404);
        }

        $payload = $request->all();

        Log::debug('WhatsApp webhook received', [
            'connection_id' => $connectionId,
            'payload' => $payload,
        ]);

        try {
            $this->processWebhook($connection, $payload);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing error', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
        }

        // Always return 200 to acknowledge receipt
        return response()->json(['status' => 'ok']);
    }

    private function processWebhook(WhatsappConnection $connection, array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];

                if ($field === 'messages') {
                    $this->processMessagesChange($connection, $value);
                }
            }
        }
    }

    private function processMessagesChange(WhatsappConnection $connection, array $value): void
    {
        // Process incoming messages
        $messages = $value['messages'] ?? [];
        $contacts = $value['contacts'] ?? [];

        foreach ($messages as $message) {
            // Add contact info to message for processing
            $message['contacts'] = $contacts;
            $this->whatsappService->processIncomingMessage($connection, $message);
        }

        // Process status updates
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $statusValue = $status['status'] ?? null;
            $timestamp = $status['timestamp'] ?? null;

            if ($messageId && $statusValue) {
                $this->whatsappService->processStatusUpdate(
                    $messageId,
                    $statusValue,
                    $timestamp ? (int) $timestamp : null
                );
            }

            // Handle errors
            if (isset($status['errors'])) {
                $this->processMessageError($messageId, $status['errors']);
            }
        }
    }

    private function processMessageError(string $messageId, array $errors): void
    {
        $message = \App\Models\WhatsappMessage::where('wa_message_id', $messageId)->first();

        if ($message && !empty($errors)) {
            $error = $errors[0];
            $message->markAsFailed(
                $error['code'] ?? 'unknown',
                $error['message'] ?? 'Unknown error'
            );
        }
    }
}
