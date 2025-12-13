<?php

namespace App\Services\Sms;

use App\Models\SmsConnection;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmsService
{
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

        // Check opt-out status
        if (SmsOptOut::isOptedOut($to, $template?->category ?? 'all')) {
            $message = SmsMessage::create([
                'connection_id' => $connection->id,
                'template_id' => $template?->id,
                'direction' => 'outbound',
                'from_number' => $connection->phone_number,
                'to_number' => $to,
                'content' => $content,
                'status' => 'failed',
                'error_code' => 'OPT_OUT',
                'error_message' => 'Recipient has opted out of SMS messages',
                'module_record_id' => $recordId,
                'module_api_name' => $moduleApiName,
                'campaign_id' => $campaignId,
                'sent_by' => auth()->id(),
            ]);

            return $message;
        }

        // Check rate limits
        if (!$connection->isWithinDailyLimit() || !$connection->isWithinMonthlyLimit()) {
            $message = SmsMessage::create([
                'connection_id' => $connection->id,
                'template_id' => $template?->id,
                'direction' => 'outbound',
                'from_number' => $connection->phone_number,
                'to_number' => $to,
                'content' => $content,
                'status' => 'failed',
                'error_code' => 'RATE_LIMIT',
                'error_message' => 'Daily or monthly message limit exceeded',
                'module_record_id' => $recordId,
                'module_api_name' => $moduleApiName,
                'campaign_id' => $campaignId,
                'sent_by' => auth()->id(),
            ]);

            return $message;
        }

        // Render template if provided
        if ($template && $mergeData) {
            $content = $template->render($mergeData);
            $template->incrementUsage();
        }

        // Create message record
        $message = SmsMessage::create([
            'connection_id' => $connection->id,
            'template_id' => $template?->id,
            'direction' => 'outbound',
            'from_number' => $connection->phone_number,
            'to_number' => $to,
            'content' => $content,
            'status' => 'pending',
            'segment_count' => SmsTemplate::calculateSegments($content),
            'module_record_id' => $recordId,
            'module_api_name' => $moduleApiName,
            'campaign_id' => $campaignId,
            'sent_by' => auth()->id(),
        ]);

        // Send via provider
        $result = $this->sendViaProvider($connection, $to, $content);

        if ($result['success']) {
            $message->markAsSent($result['message_sid']);
            $message->update([
                'cost' => $result['price'] ?? null,
                'segment_count' => $result['segments'] ?? 1,
            ]);
        } else {
            $message->markAsFailed($result['error_code'], $result['error_message']);
        }

        // Update connection last used
        $connection->update(['last_used_at' => now()]);

        return $message;
    }

    /**
     * Send via the appropriate provider
     */
    protected function sendViaProvider(SmsConnection $connection, string $to, string $content): array
    {
        return match ($connection->provider) {
            'twilio' => (new TwilioService($connection))->sendMessage($to, $content),
            'vonage' => $this->sendViaNexmo($connection, $to, $content),
            'messagebird' => $this->sendViaMessageBird($connection, $to, $content),
            'plivo' => $this->sendViaPlivo($connection, $to, $content),
            default => [
                'success' => false,
                'error_code' => 'UNSUPPORTED_PROVIDER',
                'error_message' => "Provider {$connection->provider} is not supported",
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
            SmsOptOut::optOut($from, 'all', 'STOP keyword received', $connection->id);

            Log::info('SMS opt-out processed', [
                'phone' => $from,
                'connection_id' => $connection->id,
            ]);
        }

        // Check for opt-in keywords
        $optInKeywords = ['START', 'YES', 'SUBSCRIBE', 'UNSTOP'];
        if (in_array($upperContent, $optInKeywords)) {
            SmsOptOut::optIn($from, 'all');

            Log::info('SMS opt-in processed', [
                'phone' => $from,
                'connection_id' => $connection->id,
            ]);
        }

        // Create incoming message record
        $message = SmsMessage::create([
            'connection_id' => $connection->id,
            'direction' => 'inbound',
            'from_number' => $from,
            'to_number' => $to,
            'content' => $content,
            'status' => 'delivered',
            'provider_message_id' => $providerMessageId,
            'delivered_at' => now(),
        ]);

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
        $message = SmsMessage::where('provider_message_id', $providerMessageId)->first();

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

        $message->update([
            'status' => $mappedStatus,
            'error_code' => $errorCode,
            'delivered_at' => $mappedStatus === 'delivered' ? now() : $message->delivered_at,
        ]);

        // Update campaign stats if applicable
        if ($message->campaign_id) {
            $campaign = SmsCampaign::find($message->campaign_id);
            if ($campaign) {
                if ($mappedStatus === 'delivered') {
                    $campaign->incrementDelivered();
                } elseif (in_array($mappedStatus, ['failed', 'undelivered'])) {
                    $campaign->incrementFailed();
                }
            }
        }
    }

    /**
     * Get conversation history for a phone number
     */
    public function getConversation(string $phone, int $connectionId, int $limit = 50): array
    {
        return SmsMessage::where('connection_id', $connectionId)
            ->where(function ($q) use ($phone) {
                $q->where('to_number', $phone)
                  ->orWhere('from_number', $phone);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->toArray();
    }

    /**
     * Normalize phone number
     */
    protected function normalizePhone(string $phone): string
    {
        return SmsOptOut::normalizePhone($phone);
    }
}
