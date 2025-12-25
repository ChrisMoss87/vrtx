<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\DealRoom;

use App\Domain\DealRoom\Entities\DealRoom as DealRoomEntity;
use App\Domain\DealRoom\Repositories\DealRoomRepositoryInterface;
use App\Domain\DealRoom\ValueObjects\DealRoomStatus;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class DbDealRoomRepository implements DealRoomRepositoryInterface
{
    private const TABLE_DEAL_ROOMS = 'deal_rooms';
    private const TABLE_MEMBERS = 'deal_room_members';
    private const TABLE_DOCUMENTS = 'deal_room_documents';
    private const TABLE_DOCUMENT_VIEWS = 'deal_room_document_views';
    private const TABLE_ACTION_ITEMS = 'deal_room_action_items';
    private const TABLE_MESSAGES = 'deal_room_messages';
    private const TABLE_ACTIVITIES = 'deal_room_activities';
    private const TABLE_USERS = 'users';

    // Status constants
    private const STATUS_ACTIVE = 'active';
    private const STATUS_CLOSED = 'closed';
    private const STATUS_ARCHIVED = 'archived';

    // Role constants
    private const ROLE_OWNER = 'owner';
    private const ROLE_TEAM = 'team';
    private const ROLE_VIEWER = 'viewer';

    // =========================================================================
    // DEAL ROOM QUERIES (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DealRoomEntity
    {
        $row = DB::table(self::TABLE_DEAL_ROOMS)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(DealRoomEntity $dealRoom): DealRoomEntity
    {
        $data = $this->toRowData($dealRoom);

        if ($dealRoom->getId() !== null) {
            DB::table(self::TABLE_DEAL_ROOMS)
                ->where('id', $dealRoom->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $dealRoom->getId();
        } else {
            $id = DB::table(self::TABLE_DEAL_ROOMS)->insertGetId(
                array_merge($data, [
                    'slug' => Str::slug($data['name']) . '-' . Str::random(8),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function findByIdAsArray(int $id, array $with = []): ?array
    {
        $row = DB::table(self::TABLE_DEAL_ROOMS)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->rowToArrayWithRelations($row, $with);
    }

    public function findBySlug(string $slug, array $with = []): ?array
    {
        $row = DB::table(self::TABLE_DEAL_ROOMS)->where('slug', $slug)->first();

        if (!$row) {
            return null;
        }

        return $this->rowToArrayWithRelations($row, $with);
    }

    public function findByDealId(int $dealId): ?array
    {
        $row = DB::table(self::TABLE_DEAL_ROOMS)->where('deal_record_id', $dealId)->first();
        return $row ? $this->rowToArray($row) : null;
    }

    public function listDealRooms(
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        array $with = []
    ): PaginatedResult {
        $query = DB::table(self::TABLE_DEAL_ROOMS);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->where('status', self::STATUS_ACTIVE);
        }

        // Filter by user (rooms user is a member of)
        if (!empty($filters['user_id'])) {
            $memberRoomIds = DB::table(self::TABLE_MEMBERS)
                ->where('user_id', $filters['user_id'])
                ->pluck('room_id');
            $query->whereIn('id', $memberRoomIds);
        }

        // Filter by deal record
        if (!empty($filters['deal_record_id'])) {
            $query->where('deal_record_id', $filters['deal_record_id']);
        }

        // Filter by creator
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Apply pagination
        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => $this->rowToArrayWithRelations($row, $with))
            ->all();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function findByUserId(int $userId, array $with = []): array
    {
        $memberRoomIds = DB::table(self::TABLE_MEMBERS)
            ->where('user_id', $userId)
            ->pluck('room_id');

        return DB::table(self::TABLE_DEAL_ROOMS)
            ->whereIn('id', $memberRoomIds)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($row) => $this->rowToArrayWithRelations($row, $with))
            ->all();
    }

    public function findActive(array $with = []): array
    {
        return DB::table(self::TABLE_DEAL_ROOMS)
            ->where('status', self::STATUS_ACTIVE)
            ->get()
            ->map(fn($row) => $this->rowToArrayWithRelations($row, $with))
            ->all();
    }

    public function getEngagementData(int $roomId): array
    {
        $room = DB::table(self::TABLE_DEAL_ROOMS)->where('id', $roomId)->first();

        if (!$room) {
            throw new \RuntimeException("DealRoom not found: {$roomId}");
        }

        $members = DB::table(self::TABLE_MEMBERS)->where('room_id', $roomId)->get();
        $internalCount = $members->whereNotNull('user_id')->count();
        $externalCount = $members->whereNull('user_id')->count();

        $documentIds = DB::table(self::TABLE_DOCUMENTS)
            ->where('room_id', $roomId)
            ->pluck('id');

        $documentViews = DB::table(self::TABLE_DOCUMENT_VIEWS)
            ->whereIn('document_id', $documentIds)
            ->count();

        $actionProgress = $this->getActionPlanProgress($roomId);

        $lastExternalAccess = DB::table(self::TABLE_MEMBERS)
            ->where('room_id', $roomId)
            ->whereNull('user_id')
            ->whereNotNull('last_accessed_at')
            ->orderByDesc('last_accessed_at')
            ->first();

        $messageCount = DB::table(self::TABLE_MESSAGES)->where('room_id', $roomId)->count();

        return [
            'total_members' => $members->count(),
            'internal_members' => $internalCount,
            'external_members' => $externalCount,
            'document_views' => $documentViews,
            'action_plan_progress' => $actionProgress,
            'message_count' => $messageCount,
            'last_external_access' => $lastExternalAccess?->last_accessed_at,
            'activities_last_7_days' => DB::table(self::TABLE_ACTIVITIES)
                ->where('room_id', $roomId)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    // =========================================================================
    // MEMBER QUERIES
    // =========================================================================

    public function listMembers(int $roomId): array
    {
        return DB::table(self::TABLE_MEMBERS)
            ->where('room_id', $roomId)
            ->orderBy('role')
            ->orderBy('created_at')
            ->get()
            ->map(function ($row) {
                $array = (array) $row;
                if ($row->user_id) {
                    $user = DB::table(self::TABLE_USERS)
                        ->select('id', 'name', 'email')
                        ->where('id', $row->user_id)
                        ->first();
                    $array['user'] = $user ? (array) $user : null;
                }
                return $array;
            })
            ->all();
    }

    public function findMemberById(int $memberId): ?array
    {
        $member = DB::table(self::TABLE_MEMBERS)->where('id', $memberId)->first();

        if (!$member) {
            return null;
        }

        $array = (array) $member;
        if ($member->user_id) {
            $user = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $member->user_id)
                ->first();
            $array['user'] = $user ? (array) $user : null;
        }

        return $array;
    }

    public function findMemberByAccessToken(string $token): ?array
    {
        $member = DB::table(self::TABLE_MEMBERS)->where('access_token', $token)->first();

        if (!$member) {
            return null;
        }

        $array = (array) $member;
        if ($member->user_id) {
            $user = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $member->user_id)
                ->first();
            $array['user'] = $user ? (array) $user : null;
        }

        return $array;
    }

    public function isUserMember(int $roomId, int $userId): bool
    {
        return DB::table(self::TABLE_MEMBERS)
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function isEmailMember(int $roomId, string $email): bool
    {
        return DB::table(self::TABLE_MEMBERS)
            ->where('room_id', $roomId)
            ->where('external_email', $email)
            ->exists();
    }

    // =========================================================================
    // DOCUMENT QUERIES
    // =========================================================================

    public function listDocuments(int $roomId, bool $externalOnly = false): array
    {
        $query = DB::table(self::TABLE_DOCUMENTS)->where('room_id', $roomId);

        if ($externalOnly) {
            $query->where('is_external_visible', true);
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($row) {
                $array = (array) $row;
                if ($row->uploaded_by) {
                    $user = DB::table(self::TABLE_USERS)
                        ->select('id', 'name')
                        ->where('id', $row->uploaded_by)
                        ->first();
                    $array['uploader'] = $user ? (array) $user : null;
                }
                return $array;
            })
            ->all();
    }

    public function findDocumentById(int $documentId): ?array
    {
        $document = DB::table(self::TABLE_DOCUMENTS)->where('id', $documentId)->first();

        if (!$document) {
            return null;
        }

        $array = (array) $document;
        if ($document->uploaded_by) {
            $user = DB::table(self::TABLE_USERS)
                ->select('id', 'name')
                ->where('id', $document->uploaded_by)
                ->first();
            $array['uploader'] = $user ? (array) $user : null;
        }

        return $array;
    }

    public function getDocumentAnalytics(int $documentId): array
    {
        $document = DB::table(self::TABLE_DOCUMENTS)->where('id', $documentId)->first();

        if (!$document) {
            throw new \RuntimeException("Document not found: {$documentId}");
        }

        $views = DB::table(self::TABLE_DOCUMENT_VIEWS)
            ->where('document_id', $documentId)
            ->get();

        $memberIds = $views->pluck('member_id')->unique()->all();
        $members = DB::table(self::TABLE_MEMBERS)
            ->whereIn('id', $memberIds)
            ->get()
            ->keyBy('id');

        $userIds = $members->pluck('user_id')->filter()->unique()->all();
        $users = DB::table(self::TABLE_USERS)
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        return [
            'document' => (array) $document,
            'total_views' => $views->count(),
            'unique_viewers' => $views->unique('member_id')->count(),
            'total_time_spent' => $views->sum('time_spent_seconds'),
            'views_by_member' => $views->groupBy('member_id')->map(function ($memberViews) use ($members, $users) {
                $member = $members[$memberViews->first()->member_id] ?? null;
                $memberArray = $member ? (array) $member : null;
                if ($memberArray && isset($memberArray['user_id']) && $memberArray['user_id']) {
                    $user = $users[$memberArray['user_id']] ?? null;
                    $memberArray['user'] = $user ? (array) $user : null;
                }

                return [
                    'member' => $memberArray,
                    'view_count' => $memberViews->count(),
                    'total_time' => $memberViews->sum('time_spent_seconds'),
                    'last_viewed' => $memberViews->max('created_at'),
                ];
            })->values()->all(),
        ];
    }

    // =========================================================================
    // ACTION ITEM QUERIES
    // =========================================================================

    public function listActionItems(int $roomId): array
    {
        return DB::table(self::TABLE_ACTION_ITEMS)
            ->where('room_id', $roomId)
            ->orderBy('display_order')
            ->get()
            ->map(function ($row) {
                $array = (array) $row;

                // Load assignee
                if ($row->assignee_id) {
                    $assignee = DB::table(self::TABLE_MEMBERS)->where('id', $row->assignee_id)->first();
                    if ($assignee) {
                        $assigneeArray = (array) $assignee;
                        if ($assignee->user_id) {
                            $user = DB::table(self::TABLE_USERS)
                                ->select('id', 'name', 'email')
                                ->where('id', $assignee->user_id)
                                ->first();
                            $assigneeArray['user'] = $user ? (array) $user : null;
                        }
                        $array['assignee'] = $assigneeArray;
                    }
                }

                // Load creator
                if ($row->created_by) {
                    $creator = DB::table(self::TABLE_USERS)
                        ->select('id', 'name')
                        ->where('id', $row->created_by)
                        ->first();
                    $array['creator'] = $creator ? (array) $creator : null;
                }

                return $array;
            })
            ->all();
    }

    public function findActionItemById(int $itemId): ?array
    {
        $item = DB::table(self::TABLE_ACTION_ITEMS)->where('id', $itemId)->first();

        if (!$item) {
            return null;
        }

        $array = (array) $item;

        // Load assignee
        if ($item->assignee_id) {
            $assignee = DB::table(self::TABLE_MEMBERS)->where('id', $item->assignee_id)->first();
            if ($assignee) {
                $assigneeArray = (array) $assignee;
                if ($assignee->user_id) {
                    $user = DB::table(self::TABLE_USERS)
                        ->select('id', 'name', 'email')
                        ->where('id', $assignee->user_id)
                        ->first();
                    $assigneeArray['user'] = $user ? (array) $user : null;
                }
                $array['assignee'] = $assigneeArray;
            }
        }

        // Load creator
        if ($item->created_by) {
            $creator = DB::table(self::TABLE_USERS)
                ->select('id', 'name')
                ->where('id', $item->created_by)
                ->first();
            $array['creator'] = $creator ? (array) $creator : null;
        }

        return $array;
    }

    public function getMaxActionItemOrder(int $roomId): int
    {
        return DB::table(self::TABLE_ACTION_ITEMS)
            ->where('room_id', $roomId)
            ->max('display_order') ?? 0;
    }

    // =========================================================================
    // MESSAGE QUERIES
    // =========================================================================

    public function listMessages(int $roomId, int $page = 1, int $perPage = 50): PaginatedResult
    {
        $query = DB::table(self::TABLE_MESSAGES)
            ->where('room_id', $roomId)
            ->orderByDesc('created_at');

        $total = $query->count();

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($row) {
                $array = (array) $row;
                if ($row->member_id) {
                    $member = DB::table(self::TABLE_MEMBERS)->where('id', $row->member_id)->first();
                    if ($member) {
                        $memberArray = (array) $member;
                        if ($member->user_id) {
                            $user = DB::table(self::TABLE_USERS)
                                ->select('id', 'name', 'email')
                                ->where('id', $member->user_id)
                                ->first();
                            $memberArray['user'] = $user ? (array) $user : null;
                        }
                        $array['member'] = $memberArray;
                    }
                }
                return $array;
            })
            ->all();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function findMessageById(int $messageId): ?array
    {
        $message = DB::table(self::TABLE_MESSAGES)->where('id', $messageId)->first();

        if (!$message) {
            return null;
        }

        $array = (array) $message;
        if ($message->member_id) {
            $member = DB::table(self::TABLE_MEMBERS)->where('id', $message->member_id)->first();
            if ($member) {
                $memberArray = (array) $member;
                if ($member->user_id) {
                    $user = DB::table(self::TABLE_USERS)
                        ->select('id', 'name', 'email')
                        ->where('id', $member->user_id)
                        ->first();
                    $memberArray['user'] = $user ? (array) $user : null;
                }
                $array['member'] = $memberArray;
            }
        }

        return $array;
    }

    // =========================================================================
    // ACTIVITY QUERIES
    // =========================================================================

    public function getActivityFeed(int $roomId, int $limit = 100): array
    {
        return DB::table(self::TABLE_ACTIVITIES)
            ->where('room_id', $roomId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $array = (array) $row;
                if ($row->member_id) {
                    $member = DB::table(self::TABLE_MEMBERS)->where('id', $row->member_id)->first();
                    if ($member) {
                        $memberArray = (array) $member;
                        if ($member->user_id) {
                            $user = DB::table(self::TABLE_USERS)
                                ->select('id', 'name', 'email')
                                ->where('id', $member->user_id)
                                ->first();
                            $memberArray['user'] = $user ? (array) $user : null;
                        }
                        $array['member'] = $memberArray;
                    }
                }
                // Decode metadata if it's JSON
                if (isset($array['metadata']) && is_string($array['metadata'])) {
                    $array['metadata'] = json_decode($array['metadata'], true);
                }
                return $array;
            })
            ->all();
    }

    public function getActivityCount(int $roomId, string $startDate, string $endDate): int
    {
        return DB::table(self::TABLE_ACTIVITIES)
            ->where('room_id', $roomId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    // =========================================================================
    // COMMAND METHODS
    // =========================================================================

    public function createDealRoom(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $roomId = DB::table(self::TABLE_DEAL_ROOMS)->insertGetId([
                'deal_record_id' => $data['deal_record_id'] ?? null,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']) . '-' . Str::random(8),
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? self::STATUS_ACTIVE,
                'branding' => json_encode($data['branding'] ?? []),
                'settings' => json_encode($data['settings'] ?? []),
                'created_by' => $data['created_by'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add creator as owner if user_id provided
            if (!empty($data['created_by'])) {
                DB::table(self::TABLE_MEMBERS)->insert([
                    'room_id' => $roomId,
                    'user_id' => $data['created_by'],
                    'role' => self::ROLE_OWNER,
                    'access_token' => Str::random(64),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Log activity
            $this->logActivityInternal($roomId, 'room_created', null, ['name' => $data['name']]);

            return $this->findByIdAsArray($roomId, ['members']);
        });
    }

    public function updateDealRoom(int $id, array $data): array
    {
        $room = DB::table(self::TABLE_DEAL_ROOMS)->where('id', $id)->first();

        if (!$room) {
            throw new \RuntimeException("DealRoom not found: {$id}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['branding'])) {
            $existingBranding = $room->branding ? json_decode($room->branding, true) : [];
            $updateData['branding'] = json_encode(array_merge($existingBranding, $data['branding']));
        }
        if (isset($data['settings'])) {
            $existingSettings = $room->settings ? json_decode($room->settings, true) : [];
            $updateData['settings'] = json_encode(array_merge($existingSettings, $data['settings']));
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        DB::table(self::TABLE_DEAL_ROOMS)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE_DEAL_ROOMS)->where('id', $id)->first();

        return $this->rowToArray($updated);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE_DEAL_ROOMS)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // MEMBER COMMAND METHODS
    // =========================================================================

    public function createMember(int $roomId, array $data): array
    {
        $memberId = DB::table(self::TABLE_MEMBERS)->insertGetId([
            'room_id' => $roomId,
            'user_id' => $data['user_id'] ?? null,
            'external_email' => $data['external_email'] ?? null,
            'external_name' => $data['external_name'] ?? null,
            'role' => $data['role'] ?? self::ROLE_TEAM,
            'access_token' => Str::random(64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivityInternal($roomId, 'member_added', $memberId, [
            'role' => $data['role'] ?? 'team',
            'type' => isset($data['user_id']) ? 'internal' : 'external',
        ]);

        return $this->findMemberById($memberId);
    }

    public function updateMember(int $memberId, array $data): array
    {
        $member = DB::table(self::TABLE_MEMBERS)->where('id', $memberId)->first();

        if (!$member) {
            throw new \RuntimeException("Member not found: {$memberId}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }
        if (isset($data['external_name'])) {
            $updateData['external_name'] = $data['external_name'];
        }

        DB::table(self::TABLE_MEMBERS)->where('id', $memberId)->update($updateData);

        if (isset($data['role'])) {
            $this->logActivityInternal($member->room_id, 'member_role_changed', $memberId, ['role' => $data['role']]);
        }

        return $this->findMemberById($memberId);
    }

    public function deleteMember(int $memberId): bool
    {
        $member = DB::table(self::TABLE_MEMBERS)->where('id', $memberId)->first();

        if (!$member) {
            throw new \RuntimeException("Member not found: {$memberId}");
        }

        $memberName = $member->external_name ?? 'Unknown';
        if ($member->user_id) {
            $user = DB::table(self::TABLE_USERS)->where('id', $member->user_id)->first();
            $memberName = $user?->name ?? $memberName;
        }

        $this->logActivityInternal($member->room_id, 'member_removed', null, ['name' => $memberName]);

        return DB::table(self::TABLE_MEMBERS)->where('id', $memberId)->delete() > 0;
    }

    public function recordMemberAccess(int $memberId): void
    {
        DB::table(self::TABLE_MEMBERS)
            ->where('id', $memberId)
            ->update(['last_accessed_at' => now(), 'updated_at' => now()]);
    }

    // =========================================================================
    // DOCUMENT COMMAND METHODS
    // =========================================================================

    public function createDocument(int $roomId, array $data): array
    {
        $documentId = DB::table(self::TABLE_DOCUMENTS)->insertGetId([
            'room_id' => $roomId,
            'name' => $data['name'],
            'file_path' => $data['file_path'],
            'file_type' => $data['file_type'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'is_external_visible' => $data['is_external_visible'] ?? true,
            'uploaded_by' => $data['uploaded_by'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivityInternal($roomId, 'document_uploaded', null, ['name' => $data['name']]);

        return $this->findDocumentById($documentId);
    }

    public function updateDocument(int $documentId, array $data): array
    {
        $document = DB::table(self::TABLE_DOCUMENTS)->where('id', $documentId)->first();

        if (!$document) {
            throw new \RuntimeException("Document not found: {$documentId}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['is_external_visible'])) {
            $updateData['is_external_visible'] = $data['is_external_visible'];
        }

        DB::table(self::TABLE_DOCUMENTS)->where('id', $documentId)->update($updateData);

        return $this->findDocumentById($documentId);
    }

    public function deleteDocument(int $documentId): bool
    {
        $document = DB::table(self::TABLE_DOCUMENTS)->where('id', $documentId)->first();

        if (!$document) {
            throw new \RuntimeException("Document not found: {$documentId}");
        }

        $this->logActivityInternal($document->room_id, 'document_deleted', null, ['name' => $document->name]);

        return DB::table(self::TABLE_DOCUMENTS)->where('id', $documentId)->delete() > 0;
    }

    public function recordDocumentView(int $documentId, int $memberId, int $timeSpentSeconds): array
    {
        $document = DB::table(self::TABLE_DOCUMENTS)->where('id', $documentId)->first();

        if (!$document) {
            throw new \RuntimeException("Document not found: {$documentId}");
        }

        $viewId = DB::table(self::TABLE_DOCUMENT_VIEWS)->insertGetId([
            'document_id' => $documentId,
            'member_id' => $memberId,
            'time_spent_seconds' => $timeSpentSeconds,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivityInternal($document->room_id, 'document_viewed', $memberId, ['name' => $document->name]);

        $view = DB::table(self::TABLE_DOCUMENT_VIEWS)->where('id', $viewId)->first();

        return (array) $view;
    }

    // =========================================================================
    // ACTION ITEM COMMAND METHODS
    // =========================================================================

    public function createActionItem(int $roomId, array $data): array
    {
        $itemId = DB::table(self::TABLE_ACTION_ITEMS)->insertGetId([
            'room_id' => $roomId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'display_order' => $data['display_order'] ?? ($this->getMaxActionItemOrder($roomId) + 1),
            'created_by' => $data['created_by'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivityInternal($roomId, 'action_item_created', null, ['title' => $data['title']]);

        return $this->findActionItemById($itemId);
    }

    public function updateActionItem(int $itemId, array $data): array
    {
        $item = DB::table(self::TABLE_ACTION_ITEMS)->where('id', $itemId)->first();

        if (!$item) {
            throw new \RuntimeException("ActionItem not found: {$itemId}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['assignee_id'])) {
            $updateData['assignee_id'] = $data['assignee_id'];
        }
        if (isset($data['due_date'])) {
            $updateData['due_date'] = $data['due_date'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['completed_by'])) {
            $updateData['completed_by'] = $data['completed_by'];
            $updateData['completed_at'] = now();
        }

        DB::table(self::TABLE_ACTION_ITEMS)->where('id', $itemId)->update($updateData);

        // Log specific activities
        if (isset($data['status']) && $data['status'] === 'completed' && isset($data['completed_by'])) {
            $this->logActivityInternal($item->room_id, 'action_item_completed', $data['completed_by'], ['title' => $item->title]);
        } elseif (isset($data['status']) && $data['status'] === 'pending') {
            $this->logActivityInternal($item->room_id, 'action_item_reopened', null, ['title' => $item->title]);
        }

        return $this->findActionItemById($itemId);
    }

    public function reorderActionItems(int $roomId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $itemId) {
            DB::table(self::TABLE_ACTION_ITEMS)
                ->where('id', $itemId)
                ->where('room_id', $roomId)
                ->update(['display_order' => $index + 1, 'updated_at' => now()]);
        }
    }

    public function deleteActionItem(int $itemId): bool
    {
        $item = DB::table(self::TABLE_ACTION_ITEMS)->where('id', $itemId)->first();

        if (!$item) {
            throw new \RuntimeException("ActionItem not found: {$itemId}");
        }

        $this->logActivityInternal($item->room_id, 'action_item_deleted', null, ['title' => $item->title]);

        return DB::table(self::TABLE_ACTION_ITEMS)->where('id', $itemId)->delete() > 0;
    }

    // =========================================================================
    // MESSAGE COMMAND METHODS
    // =========================================================================

    public function createMessage(int $roomId, array $data): array
    {
        $messageId = DB::table(self::TABLE_MESSAGES)->insertGetId([
            'room_id' => $roomId,
            'member_id' => $data['member_id'],
            'content' => $data['content'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivityInternal($roomId, 'message_sent', $data['member_id'] ?? null);

        return $this->findMessageById($messageId);
    }

    public function deleteMessage(int $messageId): bool
    {
        return DB::table(self::TABLE_MESSAGES)->where('id', $messageId)->delete() > 0;
    }

    // =========================================================================
    // ACTIVITY COMMAND METHODS
    // =========================================================================

    public function logActivity(int $roomId, string $type, ?int $memberId, array $data): array
    {
        return $this->logActivityInternal($roomId, $type, $memberId, $data);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    private function rowToArray(stdClass $row): array
    {
        $array = (array) $row;

        // Handle JSON fields
        if (isset($array['branding']) && is_string($array['branding'])) {
            $array['branding'] = json_decode($array['branding'], true);
        }
        if (isset($array['settings']) && is_string($array['settings'])) {
            $array['settings'] = json_decode($array['settings'], true);
        }

        return $array;
    }

    private function rowToArrayWithRelations(stdClass $row, array $with = []): array
    {
        $result = $this->rowToArray($row);

        if (in_array('members', $with)) {
            $result['members'] = $this->listMembers($row->id);
        }

        if (in_array('documents', $with)) {
            $result['documents'] = $this->listDocuments($row->id);
        }

        if (in_array('actionItems', $with)) {
            $result['action_items'] = $this->listActionItems($row->id);
        }

        return $result;
    }

    private function getActionPlanProgress(int $roomId): array
    {
        $total = DB::table(self::TABLE_ACTION_ITEMS)->where('room_id', $roomId)->count();
        $completed = DB::table(self::TABLE_ACTION_ITEMS)
            ->where('room_id', $roomId)
            ->where('status', 'completed')
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    private function logActivityInternal(int $roomId, string $type, ?int $memberId, array $data = []): array
    {
        $activityId = DB::table(self::TABLE_ACTIVITIES)->insertGetId([
            'room_id' => $roomId,
            'member_id' => $memberId,
            'type' => $type,
            'metadata' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activity = DB::table(self::TABLE_ACTIVITIES)->where('id', $activityId)->first();

        $array = (array) $activity;
        if (isset($array['metadata']) && is_string($array['metadata'])) {
            $array['metadata'] = json_decode($array['metadata'], true);
        }

        return $array;
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): DealRoomEntity
    {
        return DealRoomEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            dealId: $row->deal_record_id ? (int) $row->deal_record_id : null,
            accountId: $row->account_id ?? null,
            status: DealRoomStatus::from($row->status),
            description: $row->description,
            accessToken: $row->access_token ?? null,
            isPublic: (bool) ($row->is_public ?? false),
            settings: $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            createdBy: $row->created_by ? (int) $row->created_by : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : new DateTimeImmutable(),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(DealRoomEntity $dealRoom): array
    {
        return [
            'name' => $dealRoom->getName(),
            'deal_record_id' => $dealRoom->getDealId(),
            'account_id' => $dealRoom->getAccountId(),
            'status' => $dealRoom->getStatus()->value,
            'description' => $dealRoom->getDescription(),
            'access_token' => $dealRoom->getAccessToken(),
            'is_public' => $dealRoom->isPublic(),
            'settings' => json_encode($dealRoom->getSettings()),
        ];
    }
}
