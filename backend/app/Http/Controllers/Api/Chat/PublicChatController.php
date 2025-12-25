<?php

namespace App\Http\Controllers\Api\Chat;

use App\Application\Services\Chat\ChatApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicChatController extends Controller
{
    public function __construct(
        protected ChatApplicationService $chatApplicationService,
        protected ChatService $chatService
    ) {}

    /**
     * Get widget configuration (public endpoint)
     */
    public function getConfig(string $widgetKey): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        return response()->json([
            'data' => $this->chatService->getWidgetConfig($widget),
        ]);
    }

    /**
     * Initialize visitor session
     */
    public function initSession(Request $request, string $widgetKey): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget || !$widget->is_active) {
            return response()->json(['error' => 'Widget not available'], 404);
        }

        // Check domain
        $origin = $request->header('Origin');
        $domain = $origin ? parse_url($origin, PHP_URL_HOST) : null;
        if (!$widget->isDomainAllowed($domain)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $validated = $request->validate([
            'fingerprint' => 'required|string|max:64',
            'current_page' => 'nullable|string|max:500',
            'referrer' => 'nullable|string|max:500',
        ]);

        $visitor = $this->chatService->getOrCreateVisitor($widget, $validated['fingerprint'], [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'current_page' => $validated['current_page'] ?? null,
            'referrer' => $validated['referrer'] ?? null,
        ]);

        // Check for existing open conversation
        $conversation = DB::table('chat_conversations')->where('visitor_id', $visitor->id)
            ->where('status', ChatConversation::STATUS_OPEN)
            ->with(['messages' => fn($q) => $q->visible()->orderBy('created_at')])
            ->first();

        return response()->json([
            'data' => [
                'visitor_id' => $visitor->id,
                'is_online' => $widget->isOnline(),
                'conversation' => $conversation ? $this->formatConversation($conversation) : null,
            ],
        ]);
    }

    /**
     * Identify visitor (collect email/name)
     */
    public function identify(Request $request, string $widgetKey): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $validated = $request->validate([
            'visitor_id' => 'required|integer',
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $visitor = $widget->visitors()->findOrFail($validated['visitor_id']);
        $visitor->identify($validated['email'], $validated['name'] ?? null);

        return response()->json([
            'data' => [
                'visitor_id' => $visitor->id,
                'contact_id' => $visitor->contact_id,
            ],
            'message' => 'Visitor identified',
        ]);
    }

    /**
     * Start a new conversation
     */
    public function startConversation(Request $request, string $widgetKey): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget || !$widget->is_active) {
            return response()->json(['error' => 'Widget not available'], 404);
        }

        $validated = $request->validate([
            'visitor_id' => 'required|integer',
            'message' => 'required|string|max:5000',
            'department' => 'nullable|string|max:100',
        ]);

        $visitor = $widget->visitors()->findOrFail($validated['visitor_id']);

        $conversation = $this->chatService->startConversation($visitor, [
            'department' => $validated['department'] ?? null,
        ]);

        // Add initial message
        $message = $this->chatService->sendMessage(
            $conversation,
            $validated['message'],
            ChatMessage::SENDER_VISITOR
        );

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'message' => $this->formatMessage($message),
            ],
        ], 201);
    }

    /**
     * Send a message in existing conversation
     */
    public function sendMessage(Request $request, string $widgetKey, int $conversationId): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $validated = $request->validate([
            'visitor_id' => 'required|integer',
            'content' => 'required|string|max:5000',
            'attachments' => 'nullable|array',
        ]);

        $conversation = DB::table('chat_conversations')->where('widget_id', $widget->id)
            ->where('id', $conversationId)
            ->where('visitor_id', $validated['visitor_id'])
            ->firstOrFail();

        if ($conversation->status === ChatConversation::STATUS_CLOSED) {
            // Reopen conversation
            $conversation->reopen();
        }

        $message = $this->chatService->sendMessage(
            $conversation,
            $validated['content'],
            ChatMessage::SENDER_VISITOR,
            null,
            ['attachments' => $validated['attachments'] ?? null]
        );

        return response()->json([
            'data' => $this->formatMessage($message),
        ], 201);
    }

    /**
     * Get conversation messages
     */
    public function getMessages(Request $request, string $widgetKey, int $conversationId): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $visitorId = $request->query('visitor_id');

        $conversation = DB::table('chat_conversations')->where('widget_id', $widget->id)
            ->where('id', $conversationId)
            ->where('visitor_id', $visitorId)
            ->firstOrFail();

        $messages = $conversation->messages()
            ->visible() // Don't show internal notes
            ->orderBy('created_at')
            ->get();

        // Mark agent messages as read
        $messages->where('sender_type', ChatMessage::SENDER_AGENT)
            ->whereNull('read_at')
            ->each(fn($m) => $m->markAsRead());

        return response()->json([
            'data' => $messages->map(fn($m) => $this->formatMessage($m)),
        ]);
    }

    /**
     * Rate conversation
     */
    public function rateConversation(Request $request, string $widgetKey, int $conversationId): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $validated = $request->validate([
            'visitor_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $conversation = DB::table('chat_conversations')->where('widget_id', $widget->id)
            ->where('id', $conversationId)
            ->where('visitor_id', $validated['visitor_id'])
            ->firstOrFail();

        $conversation->update([
            'rating' => $validated['rating'],
            'rating_comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Thank you for your feedback!',
        ]);
    }

    /**
     * Record page view
     */
    public function trackPageView(Request $request, string $widgetKey): JsonResponse
    {
        $widget = DB::table('chat_widgets')->where('widget_key', $widgetKey)->first();

        if (!$widget) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        $validated = $request->validate([
            'visitor_id' => 'required|integer',
            'url' => 'required|string|max:500',
            'title' => 'nullable|string|max:255',
        ]);

        $visitor = $widget->visitors()->findOrFail($validated['visitor_id']);
        $visitor->recordPageView($validated['url'], $validated['title'] ?? null);

        return response()->json(['success' => true]);
    }

    private function formatConversation(ChatConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'agent_name' => $conversation->assignedAgent?->name,
            'messages' => $conversation->messages->map(fn($m) => $this->formatMessage($m)),
        ];
    }

    private function formatMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_name' => $message->getSenderName(),
            'content' => $message->content,
            'content_type' => $message->content_type,
            'attachments' => $message->attachments,
            'created_at' => $message->created_at->toISOString(),
        ];
    }
}
