<?php

declare(strict_types=1);

namespace Plugins\SMS\Application\Services;

use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use Plugins\SMS\Domain\Repositories\SMSRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSApplicationService
{
    private const PLUGIN_SLUG = 'sms';
    private const USAGE_METRIC_MESSAGES = 'messages_sent';
    private const USAGE_METRIC_CAMPAIGNS = 'campaigns';

    public function __construct(
        private readonly SMSRepositoryInterface $smsRepository,
        private readonly PluginRepositoryInterface $pluginRepository,
    ) {}

    // =========================================================================
    // CONNECTIONS
    // =========================================================================

    /**
     * List all SMS connections.
     */
    public function listConnections(bool $activeOnly = false): array
    {
        return $this->smsRepository->listConnections($activeOnly);
    }

    /**
     * Get connection details.
     */
    public function getConnection(int $id): ?array
    {
        return $this->smsRepository->findConnectionById($id);
    }

    /**
     * Create a new SMS connection.
     */
    public function createConnection(array $data): array
    {
        // Encrypt sensitive credentials
        $connectionData = [
            'name' => $data['name'],
            'provider' => $data['provider'],
            'phone_number' => $data['phone_number'],
            'account_sid' => isset($data['account_sid']) ? encrypt($data['account_sid']) : null,
            'auth_token' => isset($data['auth_token']) ? encrypt($data['auth_token']) : null,
            'messaging_service_sid' => $data['messaging_service_sid'] ?? null,
            'status' => 'active',
            'daily_limit' => $data['daily_limit'] ?? 1000,
            'monthly_limit' => $data['monthly_limit'] ?? 30000,
        ];

        return $this->smsRepository->createConnection($connectionData);
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
        if (isset($data['auth_token'])) {
            $updateData['auth_token'] = encrypt($data['auth_token']);
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['daily_limit'])) {
            $updateData['daily_limit'] = $data['daily_limit'];
        }
        if (isset($data['monthly_limit'])) {
            $updateData['monthly_limit'] = $data['monthly_limit'];
        }

        return $this->smsRepository->updateConnection($id, $updateData);
    }

    /**
     * Delete a connection.
     */
    public function deleteConnection(int $id): bool
    {
        return $this->smsRepository->deleteConnection($id);
    }

    /**
     * Test connection credentials.
     */
    public function testConnection(int $id): array
    {
        $connection = $this->smsRepository->findConnectionById($id);

        if (!$connection) {
            return ['success' => false, 'error' => 'Connection not found'];
        }

        // Test with provider API
        try {
            if ($connection['provider'] === 'twilio') {
                return $this->testTwilioConnection($connection);
            }

            return ['success' => false, 'error' => 'Unsupported provider'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // MESSAGING
    // =========================================================================

    /**
     * List messages.
     */
    public function listMessages(array $filters = [], int $perPage = 20): array
    {
        return $this->smsRepository->listMessages($filters, $perPage);
    }

    /**
     * Get a message.
     */
    public function getMessage(int $id): ?array
    {
        return $this->smsRepository->findMessageById($id);
    }

    /**
     * Get conversation for a phone number.
     */
    public function getConversation(string $phoneNumber, int $limit = 100): array
    {
        return $this->smsRepository->getConversation($phoneNumber, $limit);
    }

    /**
     * Get messages for a CRM record.
     */
    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): array
    {
        return $this->smsRepository->getRecordMessages($moduleApiName, $recordId, $limit);
    }

    /**
     * Send an SMS message.
     */
    public function sendSms(
        string $toPhoneNumber,
        string $content,
        int $connectionId,
        ?string $moduleApiName = null,
        ?int $recordId = null
    ): array {
        $connection = $this->smsRepository->findActiveConnectionById($connectionId);

        if (!$connection) {
            throw new \RuntimeException('No active SMS connection found');
        }

        // Check opt-out
        if ($this->smsRepository->isOptedOut($toPhoneNumber, $connectionId)) {
            throw new \RuntimeException('Recipient has opted out of SMS messages');
        }

        // Check usage limits
        $usage = $this->smsRepository->getConnectionUsage($connectionId);
        if ($usage['daily_remaining'] <= 0) {
            throw new \RuntimeException('Daily SMS limit reached');
        }

        // Send via provider
        $result = $this->sendViaProvider($connection, $toPhoneNumber, $content);

        if (!$result['success']) {
            throw new \RuntimeException($result['error']);
        }

        // Track usage
        $this->trackUsage(self::USAGE_METRIC_MESSAGES);

        // Store message
        return $this->smsRepository->createMessage([
            'connection_id' => $connectionId,
            'direction' => 'outbound',
            'from_number' => $connection['phone_number'],
            'to_number' => $toPhoneNumber,
            'content' => $content,
            'provider_message_id' => $result['message_id'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
            'segment_count' => $this->calculateSegments($content),
            'module_api_name' => $moduleApiName,
            'module_record_id' => $recordId,
        ]);
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(
        string $toPhoneNumber,
        int $templateId,
        array $mergeData = [],
        int $connectionId = null,
        ?string $moduleApiName = null,
        ?int $recordId = null
    ): array {
        $template = $this->smsRepository->findTemplateById($templateId);

        if (!$template) {
            throw new \RuntimeException("Template not found");
        }

        $content = $this->renderTemplate($template['content'], $mergeData);

        if (!$connectionId) {
            $connections = $this->smsRepository->listConnections(true);
            $connectionId = $connections[0]['id'] ?? null;

            if (!$connectionId) {
                throw new \RuntimeException('No active SMS connection available');
            }
        }

        $message = $this->sendSms($toPhoneNumber, $content, $connectionId, $moduleApiName, $recordId);

        // Track template usage
        $this->smsRepository->incrementTemplateUsage($templateId);

        return $message;
    }

    /**
     * Handle incoming SMS webhook.
     */
    public function handleIncomingMessage(array $webhookData): ?array
    {
        $fromNumber = $webhookData['From'] ?? $webhookData['from'] ?? null;
        $toNumber = $webhookData['To'] ?? $webhookData['to'] ?? null;
        $content = $webhookData['Body'] ?? $webhookData['body'] ?? '';

        if (!$fromNumber || !$toNumber) {
            return null;
        }

        // Check for opt-out keywords
        $optOutKeywords = ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];
        if (in_array(strtoupper(trim($content)), $optOutKeywords)) {
            $connection = $this->smsRepository->findConnectionByPhoneNumber($toNumber);
            $this->smsRepository->recordOptOut($fromNumber, $connection['id'] ?? null, 'STOP keyword received');
        }

        // Find connection
        $connection = $this->smsRepository->findConnectionByPhoneNumber($toNumber);

        // Store message
        return $this->smsRepository->createMessage([
            'connection_id' => $connection['id'] ?? null,
            'direction' => 'inbound',
            'from_number' => $fromNumber,
            'to_number' => $toNumber,
            'content' => $content,
            'provider_message_id' => $webhookData['MessageSid'] ?? $webhookData['message_sid'] ?? null,
            'status' => 'received',
            'received_at' => now(),
        ]);
    }

    /**
     * Handle status update webhook.
     */
    public function handleStatusUpdate(array $webhookData): void
    {
        $messageId = $webhookData['MessageSid'] ?? $webhookData['message_sid'] ?? null;
        $status = $webhookData['MessageStatus'] ?? $webhookData['status'] ?? null;

        if (!$messageId || !$status) {
            return;
        }

        $message = $this->smsRepository->findByProviderMessageId($messageId);

        if ($message) {
            $this->smsRepository->updateMessage($message['id'], [
                'status' => $this->mapProviderStatus($status),
            ]);
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
        return $this->smsRepository->listTemplates($filters);
    }

    /**
     * Get a template.
     */
    public function getTemplate(int $id): ?array
    {
        return $this->smsRepository->findTemplateById($id);
    }

    /**
     * Create a template.
     */
    public function createTemplate(array $data): array
    {
        return $this->smsRepository->createTemplate($data);
    }

    /**
     * Update a template.
     */
    public function updateTemplate(int $id, array $data): array
    {
        return $this->smsRepository->updateTemplate($id, $data);
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate(int $id): bool
    {
        return $this->smsRepository->deleteTemplate($id);
    }

    // =========================================================================
    // OPT-OUT
    // =========================================================================

    /**
     * Check opt-out status.
     */
    public function isOptedOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        return $this->smsRepository->isOptedOut($phoneNumber, $connectionId);
    }

    /**
     * Record opt-out.
     */
    public function recordOptOut(string $phoneNumber, ?int $connectionId = null, ?string $reason = null): array
    {
        return $this->smsRepository->recordOptOut($phoneNumber, $connectionId, $reason);
    }

    /**
     * Remove opt-out.
     */
    public function removeOptOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        return $this->smsRepository->removeOptOut($phoneNumber, $connectionId);
    }

    /**
     * List opt-outs.
     */
    public function listOptOuts(?int $connectionId = null, int $perPage = 50): array
    {
        return $this->smsRepository->listOptOuts($connectionId, $perPage);
    }

    // =========================================================================
    // CAMPAIGNS
    // =========================================================================

    /**
     * List campaigns.
     */
    public function listCampaigns(array $filters = [], int $perPage = 20): array
    {
        return $this->smsRepository->listCampaigns($filters, $perPage);
    }

    /**
     * Get a campaign.
     */
    public function getCampaign(int $id): ?array
    {
        return $this->smsRepository->findCampaignById($id);
    }

    /**
     * Create a campaign.
     */
    public function createCampaign(array $data): array
    {
        $this->trackUsage(self::USAGE_METRIC_CAMPAIGNS);

        return $this->smsRepository->createCampaign($data);
    }

    /**
     * Get campaign statistics.
     */
    public function getCampaignStats(int $campaignId): array
    {
        return $this->smsRepository->getCampaignStats($campaignId);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    /**
     * Get SMS statistics.
     */
    public function getStats(?int $connectionId = null, ?string $period = 'today'): array
    {
        return $this->smsRepository->getMessageStats($connectionId, $period);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Send SMS via provider (Twilio).
     */
    private function sendViaProvider(array $connection, string $to, string $content): array
    {
        if ($connection['provider'] === 'twilio') {
            return $this->sendViaTwilio($connection, $to, $content);
        }

        return ['success' => false, 'error' => 'Unsupported provider'];
    }

    /**
     * Send via Twilio.
     */
    private function sendViaTwilio(array $connection, string $to, string $content): array
    {
        try {
            $accountSid = decrypt($connection['account_sid']);
            $authToken = decrypt($connection['auth_token']);

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $connection['phone_number'],
                    'To' => $to,
                    'Body' => $content,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('sid'),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message', 'Failed to send SMS'),
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS send failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test Twilio connection.
     */
    private function testTwilioConnection(array $connection): array
    {
        try {
            $accountSid = decrypt($connection['account_sid']);
            $authToken = decrypt($connection['auth_token']);

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => 'Invalid credentials'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calculate SMS segments.
     */
    private function calculateSegments(string $content): int
    {
        $length = strlen($content);
        $isUnicode = preg_match('/[^\x00-\x7F]/', $content);

        if ($isUnicode) {
            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    /**
     * Render template with merge data.
     */
    private function renderTemplate(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }

        return preg_replace('/\{\{\w+\}\}/', '', $content);
    }

    /**
     * Map provider status to our status.
     */
    private function mapProviderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'queued', 'accepted' => 'pending',
            'sending' => 'sending',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed', 'undelivered' => 'failed',
            default => $status,
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
            Log::warning('Failed to track SMS plugin usage', [
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
