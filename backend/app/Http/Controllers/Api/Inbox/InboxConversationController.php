<?php

namespace App\Http\Controllers\Api\Inbox;

use App\Application\Services\Inbox\InboxApplicationService;
use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Inbox\InboxService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InboxConversationController extends Controller
{
    public function __construct(
        protected InboxApplicationService $inboxApplicationService,
        protected InboxService $inboxService,
        protected InboxConversationRepositoryInterface $conversationRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [];

        // Filter by inbox
        if ($request->filled('inbox_id')) {
            $filters['inbox_id'] = $request->inbox_id;
        }

        // Filter by status
        if ($request->filled('status')) {
            $filters['status'] = $request->status;
        }

        // Filter by assignee
        if ($request->filled('assigned_to')) {
            if ($request->assigned_to === 'unassigned') {
                $filters['unassigned'] = true;
            } elseif ($request->assigned_to === 'me') {
                $filters['assigned_to'] = auth()->id();
            } else {
                $filters['assigned_to'] = $request->assigned_to;
            }
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $filters['priority'] = $request->priority;
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $filters['channel'] = $request->channel;
        }

        // Filter by starred
        if ($request->boolean('starred', false)) {
            $filters['starred'] = true;
        }

        // Exclude spam by default
        if (!$request->boolean('include_spam', false)) {
            $filters['not_spam'] = true;
        }

        // Search
        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }

        // Tag filter
        if ($request->filled('tag')) {
            $filters['tag'] = $request->tag;
        }

        $filters['sort_by'] = 'last_message_at';
        $filters['sort_dir'] = 'desc';

        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);

        $result = $this->conversationRepository->listConversations($filters, $perPage, $page);

        return response()->json([
            'data' => $result->items,
            'meta' => [
                'current_page' => $result->currentPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->getConversation($id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        return response()->json(['data' => $conversation]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:open,pending,resolved,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        // Handle assignment change separately
        if (array_key_exists('assigned_to', $validated)) {
            $this->conversationRepository->assignConversation($id, $validated['assigned_to']);
            unset($validated['assigned_to']);
        }

        // Handle status change separately
        if (array_key_exists('status', $validated)) {
            $this->conversationRepository->changeStatus($id, $validated['status']);
            unset($validated['status']);
        }

        // Update remaining fields
        if (!empty($validated)) {
            $this->conversationRepository->updateConversation($id, $validated);
        }

        $conversation = $this->conversationRepository->getConversation($id);
        return response()->json(['data' => $conversation]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
        ]);

        $user = auth()->user();
        $message = $this->conversationRepository->sendReply(
            $id,
            [
                'body' => $validated['body'],
                'cc_emails' => $validated['cc'] ?? [],
                'bcc_emails' => $validated['bcc'] ?? [],
            ],
            $user->id,
            $user->name
        );

        return response()->json(['data' => $message], 201);
    }

    public function note(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $user = auth()->user();
        $message = $this->conversationRepository->addNote(
            $id,
            ['body' => $validated['body']],
            $user->id,
            $user->name
        );

        return response()->json(['data' => $message], 201);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $conversation = $this->conversationRepository->assignConversation(
            $id,
            $validated['user_id']
        );

        return response()->json(['data' => $conversation]);
    }

    public function resolve(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->changeStatus($id, 'resolved');
        return response()->json(['data' => $conversation]);
    }

    public function reopen(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->changeStatus($id, 'open');
        return response()->json(['data' => $conversation]);
    }

    public function close(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->changeStatus($id, 'closed');
        return response()->json(['data' => $conversation]);
    }

    public function spam(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->markAsSpam($id);
        return response()->json(['data' => $conversation]);
    }

    public function star(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->toggleStar($id);
        return response()->json(['data' => $conversation]);
    }

    public function addTag(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tag' => 'required|string|max:50',
        ]);

        $conversation = $this->conversationRepository->addTag($id, $validated['tag']);
        return response()->json(['data' => $conversation]);
    }

    public function removeTag(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tag' => 'required|string',
        ]);

        $conversation = $this->conversationRepository->removeTag($id, $validated['tag']);
        return response()->json(['data' => $conversation]);
    }

    public function merge(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array|min:1',
            'conversation_ids.*' => 'integer|exists:inbox_conversations,id',
        ]);

        $conversation = $this->conversationRepository->mergeConversations(
            $id,
            $validated['conversation_ids']
        );

        return response()->json(['data' => $conversation]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array|min:1',
            'conversation_ids.*' => 'integer|exists:inbox_conversations,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $count = 0;
        foreach ($validated['conversation_ids'] as $conversationId) {
            $this->conversationRepository->assignConversation($conversationId, $validated['user_id']);
            $count++;
        }

        return response()->json(['assigned_count' => $count]);
    }

    public function bulkResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array|min:1',
            'conversation_ids.*' => 'integer|exists:inbox_conversations,id',
        ]);

        $count = 0;
        foreach ($validated['conversation_ids'] as $conversationId) {
            $conversation = DB::table('inbox_conversations')->where('id', $conversationId)->first();
            if ($conversation && $conversation->status !== 'resolved') {
                $this->conversationRepository->changeStatus($conversationId, 'resolved');
                $count++;
            }
        }

        return response()->json(['resolved_count' => $count]);
    }
}
