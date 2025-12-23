<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Sms;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Sms\Entities\SmsMessage as SmsMessageEntity;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Models\SmsCampaign;
use App\Models\SmsConnection;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use App\Models\SmsTemplate;
use DateTimeImmutable;

class EloquentSmsMessageRepository implements SmsMessageRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?SmsMessageEntity
    {
        $model = SmsMessage::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function save(SmsMessageEntity $entity): SmsMessageEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId() !== null) {
            $model = SmsMessage::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = SmsMessage::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    // =========================================================================
    // QUERY METHODS - MESSAGES
    // =========================================================================

    public function findByIdAsArray(int $id, array $relations = []): ?array
    {
        $query = SmsMessage::query();

        if (!empty($relations)) {
            $query->with($relations);
        } else {
            $query->with(['connection', 'sender', 'template', 'campaign', 'moduleRecord']);
        }

        $message = $query->find($id);

        return $message ? $message->toArray() : null;
    }

    public function listMessages(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
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

        // Paginate
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function getConversation(string $phoneNumber, int $limit = 100): array
    {
        $messages = SmsMessage::forPhone($phoneNumber)
            ->with(['sender:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $messages->map(fn($msg) => $msg->toArray())->toArray();
    }

    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): array
    {
        $messages = SmsMessage::forRecord($moduleApiName, $recordId)
            ->with(['sender:id,name', 'template:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $messages->map(fn($msg) => $msg->toArray())->toArray();
    }

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
            'total_cost' => (float) $totalCost,
            'period' => $period,
        ];
    }

    public function findByProviderMessageId(string $providerMessageId): ?array
    {
        $message = SmsMessage::where('provider_message_id', $providerMessageId)->first();

        return $message ? $message->toArray() : null;
    }

    // =========================================================================
    // COMMAND METHODS - MESSAGES
    // =========================================================================

    public function create(array $data): array
    {
        $message = SmsMessage::create($data);

        return $message->fresh()->toArray();
    }

    public function update(int $id, array $data): ?array
    {
        $message = SmsMessage::find($id);

        if (!$message) {
            return null;
        }

        $message->update($data);

        return $message->fresh()->toArray();
    }

    public function delete(int $id): bool
    {
        $message = SmsMessage::find($id);

        if (!$message) {
            return false;
        }

        return $message->delete();
    }

    // =========================================================================
    // QUERY METHODS - TEMPLATES
    // =========================================================================

    public function listTemplates(array $filters = []): array
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

        $templates = $query->orderBy('name')->get();

        return $templates->map(fn($tpl) => $tpl->toArray())->toArray();
    }

    public function findTemplateById(int $id): ?array
    {
        $template = SmsTemplate::with('creator:id,name')->find($id);

        return $template ? $template->toArray() : null;
    }

    public function createTemplate(array $data): array
    {
        $template = SmsTemplate::create($data);

        return $template->fresh()->toArray();
    }

    public function updateTemplate(int $id, array $data): ?array
    {
        $template = SmsTemplate::find($id);

        if (!$template) {
            return null;
        }

        $template->update($data);

        return $template->fresh()->toArray();
    }

    public function deleteTemplate(int $id): bool
    {
        $template = SmsTemplate::find($id);

        if (!$template) {
            return false;
        }

        return $template->delete();
    }

    public function incrementTemplateUsage(int $id): void
    {
        $template = SmsTemplate::find($id);

        if ($template) {
            $template->incrementUsage();
        }
    }

    // =========================================================================
    // QUERY METHODS - CONNECTIONS
    // =========================================================================

    public function listConnections(bool $activeOnly = false): array
    {
        $query = SmsConnection::query();

        if ($activeOnly) {
            $query->active();
        }

        $connections = $query->orderBy('name')->get();

        return $connections->map(fn($conn) => $conn->toArray())->toArray();
    }

    public function findConnectionById(int $id): ?array
    {
        $connection = SmsConnection::find($id);

        return $connection ? $connection->toArray() : null;
    }

    public function findActiveConnectionById(int $id): ?array
    {
        $connection = SmsConnection::active()->find($id);

        return $connection ? $connection->toArray() : null;
    }

    public function findConnectionByPhoneNumber(string $phoneNumber): ?array
    {
        $connection = SmsConnection::where('phone_number', $phoneNumber)->first();

        return $connection ? $connection->toArray() : null;
    }

    public function getConnectionUsage(int $connectionId): array
    {
        $connection = SmsConnection::find($connectionId);

        if (!$connection) {
            throw new \InvalidArgumentException("Connection not found");
        }

        return [
            'connection' => $connection->toArray(),
            'today_count' => $connection->getTodayMessageCount(),
            'month_count' => $connection->getMonthMessageCount(),
            'daily_limit' => $connection->daily_limit,
            'monthly_limit' => $connection->monthly_limit,
            'daily_remaining' => max(0, $connection->daily_limit - $connection->getTodayMessageCount()),
            'monthly_remaining' => max(0, $connection->monthly_limit - $connection->getMonthMessageCount()),
        ];
    }

    public function createConnection(array $data): array
    {
        $connection = SmsConnection::create($data);

        return $connection->fresh()->toArray();
    }

    public function updateConnection(int $id, array $data): ?array
    {
        $connection = SmsConnection::find($id);

        if (!$connection) {
            return null;
        }

        $connection->update($data);

        return $connection->fresh()->toArray();
    }

    public function deleteConnection(int $id): bool
    {
        $connection = SmsConnection::find($id);

        if (!$connection) {
            return false;
        }

        return $connection->delete();
    }

    public function connectionHasMessages(int $connectionId): bool
    {
        return SmsMessage::where('connection_id', $connectionId)->exists();
    }

    // =========================================================================
    // OPT-OUT METHODS
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
            [
                'phone_number' => $phoneNumber,
                'connection_id' => $connectionId,
            ],
            [
                'reason' => $reason,
                'opted_out_at' => now(),
            ]
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

    public function listOptOuts(?int $connectionId = null, int $perPage = 50, int $page = 1): PaginatedResult
    {
        $query = SmsOptOut::query();

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $paginator = $query->orderBy('opted_out_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    // =========================================================================
    // CAMPAIGN METHODS
    // =========================================================================

    public function listCampaigns(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
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

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function findCampaignById(int $id): ?array
    {
        $campaign = SmsCampaign::with(['connection', 'template', 'messages'])->find($id);

        return $campaign ? $campaign->toArray() : null;
    }

    public function createCampaign(array $data): array
    {
        $campaign = SmsCampaign::create($data);

        return $campaign->fresh()->toArray();
    }

    public function updateCampaign(int $id, array $data): ?array
    {
        $campaign = SmsCampaign::find($id);

        if (!$campaign) {
            return null;
        }

        $campaign->update($data);

        return $campaign->fresh()->toArray();
    }

    public function getCampaignStats(int $campaignId): array
    {
        $campaign = SmsCampaign::find($campaignId);

        if (!$campaign) {
            throw new \InvalidArgumentException("Campaign not found");
        }

        $messages = $campaign->messages();
        $total = $messages->count();
        $delivered = (clone $messages)->byStatus('delivered')->count();
        $failed = (clone $messages)->whereIn('status', ['failed', 'undelivered'])->count();
        $totalCost = (clone $messages)->sum('cost');

        return [
            'campaign' => $campaign->toArray(),
            'total_messages' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $total - $delivered - $failed,
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
            'total_cost' => (float) $totalCost,
        ];
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(SmsMessage $model): SmsMessageEntity
    {
        return SmsMessageEntity::reconstitute(
            id: $model->id,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at->toDateTimeString()) : null,
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(SmsMessageEntity $entity): array
    {
        return [
            // Basic entity fields - SmsMessage entity is minimal, only has timestamps
            // Additional fields would be handled by array methods for now
        ];
    }
}
