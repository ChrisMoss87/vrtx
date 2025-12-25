<?php

namespace App\Http\Controllers\Api\Chat;

use App\Application\Services\Chat\ChatApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatConversationController extends Controller
{
    public function __construct(
        protected ChatApplicationService $chatApplicationService,
        protected ChatService $chatService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $assignedTo = $request->query('assigned_to');
        $widgetId = $request->query('widget_id');

        $query = ChatConversation::with(['visitor', 'assignedAgent', 'widget'])
            ->withCount('messages');

        if ($status) {
            $query->where('status', $status);
        }

        if ($assignedTo === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($widgetId) {
            $query->where('widget_id', $widgetId);
        }

        $conversations = $query->orderByDesc('last_message_at')->paginate(50);

        return response()->json([
            'data' => $conversations->map(fn($c) => $this->formatConversation($c)),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $conversation = ChatConversation::with([
            'visitor',
            'assignedAgent',
            'widget',
            'messages' => fn($q) => $q->orderBy('created_at'),
        ])->findOrFail($id);

        return response()->json([
            'data' => $this->formatConversation($conversation, true),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $conversation = DB::table('chat_conversations')->where('id', $id)->first();

        $validated = $request->validate([
            'status' => 'sometimes|in:open,pending,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'department' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ]);

        $conversation->update($validated);

        return response()->json([
            'data' => $this->formatConversation($conversation->fresh()),
            'message' => 'Conversation updated',
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $conversation = DB::table('chat_conversations')->where('id', $id)->first();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if ($conversation->assigned_to) {
            $this->chatService->transferConversation($conversation, $validated['user_id']);
        } else {
            $conversation->assign($validated['user_id']);
        }

        return response()->json([
            'data' => $this->formatConversation($conversation->fresh()),
            'message' => 'Conversation assigned',
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $conversation = DB::table('chat_conversations')->where('id', $id)->first();

        $this->chatService->closeConversation(
            $conversation,
            'This conversation has been closed.'
        );

        return response()->json([
            'data' => $this->formatConversation($conversation->fresh()),
            'message' => 'Conversation closed',
        ]);
    }

    public function reopen(int $id): JsonResponse
    {
        $conversation = DB::table('chat_conversations')->where('id', $id)->first();
        $conversation->reopen();

        $conversation->addMessage(
            'This conversation has been reopened.',
            ChatMessage::SENDER_SYSTEM
        );

        return response()->json([
            'data' => $this->formatConversation($conversation->fresh()),
            'message' => 'Conversation reopened',
        ]);
    }

    public function messages(int $id): JsonResponse
    {
        $conversation = DB::table('chat_conversations')->where('id', $id)->first();

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $messages->map(fn($m) => $this->formatMessage($m)),
        ]);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $conversation = DB::table('chat_conversations')->where('id', $id)->first();

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
            'content_type' => 'sometimes|in:text,html',
            'attachments' => 'nullable|array',
            'is_internal' => 'sometimes|boolean',
        ]);

        $message = $this->chatService->sendMessage(
            $conversation,
            $validated['content'],
            ChatMessage::SENDER_AGENT,
            $request->user()->id,
            [
                'content_type' => $validated['content_type'] ?? 'text',
                'attachments' => $validated['attachments'] ?? null,
                'is_internal' => $validated['is_internal'] ?? false,
            ]
        );

        return response()->json([
            'data' => $this->formatMessage($message),
            'message' => 'Message sent',
        ], 201);
    }

    private function formatConversation(ChatConversation $conversation, bool $includeMessages = false): array
    {
        $data = [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'department' => $conversation->department,
            'subject' => $conversation->subject,
            'tags' => $conversation->tags,
            'message_count' => $conversation->message_count,
            'visitor_message_count' => $conversation->visitor_message_count,
            'agent_message_count' => $conversation->agent_message_count,
            'rating' => $conversation->rating,
            'rating_comment' => $conversation->rating_comment,
            'first_response_at' => $conversation->first_response_at?->toISOString(),
            'resolved_at' => $conversation->resolved_at?->toISOString(),
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            'created_at' => $conversation->created_at->toISOString(),
            'visitor' => $conversation->visitor ? [
                'id' => $conversation->visitor->id,
                'name' => $conversation->visitor->getDisplayName(),
                'email' => $conversation->visitor->email,
                'location' => $conversation->visitor->getLocation(),
                'current_page' => $conversation->visitor->current_page,
            ] : null,
            'assigned_agent' => $conversation->assignedAgent ? [
                'id' => $conversation->assignedAgent->id,
                'name' => $conversation->assignedAgent->name,
            ] : null,
            'widget' => $conversation->widget ? [
                'id' => $conversation->widget->id,
                'name' => $conversation->widget->name,
            ] : null,
        ];

        if ($includeMessages && $conversation->relationLoaded('messages')) {
            $data['messages'] = $conversation->messages->map(fn($m) => $this->formatMessage($m));
        }

        return $data;
    }

    private function formatMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->getSenderName(),
            'content' => $message->content,
            'content_type' => $message->content_type,
            'attachments' => $message->attachments,
            'is_internal' => $message->is_internal,
            'read_at' => $message->read_at?->toISOString(),
            'created_at' => $message->created_at->toISOString(),
        ];
    }
}
