<?php

declare(strict_types=1);

namespace App\Services\DealRoom;

use App\Models\DealRoom;
use App\Models\DealRoomActionItem;
use App\Models\DealRoomActivity;
use App\Models\DealRoomDocument;
use App\Models\DealRoomMember;
use App\Models\DealRoomMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DealRoomService
{
    /**
     * Get rooms for a user.
     */
    public function getRoomsForUser(int $userId, array $filters = []): Collection
    {
        $query = DealRoom::forUser($userId)
            ->with(['members', 'dealRecord'])
            ->withCount(['actionItems', 'documents', 'messages']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get a room with all relations.
     */
    public function getRoom(int $id): ?DealRoom
    {
        return DealRoom::with([
            'members.user',
            'actionItems.assignee',
            'documents',
            'messages.member',
            'activities.member',
            'dealRecord',
        ])->find($id);
    }

    /**
     * Get room by slug (for public access).
     */
    public function getRoomBySlug(string $slug): ?DealRoom
    {
        return DealRoom::where('slug', $slug)
            ->with([
                'members.user',
                'actionItems.assignee',
                'documents' => fn($q) => $q->visibleToExternal(),
                'messages' => fn($q) => $q->public(),
            ])
            ->first();
    }

    /**
     * Create a new deal room.
     */
    public function createRoom(array $data, int $userId): DealRoom
    {
        return DB::transaction(function () use ($data, $userId) {
            $room = DealRoom::create([
                'deal_record_id' => $data['deal_record_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'branding' => $data['branding'] ?? [],
                'settings' => $data['settings'] ?? [],
                'created_by' => $userId,
            ]);

            // Add creator as owner
            $room->members()->create([
                'user_id' => $userId,
                'role' => DealRoomMember::ROLE_OWNER,
            ]);

            // Log activity
            $room->logActivity(DealRoomActivity::TYPE_ROOM_CREATED);

            return $room;
        });
    }

    /**
     * Update a room.
     */
    public function updateRoom(DealRoom $room, array $data): DealRoom
    {
        $room->update([
            'name' => $data['name'] ?? $room->name,
            'description' => $data['description'] ?? $room->description,
            'status' => $data['status'] ?? $room->status,
            'branding' => $data['branding'] ?? $room->branding,
            'settings' => $data['settings'] ?? $room->settings,
        ]);

        return $room;
    }

    /**
     * Add a member to a room.
     */
    public function addMember(DealRoom $room, array $data): DealRoomMember
    {
        return DB::transaction(function () use ($room, $data) {
            $member = $room->members()->create([
                'user_id' => $data['user_id'] ?? null,
                'external_email' => $data['external_email'] ?? null,
                'external_name' => $data['external_name'] ?? null,
                'role' => $data['role'] ?? DealRoomMember::ROLE_VIEWER,
            ]);

            // Generate access token for external members
            if ($member->isExternal()) {
                $member->generateAccessToken();
            }

            // Log activity
            $room->logActivity(DealRoomActivity::TYPE_MEMBER_JOINED, $member->id, [
                'member_name' => $member->getName(),
            ]);

            return $member;
        });
    }

    /**
     * Remove a member from a room.
     */
    public function removeMember(DealRoom $room, int $memberId): bool
    {
        $member = $room->members()->find($memberId);
        if (!$member) {
            return false;
        }

        $room->logActivity(DealRoomActivity::TYPE_MEMBER_LEFT, null, [
            'member_name' => $member->getName(),
        ]);

        return $member->delete();
    }

    /**
     * Create an action item.
     */
    public function createActionItem(DealRoom $room, array $data, int $userId): DealRoomActionItem
    {
        return DB::transaction(function () use ($room, $data, $userId) {
            $maxOrder = $room->actionItems()->max('display_order') ?? 0;

            $item = $room->actionItems()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'assigned_party' => $data['assigned_party'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'display_order' => $maxOrder + 1,
                'created_by' => $userId,
            ]);

            $room->logActivity(DealRoomActivity::TYPE_ACTION_CREATED, null, [
                'action_title' => $item->title,
            ]);

            return $item;
        });
    }

    /**
     * Update an action item.
     */
    public function updateActionItem(DealRoomActionItem $item, array $data): DealRoomActionItem
    {
        $item->update($data);
        return $item;
    }

    /**
     * Complete an action item.
     */
    public function completeActionItem(DealRoomActionItem $item, int $memberId): DealRoomActionItem
    {
        $item->markComplete($memberId);

        $item->room->logActivity(DealRoomActivity::TYPE_ACTION_COMPLETED, $memberId, [
            'action_title' => $item->title,
        ]);

        return $item;
    }

    /**
     * Upload a document.
     */
    public function uploadDocument(DealRoom $room, array $data, int $userId): DealRoomDocument
    {
        return DB::transaction(function () use ($room, $data, $userId) {
            $doc = $room->documents()->create([
                'name' => $data['name'],
                'file_path' => $data['file_path'],
                'file_size' => $data['file_size'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'description' => $data['description'] ?? null,
                'is_visible_to_external' => $data['is_visible_to_external'] ?? true,
                'uploaded_by' => $userId,
            ]);

            $room->logActivity(DealRoomActivity::TYPE_DOCUMENT_UPLOADED, null, [
                'document_name' => $doc->name,
            ]);

            return $doc;
        });
    }

    /**
     * Record a document view.
     */
    public function recordDocumentView(DealRoomDocument $document, int $memberId, int $timeSpent = 0): void
    {
        $document->recordView($memberId, $timeSpent);

        $document->room->logActivity(DealRoomActivity::TYPE_DOCUMENT_VIEWED, $memberId, [
            'document_name' => $document->name,
            'time_spent' => $timeSpent,
        ]);
    }

    /**
     * Send a message.
     */
    public function sendMessage(DealRoom $room, int $memberId, string $message, bool $isInternal = false): DealRoomMessage
    {
        return DB::transaction(function () use ($room, $memberId, $message, $isInternal) {
            $msg = $room->messages()->create([
                'member_id' => $memberId,
                'message' => $message,
                'is_internal' => $isInternal,
            ]);

            if (!$isInternal) {
                $room->logActivity(DealRoomActivity::TYPE_MESSAGE_SENT, $memberId);
            }

            return $msg;
        });
    }

    /**
     * Get room analytics.
     */
    public function getRoomAnalytics(DealRoom $room): array
    {
        $actionProgress = $room->getActionPlanProgress();

        $documentStats = $room->documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'view_count' => $doc->getViewCount(),
                'unique_viewers' => $doc->getUniqueViewerCount(),
                'total_time_spent' => $doc->getTotalTimeSpent(),
            ];
        });

        $memberEngagement = $room->members->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->getName(),
                'is_internal' => $member->isInternal(),
                'last_accessed' => $member->last_accessed_at,
                'documents_viewed' => $member->documentViews()->count(),
                'messages_sent' => $member->messages()->count(),
            ];
        });

        return [
            'action_plan' => $actionProgress,
            'documents' => $documentStats,
            'member_engagement' => $memberEngagement,
            'activity_count' => $room->activities()->count(),
            'message_count' => $room->messages()->count(),
        ];
    }

    /**
     * Validate access token for external access.
     */
    public function validateAccessToken(string $slug, string $token): ?DealRoomMember
    {
        $room = $this->getRoomBySlug($slug);
        if (!$room) {
            return null;
        }

        $member = $room->members()
            ->where('access_token', $token)
            ->first();

        if (!$member || !$member->isTokenValid()) {
            return null;
        }

        $member->recordAccess();

        $room->logActivity(DealRoomActivity::TYPE_ROOM_ACCESSED, $member->id);

        return $member;
    }
}
