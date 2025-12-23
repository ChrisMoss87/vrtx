<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Communication;

use App\Application\Services\Communication\UnifiedInboxApplicationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnifiedInboxController extends Controller
{
    public function __construct(
        private readonly UnifiedInboxApplicationService $inboxService,
    ) {}

    /**
     * List conversations with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'channel' => 'nullable|string|in:all,email,chat,whatsapp,sms,call,video',
            'status' => 'nullable|string|in:open,pending,resolved,closed',
            'assigned_to' => 'nullable|integer',
            'unassigned' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'linked_module' => 'nullable|string',
            'linked_record_id' => 'nullable|integer',
            'sort_by' => 'nullable|string|in:last_message_at,created_at,status',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        $perPage = min($request->integer('per_page', 20), 100);
        $page = $request->integer('page', 1);

        $result = $this->inboxService->listConversations($filters, $perPage, $page);

        return response()->json($result);
    }

    /**
     * Get a single conversation with messages.
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->inboxService->getConversation($id);

        if (!$result) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Get conversations for a CRM record.
     */
    public function forRecord(string $module, int $recordId): JsonResponse
    {
        $conversations = $this->inboxService->getConversationsForRecord($module, $recordId);

        return response()->json(['data' => $conversations]);
    }

    /**
     * Reply to a conversation.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'html_content' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        try {
            $message = $this->inboxService->replyToConversation($id, $validated);

            return response()->json([
                'data' => $message,
                'message' => 'Reply sent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send reply: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign conversation to a user.
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $conversation = $this->inboxService->assignConversation($id, $validated['user_id']);

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation assigned successfully',
        ]);
    }

    /**
     * Unassign conversation.
     */
    public function unassign(int $id): JsonResponse
    {
        $conversation = $this->inboxService->unassignConversation($id);

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation unassigned successfully',
        ]);
    }

    /**
     * Resolve a conversation.
     */
    public function resolve(int $id): JsonResponse
    {
        $conversation = $this->inboxService->resolveConversation($id);

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation resolved successfully',
        ]);
    }

    /**
     * Close a conversation.
     */
    public function close(int $id): JsonResponse
    {
        $conversation = $this->inboxService->closeConversation($id);

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation closed successfully',
        ]);
    }

    /**
     * Reopen a conversation.
     */
    public function reopen(int $id): JsonResponse
    {
        $conversation = $this->inboxService->reopenConversation($id);

        return response()->json([
            'data' => $conversation,
            'message' => 'Conversation reopened successfully',
        ]);
    }

    /**
     * Link conversation to a CRM record.
     */
    public function link(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'module_api_name' => 'required|string',
            'record_id' => 'required|integer',
        ]);

        $conversation = $this->inboxService->linkToRecord(
            $id,
            $validated['module_api_name'],
            $validated['record_id']
        );

        return response()->json([
            'data' => $conversation,
            'message' => 'Record linked successfully',
        ]);
    }

    /**
     * Unlink conversation from CRM record.
     */
    public function unlink(int $id): JsonResponse
    {
        $conversation = $this->inboxService->unlinkRecord($id);

        return response()->json([
            'data' => $conversation,
            'message' => 'Record unlinked successfully',
        ]);
    }

    /**
     * Add tag to conversation.
     */
    public function addTag(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tag' => 'required|string|max:50',
        ]);

        $conversation = $this->inboxService->addTag($id, $validated['tag']);

        return response()->json([
            'data' => $conversation,
            'message' => 'Tag added successfully',
        ]);
    }

    /**
     * Remove tag from conversation.
     */
    public function removeTag(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tag' => 'required|string|max:50',
        ]);

        $conversation = $this->inboxService->removeTag($id, $validated['tag']);

        return response()->json([
            'data' => $conversation,
            'message' => 'Tag removed successfully',
        ]);
    }

    /**
     * Get inbox statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'channel' => 'nullable|string|in:all,email,chat,whatsapp,sms,call,video',
        ]);

        $stats = $this->inboxService->getStats($filters);

        return response()->json(['data' => $stats]);
    }

    /**
     * Get count by status.
     */
    public function countByStatus(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'channel' => 'nullable|string',
        ]);

        $counts = $this->inboxService->getCountByStatus($filters);

        return response()->json(['data' => $counts]);
    }

    /**
     * Get available channels.
     */
    public function channels(): JsonResponse
    {
        $channels = $this->inboxService->getAvailableChannels();

        return response()->json(['data' => $channels]);
    }

    /**
     * Sync all channels.
     */
    public function sync(): JsonResponse
    {
        $results = $this->inboxService->sync();

        return response()->json([
            'data' => $results,
            'message' => 'Sync completed',
        ]);
    }
}
