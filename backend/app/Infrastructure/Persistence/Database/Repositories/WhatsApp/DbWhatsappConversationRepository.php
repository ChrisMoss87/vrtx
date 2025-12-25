<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\WhatsApp;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WhatsApp\Entities\WhatsappConversation as WhatsappConversationEntity;
use App\Domain\WhatsApp\Repositories\WhatsappConversationRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbWhatsappConversationRepository implements WhatsappConversationRepositoryInterface
{
    private const TABLE = 'whatsapp_conversations';
    private const TABLE_CONNECTIONS = 'whatsapp_connections';
    private const TABLE_USERS = 'users';

    /**
     * Convert row to array.
     */
    private function rowToArray(?stdClass $row): ?array
    {
        if (!$row) {
            return null;
        }

        return [
            'id' => $row->id,
            'connection_id' => $row->connection_id ?? null,
            'contact_wa_id' => $row->contact_wa_id ?? null,
            'contact_phone' => $row->contact_phone ?? null,
            'contact_name' => $row->contact_name ?? null,
            'status' => $row->status ?? null,
            'assigned_to' => $row->assigned_to ?? null,
            'is_resolved' => $row->is_resolved ?? false,
            'unread_count' => $row->unread_count ?? 0,
            'last_message_at' => $row->last_message_at ?? null,
            'last_incoming_at' => $row->last_incoming_at ?? null,
            'last_outgoing_at' => $row->last_outgoing_at ?? null,
            'module_api_name' => $row->module_api_name ?? null,
            'module_record_id' => $row->module_record_id ?? null,
            'metadata' => isset($row->metadata) ? (is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata) : [],
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    /**
     * Find conversation by ID.
     */
    public function findById(int $id): ?WhatsappConversationEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    /**
     * Find conversation by ID as array (for backward compatibility).
     */
    public function findByIdAsArray(int $id, array $with = []): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = $this->rowToArray($row);

        // Load relations manually
        if (in_array('connection', $with) || empty($with)) {
            $result['connection'] = $this->getConnectionById($row->connection_id);
        }
        if (in_array('assignedUser', $with) || empty($with)) {
            $result['assigned_user'] = $this->getUserById($row->assigned_to);
        }

        return $result;
    }

    /**
     * Find conversation by connection and contact WhatsApp ID.
     */
    public function findByConnectionAndContact(int $connectionId, string $contactWaId): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('connection_id', $connectionId)
            ->where('contact_wa_id', $contactWaId)
            ->first();

        return $this->rowToArray($row);
    }

    /**
     * List conversations with filtering and pagination.
     */
    public function list(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by connection
        if (!empty($filters['connection_id'])) {
            $query->where('connection_id', $filters['connection_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter open conversations
        if (!empty($filters['open'])) {
            $query->where('status', 'open');
        }

        // Filter unresolved
        if (!empty($filters['unresolved'])) {
            $query->where('is_resolved', false);
        }

        // Filter with unread messages
        if (!empty($filters['unread'])) {
            $query->where('unread_count', '>', 0);
        }

        // Filter by assigned user
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Filter unassigned
        if (!empty($filters['unassigned'])) {
            $query->whereNull('assigned_to');
        }

        // Filter by module record
        if (!empty($filters['module_api_name']) && !empty($filters['module_record_id'])) {
            $query->where('module_api_name', $filters['module_api_name'])
                ->where('module_record_id', $filters['module_record_id']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhere('contact_wa_id', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'last_message_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Count total
        $total = (clone $query)->count();

        // Paginate
        $items = $query->forPage($page, $perPage)->get();

        $mappedItems = $items->map(function ($row) {
            $item = $this->rowToArray($row);
            $item['connection'] = $this->getConnectionById($row->connection_id);
            $item['assigned_user'] = $this->getUserById($row->assigned_to);
            return $item;
        })->toArray();

        return PaginatedResult::create(
            items: $mappedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    /**
     * Get conversations assigned to a user.
     */
    public function findByAssignedUser(int $userId, array $filters = []): array
    {
        $query = DB::table(self::TABLE)->where('assigned_to', $userId);

        if (!empty($filters['unresolved'])) {
            $query->where('is_resolved', false);
        }

        return $query->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($row) {
                $item = $this->rowToArray($row);
                $item['connection'] = $this->getConnectionById($row->connection_id);
                $item['assigned_user'] = $this->getUserById($row->assigned_to);
                return $item;
            })->toArray();
    }

    /**
     * Get unread conversations count for a user.
     */
    public function countUnreadByUser(int $userId): int
    {
        return DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->where('unread_count', '>', 0)
            ->count();
    }

    /**
     * Get conversations by module record.
     */
    public function findByModuleRecord(string $moduleApiName, int $moduleRecordId): array
    {
        return DB::table(self::TABLE)
            ->where('module_api_name', $moduleApiName)
            ->where('module_record_id', $moduleRecordId)
            ->get()
            ->map(fn($row) => $this->rowToArray($row))
            ->toArray();
    }

    /**
     * Create or get conversation for a contact.
     */
    public function getOrCreate(int $connectionId, string $contactWaId, string $contactPhone, ?string $contactName = null): array
    {
        $row = DB::table(self::TABLE)
            ->where('connection_id', $connectionId)
            ->where('contact_wa_id', $contactWaId)
            ->first();

        if (!$row) {
            $id = DB::table(self::TABLE)->insertGetId([
                'connection_id' => $connectionId,
                'contact_wa_id' => $contactWaId,
                'contact_phone' => $contactPhone,
                'contact_name' => $contactName,
                'status' => 'open',
                'is_resolved' => false,
                'unread_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table(self::TABLE)->where('id', $id)->first();
        }

        return $this->rowToArray($row);
    }

    /**
     * Update conversation.
     */
    public function update(int $id, array $data): ?array
    {
        $existing = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$existing) {
            return null;
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['contact_name'])) $updateData['contact_name'] = $data['contact_name'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['assigned_to'])) $updateData['assigned_to'] = $data['assigned_to'];
        if (isset($data['is_resolved'])) $updateData['is_resolved'] = $data['is_resolved'];
        if (isset($data['last_message_at'])) $updateData['last_message_at'] = $data['last_message_at'];
        if (isset($data['last_incoming_at'])) $updateData['last_incoming_at'] = $data['last_incoming_at'];
        if (isset($data['last_outgoing_at'])) $updateData['last_outgoing_at'] = $data['last_outgoing_at'];
        if (isset($data['metadata'])) {
            $existingMeta = isset($existing->metadata) ? (is_string($existing->metadata) ? json_decode($existing->metadata, true) : $existing->metadata) : [];
            $updateData['metadata'] = json_encode(array_merge($existingMeta, $data['metadata']));
        }

        DB::table(self::TABLE)->where('id', $id)->update($updateData);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    /**
     * Assign conversation to a user.
     */
    public function assign(int $id, int $userId): bool
    {
        $affected = DB::table(self::TABLE)->where('id', $id)->update([
            'assigned_to' => $userId,
            'updated_at' => now(),
        ]);

        return $affected > 0;
    }

    /**
     * Link conversation to a module record.
     */
    public function linkToRecord(int $id, string $moduleApiName, int $recordId): bool
    {
        $affected = DB::table(self::TABLE)->where('id', $id)->update([
            'module_api_name' => $moduleApiName,
            'module_record_id' => $recordId,
            'updated_at' => now(),
        ]);

        return $affected > 0;
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(int $id): bool
    {
        $affected = DB::table(self::TABLE)->where('id', $id)->update([
            'unread_count' => 0,
            'updated_at' => now(),
        ]);

        return $affected > 0;
    }

    /**
     * Close a conversation.
     */
    public function close(int $id): bool
    {
        $affected = DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'closed',
            'is_resolved' => true,
            'updated_at' => now(),
        ]);

        return $affected > 0;
    }

    /**
     * Reopen a conversation.
     */
    public function reopen(int $id): bool
    {
        $affected = DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'open',
            'is_resolved' => false,
            'updated_at' => now(),
        ]);

        return $affected > 0;
    }

    /**
     * Increment unread count.
     */
    public function incrementUnread(int $id): bool
    {
        $affected = DB::table(self::TABLE)->where('id', $id)->increment('unread_count');

        return $affected > 0;
    }

    /**
     * Update last message timestamps.
     */
    public function updateTimestamps(int $id, array $timestamps): bool
    {
        $updateData = ['updated_at' => now()];

        if (isset($timestamps['last_message_at'])) {
            $updateData['last_message_at'] = $timestamps['last_message_at'];
        }
        if (isset($timestamps['last_incoming_at'])) {
            $updateData['last_incoming_at'] = $timestamps['last_incoming_at'];
        }
        if (isset($timestamps['last_outgoing_at'])) {
            $updateData['last_outgoing_at'] = $timestamps['last_outgoing_at'];
        }

        $affected = DB::table(self::TABLE)->where('id', $id)->update($updateData);

        return $affected > 0;
    }

    /**
     * Get conversation statistics.
     */
    public function getStats(?int $connectionId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table(self::TABLE);

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $total = (clone $query)->count();
        $open = (clone $query)->where('status', 'open')->count();
        $resolved = (clone $query)->where('is_resolved', true)->count();
        $unassigned = (clone $query)->whereNull('assigned_to')->count();
        $withUnread = (clone $query)->where('unread_count', '>', 0)->count();

        return [
            'total_conversations' => $total,
            'open' => $open,
            'resolved' => $resolved,
            'unassigned' => $unassigned,
            'with_unread' => $withUnread,
        ];
    }

    /**
     * Count conversations by status.
     */
    public function countByStatus(?int $connectionId = null): array
    {
        $query = DB::table(self::TABLE);

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        return $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Check if conversation has unresolved conversations.
     */
    public function hasUnresolvedByConnection(int $connectionId): bool
    {
        return DB::table(self::TABLE)
            ->where('connection_id', $connectionId)
            ->where('is_resolved', false)
            ->exists();
    }

    // =========================================================================
    // DDD METHODS
    // =========================================================================

    /**
     * Save a conversation entity.
     */
    public function save(WhatsappConversationEntity $entity): WhatsappConversationEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId()) {
            DB::table(self::TABLE)->where('id', $entity->getId())->update($data);
            $row = DB::table(self::TABLE)->where('id', $entity->getId())->first();
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table(self::TABLE)->insertGetId($data);
            $row = DB::table(self::TABLE)->where('id', $id)->first();
        }

        return $this->toDomainEntity($row);
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    /**
     * Map row to domain entity.
     */
    private function toDomainEntity(stdClass $row): WhatsappConversationEntity
    {
        return WhatsappConversationEntity::reconstitute(
            id: $row->id,
            createdAt: isset($row->created_at) ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: isset($row->updated_at) ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Map domain entity to model data.
     */
    private function toModelData(WhatsappConversationEntity $entity): array
    {
        $data = ['updated_at' => now()];

        if ($entity->getCreatedAt()) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getConnectionById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::table(self::TABLE_CONNECTIONS)->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'display_phone_number' => $row->display_phone_number ?? null,
        ];
    }

    private function getUserById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::table(self::TABLE_USERS)->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'email' => $row->email ?? null,
        ];
    }
}
