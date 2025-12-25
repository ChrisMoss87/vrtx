<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Sms;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Sms\Entities\SmsMessage as SmsMessageEntity;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbSmsMessageRepository implements SmsMessageRepositoryInterface
{
    private const TABLE_SMS_MESSAGES = 'sms_messages';
    private const TABLE_SMS_TEMPLATES = 'sms_templates';
    private const TABLE_SMS_CONNECTIONS = 'sms_connections';
    private const TABLE_SMS_OPT_OUTS = 'sms_opt_outs';
    private const TABLE_SMS_CAMPAIGNS = 'sms_campaigns';
    private const TABLE_USERS = 'users';
    private const TABLE_MODULE_RECORDS = 'module_records';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?SmsMessageEntity
    {
        $record = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('id', $id)
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function save(SmsMessageEntity $entity): SmsMessageEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId() !== null) {
            $data['updated_at'] = now();

            DB::table(self::TABLE_SMS_MESSAGES)
                ->where('id', $entity->getId())
                ->update($data);

            $record = DB::table(self::TABLE_SMS_MESSAGES)
                ->where('id', $entity->getId())
                ->first();
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $id = DB::table(self::TABLE_SMS_MESSAGES)->insertGetId($data);

            $record = DB::table(self::TABLE_SMS_MESSAGES)
                ->where('id', $id)
                ->first();
        }

        return $this->toDomainEntity($record);
    }

    // =========================================================================
    // QUERY METHODS - MESSAGES
    // =========================================================================

    public function findByIdAsArray(int $id, array $relations = []): ?array
    {
        $message = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('id', $id)
            ->first();

        if (!$message) {
            return null;
        }

        $result = $this->toArray($message);

        // Load default relations if none specified
        if (empty($relations)) {
            $relations = ['connection', 'sender', 'template', 'campaign', 'moduleRecord'];
        }

        // Load specified relations
        if (in_array('connection', $relations) && $message->connection_id) {
            $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
                ->where('id', $message->connection_id)
                ->first();
            $result['connection'] = $connection ? $this->toArray($connection) : null;
        }

        if (in_array('sender', $relations) && $message->sent_by) {
            $sender = DB::table(self::TABLE_USERS)
                ->select('id', 'name')
                ->where('id', $message->sent_by)
                ->first();
            $result['sender'] = $sender ? $this->toArray($sender) : null;
        }

        if (in_array('template', $relations) && $message->template_id) {
            $template = DB::table(self::TABLE_SMS_TEMPLATES)
                ->where('id', $message->template_id)
                ->first();
            $result['template'] = $template ? $this->toArray($template) : null;
        }

        if (in_array('campaign', $relations) && $message->campaign_id) {
            $campaign = DB::table(self::TABLE_SMS_CAMPAIGNS)
                ->where('id', $message->campaign_id)
                ->first();
            $result['campaign'] = $campaign ? $this->toArray($campaign) : null;
        }

        if (in_array('moduleRecord', $relations) && $message->module_record_id) {
            $moduleRecord = DB::table(self::TABLE_MODULE_RECORDS)
                ->where('id', $message->module_record_id)
                ->first();
            $result['moduleRecord'] = $moduleRecord ? $this->toArray($moduleRecord) : null;
        }

        return $result;
    }

    public function listMessages(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_SMS_MESSAGES);

        // Filter by direction
        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by phone number
        if (!empty($filters['phone'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('to_number', $filters['phone'])
                  ->orWhere('from_number', $filters['phone']);
            });
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
            $query->where('module_api_name', $filters['module_api_name'])
                  ->where('module_record_id', $filters['module_record_id']);
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

        // Get total count
        $total = $query->count();

        // Paginate
        $items = $query->forPage($page, $perPage)->get();

        // Load relations for each item
        $itemsArray = [];
        foreach ($items as $item) {
            $itemArray = $this->toArray($item);

            if ($item->connection_id) {
                $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
                    ->select('id', 'name', 'phone_number')
                    ->where('id', $item->connection_id)
                    ->first();
                $itemArray['connection'] = $connection ? $this->toArray($connection) : null;
            }

            if ($item->sent_by) {
                $sender = DB::table(self::TABLE_USERS)
                    ->select('id', 'name')
                    ->where('id', $item->sent_by)
                    ->first();
                $itemArray['sender'] = $sender ? $this->toArray($sender) : null;
            }

            if ($item->template_id) {
                $template = DB::table(self::TABLE_SMS_TEMPLATES)
                    ->select('id', 'name')
                    ->where('id', $item->template_id)
                    ->first();
                $itemArray['template'] = $template ? $this->toArray($template) : null;
            }

            $itemsArray[] = $itemArray;
        }

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getConversation(string $phoneNumber, int $limit = 100): array
    {
        $messages = DB::table(self::TABLE_SMS_MESSAGES)
            ->where(function ($q) use ($phoneNumber) {
                $q->where('to_number', $phoneNumber)
                  ->orWhere('from_number', $phoneNumber);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($messages as $message) {
            $msgArray = $this->toArray($message);

            if ($message->sent_by) {
                $sender = DB::table(self::TABLE_USERS)
                    ->select('id', 'name')
                    ->where('id', $message->sent_by)
                    ->first();
                $msgArray['sender'] = $sender ? $this->toArray($sender) : null;
            }

            $result[] = $msgArray;
        }

        return $result;
    }

    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): array
    {
        $messages = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('module_api_name', $moduleApiName)
            ->where('module_record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($messages as $message) {
            $msgArray = $this->toArray($message);

            if ($message->sent_by) {
                $sender = DB::table(self::TABLE_USERS)
                    ->select('id', 'name')
                    ->where('id', $message->sent_by)
                    ->first();
                $msgArray['sender'] = $sender ? $this->toArray($sender) : null;
            }

            if ($message->template_id) {
                $template = DB::table(self::TABLE_SMS_TEMPLATES)
                    ->select('id', 'name')
                    ->where('id', $message->template_id)
                    ->first();
                $msgArray['template'] = $template ? $this->toArray($template) : null;
            }

            $result[] = $msgArray;
        }

        return $result;
    }

    public function getStats(?int $connectionId = null, ?string $period = 'today'): array
    {
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        $baseQuery = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('created_at', '>=', $startDate);

        if ($connectionId) {
            $baseQuery->where('connection_id', $connectionId);
        }

        $total = (clone $baseQuery)->count();

        $sent = (clone $baseQuery)->where('direction', 'outbound')->count();

        $received = (clone $baseQuery)->where('direction', 'inbound')->count();

        $delivered = (clone $baseQuery)->where('status', 'delivered')->count();

        $failed = (clone $baseQuery)->whereIn('status', ['failed', 'undelivered'])->count();

        $totalCost = (clone $baseQuery)->where('direction', 'outbound')->sum('cost');

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
        $message = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('provider_message_id', $providerMessageId)
            ->first();

        return $message ? $this->toArray($message) : null;
    }

    // =========================================================================
    // COMMAND METHODS - MESSAGES
    // =========================================================================

    public function create(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_SMS_MESSAGES)->insertGetId($data);

        $message = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('id', $id)
            ->first();

        return $this->toArray($message);
    }

    public function update(int $id, array $data): ?array
    {
        $message = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('id', $id)
            ->first();

        if (!$message) {
            return null;
        }

        $data['updated_at'] = now();

        DB::table(self::TABLE_SMS_MESSAGES)
            ->where('id', $id)
            ->update($data);

        $updated = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('id', $id)
            ->first();

        return $this->toArray($updated);
    }

    public function delete(int $id): bool
    {
        $message = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('id', $id)
            ->first();

        if (!$message) {
            return false;
        }

        return DB::table(self::TABLE_SMS_MESSAGES)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // QUERY METHODS - TEMPLATES
    // =========================================================================

    public function listTemplates(array $filters = []): array
    {
        $query = DB::table(self::TABLE_SMS_TEMPLATES);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderBy('name')->get();

        $result = [];
        foreach ($templates as $template) {
            $tplArray = $this->toArray($template);

            if ($template->created_by) {
                $creator = DB::table(self::TABLE_USERS)
                    ->select('id', 'name')
                    ->where('id', $template->created_by)
                    ->first();
                $tplArray['creator'] = $creator ? $this->toArray($creator) : null;
            }

            $result[] = $tplArray;
        }

        return $result;
    }

    public function findTemplateById(int $id): ?array
    {
        $template = DB::table(self::TABLE_SMS_TEMPLATES)
            ->where('id', $id)
            ->first();

        if (!$template) {
            return null;
        }

        $result = $this->toArray($template);

        if ($template->created_by) {
            $creator = DB::table(self::TABLE_USERS)
                ->select('id', 'name')
                ->where('id', $template->created_by)
                ->first();
            $result['creator'] = $creator ? $this->toArray($creator) : null;
        }

        return $result;
    }

    public function createTemplate(array $data): array
    {
        // Handle JSON fields
        if (isset($data['merge_fields']) && is_array($data['merge_fields'])) {
            $data['merge_fields'] = json_encode($data['merge_fields']);
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_SMS_TEMPLATES)->insertGetId($data);

        $template = DB::table(self::TABLE_SMS_TEMPLATES)
            ->where('id', $id)
            ->first();

        return $this->toArray($template);
    }

    public function updateTemplate(int $id, array $data): ?array
    {
        $template = DB::table(self::TABLE_SMS_TEMPLATES)
            ->where('id', $id)
            ->first();

        if (!$template) {
            return null;
        }

        // Handle JSON fields
        if (isset($data['merge_fields']) && is_array($data['merge_fields'])) {
            $data['merge_fields'] = json_encode($data['merge_fields']);
        }

        $data['updated_at'] = now();

        DB::table(self::TABLE_SMS_TEMPLATES)
            ->where('id', $id)
            ->update($data);

        $updated = DB::table(self::TABLE_SMS_TEMPLATES)
            ->where('id', $id)
            ->first();

        return $this->toArray($updated);
    }

    public function deleteTemplate(int $id): bool
    {
        $template = DB::table(self::TABLE_SMS_TEMPLATES)
            ->where('id', $id)
            ->first();

        if (!$template) {
            return false;
        }

        return DB::table(self::TABLE_SMS_TEMPLATES)->where('id', $id)->delete() > 0;
    }

    public function incrementTemplateUsage(int $id): void
    {
        $template = DB::table(self::TABLE_SMS_TEMPLATES)
            ->where('id', $id)
            ->first();

        if ($template) {
            DB::table(self::TABLE_SMS_TEMPLATES)
                ->where('id', $id)
                ->increment('usage_count');

            DB::table(self::TABLE_SMS_TEMPLATES)
                ->where('id', $id)
                ->update(['last_used_at' => now()]);
        }
    }

    // =========================================================================
    // QUERY METHODS - CONNECTIONS
    // =========================================================================

    public function listConnections(bool $activeOnly = false): array
    {
        $query = DB::table(self::TABLE_SMS_CONNECTIONS);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $connections = $query->orderBy('name')->get();

        return array_map(fn($conn) => $this->toArray($conn), $connections->toArray());
    }

    public function findConnectionById(int $id): ?array
    {
        $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $id)
            ->first();

        return $connection ? $this->toArray($connection) : null;
    }

    public function findActiveConnectionById(int $id): ?array
    {
        $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        return $connection ? $this->toArray($connection) : null;
    }

    public function findConnectionByPhoneNumber(string $phoneNumber): ?array
    {
        $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('phone_number', $phoneNumber)
            ->first();

        return $connection ? $this->toArray($connection) : null;
    }

    public function getConnectionUsage(int $connectionId): array
    {
        $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $connectionId)
            ->first();

        if (!$connection) {
            throw new \InvalidArgumentException("Connection not found");
        }

        // Get today's message count
        $todayCount = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('connection_id', $connectionId)
            ->whereDate('created_at', today())
            ->where('direction', 'outbound')
            ->count();

        // Get this month's message count
        $monthCount = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('connection_id', $connectionId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('direction', 'outbound')
            ->count();

        return [
            'connection' => $this->toArray($connection),
            'today_count' => $todayCount,
            'month_count' => $monthCount,
            'daily_limit' => $connection->daily_limit,
            'monthly_limit' => $connection->monthly_limit,
            'daily_remaining' => max(0, $connection->daily_limit - $todayCount),
            'monthly_remaining' => max(0, $connection->monthly_limit - $monthCount),
        ];
    }

    public function createConnection(array $data): array
    {
        // Handle JSON fields
        if (isset($data['capabilities']) && is_array($data['capabilities'])) {
            $data['capabilities'] = json_encode($data['capabilities']);
        }
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_SMS_CONNECTIONS)->insertGetId($data);

        $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $id)
            ->first();

        return $this->toArray($connection);
    }

    public function updateConnection(int $id, array $data): ?array
    {
        $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $id)
            ->first();

        if (!$connection) {
            return null;
        }

        // Handle JSON fields
        if (isset($data['capabilities']) && is_array($data['capabilities'])) {
            $data['capabilities'] = json_encode($data['capabilities']);
        }
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        $data['updated_at'] = now();

        DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $id)
            ->update($data);

        $updated = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $id)
            ->first();

        return $this->toArray($updated);
    }

    public function deleteConnection(int $id): bool
    {
        $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
            ->where('id', $id)
            ->first();

        if (!$connection) {
            return false;
        }

        return DB::table(self::TABLE_SMS_CONNECTIONS)->where('id', $id)->delete() > 0;
    }

    public function connectionHasMessages(int $connectionId): bool
    {
        return DB::table(self::TABLE_SMS_MESSAGES)
            ->where('connection_id', $connectionId)
            ->exists();
    }

    // =========================================================================
    // OPT-OUT METHODS
    // =========================================================================

    public function isOptedOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        $query = DB::table(self::TABLE_SMS_OPT_OUTS)
            ->where('phone_number', $phoneNumber);

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
        // Check if record exists
        $existing = DB::table(self::TABLE_SMS_OPT_OUTS)
            ->where('phone_number', $phoneNumber)
            ->where('connection_id', $connectionId)
            ->first();

        if ($existing) {
            return $this->toArray($existing);
        }

        // Create new opt-out record
        $data = [
            'phone_number' => $phoneNumber,
            'connection_id' => $connectionId,
            'reason' => $reason,
            'opted_out_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table(self::TABLE_SMS_OPT_OUTS)->insertGetId($data);

        $optOut = DB::table(self::TABLE_SMS_OPT_OUTS)
            ->where('id', $id)
            ->first();

        return $this->toArray($optOut);
    }

    public function removeOptOut(string $phoneNumber, ?int $connectionId = null): bool
    {
        $query = DB::table(self::TABLE_SMS_OPT_OUTS)
            ->where('phone_number', $phoneNumber);

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        return $query->delete() > 0;
    }

    public function listOptOuts(?int $connectionId = null, int $perPage = 50, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_SMS_OPT_OUTS);

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $total = $query->count();

        $items = $query->orderBy('opted_out_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        $itemsArray = array_map(fn($item) => $this->toArray($item), $items->toArray());

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    // =========================================================================
    // CAMPAIGN METHODS
    // =========================================================================

    public function listCampaigns(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_SMS_CAMPAIGNS);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['connection_id'])) {
            $query->where('connection_id', $filters['connection_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        $total = $query->count();

        $items = $query->orderBy('created_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        // Load relations for each item
        $itemsArray = [];
        foreach ($items as $item) {
            $itemArray = $this->toArray($item);

            if ($item->connection_id) {
                $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
                    ->select('id', 'name', 'phone_number')
                    ->where('id', $item->connection_id)
                    ->first();
                $itemArray['connection'] = $connection ? $this->toArray($connection) : null;
            }

            if ($item->template_id) {
                $template = DB::table(self::TABLE_SMS_TEMPLATES)
                    ->select('id', 'name')
                    ->where('id', $item->template_id)
                    ->first();
                $itemArray['template'] = $template ? $this->toArray($template) : null;
            }

            $itemsArray[] = $itemArray;
        }

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findCampaignById(int $id): ?array
    {
        $campaign = DB::table(self::TABLE_SMS_CAMPAIGNS)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return null;
        }

        $result = $this->toArray($campaign);

        // Load relations
        if ($campaign->connection_id) {
            $connection = DB::table(self::TABLE_SMS_CONNECTIONS)
                ->where('id', $campaign->connection_id)
                ->first();
            $result['connection'] = $connection ? $this->toArray($connection) : null;
        }

        if ($campaign->template_id) {
            $template = DB::table(self::TABLE_SMS_TEMPLATES)
                ->where('id', $campaign->template_id)
                ->first();
            $result['template'] = $template ? $this->toArray($template) : null;
        }

        // Load messages
        $messages = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('campaign_id', $id)
            ->get();
        $result['messages'] = array_map(fn($msg) => $this->toArray($msg), $messages->toArray());

        return $result;
    }

    public function createCampaign(array $data): array
    {
        // Handle JSON fields
        if (isset($data['target_filters']) && is_array($data['target_filters'])) {
            $data['target_filters'] = json_encode($data['target_filters']);
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_SMS_CAMPAIGNS)->insertGetId($data);

        $campaign = DB::table(self::TABLE_SMS_CAMPAIGNS)
            ->where('id', $id)
            ->first();

        return $this->toArray($campaign);
    }

    public function updateCampaign(int $id, array $data): ?array
    {
        $campaign = DB::table(self::TABLE_SMS_CAMPAIGNS)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return null;
        }

        // Handle JSON fields
        if (isset($data['target_filters']) && is_array($data['target_filters'])) {
            $data['target_filters'] = json_encode($data['target_filters']);
        }

        $data['updated_at'] = now();

        DB::table(self::TABLE_SMS_CAMPAIGNS)
            ->where('id', $id)
            ->update($data);

        $updated = DB::table(self::TABLE_SMS_CAMPAIGNS)
            ->where('id', $id)
            ->first();

        return $this->toArray($updated);
    }

    public function getCampaignStats(int $campaignId): array
    {
        $campaign = DB::table(self::TABLE_SMS_CAMPAIGNS)
            ->where('id', $campaignId)
            ->first();

        if (!$campaign) {
            throw new \InvalidArgumentException("Campaign not found");
        }

        $messagesQuery = DB::table(self::TABLE_SMS_MESSAGES)
            ->where('campaign_id', $campaignId);

        $total = (clone $messagesQuery)->count();
        $delivered = (clone $messagesQuery)->where('status', 'delivered')->count();
        $failed = (clone $messagesQuery)->whereIn('status', ['failed', 'undelivered'])->count();
        $totalCost = (clone $messagesQuery)->sum('cost');

        return [
            'campaign' => $this->toArray($campaign),
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

    private function toDomainEntity(stdClass $record): SmsMessageEntity
    {
        return SmsMessageEntity::reconstitute(
            id: $record->id,
            createdAt: isset($record->created_at) ? new DateTimeImmutable($record->created_at) : null,
            updatedAt: isset($record->updated_at) ? new DateTimeImmutable($record->updated_at) : null,
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

    /**
     * Convert stdClass to array and handle JSON fields
     *
     * @param stdClass $record
     * @return array<string, mixed>
     */
    private function toArray(stdClass $record): array
    {
        $array = json_decode(json_encode($record), true);

        // Decode JSON fields for templates
        if (isset($array['merge_fields']) && is_string($array['merge_fields'])) {
            $array['merge_fields'] = json_decode($array['merge_fields'], true) ?? [];
        }

        // Decode JSON fields for connections
        if (isset($array['capabilities']) && is_string($array['capabilities'])) {
            $array['capabilities'] = json_decode($array['capabilities'], true) ?? [];
        }
        if (isset($array['settings']) && is_string($array['settings'])) {
            $array['settings'] = json_decode($array['settings'], true) ?? [];
        }

        // Decode JSON fields for campaigns
        if (isset($array['target_filters']) && is_string($array['target_filters'])) {
            $array['target_filters'] = json_decode($array['target_filters'], true) ?? [];
        }

        // Decode JSON fields for opt-outs
        if (isset($array['metadata']) && is_string($array['metadata'])) {
            $array['metadata'] = json_decode($array['metadata'], true) ?? [];
        }

        return $array;
    }
}
