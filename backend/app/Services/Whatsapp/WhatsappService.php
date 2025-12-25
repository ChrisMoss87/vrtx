<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\DB;

class WhatsappService
{
    /**
     * Send a text message
     */
    public function sendTextMessage(
        WhatsappConnection $connection,
        string $to,
        string $text,
        ?int $userId = null,
        ?WhatsappConversation $conversation = null
    ): WhatsappMessage {
        $conversation = $conversation ?? $this->getOrCreateConversation($connection, $to);

        // Create pending message record
        $message = DB::table('whatsapp_messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'connection_id' => $connection->id,
            'direction' => 'outbound',
            'type' => 'text',
            'content' => $text,
            'status' => 'pending',
            'sent_by' => $userId,
        ]);

        // Send via API
        $api = WhatsappApiService::for($connection);
        $result = $api->sendTextMessage($to, $text);

        if ($result['success']) {
            $message->markAsSent($result['message_id']);
            $conversation->update([
                'last_message_at' => now(),
                'last_outgoing_at' => now(),
            ]);
        } else {
            $message->markAsFailed($result['error_code'], $result['error_message']);
        }

        return $message->fresh();
    }

    /**
     * Send a template message
     */
    public function sendTemplateMessage(
        WhatsappConnection $connection,
        string $to,
        WhatsappTemplate $template,
        array $params = [],
        ?int $userId = null,
        ?WhatsappConversation $conversation = null
    ): WhatsappMessage {
        $conversation = $conversation ?? $this->getOrCreateConversation($connection, $to);

        // Create pending message record
        $message = DB::table('whatsapp_messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'connection_id' => $connection->id,
            'direction' => 'outbound',
            'type' => 'template',
            'content' => $template->renderBody($params['body'] ?? []),
            'template_id' => $template->id,
            'template_params' => $params,
            'status' => 'pending',
            'sent_by' => $userId,
        ]);

        // Send via API
        $api = WhatsappApiService::for($connection);
        $result = $api->sendTemplateMessage(
            $to,
            $template,
            $params['body'] ?? [],
            $params['header'] ?? [],
            $params['buttons'] ?? null
        );

        if ($result['success']) {
            $message->markAsSent($result['message_id']);
            $conversation->update([
                'last_message_at' => now(),
                'last_outgoing_at' => now(),
            ]);
        } else {
            $message->markAsFailed($result['error_code'], $result['error_message']);
        }

        return $message->fresh();
    }

    /**
     * Send a media message
     */
    public function sendMediaMessage(
        WhatsappConnection $connection,
        string $to,
        string $type,
        string $mediaUrl,
        ?string $caption = null,
        ?string $filename = null,
        ?int $userId = null,
        ?WhatsappConversation $conversation = null
    ): WhatsappMessage {
        $conversation = $conversation ?? $this->getOrCreateConversation($connection, $to);

        // Create pending message record
        $message = DB::table('whatsapp_messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'connection_id' => $connection->id,
            'direction' => 'outbound',
            'type' => $type,
            'content' => $caption,
            'media' => [
                'url' => $mediaUrl,
                'filename' => $filename,
            ],
            'status' => 'pending',
            'sent_by' => $userId,
        ]);

        // Send via API
        $api = WhatsappApiService::for($connection);
        $result = $api->sendMediaMessage($to, $type, $mediaUrl, $caption, $filename);

        if ($result['success']) {
            $message->markAsSent($result['message_id']);
            $conversation->update([
                'last_message_at' => now(),
                'last_outgoing_at' => now(),
            ]);
        } else {
            $message->markAsFailed($result['error_code'], $result['error_message']);
        }

        return $message->fresh();
    }

    /**
     * Process incoming webhook message
     */
    public function processIncomingMessage(WhatsappConnection $connection, array $messageData): ?WhatsappMessage
    {
        $from = $messageData['from'] ?? null;
        $messageId = $messageData['id'] ?? null;
        $type = $messageData['type'] ?? 'text';

        if (!$from || !$messageId) {
            return null;
        }

        // Check for duplicate
        if (DB::table('whatsapp_messages')->where('wa_message_id', $messageId)->exists()) {
            return null;
        }

        $conversation = $this->getOrCreateConversation(
            $connection,
            $from,
            $messageData['contacts'][0]['profile']['name'] ?? null
        );

        // Extract content based on type
        $content = null;
        $media = null;

        switch ($type) {
            case 'text':
                $content = $messageData['text']['body'] ?? null;
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $media = [
                    'id' => $messageData[$type]['id'] ?? null,
                    'mime_type' => $messageData[$type]['mime_type'] ?? null,
                    'sha256' => $messageData[$type]['sha256'] ?? null,
                    'filename' => $messageData[$type]['filename'] ?? null,
                ];
                $content = $messageData[$type]['caption'] ?? null;
                break;
            case 'location':
                $content = json_encode($messageData['location']);
                break;
            case 'contacts':
                $content = json_encode($messageData['contacts']);
                break;
            case 'interactive':
                $interactive = $messageData['interactive'] ?? [];
                $content = $interactive['button_reply']['title'] ?? $interactive['list_reply']['title'] ?? null;
                break;
            case 'reaction':
                $content = $messageData['reaction']['emoji'] ?? null;
                break;
        }

        // Create message
        $message = DB::table('whatsapp_messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'connection_id' => $connection->id,
            'wa_message_id' => $messageId,
            'direction' => 'inbound',
            'type' => $type,
            'content' => $content,
            'media' => $media,
            'context_message_id' => $messageData['context']['id'] ?? null,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Update conversation
        $conversation->update([
            'last_message_at' => now(),
            'last_incoming_at' => now(),
            'status' => 'open',
        ]);
        $conversation->incrementUnread();

        return $message;
    }

    /**
     * Process message status update
     */
    public function processStatusUpdate(string $messageId, string $status, ?int $timestamp = null): void
    {
        $message = DB::table('whatsapp_messages')->where('wa_message_id', $messageId)->first();

        if (!$message) {
            return;
        }

        switch ($status) {
            case 'sent':
                $message->update([
                    'status' => 'sent',
                    'sent_at' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : now(),
                ]);
                break;
            case 'delivered':
                $message->markAsDelivered();
                break;
            case 'read':
                $message->markAsRead();
                break;
            case 'failed':
                // Error details should be in separate webhook event
                break;
        }
    }

    /**
     * Get or create conversation
     */
    public function getOrCreateConversation(
        WhatsappConnection $connection,
        string $phone,
        ?string $name = null
    ): WhatsappConversation {
        $normalizedPhone = $this->normalizePhoneNumber($phone);

        return WhatsappConversation::firstOrCreate(
            [
                'connection_id' => $connection->id,
                'contact_wa_id' => $normalizedPhone,
            ],
            [
                'contact_phone' => '+' . $normalizedPhone,
                'contact_name' => $name,
                'status' => 'open',
            ]
        );
    }

    /**
     * Link conversation to CRM record
     */
    public function linkToRecord(
        WhatsappConversation $conversation,
        string $moduleApiName,
        int $recordId
    ): WhatsappConversation {
        $conversation->linkToRecord($moduleApiName, $recordId);
        return $conversation->fresh();
    }

    /**
     * Find conversations by phone number
     */
    public function findConversationsByPhone(string $phone): \Illuminate\Support\Collection
    {
        $normalizedPhone = $this->normalizePhoneNumber($phone);

        return DB::table('whatsapp_conversations')->where('contact_wa_id', $normalizedPhone)
            ->orWhere('contact_phone', 'LIKE', '%' . $normalizedPhone)
            ->with(['connection', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->get();
    }

    /**
     * Get conversations for a CRM record
     */
    public function getRecordConversations(string $moduleApiName, int $recordId): \Illuminate\Support\Collection
    {
        return DB::table('whatsapp_conversations')->where('module_api_name', $moduleApiName)
            ->where('module_record_id', $recordId)
            ->with(['connection', 'messages' => fn($q) => $q->latest()->limit(5)])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Bulk send template messages
     */
    public function bulkSendTemplate(
        WhatsappConnection $connection,
        WhatsappTemplate $template,
        array $recipients,
        ?int $userId = null
    ): array {
        $results = [];

        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'];
            $params = $recipient['params'] ?? [];

            try {
                $message = $this->sendTemplateMessage($connection, $phone, $template, $params, $userId);
                $results[] = [
                    'phone' => $phone,
                    'success' => $message->status !== 'failed',
                    'message_id' => $message->id,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'phone' => $phone,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function normalizePhoneNumber(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
