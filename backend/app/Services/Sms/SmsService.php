<?php

namespace App\Services\Sms;

use App\Domain\Sms\Entities\SmsConnection;
use App\Domain\Sms\Entities\SmsCampaign;
use App\Domain\Sms\Entities\SmsMessage;
use App\Domain\Sms\Entities\SmsTemplate;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(
        protected SmsMessageRepositoryInterface $messageRepository,
    ) {}

    /**
     * Send a single SMS message
     */
    public function sendMessage(
        SmsConnection $connection,
        string $to,
        string $content,
        ?SmsTemplate $template = null,
        ?array $mergeData = null,
        ?int $recordId = null,
        ?string $moduleApiName = null,
        ?int $campaignId = null
    ): SmsMessage {
        // Normalize phone number
        $to = $this->normalizePhone($to);

        // Check opt-out status (would use opt-out repository)
        // Simplified for now

        // Check rate limits
        if (!$connection->isWithinDailyLimit() || !$connection->isWithinMonthlyLimit()) {
            $message = SmsMessage::createFailed(
                connectionId: $connection->getId(),
                fromNumber: $connection->getPhoneNumber(),
                toNumber: $to,
                content: $content,
                errorCode: 'RATE_LIMIT',
                errorMessage: 'Daily or monthly message limit exceeded',
                templateId: $template?->getId(),
                moduleRecordId: $recordId,
                moduleApiName: $moduleApiName,
                campaignId: $campaignId,
                sentBy: auth()->id()
            );

            return $this->messageRepository->save($message);
        }

        // Render template if provided
        if ($template && $mergeData) {
            $content = $template->render($mergeData);
        }

        // Create message record
        $message = SmsMessage::createPending(
            connectionId: $connection->getId(),
            fromNumber: $connection->getPhoneNumber(),
            toNumber: $to,
            content: $content,
            segmentCount: SmsTemplate::calculateSegments($content),
            templateId: $template?->getId(),
            moduleRecordId: $recordId,
            moduleApiName: $moduleApiName,
            campaignId: $campaignId,
            sentBy: auth()->id()
        );

        // Send via provider
        $result = $this->sendViaProvider($connection, $to, $content);

        if ($result['success']) {
            $message->markAsSent($result['message_sid']);
            $message->updateCost($result['price'] ?? null);
            $message->updateSegmentCount($result['segments'] ?? 1);
        } else {
            $message->markAsFailed($result['error_code'], $result['error_message']);
        }

        $message = $this->messageRepository->save($message);

        // Update connection last used
        $connection->updateLastUsed(now());

        return $message;
    }

    /**
     * Send via the appropriate provider
     */
    protected function sendViaProvider(SmsConnection $connection, string $to, string $content): array
    {
        return match ($connection->getProvider()) {
            'twilio' => (new TwilioService($connection))->sendMessage($to, $content),
            'vonage' => $this->sendViaNexmo($connection, $to, $content),
            'messagebird' => $this->sendViaMessageBird($connection, $to, $content),
            'plivo' => $this->sendViaPlivo($connection, $to, $content),
            default => [
                'success' => false,
                'error_code' => 'UNSUPPORTED_PROVIDER',
                'error_message' => "Provider {$connection->getProvider()} is not supported",
            ],
        };
    }

    /**
     * Send via Vonage (Nexmo)
     */
    protected function sendViaNexmo(SmsConnection $connection, string $to, string $content): array
    {
        // Implementation for Vonage
        return [
            'success' => false,
            'error_code' => 'NOT_IMPLEMENTED',
            'error_message' => 'Vonage provider not yet implemented',
        ];
    }

    /**
     * Send via MessageBird
     */
    protected function sendViaMessageBird(SmsConnection $connection, string $to, string $content): array
    {
        // Implementation for MessageBird
        return [
            'success' => false,
            'error_code' => 'NOT_IMPLEMENTED',
            'error_message' => 'MessageBird provider not yet implemented',
        ];
    }

    /**
     * Send via Plivo
     */
    protected function sendViaPlivo(SmsConnection $connection, string $to, string $content): array
    {
        // Implementation for Plivo
        return [
            'success' => false,
            'error_code' => 'NOT_IMPLEMENTED',
            'error_message' => 'Plivo provider not yet implemented',
        ];
    }

    /**
     * Process an incoming SMS message
     */
    public function processIncoming(
        SmsConnection $connection,
        string $from,
        string $to,
        string $content,
        ?string $providerMessageId = null
    ): SmsMessage {
        // Check for opt-out keywords
        $optOutKeywords = ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];
        $upperContent = strtoupper(trim($content));

        if (in_array($upperContent, $optOutKeywords)) {
            // Would use opt-out repository here
            Log::info('SMS opt-out processed', [
                'phone' => $from,
                'connection_id' => $connection->getId(),
            ]);
        }

        // Check for opt-in keywords
        $optInKeywords = ['START', 'YES', 'SUBSCRIBE', 'UNSTOP'];
        if (in_array($upperContent, $optInKeywords)) {
            // Would use opt-out repository here
            Log::info('SMS opt-in processed', [
                'phone' => $from,
                'connection_id' => $connection->getId(),
            ]);
        }

        // Create incoming message record
        $message = SmsMessage::createInbound(
            connectionId: $connection->getId(),
            fromNumber: $from,
            toNumber: $to,
            content: $content,
            providerMessageId: $providerMessageId,
            deliveredAt: now()
        );

        $message = $this->messageRepository->save($message);

        // Try to match with a CRM record
        $this->linkToRecord($message);

        // Check if this is a campaign reply
        $this->checkCampaignReply($message);

        return $message;
    }

    /**
     * Link message to a CRM record by phone number
     */
    protected function linkToRecord(SmsMessage $message): void
    {
        // Search for matching records by phone
        $phone = $message->direction === 'inbound' ? $message->from_number : $message->to_number;

        // This would search across modules for matching phone numbers
        // For now, we'll leave this as a placeholder
        // You could implement this based on your module structure
    }

    /**
     * Check if message is a reply to a campaign
     */
    protected function checkCampaignReply(SmsMessage $message): void
    {
        // Find recent outbound message to this phone
        $recentOutbound = SmsMessage::where('to_number', $message->from_number)
            ->where('direction', 'outbound')
            ->whereNotNull('campaign_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($recentOutbound && $recentOutbound->campaign_id) {
            $message->update(['campaign_id' => $recentOutbound->campaign_id]);

            // Increment campaign reply count
            SmsCampaign::find($recentOutbound->campaign_id)?->incrementReplies();
        }
    }

    /**
     * Update message status from webhook
     */
    public function updateMessageStatus(string $providerMessageId, string $status, ?string $errorCode = null): void
    {
        $message = $this->messageRepository->findByProviderMessageId($providerMessageId);

        if (!$message) {
            Log::warning('SMS status update for unknown message', [
                'provider_message_id' => $providerMessageId,
                'status' => $status,
            ]);
            return;
        }

        $statusMap = [
            // Twilio statuses
            'queued' => 'queued',
            'sending' => 'queued',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'undelivered' => 'undelivered',
            'failed' => 'failed',
        ];

        $mappedStatus = $statusMap[$status] ?? $status;

        $message->updateStatus($mappedStatus, $errorCode);
        if ($mappedStatus === 'delivered') {
            $message->markAsDelivered(now());
        }

        $this->messageRepository->save($message);

        // Update campaign stats if applicable (would use campaign repository)
    }

    /**
     * Get conversation history for a phone number
     */
    public function getConversation(string $phone, int $connectionId, int $limit = 50): array
    {
        return $this->messageRepository->findConversationByPhone($connectionId, $phone, $limit);
    }

    /**
     * Normalize phone number
     */
    protected function normalizePhone(string $phone): string
    {
        // Normalize phone number (remove non-numeric characters, add country code if missing)
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Add + if not present and doesn't start with country code
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
