<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DealRoom;

use App\Http\Controllers\Controller;
use App\Models\DealRoom;
use App\Models\DealRoomActionItem;
use App\Models\DealRoomDocument;
use App\Models\DealRoomMember;
use App\Services\DealRoom\DealRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealRoomController extends Controller
{
    public function __construct(
        protected DealRoomService $service
    ) {}

    /**
     * List deal rooms.
     * GET /api/v1/deal-rooms
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:active,won,lost,archived',
        ]);

        $rooms = $this->service->getRoomsForUser(auth()->id(), $validated);

        return response()->json([
            'data' => $rooms->map(fn($room) => $this->formatRoom($room)),
        ]);
    }

    /**
     * Get a deal room.
     * GET /api/v1/deal-rooms/{id}
     */
    public function show(int $id): JsonResponse
    {
        $room = $this->service->getRoom($id);

        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        return response()->json([
            'data' => $this->formatRoomDetails($room),
        ]);
    }

    /**
     * Create a deal room.
     * POST /api/v1/deal-rooms
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deal_record_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'branding' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $room = $this->service->createRoom($validated, auth()->id());

        return response()->json([
            'data' => $this->formatRoom($room),
            'message' => 'Deal room created successfully',
        ], 201);
    }

    /**
     * Update a deal room.
     * PUT /api/v1/deal-rooms/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,won,lost,archived',
            'branding' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $room = $this->service->updateRoom($room, $validated);

        return response()->json([
            'data' => $this->formatRoom($room),
            'message' => 'Deal room updated successfully',
        ]);
    }

    /**
     * Delete a deal room.
     * DELETE /api/v1/deal-rooms/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);
        $room->delete();

        return response()->json([
            'message' => 'Deal room deleted successfully',
        ]);
    }

    /**
     * Get room members.
     * GET /api/v1/deal-rooms/{id}/members
     */
    public function members(int $id): JsonResponse
    {
        $room = DealRoom::with('members.user')->findOrFail($id);

        return response()->json([
            'data' => $room->members->map(fn($m) => $this->formatMember($m)),
        ]);
    }

    /**
     * Add a member.
     * POST /api/v1/deal-rooms/{id}/members
     */
    public function addMember(Request $request, int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'external_email' => 'nullable|email|required_without:user_id',
            'external_name' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:team,stakeholder,viewer',
        ]);

        $member = $this->service->addMember($room, $validated);

        return response()->json([
            'data' => $this->formatMember($member),
            'message' => 'Member added successfully',
        ], 201);
    }

    /**
     * Remove a member.
     * DELETE /api/v1/deal-rooms/{id}/members/{memberId}
     */
    public function removeMember(int $id, int $memberId): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        if (!$this->service->removeMember($room, $memberId)) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }

    /**
     * Get action items.
     * GET /api/v1/deal-rooms/{id}/actions
     */
    public function actions(int $id): JsonResponse
    {
        $room = DealRoom::with('actionItems.assignee')->findOrFail($id);

        return response()->json([
            'data' => $room->actionItems->map(fn($item) => $this->formatActionItem($item)),
            'progress' => $room->getActionPlanProgress(),
        ]);
    }

    /**
     * Create an action item.
     * POST /api/v1/deal-rooms/{id}/actions
     */
    public function createAction(Request $request, int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|integer|exists:deal_room_members,id',
            'assigned_party' => 'nullable|string|in:seller,buyer,both',
            'due_date' => 'nullable|date',
        ]);

        $item = $this->service->createActionItem($room, $validated, auth()->id());

        return response()->json([
            'data' => $this->formatActionItem($item),
            'message' => 'Action item created successfully',
        ], 201);
    }

    /**
     * Update an action item.
     * PUT /api/v1/deal-rooms/{id}/actions/{actionId}
     */
    public function updateAction(Request $request, int $id, int $actionId): JsonResponse
    {
        $room = DealRoom::findOrFail($id);
        $item = $room->actionItems()->findOrFail($actionId);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|integer|exists:deal_room_members,id',
            'assigned_party' => 'nullable|string|in:seller,buyer,both',
            'due_date' => 'nullable|date',
            'status' => 'nullable|string|in:pending,in_progress,completed',
            'display_order' => 'nullable|integer',
        ]);

        $item = $this->service->updateActionItem($item, $validated);

        return response()->json([
            'data' => $this->formatActionItem($item),
            'message' => 'Action item updated successfully',
        ]);
    }

    /**
     * Delete an action item.
     * DELETE /api/v1/deal-rooms/{id}/actions/{actionId}
     */
    public function deleteAction(int $id, int $actionId): JsonResponse
    {
        $room = DealRoom::findOrFail($id);
        $item = $room->actionItems()->findOrFail($actionId);
        $item->delete();

        return response()->json([
            'message' => 'Action item deleted successfully',
        ]);
    }

    /**
     * Get documents.
     * GET /api/v1/deal-rooms/{id}/documents
     */
    public function documents(int $id): JsonResponse
    {
        $room = DealRoom::with('documents.uploader')->findOrFail($id);

        return response()->json([
            'data' => $room->documents->map(fn($doc) => $this->formatDocument($doc)),
        ]);
    }

    /**
     * Upload a document.
     * POST /api/v1/deal-rooms/{id}/documents
     */
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        $validated = $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_visible_to_external' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $path = $file->store("deal-rooms/{$room->id}", 'local');

        $doc = $this->service->uploadDocument($room, [
            'name' => $validated['name'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'description' => $validated['description'] ?? null,
            'is_visible_to_external' => $validated['is_visible_to_external'] ?? true,
        ], auth()->id());

        return response()->json([
            'data' => $this->formatDocument($doc),
            'message' => 'Document uploaded successfully',
        ], 201);
    }

    /**
     * Delete a document.
     * DELETE /api/v1/deal-rooms/{id}/documents/{docId}
     */
    public function deleteDocument(int $id, int $docId): JsonResponse
    {
        $room = DealRoom::findOrFail($id);
        $doc = $room->documents()->findOrFail($docId);
        $doc->delete();

        return response()->json([
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * Get messages.
     * GET /api/v1/deal-rooms/{id}/messages
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        $query = $room->messages()->with('member');

        if (!$request->boolean('include_internal')) {
            $query->public();
        }

        $messages = $query->orderBy('created_at', 'desc')->limit(100)->get();

        return response()->json([
            'data' => $messages->map(fn($msg) => [
                'id' => $msg->id,
                'member' => $msg->member ? $this->formatMember($msg->member) : null,
                'message' => $msg->message,
                'is_internal' => $msg->is_internal,
                'attachments' => $msg->attachments,
                'created_at' => $msg->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Send a message.
     * POST /api/v1/deal-rooms/{id}/messages
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
        ]);

        // Find the member for the current user
        $member = $room->members()->where('user_id', auth()->id())->first();
        if (!$member) {
            return response()->json(['message' => 'You are not a member of this room'], 403);
        }

        $msg = $this->service->sendMessage(
            $room,
            $member->id,
            $validated['message'],
            $validated['is_internal'] ?? false
        );

        return response()->json([
            'data' => [
                'id' => $msg->id,
                'member' => $this->formatMember($member),
                'message' => $msg->message,
                'is_internal' => $msg->is_internal,
                'created_at' => $msg->created_at->toISOString(),
            ],
            'message' => 'Message sent successfully',
        ], 201);
    }

    /**
     * Get room analytics.
     * GET /api/v1/deal-rooms/{id}/analytics
     */
    public function analytics(int $id): JsonResponse
    {
        $room = $this->service->getRoom($id);

        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        return response()->json([
            'data' => $this->service->getRoomAnalytics($room),
        ]);
    }

    /**
     * Get room activity feed.
     * GET /api/v1/deal-rooms/{id}/activities
     */
    public function activities(int $id): JsonResponse
    {
        $room = DealRoom::findOrFail($id);

        $activities = $room->activities()
            ->with('member')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $activities->map(fn($a) => [
                'id' => $a->id,
                'type' => $a->activity_type,
                'description' => $a->getDescription(),
                'member' => $a->member ? $this->formatMember($a->member) : null,
                'data' => $a->activity_data,
                'created_at' => $a->created_at->toISOString(),
            ]),
        ]);
    }

    protected function formatRoom(DealRoom $room): array
    {
        return [
            'id' => $room->id,
            'deal_record_id' => $room->deal_record_id,
            'name' => $room->name,
            'slug' => $room->slug,
            'description' => $room->description,
            'status' => $room->status,
            'action_items_count' => $room->action_items_count ?? $room->actionItems()->count(),
            'documents_count' => $room->documents_count ?? $room->documents()->count(),
            'messages_count' => $room->messages_count ?? $room->messages()->count(),
            'member_count' => $room->members()->count(),
            'created_at' => $room->created_at->toISOString(),
            'updated_at' => $room->updated_at->toISOString(),
        ];
    }

    protected function formatRoomDetails(DealRoom $room): array
    {
        $base = $this->formatRoom($room);
        $base['branding'] = $room->branding;
        $base['settings'] = $room->settings;
        $base['members'] = $room->members->map(fn($m) => $this->formatMember($m));
        $base['action_items'] = $room->actionItems->map(fn($i) => $this->formatActionItem($i));
        $base['documents'] = $room->documents->map(fn($d) => $this->formatDocument($d));
        $base['progress'] = $room->getActionPlanProgress();

        return $base;
    }

    protected function formatMember(DealRoomMember $member): array
    {
        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'name' => $member->getName(),
            'email' => $member->getEmail(),
            'role' => $member->role,
            'is_internal' => $member->isInternal(),
            'last_accessed_at' => $member->last_accessed_at?->toISOString(),
            'access_token' => $member->isExternal() ? $member->access_token : null,
        ];
    }

    protected function formatActionItem(DealRoomActionItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'assigned_to' => $item->assigned_to,
            'assignee_name' => $item->assignee?->getName(),
            'assigned_party' => $item->assigned_party,
            'due_date' => $item->due_date?->format('Y-m-d'),
            'status' => $item->status,
            'display_order' => $item->display_order,
            'is_overdue' => $item->isOverdue(),
            'completed_at' => $item->completed_at?->toISOString(),
        ];
    }

    protected function formatDocument(DealRoomDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'name' => $doc->name,
            'file_size' => $doc->file_size,
            'formatted_size' => $doc->getFormattedFileSize(),
            'mime_type' => $doc->mime_type,
            'version' => $doc->version,
            'description' => $doc->description,
            'is_visible_to_external' => $doc->is_visible_to_external,
            'view_count' => $doc->getViewCount(),
            'uploaded_by' => $doc->uploader?->name,
            'created_at' => $doc->created_at->toISOString(),
        ];
    }
}
