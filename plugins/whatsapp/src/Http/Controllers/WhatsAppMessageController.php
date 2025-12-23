<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\WhatsApp\Application\Services\WhatsAppApplicationService;
use Plugins\WhatsApp\Domain\Repositories\WhatsAppRepositoryInterface;

class WhatsAppMessageController extends Controller
{
    public function __construct(
        private readonly WhatsAppApplicationService $whatsAppService,
        private readonly WhatsAppRepositoryInterface $repository,
    ) {}

    /**
     * List messages in a conversation.
     */
    public function index(Request $request, int $conversation): JsonResponse
    {
        $perPage = $request->input('per_page', 50);

        $messages = $this->repository->listMessages($conversation, $perPage);

        return response()->json($messages);
    }

    /**
     * Send a message.
     */
    public function send(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:text,template',
            'content' => 'required_if:type,text|nullable|string|max:4096',
            'template_slug' => 'required_if:type,template|nullable|string|max:100',
            'template_params' => 'nullable|array',
        ]);

        $conversationData = $this->whatsAppService->getConversation($conversation);

        if (!$conversationData) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        $message = match ($validated['type']) {
            'text' => $this->whatsAppService->sendTextMessage(
                $conversationData['phone_number'],
                $validated['content'],
                $conversation
            ),
            'template' => $this->whatsAppService->sendTemplateMessage(
                $conversationData['phone_number'],
                $validated['template_slug'],
                $validated['template_params'] ?? [],
                $conversation
            ),
        };

        return response()->json(['data' => $message], 201);
    }

    /**
     * Get a specific message.
     */
    public function show(int $message): JsonResponse
    {
        $data = $this->repository->findMessageById($message);

        if (!$data) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        return response()->json(['data' => $data]);
    }
}
