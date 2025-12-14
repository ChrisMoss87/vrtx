<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DealRoom;

use App\Application\Services\DealRoom\DealRoomApplicationService;
use App\Http\Controllers\Controller;
use App\Models\DealRoomActionItem;
use App\Services\DealRoom\DealRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDealRoomController extends Controller
{
    public function __construct(
        protected DealRoomApplicationService $dealRoomApplicationService,
        protected DealRoomService $service
    ) {}

    /**
     * Get public room view.
     * GET /rooms/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Access token required'], 401);
        }

        $member = $this->service->validateAccessToken($slug, $token);

        if (!$member) {
            return response()->json(['message' => 'Invalid or expired access token'], 401);
        }

        $room = $member->room;

        return response()->json([
            'data' => [
                'room' => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'description' => $room->description,
                    'branding' => $room->branding,
                ],
                'member' => [
                    'id' => $member->id,
                    'name' => $member->getName(),
                    'role' => $member->role,
                ],
                'action_items' => $room->actionItems->map(fn($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'assigned_party' => $item->assigned_party,
                    'due_date' => $item->due_date?->format('Y-m-d'),
                    'status' => $item->status,
                    'is_overdue' => $item->isOverdue(),
                ]),
                'documents' => $room->documents()
                    ->visibleToExternal()
                    ->get()
                    ->map(fn($doc) => [
                        'id' => $doc->id,
                        'name' => $doc->name,
                        'formatted_size' => $doc->getFormattedFileSize(),
                    ]),
                'progress' => $room->getActionPlanProgress(),
            ],
        ]);
    }

    /**
     * Complete an action item.
     * POST /rooms/{slug}/actions/{id}/complete
     */
    public function completeAction(Request $request, string $slug, int $actionId): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Access token required'], 401);
        }

        $member = $this->service->validateAccessToken($slug, $token);

        if (!$member) {
            return response()->json(['message' => 'Invalid or expired access token'], 401);
        }

        $room = $member->room;
        $item = $room->actionItems()->findOrFail($actionId);

        // External stakeholders can only complete buyer tasks
        if ($item->assigned_party === 'seller') {
            return response()->json(['message' => 'You cannot complete seller tasks'], 403);
        }

        $this->service->completeActionItem($item, $member->id);

        return response()->json([
            'message' => 'Action item completed',
            'progress' => $room->getActionPlanProgress(),
        ]);
    }

    /**
     * Send a message.
     * POST /rooms/{slug}/messages
     */
    public function sendMessage(Request $request, string $slug): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Access token required'], 401);
        }

        $member = $this->service->validateAccessToken($slug, $token);

        if (!$member) {
            return response()->json(['message' => 'Invalid or expired access token'], 401);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $msg = $this->service->sendMessage(
            $member->room,
            $member->id,
            $validated['message'],
            false // External messages are never internal
        );

        return response()->json([
            'data' => [
                'id' => $msg->id,
                'message' => $msg->message,
                'created_at' => $msg->created_at->toISOString(),
            ],
            'message' => 'Message sent successfully',
        ], 201);
    }

    /**
     * Get messages.
     * GET /rooms/{slug}/messages
     */
    public function messages(Request $request, string $slug): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Access token required'], 401);
        }

        $member = $this->service->validateAccessToken($slug, $token);

        if (!$member) {
            return response()->json(['message' => 'Invalid or expired access token'], 401);
        }

        $messages = $member->room->messages()
            ->public()
            ->with('member')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $messages->map(fn($msg) => [
                'id' => $msg->id,
                'sender_name' => $msg->member?->getName() ?? 'Unknown',
                'is_internal_member' => $msg->member?->isInternal() ?? false,
                'message' => $msg->message,
                'created_at' => $msg->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Record document view.
     * POST /rooms/{slug}/documents/{id}/view
     */
    public function recordDocumentView(Request $request, string $slug, int $docId): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Access token required'], 401);
        }

        $member = $this->service->validateAccessToken($slug, $token);

        if (!$member) {
            return response()->json(['message' => 'Invalid or expired access token'], 401);
        }

        $doc = $member->room->documents()
            ->visibleToExternal()
            ->findOrFail($docId);

        $validated = $request->validate([
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $this->service->recordDocumentView(
            $doc,
            $member->id,
            $validated['time_spent'] ?? 0
        );

        return response()->json([
            'message' => 'View recorded',
        ]);
    }
}
