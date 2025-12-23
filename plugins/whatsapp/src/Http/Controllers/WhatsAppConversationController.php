<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\WhatsApp\Application\Services\WhatsAppApplicationService;

class WhatsAppConversationController extends Controller
{
    public function __construct(
        private readonly WhatsAppApplicationService $whatsAppService,
    ) {}

    /**
     * List conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status',
            'assigned_to',
            'search',
            'module_api_name',
            'record_id',
        ]);

        $perPage = $request->input('per_page', 20);

        $conversations = $this->whatsAppService->listConversations($filters, $perPage);

        return response()->json($conversations);
    }

    /**
     * Start a new conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'template_slug' => 'required|string|max:100',
            'template_params' => 'nullable|array',
            'module_api_name' => 'nullable|string|max:100',
            'record_id' => 'nullable|integer',
        ]);

        // Send initial template message (required by WhatsApp)
        $message = $this->whatsAppService->sendTemplateMessage(
            $validated['phone_number'],
            $validated['template_slug'],
            $validated['template_params'] ?? []
        );

        // Link to record if provided
        if (!empty($validated['module_api_name']) && !empty($validated['record_id'])) {
            $this->whatsAppService->linkConversationToRecord(
                $message['conversation_id'],
                $validated['module_api_name'],
                $validated['record_id']
            );
        }

        $conversation = $this->whatsAppService->getConversation($message['conversation_id']);

        return response()->json(['data' => $conversation], 201);
    }

    /**
     * Get a conversation with messages.
     */
    public function show(int $conversation): JsonResponse
    {
        $data = $this->whatsAppService->getConversation($conversation);

        if (!$data) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Update conversation metadata.
     */
    public function update(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:open,closed,pending',
            'notes' => 'sometimes|nullable|string',
        ]);

        if (isset($validated['status'])) {
            $data = $this->whatsAppService->updateConversationStatus($conversation, $validated['status']);
        } else {
            $data = $this->whatsAppService->getConversation($conversation);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Assign conversation to a user.
     */
    public function assign(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $data = $this->whatsAppService->assignConversation($conversation, $validated['user_id']);

        return response()->json(['data' => $data]);
    }

    /**
     * Close a conversation.
     */
    public function close(int $conversation): JsonResponse
    {
        $data = $this->whatsAppService->updateConversationStatus($conversation, 'closed');

        return response()->json(['data' => $data]);
    }

    /**
     * Reopen a conversation.
     */
    public function reopen(int $conversation): JsonResponse
    {
        $data = $this->whatsAppService->updateConversationStatus($conversation, 'open');

        return response()->json(['data' => $data]);
    }

    /**
     * Link conversation to a CRM record.
     */
    public function linkToRecord(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'module_api_name' => 'required|string|max:100',
            'record_id' => 'required|integer',
        ]);

        $data = $this->whatsAppService->linkConversationToRecord(
            $conversation,
            $validated['module_api_name'],
            $validated['record_id']
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Get WhatsApp statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->whatsAppService->getStats();

        return response()->json(['data' => $stats]);
    }
}
