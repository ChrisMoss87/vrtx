<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Application\Services;

use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use Plugins\WhatsApp\Domain\Repositories\WhatsAppRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppApplicationService
{
    private const PLUGIN_SLUG = 'whatsapp';
    private const USAGE_METRIC_MESSAGES = 'messages_sent';
    private const USAGE_METRIC_CONVERSATIONS = 'conversations';

    public function __construct(
        private readonly WhatsAppRepositoryInterface $whatsAppRepository,
        private readonly PluginRepositoryInterface $pluginRepository,
    ) {}

    // =========================================================================
    // CONNECTIONS
    // =========================================================================

    /**
     * List all WhatsApp connections.
     */
    public function listConnections(): array
    {
        return $this->whatsAppRepository->listConnections();
    }

    /**
     * Get connection details.
     */
    public function getConnection(int $id): ?array
    {
        return $this->whatsAppRepository->findConnectionById($id);
    }

    /**
     * Create a new WhatsApp connection.
     */
    public function createConnection(array $data): array
    {
        // Validate connection with WhatsApp Business API
        $validated = $this->validateConnectionCredentials($data);

        if (!$validated['success']) {
            throw new \RuntimeException($validated['error']);
        }

        return $this->whatsAppRepository->createConnection([
            'name' => $data['name'],
            'phone_number_id' => $data['phone_number_id'],
            'business_account_id' => $data['business_account_id'],
            'access_token' => encrypt($data['access_token']),
            'webhook_verify_token' => $data['webhook_verify_token'] ?? \Str::random(32),
            'status' => 'active',
        ]);
    }

    /**
     * Update a connection.
     */
    public function updateConnection(int $id, array $data): array
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['access_token'])) {
            $updateData['access_token'] = encrypt($data['access_token']);
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        return $this->whatsAppRepository->updateConnection($id, $updateData);
    }

    /**
     * Delete a connection.
     */
    public function deleteConnection(int $id): bool
    {
        return $this->whatsAppRepository->deleteConnection($id);
    }

    /**
     * Test connection credentials.
     */
    public function testConnection(int $id): array
    {
        $connection = $this->whatsAppRepository->findConnectionById($id);

        if (!$connection) {
            return ['success' => false, 'error' => 'Connection not found'];
        }

        return $this->validateConnectionCredentials([
            'phone_number_id' => $connection['phone_number_id'],
            'access_token' => decrypt($connection['access_token']),
        ]);
    }

    // =========================================================================
    // CONVERSATIONS
    // =========================================================================

    /**
     * List conversations.
     */
    public function listConversations(array $filters = [], int $perPage = 20): array
    {
        return $this->whatsAppRepository->listConversations($filters, $perPage);
    }

    /**
     * Get conversation with messages.
     */
    public function getConversation(int $id): ?array
    {
        $conversation = $this->whatsAppRepository->findConversationById($id);

        if (!$conversation) {
            return null;
        }

        $conversation['messages'] = $this->whatsAppRepository->listMessages($id);

        return $conversation;
    }

    /**
     * Get conversations for a CRM record.
     */
    public function getConversationsForRecord(string $moduleApiName, int $recordId): array
    {
        return $this->whatsAppRepository->getConversationsForRecord($moduleApiName, $recordId);
    }

    /**
     * Link conversation to CRM record.
     */
    public function linkConversationToRecord(int $conversationId, string $moduleApiName, int $recordId): array
    {
        return $this->whatsAppRepository->updateConversation($conversationId, [
            'linked_module_api_name' => $moduleApiName,
            'linked_record_id' => $recordId,
        ]);
    }

    /**
     * Assign conversation to user.
     */
    public function assignConversation(int $conversationId, int $userId): array
    {
        return $this->whatsAppRepository->updateConversation($conversationId, [
            'assigned_to' => $userId,
        ]);
    }

    /**
     * Update conversation status.
     */
    public function updateConversationStatus(int $conversationId, string $status): array
    {
        return $this->whatsAppRepository->updateConversation($conversationId, [
            'status' => $status,
        ]);
    }

    // =========================================================================
    // MESSAGING
    // =========================================================================

    /**
     * Send a text message.
     */
    public function sendTextMessage(string $toPhoneNumber, string $message, ?int $conversationId = null): array
    {
        $connection = $this->whatsAppRepository->getActiveConnection();

        if (!$connection) {
            throw new \RuntimeException('No active WhatsApp connection configured');
        }

        // Get or create conversation
        if (!$conversationId) {
            $conversation = $this->whatsAppRepository->getOrCreateConversation($toPhoneNumber);
            $conversationId = $conversation['id'];

            // Track new conversation usage
            $this->trackUsage(self::USAGE_METRIC_CONVERSATIONS);
        }

        // Send message via WhatsApp API
        $result = $this->sendWhatsAppMessage($connection, $toPhoneNumber, [
            'type' => 'text',
            'text' => ['body' => $message],
        ]);

        // Track message usage
        $this->trackUsage(self::USAGE_METRIC_MESSAGES);

        // Store message in database
        return $this->whatsAppRepository->createMessage([
            'conversation_id' => $conversationId,
            'whatsapp_message_id' => $result['messages'][0]['id'] ?? null,
            'direction' => 'outbound',
            'type' => 'text',
            'content' => $message,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(
        string $toPhoneNumber,
        string $templateSlug,
        array $parameters = [],
        ?int $conversationId = null
    ): array {
        $connection = $this->whatsAppRepository->getActiveConnection();

        if (!$connection) {
            throw new \RuntimeException('No active WhatsApp connection configured');
        }

        $template = $this->whatsAppRepository->findTemplateBySlug($templateSlug);

        if (!$template) {
            throw new \RuntimeException("Template not found: {$templateSlug}");
        }

        // Get or create conversation
        if (!$conversationId) {
            $conversation = $this->whatsAppRepository->getOrCreateConversation($toPhoneNumber);
            $conversationId = $conversation['id'];
            $this->trackUsage(self::USAGE_METRIC_CONVERSATIONS);
        }

        // Build template components
        $components = $this->buildTemplateComponents($parameters);

        // Send template via WhatsApp API
        $result = $this->sendWhatsAppMessage($connection, $toPhoneNumber, [
            'type' => 'template',
            'template' => [
                'name' => $template['slug'],
                'language' => ['code' => $template['language'] ?? 'en'],
                'components' => $components,
            ],
        ]);

        // Track usage
        $this->trackUsage(self::USAGE_METRIC_MESSAGES);

        // Store message
        return $this->whatsAppRepository->createMessage([
            'conversation_id' => $conversationId,
            'whatsapp_message_id' => $result['messages'][0]['id'] ?? null,
            'direction' => 'outbound',
            'type' => 'template',
            'content' => $this->renderTemplate($template['content'], $parameters),
            'template_id' => $template['id'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Handle incoming webhook message.
     */
    public function handleIncomingMessage(array $webhookData): ?array
    {
        $entry = $webhookData['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $messages = $value['messages'] ?? [];

        if (empty($messages)) {
            return null;
        }

        $messageData = $messages[0];
        $phoneNumber = $messageData['from'];
        $contactName = $value['contacts'][0]['profile']['name'] ?? null;

        // Get or create conversation
        $conversation = $this->whatsAppRepository->getOrCreateConversation($phoneNumber, $contactName);

        // Store the message
        $message = $this->whatsAppRepository->createMessage([
            'conversation_id' => $conversation['id'],
            'whatsapp_message_id' => $messageData['id'],
            'direction' => 'inbound',
            'type' => $messageData['type'],
            'content' => $this->extractMessageContent($messageData),
            'status' => 'received',
            'received_at' => now(),
        ]);

        // Update conversation with last message info
        $this->whatsAppRepository->updateConversation($conversation['id'], [
            'last_message_at' => now(),
            'last_message_preview' => \Str::limit($message['content'], 100),
        ]);

        return $message;
    }

    /**
     * Handle message status webhook.
     */
    public function handleStatusUpdate(array $webhookData): void
    {
        $statuses = $webhookData['entry'][0]['changes'][0]['value']['statuses'] ?? [];

        foreach ($statuses as $status) {
            $message = $this->whatsAppRepository->findMessageByWhatsAppId($status['id']);

            if ($message) {
                $this->whatsAppRepository->updateMessageStatus($message['id'], $status['status']);
            }
        }
    }

    // =========================================================================
    // TEMPLATES
    // =========================================================================

    /**
     * List templates.
     */
    public function listTemplates(array $filters = []): array
    {
        return $this->whatsAppRepository->listTemplates($filters);
    }

    /**
     * Get a template.
     */
    public function getTemplate(int $id): ?array
    {
        return $this->whatsAppRepository->findTemplateById($id);
    }

    /**
     * Create a template.
     */
    public function createTemplate(array $data): array
    {
        return $this->whatsAppRepository->createTemplate($data);
    }

    /**
     * Update a template.
     */
    public function updateTemplate(int $id, array $data): array
    {
        return $this->whatsAppRepository->updateTemplate($id, $data);
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate(int $id): bool
    {
        return $this->whatsAppRepository->deleteTemplate($id);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    /**
     * Get WhatsApp statistics.
     */
    public function getStats(): array
    {
        return [
            'messages' => $this->whatsAppRepository->getMessageStats(),
            'conversations' => $this->whatsAppRepository->getConversationStats(),
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Validate WhatsApp API credentials.
     */
    private function validateConnectionCredentials(array $data): array
    {
        try {
            $response = Http::withToken($data['access_token'])
                ->get("https://graph.facebook.com/v18.0/{$data['phone_number_id']}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => $response->json('error.message', 'Invalid credentials')];
        } catch (\Exception $e) {
            Log::error('WhatsApp credential validation failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send message via WhatsApp Business API.
     */
    private function sendWhatsAppMessage(array $connection, string $to, array $messageData): array
    {
        $response = Http::withToken(decrypt($connection['access_token']))
            ->post("https://graph.facebook.com/v18.0/{$connection['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                ...$messageData,
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Failed to send message');
            Log::error('WhatsApp message send failed', [
                'error' => $error,
                'to' => $to,
            ]);
            throw new \RuntimeException($error);
        }

        return $response->json();
    }

    /**
     * Build template components from parameters.
     */
    private function buildTemplateComponents(array $parameters): array
    {
        if (empty($parameters)) {
            return [];
        }

        $components = [];

        if (!empty($parameters['body'])) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn($value) => [
                    'type' => 'text',
                    'text' => $value,
                ], $parameters['body']),
            ];
        }

        if (!empty($parameters['header'])) {
            $components[] = [
                'type' => 'header',
                'parameters' => $parameters['header'],
            ];
        }

        return $components;
    }

    /**
     * Render template with parameters.
     */
    private function renderTemplate(string $content, array $parameters): string
    {
        $rendered = $content;

        foreach ($parameters['body'] ?? [] as $index => $value) {
            $rendered = str_replace("{{" . ($index + 1) . "}}", $value, $rendered);
        }

        return $rendered;
    }

    /**
     * Extract message content from webhook data.
     */
    private function extractMessageContent(array $messageData): string
    {
        return match ($messageData['type']) {
            'text' => $messageData['text']['body'] ?? '',
            'image' => '[Image: ' . ($messageData['image']['caption'] ?? 'No caption') . ']',
            'document' => '[Document: ' . ($messageData['document']['filename'] ?? 'Unknown') . ']',
            'audio' => '[Audio message]',
            'video' => '[Video: ' . ($messageData['video']['caption'] ?? 'No caption') . ']',
            'location' => '[Location: ' . ($messageData['location']['name'] ?? 'Unknown location') . ']',
            'contacts' => '[Contact shared]',
            'sticker' => '[Sticker]',
            default => '[Unsupported message type: ' . $messageData['type'] . ']',
        };
    }

    /**
     * Track plugin usage.
     */
    private function trackUsage(string $metric, int $amount = 1): void
    {
        try {
            $this->pluginRepository->trackUsage(self::PLUGIN_SLUG, $metric, $amount);
        } catch (\Exception $e) {
            Log::warning('Failed to track WhatsApp plugin usage', [
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
