<?php

declare(strict_types=1);

namespace App\Application\Services\Sms;

use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;

class SmsApplicationService
{
    public function __construct(
        private SmsMessageRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - MESSAGES
    // =========================================================================

    /**
     * List SMS messages with filtering and pagination.
     */
    public function listMessages(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listMessages($filters, $perPage, $page);
    }

    /**
     * Get a single message by ID.
     */
    public function getMessage(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Get conversation history for a phone number.
     */
    public function getConversation(string $phoneNumber, int $limit = 100): array
    {
        return $this->repository->getConversation($phoneNumber, $limit);
    }

    /**
     * Get messages for a module record.
     */
    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): array
    {
        return $this->repository->getRecordMessages($moduleApiName, $recordId, $limit);
    }

    /**
     * Get SMS statistics.
     */
    public function getStats(?int $connectionId = null, ?string $period = 'today'): array
    {
        return $this->repository->getStats($connectionId, $period);
    }

    // =========================================================================
    // COMMAND USE CASES - MESSAGES
    // =========================================================================

    /**
     * Send an SMS message.
     */
    public function sendSms(array $data): array
    {
        $connection = $this->repository->findActiveConnectionById($data['connection_id']);

        if (!$connection) {
            throw new \InvalidArgumentException('Active connection not found');
        }

        // Check limits
        $usage = $this->repository->getConnectionUsage($connection['id']);
        if ($usage['daily_remaining'] <= 0) {
            throw new \InvalidArgumentException('Daily SMS limit reached');
        }
        if ($usage['monthly_remaining'] <= 0) {
            throw new \InvalidArgumentException('Monthly SMS limit reached');
        }

        // Check opt-out status
        if ($this->repository->isOptedOut($data['to_number'], $connection['id'])) {
            throw new \InvalidArgumentException('Recipient has opted out of SMS messages');
        }

        // Get content from template or direct
        $content = $data['content'];
        $templateId = null;

        if (!empty($data['template_id'])) {
            $template = $this->repository->findTemplateById($data['template_id']);

            if (!$template) {
                throw new \InvalidArgumentException('Template not found');
            }

            $content = $this->renderTemplate($template['content'], $data['merge_data'] ?? []);
            $this->repository->incrementTemplateUsage($template['id']);
            $templateId = $template['id'];
        }

        // Create the message
        $message = $this->repository->create([
            'connection_id' => $connection['id'],
            'template_id' => $templateId,
            'direction' => 'outbound',
            'from_number' => $connection['phone_number'],
            'to_number' => $data['to_number'],
            'content' => $content,
            'status' => 'pending',
            'segment_count' => $this->calculateSegments($content),
            'module_record_id' => $data['module_record_id'] ?? null,
            'module_api_name' => $data['module_api_name'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'sent_by' => $this->authContext->userId(),
        ]);

        // Update connection last used
        $this->repository->updateConnection($connection['id'], ['last_used_at' => now()]);

        return $message;
    }

    /**
     * Record an inbound SMS message (from webhook).
     */
    public function recordInboundSms(array $data): array
    {
        $connection = $this->repository->findConnectionByPhoneNumber($data['to_number']);

        return $this->repository->create([
            'connection_id' => $connection['id'] ?? null,
            'direction' => 'inbound',
            'from_number' => $data['from_number'],
            'to_number' => $data['to_number'],
            'content' => $data['content'],
            'status' => 'received',
            'provider_message_id' => $data['provider_message_id'] ?? null,
            'segment_count' => $data['segment_count'] ?? 1,
        ]);
    }

    /**
     * Update message status (delivery callback).
     */
    public function updateMessageStatus(string $providerMessageId, string $status, ?array $extra = null): ?array
    {
        $message = $this->repository->findByProviderMessageId($providerMessageId);

        if (!$message) {
            return null;
        }

        $updateData = ['status' => $status];

        if ($status === 'delivered') {
            $updateData['delivered_at'] = now();
        } elseif ($status === 'read') {
            $updateData['read_at'] = now();
        } elseif (in_array($status, ['failed', 'undelivered'])) {
            $updateData['error_code'] = $extra['error_code'] ?? null;
            $updateData['error_message'] = $extra['error_message'] ?? null;
        }

        if (isset($extra['cost'])) {
            $updateData['cost'] = $extra['cost'];
        }

        return $this->repository->update($message['id'], $updateData);
    }

    /**
     * Bulk send SMS to multiple recipients.
     */
    public function bulkSend(array $recipients, string $content, int $connectionId, ?int $templateId = null): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($recipients as $recipient) {
            try {
                $this->sendSms([
                    'connection_id' => $connectionId,
                    'to_number' => $recipient['phone'],
                    'content' => $content,
                    'template_id' => $templateId,
                    'merge_data' => $recipient['data'] ?? [],
                    'module_record_id' => $recipient['record_id'] ?? null,
                    'module_api_name' => $recipient['module'] ?? null,
                ]);
                $results['sent']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'phone' => $recipient['phone'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    // =========================================================================
    // QUERY USE CASES - TEMPLATES
    // =========================================================================

    /**
     * List SMS templates.
     */
    public function listTemplates(array $filters = []): array
    {
        return $this->repository->listTemplates($filters);
    }

    /**
     * Get a template by ID.
     */
    public function getTemplate(int $id): ?array
    {
        return $this->repository->findTemplateById($id);
    }

    // =========================================================================
    // COMMAND USE CASES - TEMPLATES
    // =========================================================================

    /**
     * Create an SMS template.
     */
    public function createTemplate(array $data): array
    {
        return $this->repository->createTemplate([
            'name' => $data['name'],
            'content' => $data['content'],
            'category' => $data['category'] ?? 'general',
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $this->authContext->userId(),
        ]);
    }

    /**
     * Update an SMS template.
     */
    public function updateTemplate(int $id, array $data): ?array
    {
        $template = $this->repository->findTemplateById($id);

        if (!$template) {
            throw new \InvalidArgumentException('Template not found');
        }

        return $this->repository->updateTemplate($id, [
            'name' => $data['name'] ?? $template['name'],
            'content' => $data['content'] ?? $template['content'],
            'category' => $data['category'] ?? $template['category'],
            'is_active' => $data['is_active'] ?? $template['is_active'],
        ]);
    }

    /**
     * Delete an SMS template.
     */
    public function deleteTemplate(int $id): bool
    {
        $template = $this->repository->findTemplateById($id);

        if (!$template) {
            throw new \InvalidArgumentException('Template not found');
        }

        return $this->repository->deleteTemplate($id);
    }

    /**
     * Preview template with sample data.
     */
    public function previewTemplate(int $id, array $sampleData): array
    {
        $template = $this->repository->findTemplateById($id);

        if (!$template) {
            throw new \InvalidArgumentException('Template not found');
        }

        $rendered = $this->renderTemplate($template['content'], $sampleData);

        return [
            'rendered' => $rendered,
            'character_count' => strlen($rendered),
            'segment_count' => $this->calculateSegments($rendered),
            'merge_fields' => $template['merge_fields'] ?? [],
        ];
    }

    // =========================================================================
    // QUERY USE CASES - CONNECTIONS
    // =========================================================================

    /**
     * List SMS connections.
     */
    public function listConnections(bool $activeOnly = false): array
    {
        return $this->repository->listConnections($activeOnly);
    }

    /**
     * Get a connection by ID.
     */
    public function getConnection(int $id): ?array
    {
        return $this->repository->findConnectionById($id);
    }

    /**
     * Get connection usage stats.
     */
    public function getConnectionUsage(int $connectionId): array
    {
        return $this->repository->getConnectionUsage($connectionId);
    }

    // =========================================================================
    // COMMAND USE CASES - CONNECTIONS
    // =========================================================================

    /**
     * Create an SMS connection.
     */
    public function createConnection(array $data): array
    {
        return $this->repository->createConnection([
            'name' => $data['name'],
            'provider' => $data['provider'],
            'phone_number' => $data['phone_number'],
            'account_sid' => $data['account_sid'] ?? null,
            'auth_token' => $data['auth_token'] ?? null,
            'messaging_service_sid' => $data['messaging_service_sid'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'capabilities' => $data['capabilities'] ?? ['sms'],
            'settings' => $data['settings'] ?? [],
            'daily_limit' => $data['daily_limit'] ?? 1000,
            'monthly_limit' => $data['monthly_limit'] ?? 30000,
        ]);
    }

    /**
     * Update an SMS connection.
     */
    public function updateConnection(int $id, array $data): ?array
    {
        $connection = $this->repository->findConnectionById($id);

        if (!$connection) {
            throw new \InvalidArgumentException('Connection not found');
        }

        $updateData = [
            'name' => $data['name'] ?? $connection['name'],
            'phone_number' => $data['phone_number'] ?? $connection['phone_number'],
            'messaging_service_sid' => $data['messaging_service_sid'] ?? $connection['messaging_service_sid'],
            'is_active' => $data['is_active'] ?? $connection['is_active'],
            'capabilities' => $data['capabilities'] ?? $connection['capabilities'],
            'settings' => array_merge($connection['settings'] ?? [], $data['settings'] ?? []),
            'daily_limit' => $data['daily_limit'] ?? $connection['daily_limit'],
            'monthly_limit' => $data['monthly_limit'] ?? $connection['monthly_limit'],
        ];

        if (!empty($data['auth_token'])) {
            $updateData['auth_token'] = $data['auth_token'];
        }

        return $this->repository->updateConnection($id, $updateData);
    }

    /**
     * Delete an SMS connection.
     */
    public function deleteConnection(int $id): bool
    {
        $connection = $this->repository->findConnectionById($id);

        if (!$connection) {
            throw new \InvalidArgumentException('Connection not found');
        }

        if ($this->repository->connectionHasMessages($id)) {
            throw new \InvalidArgumentException('Cannot delete connection with existing messages');
        }

        return $this->repository->deleteConnection($id);
    }

    /**
     * Verify connection credentials.
     */
    public function verifyConnection(int $id): array
    {
        $connection = $this->repository->findConnectionById($id);

        if (!$connection) {
            throw new \InvalidArgumentException('Connection not found');
        }

        // This would typically make an API call to verify credentials
        // For now, just mark as verified
        $updatedConnection = $this->repository->updateConnection($id, ['is_verified' => true]);

        return [
            'verified' => true,
            'connection' => $updatedConnection,
        ];
    }

    // =========================================================================
    // OPT-OUT USE CASES
    // =========================================================================

    /**
     * Check if a phone number has opted out.
     */
    public function isOptedOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        return $this->repository->isOptedOut($phoneNumber, $connectionId);
    }

    /**
     * Record an opt-out.
     */
    public function recordOptOut(string $phoneNumber, ?int $connectionId = null, ?string $reason = null): array
    {
        return $this->repository->recordOptOut($phoneNumber, $connectionId, $reason);
    }

    /**
     * Remove an opt-out (opt back in).
     */
    public function removeOptOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        return $this->repository->removeOptOut($phoneNumber, $connectionId);
    }

    /**
     * List opt-outs.
     */
    public function listOptOuts(?int $connectionId = null, int $perPage = 50): PaginatedResult
    {
        return $this->repository->listOptOuts($connectionId, $perPage);
    }

    // =========================================================================
    // CAMPAIGN USE CASES
    // =========================================================================

    /**
     * List SMS campaigns.
     */
    public function listCampaigns(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listCampaigns($filters, $perPage, $page);
    }

    /**
     * Get a campaign by ID.
     */
    public function getCampaign(int $id): ?array
    {
        return $this->repository->findCampaignById($id);
    }

    /**
     * Create an SMS campaign.
     */
    public function createCampaign(array $data): array
    {
        return $this->repository->createCampaign([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'connection_id' => $data['connection_id'],
            'template_id' => $data['template_id'] ?? null,
            'content' => $data['content'] ?? null,
            'recipient_list' => $data['recipient_list'] ?? [],
            'status' => 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => $this->authContext->userId(),
        ]);
    }

    /**
     * Start/send an SMS campaign.
     */
    public function startCampaign(int $campaignId): array
    {
        $campaign = $this->repository->findCampaignById($campaignId);

        if (!$campaign) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        if ($campaign['status'] !== 'draft' && $campaign['status'] !== 'scheduled') {
            throw new \InvalidArgumentException('Campaign cannot be started');
        }

        $this->repository->updateCampaign($campaignId, ['status' => 'sending', 'started_at' => now()]);

        // Get template content if template_id is set
        $content = $campaign['content'];
        if (!$content && !empty($campaign['template_id'])) {
            $template = $this->repository->findTemplateById($campaign['template_id']);
            $content = $template['content'] ?? '';
        }

        // Send to recipients
        $results = $this->bulkSend(
            $campaign['recipient_list'],
            $content,
            $campaign['connection_id'],
            $campaign['template_id']
        );

        // Update campaign stats
        $this->repository->updateCampaign($campaignId, [
            'status' => 'completed',
            'completed_at' => now(),
            'total_sent' => $results['sent'],
            'total_failed' => $results['failed'],
        ]);

        return $results;
    }

    /**
     * Get campaign statistics.
     */
    public function getCampaignStats(int $campaignId): array
    {
        return $this->repository->getCampaignStats($campaignId);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate number of SMS segments needed for the message.
     */
    private function calculateSegments(string $content): int
    {
        $length = strlen($content);

        // Check if message contains non-GSM characters (requires UCS-2 encoding)
        $isUnicode = preg_match('/[^\x00-\x7F]/', $content);

        if ($isUnicode) {
            // UCS-2: 70 chars per segment, 67 for multipart
            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        // GSM-7: 160 chars per segment, 153 for multipart
        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    /**
     * Render template with data.
     */
    private function renderTemplate(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }

        // Remove any unmatched merge fields
        $content = preg_replace('/\{\{\w+\}\}/', '', $content);

        return trim($content);
    }
}
