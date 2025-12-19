<?php

declare(strict_types=1);

namespace App\Application\Services\DealRoom;

use App\Domain\DealRoom\Repositories\DealRoomRepositoryInterface;
use App\Models\DealRoom;
use App\Models\DealRoomActionItem;
use App\Models\DealRoomActivity;
use App\Models\DealRoomDocument;
use App\Models\DealRoomDocumentView;
use App\Models\DealRoomMember;
use App\Models\DealRoomMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DealRoomApplicationService
{
    public function __construct(
        private DealRoomRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - DEAL ROOMS
    // =========================================================================

    /**
     * List deal rooms with filtering and pagination.
     */
    public function listDealRooms(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = DealRoom::query()
            ->with(['creator:id,name,email', 'dealRecord']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->active();
        }

        // Filter by user (rooms user is a member of)
        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
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
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a deal room by ID.
     */
    public function getDealRoom(int $id): ?DealRoom
    {
        return DealRoom::with([
            'creator:id,name,email',
            'dealRecord',
            'members.user:id,name,email',
            'actionItems',
            'documents',
        ])->find($id);
    }

    /**
     * Get a deal room by slug (for external access).
     */
    public function getDealRoomBySlug(string $slug): ?DealRoom
    {
        return DealRoom::with([
            'members.user:id,name,email',
            'actionItems',
            'documents' => function ($q) {
                $q->visibleToExternal();
            },
        ])->where('slug', $slug)->first();
    }

    /**
     * Get deal rooms for a user.
     */
    public function getUserDealRooms(int $userId): Collection
    {
        return DealRoom::forUser($userId)
            ->with(['dealRecord', 'members'])
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Get deal room with full activity timeline.
     */
    public function getDealRoomWithTimeline(int $id, int $limit = 50): array
    {
        $room = $this->getDealRoom($id);

        if (!$room) {
            return [];
        }

        $activities = DealRoomActivity::where('room_id', $id)
            ->with('member.user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'room' => $room,
            'activities' => $activities,
        ];
    }

    /**
     * Get deal room analytics/engagement data.
     */
    public function getDealRoomEngagement(int $roomId): array
    {
        $room = DealRoom::findOrFail($roomId);

        $members = $room->members()->with('user:id,name,email')->get();
        $internalCount = $members->where('user_id', '!=', null)->count();
        $externalCount = $members->where('user_id', null)->count();

        $documentViews = DealRoomDocumentView::whereHas('document', function ($q) use ($roomId) {
            $q->where('room_id', $roomId);
        })->count();

        $actionProgress = $room->getActionPlanProgress();

        $lastExternalAccess = $room->externalMembers()
            ->whereNotNull('last_accessed_at')
            ->orderBy('last_accessed_at', 'desc')
            ->first();

        $messageCount = $room->messages()->count();

        return [
            'total_members' => $members->count(),
            'internal_members' => $internalCount,
            'external_members' => $externalCount,
            'document_views' => $documentViews,
            'action_plan_progress' => $actionProgress,
            'message_count' => $messageCount,
            'last_external_access' => $lastExternalAccess?->last_accessed_at,
            'activities_last_7_days' => $room->activities()
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    // =========================================================================
    // COMMAND USE CASES - DEAL ROOMS
    // =========================================================================

    /**
     * Create a new deal room.
     */
    public function createDealRoom(array $data): DealRoom
    {
        return DB::transaction(function () use ($data) {
            $room = DealRoom::create([
                'deal_record_id' => $data['deal_record_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => DealRoom::STATUS_ACTIVE,
                'branding' => $data['branding'] ?? [],
                'settings' => $data['settings'] ?? [],
                'created_by' => Auth::id(),
            ]);

            // Add creator as owner
            $room->members()->create([
                'user_id' => Auth::id(),
                'role' => DealRoomMember::ROLE_OWNER,
            ]);

            // Log activity
            $room->logActivity('room_created', null, ['name' => $room->name]);

            return $room->load(['members.user:id,name,email']);
        });
    }

    /**
     * Update a deal room.
     */
    public function updateDealRoom(int $id, array $data): DealRoom
    {
        $room = DealRoom::findOrFail($id);

        $room->update([
            'name' => $data['name'] ?? $room->name,
            'description' => $data['description'] ?? $room->description,
            'branding' => array_merge($room->branding ?? [], $data['branding'] ?? []),
            'settings' => array_merge($room->settings ?? [], $data['settings'] ?? []),
        ]);

        return $room->fresh();
    }

    /**
     * Update deal room status.
     */
    public function updateStatus(int $id, string $status): DealRoom
    {
        $room = DealRoom::findOrFail($id);

        if (!in_array($status, [
            DealRoom::STATUS_ACTIVE,
            DealRoom::STATUS_WON,
            DealRoom::STATUS_LOST,
            DealRoom::STATUS_ARCHIVED,
        ])) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $room->update(['status' => $status]);
        $room->logActivity('status_changed', null, ['status' => $status]);

        return $room->fresh();
    }

    /**
     * Archive a deal room.
     */
    public function archiveDealRoom(int $id): DealRoom
    {
        return $this->updateStatus($id, DealRoom::STATUS_ARCHIVED);
    }

    /**
     * Delete a deal room.
     */
    public function deleteDealRoom(int $id): bool
    {
        $room = DealRoom::findOrFail($id);
        return $room->delete();
    }

    // =========================================================================
    // MEMBER USE CASES
    // =========================================================================

    /**
     * List members for a deal room.
     */
    public function listMembers(int $roomId): Collection
    {
        return DealRoomMember::where('room_id', $roomId)
            ->with('user:id,name,email')
            ->orderBy('role')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Add an internal member (user).
     */
    public function addInternalMember(int $roomId, int $userId, string $role = DealRoomMember::ROLE_TEAM): DealRoomMember
    {
        $room = DealRoom::findOrFail($roomId);

        // Check if already a member
        $existing = $room->members()->where('user_id', $userId)->first();
        if ($existing) {
            throw new \InvalidArgumentException('User is already a member');
        }

        $member = $room->members()->create([
            'user_id' => $userId,
            'role' => $role,
        ]);

        $room->logActivity('member_added', $member->id, ['role' => $role, 'type' => 'internal']);

        return $member->load('user:id,name,email');
    }

    /**
     * Add an external member (by email).
     */
    public function addExternalMember(int $roomId, string $email, string $name, string $role = DealRoomMember::ROLE_STAKEHOLDER): DealRoomMember
    {
        $room = DealRoom::findOrFail($roomId);

        // Check if already a member
        $existing = $room->members()->where('external_email', $email)->first();
        if ($existing) {
            throw new \InvalidArgumentException('Email is already a member');
        }

        $member = $room->members()->create([
            'external_email' => $email,
            'external_name' => $name,
            'role' => $role,
        ]);

        // Generate access token
        $member->generateAccessToken();

        $room->logActivity('member_added', $member->id, ['role' => $role, 'type' => 'external']);

        return $member;
    }

    /**
     * Update a member's role.
     */
    public function updateMemberRole(int $memberId, string $role): DealRoomMember
    {
        $member = DealRoomMember::findOrFail($memberId);

        if (!in_array($role, array_keys(DealRoomMember::getRoles()))) {
            throw new \InvalidArgumentException('Invalid role');
        }

        $member->update(['role' => $role]);

        $member->room->logActivity('member_role_changed', $memberId, ['role' => $role]);

        return $member->fresh();
    }

    /**
     * Remove a member.
     */
    public function removeMember(int $memberId): bool
    {
        $member = DealRoomMember::findOrFail($memberId);
        $room = $member->room;

        $room->logActivity('member_removed', null, ['name' => $member->getName()]);

        return $member->delete();
    }

    /**
     * Regenerate access token for external member.
     */
    public function regenerateAccessToken(int $memberId): string
    {
        $member = DealRoomMember::findOrFail($memberId);

        if (!$member->isExternal()) {
            throw new \InvalidArgumentException('Can only regenerate tokens for external members');
        }

        return $member->generateAccessToken();
    }

    /**
     * Validate access token and record access.
     */
    public function validateAndRecordAccess(string $token): ?DealRoomMember
    {
        $member = DealRoomMember::where('access_token', $token)->first();

        if (!$member || !$member->isTokenValid()) {
            return null;
        }

        $member->recordAccess();

        return $member;
    }

    // =========================================================================
    // DOCUMENT USE CASES
    // =========================================================================

    /**
     * List documents for a deal room.
     */
    public function listDocuments(int $roomId, bool $externalOnly = false): Collection
    {
        $query = DealRoomDocument::where('room_id', $roomId)
            ->with('uploader:id,name');

        if ($externalOnly) {
            $query->visibleToExternal();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Upload a document.
     */
    public function uploadDocument(int $roomId, array $data, $file): DealRoomDocument
    {
        $room = DealRoom::findOrFail($roomId);

        // Store the file
        $path = $file->store("deal-rooms/{$roomId}/documents", 'private');

        $document = $room->documents()->create([
            'name' => $data['name'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'version' => 1,
            'description' => $data['description'] ?? null,
            'is_visible_to_external' => $data['is_visible_to_external'] ?? true,
            'uploaded_by' => Auth::id(),
        ]);

        $room->logActivity('document_uploaded', null, ['name' => $document->name]);

        return $document->load('uploader:id,name');
    }

    /**
     * Update document metadata.
     */
    public function updateDocument(int $documentId, array $data): DealRoomDocument
    {
        $document = DealRoomDocument::findOrFail($documentId);

        $document->update([
            'name' => $data['name'] ?? $document->name,
            'description' => $data['description'] ?? $document->description,
            'is_visible_to_external' => $data['is_visible_to_external'] ?? $document->is_visible_to_external,
        ]);

        return $document->fresh();
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(int $documentId): bool
    {
        $document = DealRoomDocument::findOrFail($documentId);
        $room = $document->room;

        // Delete the file
        if ($document->file_path) {
            Storage::disk('private')->delete($document->file_path);
        }

        $room->logActivity('document_deleted', null, ['name' => $document->name]);

        return $document->delete();
    }

    /**
     * Track document view.
     */
    public function trackDocumentView(int $documentId, int $memberId, int $timeSpentSeconds = 0): DealRoomDocumentView
    {
        $document = DealRoomDocument::findOrFail($documentId);

        $view = $document->recordView($memberId, $timeSpentSeconds);

        $document->room->logActivity('document_viewed', $memberId, ['name' => $document->name]);

        return $view;
    }

    /**
     * Get document analytics.
     */
    public function getDocumentAnalytics(int $documentId): array
    {
        $document = DealRoomDocument::findOrFail($documentId);

        $views = $document->views()->with('member.user:id,name,email')->get();

        return [
            'document' => $document,
            'total_views' => $views->count(),
            'unique_viewers' => $views->unique('member_id')->count(),
            'total_time_spent' => $views->sum('time_spent_seconds'),
            'views_by_member' => $views->groupBy('member_id')->map(function ($memberViews) {
                return [
                    'member' => $memberViews->first()->member,
                    'view_count' => $memberViews->count(),
                    'total_time' => $memberViews->sum('time_spent_seconds'),
                    'last_viewed' => $memberViews->max('created_at'),
                ];
            })->values(),
        ];
    }

    // =========================================================================
    // ACTION ITEM USE CASES
    // =========================================================================

    /**
     * List action items for a deal room.
     */
    public function listActionItems(int $roomId): Collection
    {
        return DealRoomActionItem::where('room_id', $roomId)
            ->with(['assignee.user:id,name,email', 'creator:id,name'])
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Create an action item.
     */
    public function createActionItem(int $roomId, array $data): DealRoomActionItem
    {
        $room = DealRoom::findOrFail($roomId);

        // Get max display order
        $maxOrder = $room->actionItems()->max('display_order') ?? 0;

        $item = $room->actionItems()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'assigned_party' => $data['assigned_party'] ?? DealRoomActionItem::PARTY_SELLER,
            'due_date' => $data['due_date'] ?? null,
            'status' => DealRoomActionItem::STATUS_PENDING,
            'display_order' => $maxOrder + 1,
            'created_by' => Auth::id(),
        ]);

        $room->logActivity('action_item_created', null, ['title' => $item->title]);

        return $item->load(['assignee.user:id,name,email']);
    }

    /**
     * Update an action item.
     */
    public function updateActionItem(int $itemId, array $data): DealRoomActionItem
    {
        $item = DealRoomActionItem::findOrFail($itemId);

        $item->update([
            'title' => $data['title'] ?? $item->title,
            'description' => $data['description'] ?? $item->description,
            'assigned_to' => $data['assigned_to'] ?? $item->assigned_to,
            'assigned_party' => $data['assigned_party'] ?? $item->assigned_party,
            'due_date' => $data['due_date'] ?? $item->due_date,
        ]);

        return $item->fresh(['assignee.user:id,name,email']);
    }

    /**
     * Complete an action item.
     */
    public function completeActionItem(int $itemId, int $memberId): DealRoomActionItem
    {
        $item = DealRoomActionItem::findOrFail($itemId);

        $item->markComplete($memberId);

        $item->room->logActivity('action_item_completed', $memberId, ['title' => $item->title]);

        return $item->fresh();
    }

    /**
     * Reopen an action item.
     */
    public function reopenActionItem(int $itemId): DealRoomActionItem
    {
        $item = DealRoomActionItem::findOrFail($itemId);

        $item->markPending();

        $item->room->logActivity('action_item_reopened', null, ['title' => $item->title]);

        return $item->fresh();
    }

    /**
     * Reorder action items.
     */
    public function reorderActionItems(int $roomId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $itemId) {
            DealRoomActionItem::where('id', $itemId)
                ->where('room_id', $roomId)
                ->update(['display_order' => $index + 1]);
        }
    }

    /**
     * Delete an action item.
     */
    public function deleteActionItem(int $itemId): bool
    {
        $item = DealRoomActionItem::findOrFail($itemId);
        $room = $item->room;

        $room->logActivity('action_item_deleted', null, ['title' => $item->title]);

        return $item->delete();
    }

    // =========================================================================
    // MESSAGE USE CASES
    // =========================================================================

    /**
     * List messages for a deal room.
     */
    public function listMessages(int $roomId, int $perPage = 50): LengthAwarePaginator
    {
        return DealRoomMessage::where('room_id', $roomId)
            ->with('member.user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Send a message.
     */
    public function sendMessage(int $roomId, int $memberId, string $content, ?array $attachments = null): DealRoomMessage
    {
        $room = DealRoom::findOrFail($roomId);

        $message = $room->messages()->create([
            'member_id' => $memberId,
            'content' => $content,
            'attachments' => $attachments,
        ]);

        $room->logActivity('message_sent', $memberId);

        return $message->load('member.user:id,name,email');
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(int $messageId): bool
    {
        $message = DealRoomMessage::findOrFail($messageId);
        return $message->delete();
    }

    // =========================================================================
    // ACTIVITY USE CASES
    // =========================================================================

    /**
     * Get activity feed for a deal room.
     */
    public function getActivityFeed(int $roomId, int $limit = 100): Collection
    {
        return DealRoomActivity::where('room_id', $roomId)
            ->with('member.user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Log custom activity.
     */
    public function logActivity(int $roomId, string $type, ?int $memberId = null, array $data = []): DealRoomActivity
    {
        $room = DealRoom::findOrFail($roomId);
        return $room->logActivity($type, $memberId, $data);
    }
}
