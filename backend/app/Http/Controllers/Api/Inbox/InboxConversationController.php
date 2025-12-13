<?php

namespace App\Http\Controllers\Api\Inbox;

use App\Http\Controllers\Controller;
use App\Models\SharedInbox;
use App\Models\InboxConversation;
use App\Services\Inbox\InboxService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InboxConversationController extends Controller
{
    protected InboxService $inboxService;

    public function __construct(InboxService $inboxService)
    {
        $this->inboxService = $inboxService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = InboxConversation::with([
            'inbox:id,name,email',
            'assignee:id,name',
        ]);

        // Filter by inbox
        if ($request->filled('inbox_id')) {
            $query->where('inbox_id', $request->inbox_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assignee
        if ($request->filled('assigned_to')) {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($request->assigned_to === 'me') {
                $query->where('assigned_to', auth()->id());
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        // Filter by starred
        if ($request->boolean('starred', false)) {
            $query->starred();
        }

        // Exclude spam by default
        if (!$request->boolean('include_spam', false)) {
            $query->notSpam();
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                  ->orWhere('contact_email', 'ilike', "%{$search}%")
                  ->orWhere('contact_name', 'ilike', "%{$search}%")
                  ->orWhere('snippet', 'ilike', "%{$search}%");
            });
        }

        // Tag filter
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        $conversations = $query->orderBy('last_message_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($conversations);
    }

    public function show(InboxConversation $inboxConversation): JsonResponse
    {
        $inboxConversation->load([
            'inbox:id,name,email',
            'assignee:id,name,email',
            'messages' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
            'messages.sender:id,name',
        ]);

        return response()->json(['data' => $inboxConversation]);
    }

    public function update(Request $request, InboxConversation $inboxConversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:open,pending,resolved,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        // Handle assignment change
        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== $inboxConversation->assigned_to) {
            $inboxConversation = $this->inboxService->assignConversation($inboxConversation, $validated['assigned_to']);
            unset($validated['assigned_to']);
        }

        $inboxConversation->update($validated);

        return response()->json(['data' => $inboxConversation->fresh()]);
    }

    public function reply(Request $request, InboxConversation $inboxConversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
        ]);

        $message = $this->inboxService->sendReply(
            $inboxConversation,
            $validated['body'],
            [
                'cc' => $validated['cc'] ?? [],
                'bcc' => $validated['bcc'] ?? [],
                'user_id' => auth()->id(),
            ]
        );

        return response()->json(['data' => $message], 201);
    }

    public function note(Request $request, InboxConversation $inboxConversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $message = $this->inboxService->addNote(
            $inboxConversation,
            $validated['body'],
            auth()->id()
        );

        return response()->json(['data' => $message], 201);
    }

    public function assign(Request $request, InboxConversation $inboxConversation): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $inboxConversation = $this->inboxService->assignConversation(
            $inboxConversation,
            $validated['user_id']
        );

        return response()->json(['data' => $inboxConversation]);
    }

    public function resolve(InboxConversation $inboxConversation): JsonResponse
    {
        $inboxConversation = $this->inboxService->resolveConversation($inboxConversation);
        return response()->json(['data' => $inboxConversation]);
    }

    public function reopen(InboxConversation $inboxConversation): JsonResponse
    {
        $inboxConversation = $this->inboxService->reopenConversation($inboxConversation);
        return response()->json(['data' => $inboxConversation]);
    }

    public function close(InboxConversation $inboxConversation): JsonResponse
    {
        $inboxConversation = $this->inboxService->closeConversation($inboxConversation);
        return response()->json(['data' => $inboxConversation]);
    }

    public function spam(InboxConversation $inboxConversation): JsonResponse
    {
        $inboxConversation = $this->inboxService->markAsSpam($inboxConversation);
        return response()->json(['data' => $inboxConversation]);
    }

    public function star(InboxConversation $inboxConversation): JsonResponse
    {
        $inboxConversation = $this->inboxService->toggleStar($inboxConversation);
        return response()->json(['data' => $inboxConversation]);
    }

    public function addTag(Request $request, InboxConversation $inboxConversation): JsonResponse
    {
        $validated = $request->validate([
            'tag' => 'required|string|max:50',
        ]);

        $inboxConversation = $this->inboxService->addTag($inboxConversation, $validated['tag']);
        return response()->json(['data' => $inboxConversation]);
    }

    public function removeTag(Request $request, InboxConversation $inboxConversation): JsonResponse
    {
        $validated = $request->validate([
            'tag' => 'required|string',
        ]);

        $inboxConversation = $this->inboxService->removeTag($inboxConversation, $validated['tag']);
        return response()->json(['data' => $inboxConversation]);
    }

    public function merge(Request $request, InboxConversation $inboxConversation): JsonResponse
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array|min:1',
            'conversation_ids.*' => 'exists:inbox_conversations,id',
        ]);

        $inboxConversation = $this->inboxService->mergeConversations(
            $inboxConversation,
            $validated['conversation_ids']
        );

        return response()->json(['data' => $inboxConversation]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array|min:1',
            'conversation_ids.*' => 'exists:inbox_conversations,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $count = $this->inboxService->bulkAssign(
            $validated['conversation_ids'],
            $validated['user_id']
        );

        return response()->json(['assigned_count' => $count]);
    }

    public function bulkResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array|min:1',
            'conversation_ids.*' => 'exists:inbox_conversations,id',
        ]);

        $count = 0;
        foreach ($validated['conversation_ids'] as $id) {
            $conversation = InboxConversation::find($id);
            if ($conversation && $conversation->status !== 'resolved') {
                $this->inboxService->resolveConversation($conversation);
                $count++;
            }
        }

        return response()->json(['resolved_count' => $count]);
    }
}
