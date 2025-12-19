<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappConnection;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappApiService
{
    private const API_VERSION = 'v18.0';
    private const BASE_URL = 'https://graph.facebook.com';

    private WhatsappConnection $connection;

    public function __construct(WhatsappConnection $connection)
    {
        $this->connection = $connection;
    }

    public static function for(WhatsappConnection $connection): self
    {
        return new self($connection);
    }

    /**
     * Send a text message
     */
    public function sendTextMessage(string $to, string $text, ?string $contextMessageId = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhoneNumber($to),
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $text,
            ],
        ];

        if ($contextMessageId) {
            $payload['context'] = ['message_id' => $contextMessageId];
        }

        return $this->sendMessage($payload);
    }

    /**
     * Send a template message
     */
    public function sendTemplateMessage(
        string $to,
        WhatsappTemplate $template,
        array $bodyParams = [],
        array $headerParams = [],
        ?array $buttons = null
    ): array {
        $components = [];

        // Header parameters
        if (!empty($headerParams)) {
            $components[] = [
                'type' => 'header',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => $p], $headerParams),
            ];
        }

        // Body parameters
        if (!empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => $p], $bodyParams),
            ];
        }

        // Button parameters
        if ($buttons) {
            foreach ($buttons as $index => $button) {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => $button['type'] ?? 'quick_reply',
                    'index' => (string) $index,
                    'parameters' => [['type' => 'payload', 'payload' => $button['payload'] ?? '']],
                ];
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhoneNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $template->name,
                'language' => [
                    'code' => $template->language,
                ],
                'components' => $components,
            ],
        ];

        return $this->sendMessage($payload);
    }

    /**
     * Send a media message (image, video, audio, document)
     */
    public function sendMediaMessage(
        string $to,
        string $type,
        string $mediaUrl,
        ?string $caption = null,
        ?string $filename = null
    ): array {
        $mediaPayload = ['link' => $mediaUrl];

        if ($caption && in_array($type, ['image', 'video', 'document'])) {
            $mediaPayload['caption'] = $caption;
        }

        if ($filename && $type === 'document') {
            $mediaPayload['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhoneNumber($to),
            'type' => $type,
            $type => $mediaPayload,
        ];

        return $this->sendMessage($payload);
    }

    /**
     * Send a location message
     */
    public function sendLocationMessage(
        string $to,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhoneNumber($to),
            'type' => 'location',
            'location' => array_filter([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address,
            ]),
        ];

        return $this->sendMessage($payload);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $messageId): bool
    {
        $response = $this->post('/messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);

        return $response->successful();
    }

    /**
     * Get media URL from media ID
     */
    public function getMediaUrl(string $mediaId): ?string
    {
        $response = Http::withToken($this->connection->decrypted_access_token)
            ->get(self::BASE_URL . '/' . self::API_VERSION . '/' . $mediaId);

        if ($response->successful()) {
            return $response->json('url');
        }

        return null;
    }

    /**
     * Download media file
     */
    public function downloadMedia(string $mediaUrl): ?string
    {
        $response = Http::withToken($this->connection->decrypted_access_token)
            ->get($mediaUrl);

        if ($response->successful()) {
            return base64_encode($response->body());
        }

        return null;
    }

    /**
     * Create a message template
     */
    public function createTemplate(array $templateData): array
    {
        $response = Http::withToken($this->connection->decrypted_access_token)
            ->post(self::BASE_URL . '/' . self::API_VERSION . '/' . $this->connection->waba_id . '/message_templates', $templateData);

        return [
            'success' => $response->successful(),
            'data' => $response->json(),
        ];
    }

    /**
     * Get template status
     */
    public function getTemplateStatus(string $templateName): ?array
    {
        $response = Http::withToken($this->connection->decrypted_access_token)
            ->get(self::BASE_URL . '/' . self::API_VERSION . '/' . $this->connection->waba_id . '/message_templates', [
                'name' => $templateName,
            ]);

        if ($response->successful()) {
            $templates = $response->json('data', []);
            return $templates[0] ?? null;
        }

        return null;
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(string $templateName): bool
    {
        $response = Http::withToken($this->connection->decrypted_access_token)
            ->delete(self::BASE_URL . '/' . self::API_VERSION . '/' . $this->connection->waba_id . '/message_templates', [
                'name' => $templateName,
            ]);

        return $response->successful();
    }

    /**
     * Get phone number info
     */
    public function getPhoneNumberInfo(): ?array
    {
        $response = Http::withToken($this->connection->decrypted_access_token)
            ->get(self::BASE_URL . '/' . self::API_VERSION . '/' . $this->connection->phone_number_id);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Core message sending
     */
    private function sendMessage(array $payload): array
    {
        $response = $this->post('/messages', $payload);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['messages'][0]['id'] ?? null,
                'data' => $data,
            ];
        }

        $error = $response->json('error', []);
        Log::error('WhatsApp API Error', [
            'connection_id' => $this->connection->id,
            'error' => $error,
            'payload' => $payload,
        ]);

        return [
            'success' => false,
            'error_code' => $error['code'] ?? 'unknown',
            'error_message' => $error['message'] ?? 'Unknown error',
            'data' => $response->json(),
        ];
    }

    private function post(string $endpoint, array $data): Response
    {
        return Http::withToken($this->connection->decrypted_access_token)
            ->post($this->getUrl($endpoint), $data);
    }

    private function getUrl(string $endpoint): string
    {
        return self::BASE_URL . '/' . self::API_VERSION . '/' . $this->connection->phone_number_id . $endpoint;
    }

    private function normalizePhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        // Remove leading + if present
        return ltrim($phone, '+');
    }
}
