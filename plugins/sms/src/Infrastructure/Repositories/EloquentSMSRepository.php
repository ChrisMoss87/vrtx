<?php

declare(strict_types=1);

namespace Plugins\SMS\Infrastructure\Repositories;

use App\Models\SmsCampaign;
use App\Models\SmsConnection;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\DB;
use Plugins\SMS\Domain\Repositories\SMSRepositoryInterface;

class EloquentSMSRepository implements SMSRepositoryInterface
{
    // =========================================================================
    // CONNECTIONS
    // =========================================================================

    public function listConnections(bool $activeOnly = false): array
    {
        $query = SmsConnection::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    public function findConnectionById(int $id): ?array
    {
        $connection = SmsConnection::find($id);
        return $connection?->toArray();
    }

    public function findActiveConnectionById(int $id): ?array
    {
        $connection = SmsConnection::where('id', $id)->where('is_active', true)->first();
        return $connection?->toArray();
    }

    public function findConnectionByPhoneNumber(string $phoneNumber): ?array
    {
        $connection = SmsConnection::where('phone_number', $phoneNumber)->first();
        return $connection?->toArray();
    }

    public function createConnection(array $data): array
    {
        $connection = SmsConnection::create($data);
        return $connection->toArray();
    }

    public function updateConnection(int $id, array $data): array
    {
        $connection = SmsConnection::findOrFail($id);
        $connection->update($data);
        return $connection->fresh()->toArray();
    }

    public function deleteConnection(int $id): bool
    {
        return SmsConnection::destroy($id) > 0;
    }

    public function getConnectionUsage(int $connectionId): array
    {
        $connection = SmsConnection::find($connectionId);

        if (!$connection) {
            return ['daily_remaining' => 0, 'monthly_remaining' => 0];
        }

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $dailySent = SmsMessage::where('connection_id', $connectionId)
            ->where('direction', 'outbound')
            ->where('created_at', '>=', $today)
            ->count();

        $monthlySent = SmsMessage::where('connection_id', $connectionId)
            ->where('direction', 'outbound')
            ->where('created_at', '>=', $monthStart)
            ->count();

        return [
            'daily_sent' => $dailySent,
            'daily_limit' => $connection->daily_limit ?? 1000,
            'daily_remaining' => max(0, ($connection->daily_limit ?? 1000) - $dailySent),
            'monthly_sent' => $monthlySent,
            'monthly_limit' => $connection->monthly_limit ?? 30000,
            'monthly_remaining' => max(0, ($connection->monthly_limit ?? 30000) - $monthlySent),
        ];
    }

    // =========================================================================
    // MESSAGES
    // =========================================================================

    public function listMessages(array $filters = [], int $perPage = 20): array
    {
        $query = SmsMessage::with(['connection:id,name,phone_number']);

        if (!empty($filters['connection_id'])) {
            $query->where('connection_id', $filters['connection_id']);
        }

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('from_number', 'like', "%{$search}%")
                    ->orWhere('to_number', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    public function findMessageById(int $id): ?array
    {
        $message = SmsMessage::with(['connection:id,name,phone_number'])->find($id);
        return $message?->toArray();
    }

    public function findByProviderMessageId(string $providerMessageId): ?array
    {
        $message = SmsMessage::where('provider_message_id', $providerMessageId)->first();
        return $message?->toArray();
    }

    public function getConversation(string $phoneNumber, int $limit = 100): array
    {
        return SmsMessage::where(function ($q) use ($phoneNumber) {
            $q->where('from_number', $phoneNumber)
                ->orWhere('to_number', $phoneNumber);
        })
            ->with(['connection:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): array
    {
        return SmsMessage::where('module_api_name', $moduleApiName)
            ->where('module_record_id', $recordId)
            ->with(['connection:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function createMessage(array $data): array
    {
        $message = SmsMessage::create($data);
        return $message->toArray();
    }

    public function updateMessage(int $id, array $data): array
    {
        $message = SmsMessage::findOrFail($id);
        $message->update($data);
        return $message->fresh()->toArray();
    }

    // =========================================================================
    // TEMPLATES
    // =========================================================================

    public function listTemplates(array $filters = []): array
    {
        $query = SmsTemplate::query();

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['active'])) {
            $query->where('is_active', true);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name')->get()->toArray();
    }

    public function findTemplateById(int $id): ?array
    {
        $template = SmsTemplate::find($id);
        return $template?->toArray();
    }

    public function createTemplate(array $data): array
    {
        $template = SmsTemplate::create($data);
        return $template->toArray();
    }

    public function updateTemplate(int $id, array $data): array
    {
        $template = SmsTemplate::findOrFail($id);
        $template->update($data);
        return $template->fresh()->toArray();
    }

    public function deleteTemplate(int $id): bool
    {
        return SmsTemplate::destroy($id) > 0;
    }

    public function incrementTemplateUsage(int $id): void
    {
        SmsTemplate::where('id', $id)->increment('usage_count');
    }

    // =========================================================================
    // OPT-OUT
    // =========================================================================

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

    public function recordOptOut(string $phoneNumber, ?int $connectionId = null, ?string $reason = null): array
    {
        $optOut = SmsOptOut::firstOrCreate(
            ['phone_number' => $phoneNumber, 'connection_id' => $connectionId],
            ['reason' => $reason, 'opted_out_at' => now()]
        );

        return $optOut->toArray();
    }

    public function removeOptOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        $query = SmsOptOut::where('phone_number', $phoneNumber);

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        return $query->delete() > 0;
    }

    public function listOptOuts(?int $connectionId = null, int $perPage = 50): array
    {
        $query = SmsOptOut::query();

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $paginated = $query->orderByDesc('opted_out_at')->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    // =========================================================================
    // CAMPAIGNS
    // =========================================================================

    public function listCampaigns(array $filters = [], int $perPage = 20): array
    {
        $query = SmsCampaign::with(['connection:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    public function findCampaignById(int $id): ?array
    {
        $campaign = SmsCampaign::with(['connection:id,name', 'template:id,name'])->find($id);
        return $campaign?->toArray();
    }

    public function createCampaign(array $data): array
    {
        $campaign = SmsCampaign::create($data);
        return $campaign->toArray();
    }

    public function updateCampaign(int $id, array $data): array
    {
        $campaign = SmsCampaign::findOrFail($id);
        $campaign->update($data);
        return $campaign->fresh()->toArray();
    }

    public function getCampaignStats(int $campaignId): array
    {
        $messages = SmsMessage::where('campaign_id', $campaignId);

        $total = $messages->count();
        $sent = (clone $messages)->where('status', 'sent')->count();
        $delivered = (clone $messages)->where('status', 'delivered')->count();
        $failed = (clone $messages)->where('status', 'failed')->count();

        return [
            'total_messages' => $total,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
        ];
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function getMessageStats(?int $connectionId = null, ?string $period = 'today'): array
    {
        $query = SmsMessage::query();

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfDay(),
        };

        $query->where('created_at', '>=', $startDate);

        $total = $query->count();
        $inbound = (clone $query)->where('direction', 'inbound')->count();
        $outbound = (clone $query)->where('direction', 'outbound')->count();
        $delivered = (clone $query)->where('status', 'delivered')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        $totalCost = (clone $query)->sum('cost') ?? 0;

        return [
            'total' => $total,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'delivered' => $delivered,
            'failed' => $failed,
            'delivery_rate' => $outbound > 0 ? round(($delivered / $outbound) * 100, 2) : 0,
            'total_cost' => $totalCost,
        ];
    }
}
