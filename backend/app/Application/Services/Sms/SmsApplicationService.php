<?php

declare(strict_types=1);

namespace App\Application\Services\Sms;

use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Models\SmsCampaign;
use App\Models\SmsConnection;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use App\Models\SmsTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SmsApplicationService
{
    public function __construct(
        private SmsMessageRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - MESSAGES
    // =========================================================================

    /**
     * List SMS messages with filtering and pagination.
     */
    public function listMessages(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = SmsMessage::query()
            ->with(['connection:id,name,phone_number', 'sender:id,name', 'template:id,name']);

        // Filter by direction
        if (!empty($filters['direction'])) {
            if ($filters['direction'] === 'inbound') {
                $query->inbound();
            } else {
                $query->outbound();
            }
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Filter by phone number
        if (!empty($filters['phone'])) {
            $query->forPhone($filters['phone']);
        }

        // Filter by connection
        if (!empty($filters['connection_id'])) {
            $query->where('connection_id', $filters['connection_id']);
        }

        // Filter by campaign
        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        // Filter by module record
        if (!empty($filters['module_api_name']) && !empty($filters['module_record_id'])) {
            $query->forRecord($filters['module_api_name'], $filters['module_record_id']);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Search content
        if (!empty($filters['search'])) {
            $query->where('content', 'like', "%{$filters['search']}%");
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single message by ID.
     */
    public function getMessage(int $id): ?SmsMessage
    {
        return SmsMessage::with(['connection', 'sender', 'template', 'campaign', 'moduleRecord'])->find($id);
    }

    /**
     * Get conversation history for a phone number.
     */
    public function getConversation(string $phoneNumber, int $limit = 100): Collection
    {
        return SmsMessage::forPhone($phoneNumber)
            ->with(['sender:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get messages for a module record.
     */
    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): Collection
    {
        return SmsMessage::forRecord($moduleApiName, $recordId)
            ->with(['sender:id,name', 'template:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get SMS statistics.
     */
    public function getStats(?int $connectionId = null, ?string $period = 'today'): array
    {
        $query = SmsMessage::query();

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        $query->where('created_at', '>=', $startDate);

        $total = (clone $query)->count();
        $sent = (clone $query)->outbound()->count();
        $received = (clone $query)->inbound()->count();
        $delivered = (clone $query)->byStatus('delivered')->count();
        $failed = (clone $query)->whereIn('status', ['failed', 'undelivered'])->count();
        $totalCost = (clone $query)->outbound()->sum('cost');

        return [
            'total' => $total,
            'sent' => $sent,
            'received' => $received,
            'delivered' => $delivered,
            'failed' => $failed,
            'delivery_rate' => $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0,
            'total_cost' => $totalCost,
            'period' => $period,
        ];
    }

    // =========================================================================
    // COMMAND USE CASES - MESSAGES
    // =========================================================================

    /**
     * Send an SMS message.
     */
    public function sendSms(array $data): SmsMessage
    {
        $connection = SmsConnection::active()->findOrFail($data['connection_id']);

        // Check limits
        if (!$connection->isWithinDailyLimit()) {
            throw new \InvalidArgumentException('Daily SMS limit reached');
        }
        if (!$connection->isWithinMonthlyLimit()) {
            throw new \InvalidArgumentException('Monthly SMS limit reached');
        }

        // Check opt-out status
        if ($this->isOptedOut($data['to_number'], $connection->id)) {
            throw new \InvalidArgumentException('Recipient has opted out of SMS messages');
        }

        // Get content from template or direct
        $content = $data['content'];
        $templateId = null;

        if (!empty($data['template_id'])) {
            $template = SmsTemplate::findOrFail($data['template_id']);
            $content = $template->render($data['merge_data'] ?? []);
            $template->incrementUsage();
            $templateId = $template->id;
        }

        // Create the message
        $message = SmsMessage::create([
            'connection_id' => $connection->id,
            'template_id' => $templateId,
            'direction' => 'outbound',
            'from_number' => $connection->phone_number,
            'to_number' => $data['to_number'],
            'content' => $content,
            'status' => 'pending',
            'segment_count' => SmsTemplate::calculateSegments($content),
            'module_record_id' => $data['module_record_id'] ?? null,
            'module_api_name' => $data['module_api_name'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'sent_by' => Auth::id(),
        ]);

        // Update connection last used
        $connection->update(['last_used_at' => now()]);

        return $message;
    }

    /**
     * Record an inbound SMS message (from webhook).
     */
    public function recordInboundSms(array $data): SmsMessage
    {
        $connection = SmsConnection::where('phone_number', $data['to_number'])->first();

        return SmsMessage::create([
            'connection_id' => $connection?->id,
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
    public function updateMessageStatus(string $providerMessageId, string $status, ?array $extra = null): ?SmsMessage
    {
        $message = SmsMessage::where('provider_message_id', $providerMessageId)->first();

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

        $message->update($updateData);

        return $message->fresh();
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
    public function listTemplates(array $filters = []): Collection
    {
        $query = SmsTemplate::query()->with('creator:id,name');

        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (isset($filters['is_active'])) {
            if ($filters['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a template by ID.
     */
    public function getTemplate(int $id): ?SmsTemplate
    {
        return SmsTemplate::with('creator:id,name')->find($id);
    }

    // =========================================================================
    // COMMAND USE CASES - TEMPLATES
    // =========================================================================

    /**
     * Create an SMS template.
     */
    public function createTemplate(array $data): SmsTemplate
    {
        return SmsTemplate::create([
            'name' => $data['name'],
            'content' => $data['content'],
            'category' => $data['category'] ?? 'general',
            'is_active' => $data['is_active'] ?? true,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Update an SMS template.
     */
    public function updateTemplate(int $id, array $data): SmsTemplate
    {
        $template = SmsTemplate::findOrFail($id);

        $template->update([
            'name' => $data['name'] ?? $template->name,
            'content' => $data['content'] ?? $template->content,
            'category' => $data['category'] ?? $template->category,
            'is_active' => $data['is_active'] ?? $template->is_active,
        ]);

        return $template->fresh();
    }

    /**
     * Delete an SMS template.
     */
    public function deleteTemplate(int $id): bool
    {
        $template = SmsTemplate::findOrFail($id);
        return $template->delete();
    }

    /**
     * Preview template with sample data.
     */
    public function previewTemplate(int $id, array $sampleData): array
    {
        $template = SmsTemplate::findOrFail($id);
        $rendered = $template->render($sampleData);

        return [
            'rendered' => $rendered,
            'character_count' => strlen($rendered),
            'segment_count' => SmsTemplate::calculateSegments($rendered),
            'merge_fields' => $template->merge_fields,
        ];
    }

    // =========================================================================
    // QUERY USE CASES - CONNECTIONS
    // =========================================================================

    /**
     * List SMS connections.
     */
    public function listConnections(bool $activeOnly = false): Collection
    {
        $query = SmsConnection::query();

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a connection by ID.
     */
    public function getConnection(int $id): ?SmsConnection
    {
        return SmsConnection::find($id);
    }

    /**
     * Get connection usage stats.
     */
    public function getConnectionUsage(int $connectionId): array
    {
        $connection = SmsConnection::findOrFail($connectionId);

        return [
            'connection' => $connection,
            'today_count' => $connection->getTodayMessageCount(),
            'month_count' => $connection->getMonthMessageCount(),
            'daily_limit' => $connection->daily_limit,
            'monthly_limit' => $connection->monthly_limit,
            'daily_remaining' => max(0, $connection->daily_limit - $connection->getTodayMessageCount()),
            'monthly_remaining' => max(0, $connection->monthly_limit - $connection->getMonthMessageCount()),
        ];
    }

    // =========================================================================
    // COMMAND USE CASES - CONNECTIONS
    // =========================================================================

    /**
     * Create an SMS connection.
     */
    public function createConnection(array $data): SmsConnection
    {
        return SmsConnection::create([
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
    public function updateConnection(int $id, array $data): SmsConnection
    {
        $connection = SmsConnection::findOrFail($id);

        $updateData = [
            'name' => $data['name'] ?? $connection->name,
            'phone_number' => $data['phone_number'] ?? $connection->phone_number,
            'messaging_service_sid' => $data['messaging_service_sid'] ?? $connection->messaging_service_sid,
            'is_active' => $data['is_active'] ?? $connection->is_active,
            'capabilities' => $data['capabilities'] ?? $connection->capabilities,
            'settings' => array_merge($connection->settings ?? [], $data['settings'] ?? []),
            'daily_limit' => $data['daily_limit'] ?? $connection->daily_limit,
            'monthly_limit' => $data['monthly_limit'] ?? $connection->monthly_limit,
        ];

        if (!empty($data['auth_token'])) {
            $updateData['auth_token'] = $data['auth_token'];
        }

        $connection->update($updateData);

        return $connection->fresh();
    }

    /**
     * Delete an SMS connection.
     */
    public function deleteConnection(int $id): bool
    {
        $connection = SmsConnection::findOrFail($id);

        if ($connection->messages()->exists()) {
            throw new \InvalidArgumentException('Cannot delete connection with existing messages');
        }

        return $connection->delete();
    }

    /**
     * Verify connection credentials.
     */
    public function verifyConnection(int $id): array
    {
        $connection = SmsConnection::findOrFail($id);

        // This would typically make an API call to verify credentials
        // For now, just mark as verified
        $connection->update(['is_verified' => true]);

        return [
            'verified' => true,
            'connection' => $connection->fresh(),
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
        $query = SmsOptOut::where('phone_number', $phoneNumber);

        if ($connectionId) {
            $query->where(function ($q) use ($connectionId) {
                $q->whereNull('connection_id')
                    ->orWhere('connection_id', $connectionId);
            });
        }

        return $query->exists();
    }

    /**
     * Record an opt-out.
     */
    public function recordOptOut(string $phoneNumber, ?int $connectionId = null, ?string $reason = null): SmsOptOut
    {
        return SmsOptOut::firstOrCreate(
            [
                'phone_number' => $phoneNumber,
                'connection_id' => $connectionId,
            ],
            [
                'reason' => $reason,
                'opted_out_at' => now(),
            ]
        );
    }

    /**
     * Remove an opt-out (opt back in).
     */
    public function removeOptOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        $query = SmsOptOut::where('phone_number', $phoneNumber);

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        return $query->delete() > 0;
    }

    /**
     * List opt-outs.
     */
    public function listOptOuts(?int $connectionId = null, int $perPage = 50): LengthAwarePaginator
    {
        $query = SmsOptOut::query();

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        return $query->orderBy('opted_out_at', 'desc')->paginate($perPage);
    }

    // =========================================================================
    // CAMPAIGN USE CASES
    // =========================================================================

    /**
     * List SMS campaigns.
     */
    public function listCampaigns(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = SmsCampaign::query()
            ->with(['connection:id,name,phone_number', 'template:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['connection_id'])) {
            $query->where('connection_id', $filters['connection_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a campaign by ID.
     */
    public function getCampaign(int $id): ?SmsCampaign
    {
        return SmsCampaign::with(['connection', 'template', 'messages'])->find($id);
    }

    /**
     * Create an SMS campaign.
     */
    public function createCampaign(array $data): SmsCampaign
    {
        return SmsCampaign::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'connection_id' => $data['connection_id'],
            'template_id' => $data['template_id'] ?? null,
            'content' => $data['content'] ?? null,
            'recipient_list' => $data['recipient_list'] ?? [],
            'status' => 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Start/send an SMS campaign.
     */
    public function startCampaign(int $campaignId): array
    {
        $campaign = SmsCampaign::findOrFail($campaignId);

        if ($campaign->status !== 'draft' && $campaign->status !== 'scheduled') {
            throw new \InvalidArgumentException('Campaign cannot be started');
        }

        $campaign->update(['status' => 'sending', 'started_at' => now()]);

        // Send to recipients
        $results = $this->bulkSend(
            $campaign->recipient_list,
            $campaign->content ?? $campaign->template?->content,
            $campaign->connection_id,
            $campaign->template_id
        );

        // Update campaign stats
        $campaign->update([
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
        $campaign = SmsCampaign::findOrFail($campaignId);

        $messages = $campaign->messages();
        $total = $messages->count();
        $delivered = (clone $messages)->byStatus('delivered')->count();
        $failed = (clone $messages)->whereIn('status', ['failed', 'undelivered'])->count();
        $totalCost = (clone $messages)->sum('cost');

        return [
            'campaign' => $campaign,
            'total_messages' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $total - $delivered - $failed,
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
            'total_cost' => $totalCost,
        ];
    }
}