<?php

namespace App\Services\Sms;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SmsCampaignService
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Create a new campaign
     */
    public function create(array $data): SmsCampaign
    {
        return DB::table('sms_campaigns')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'connection_id' => $data['connection_id'],
            'template_id' => $data['template_id'] ?? null,
            'message_content' => $data['message_content'] ?? null,
            'status' => 'draft',
            'target_module' => $data['target_module'] ?? null,
            'target_filters' => $data['target_filters'] ?? null,
            'phone_field' => $data['phone_field'] ?? 'phone',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Update campaign
     */
    public function update(SmsCampaign $campaign, array $data): SmsCampaign
    {
        if (!$campaign->canEdit()) {
            throw new \Exception('Campaign cannot be edited in current status');
        }

        $campaign->update($data);
        return $campaign->refresh();
    }

    /**
     * Schedule campaign for sending
     */
    public function schedule(SmsCampaign $campaign, \DateTime $scheduledAt): SmsCampaign
    {
        if (!$campaign->isDraft()) {
            throw new \Exception('Only draft campaigns can be scheduled');
        }

        // Calculate total recipients
        $recipients = $this->getRecipients($campaign);
        $campaign->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'total_recipients' => $recipients->count(),
        ]);

        return $campaign->refresh();
    }

    /**
     * Send campaign immediately
     */
    public function sendNow(SmsCampaign $campaign): SmsCampaign
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            throw new \Exception('Campaign cannot be sent in current status');
        }

        $recipients = $this->getRecipients($campaign);
        $campaign->update([
            'total_recipients' => $recipients->count(),
        ]);

        $campaign->start();

        // Process in batches
        $this->processCampaign($campaign, $recipients);

        return $campaign->refresh();
    }

    /**
     * Get recipients for campaign
     */
    public function getRecipients(SmsCampaign $campaign): Collection
    {
        if (!$campaign->target_module) {
            return collect();
        }

        $query = DB::table('module_records')->where('module_api_name', $campaign->target_module);

        // Apply filters
        if ($campaign->target_filters) {
            foreach ($campaign->target_filters as $filter) {
                $field = $filter['field'] ?? null;
                $operator = $filter['operator'] ?? '=';
                $value = $filter['value'] ?? null;

                if ($field && $value !== null) {
                    $query->whereJsonContains("data->{$field}", $value);
                }
            }
        }

        return $query->get()->filter(function ($record) use ($campaign) {
            $phone = $record->data[$campaign->phone_field] ?? null;
            return !empty($phone);
        });
    }

    /**
     * Process campaign sending
     */
    protected function processCampaign(SmsCampaign $campaign, Collection $recipients): void
    {
        $connection = $campaign->connection;
        $template = $campaign->template;

        foreach ($recipients as $record) {
            if ($campaign->status !== 'sending') {
                break; // Campaign was paused or cancelled
            }

            $phone = $record->data[$campaign->phone_field] ?? null;
            if (!$phone) {
                continue;
            }

            // Build merge data from record
            $mergeData = $this->buildMergeData($record);

            // Get message content
            $content = $template
                ? $template->render($mergeData)
                : $this->renderContent($campaign->message_content, $mergeData);

            try {
                $message = $this->smsService->sendMessage(
                    connection: $connection,
                    to: $phone,
                    content: $content,
                    template: $template,
                    mergeData: $mergeData,
                    recordId: $record->id,
                    moduleApiName: $record->module_api_name,
                    campaignId: $campaign->id
                );

                if ($message->status === 'failed') {
                    if ($message->error_code === 'OPT_OUT') {
                        $campaign->incrementOptedOut();
                    } else {
                        $campaign->incrementFailed();
                    }
                } else {
                    $campaign->incrementSent();
                }
            } catch (\Exception $e) {
                Log::error('Campaign message send failed', [
                    'campaign_id' => $campaign->id,
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
                $campaign->incrementFailed();
            }

            // Rate limiting - pause between messages
            usleep(100000); // 100ms delay
        }

        $campaign->complete();
    }

    /**
     * Build merge data from record
     */
    protected function buildMergeData(ModuleRecord $record): array
    {
        $data = $record->data ?? [];

        // Add standard fields
        $data['record_id'] = $record->id;
        $data['module'] = $record->module_api_name;

        // Add owner info if available
        if ($record->owner) {
            $data['owner_name'] = $record->owner->name;
            $data['owner_email'] = $record->owner->email;
        }

        return $data;
    }

    /**
     * Render message content with merge fields
     */
    protected function renderContent(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
        }

        // Remove unmatched merge fields
        $content = preg_replace('/\{\{\w+\}\}/', '', $content);

        return trim($content);
    }

    /**
     * Get campaign statistics
     */
    public function getStats(SmsCampaign $campaign): array
    {
        return [
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'failed_count' => $campaign->failed_count,
            'opted_out_count' => $campaign->opted_out_count,
            'reply_count' => $campaign->reply_count,
            'delivery_rate' => $campaign->getDeliveryRate(),
            'failure_rate' => $campaign->getFailureRate(),
            'progress' => $campaign->getProgress(),
        ];
    }

    /**
     * Preview campaign message
     */
    public function preview(SmsCampaign $campaign, ?array $sampleData = null): array
    {
        $template = $campaign->template;
        $content = $template ? $template->content : ($campaign->message_content ?? '');

        // Use sample data or generate placeholder
        $mergeData = $sampleData ?? [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'company' => 'Acme Inc',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
        ];

        $rendered = $template
            ? $template->render($mergeData)
            : $this->renderContent($content, $mergeData);

        return [
            'original' => $content,
            'rendered' => $rendered,
            'character_count' => strlen($rendered),
            'segment_count' => SmsTemplate::calculateSegments($rendered),
            'merge_fields' => SmsTemplate::extractMergeFields($content),
        ];
    }
}
