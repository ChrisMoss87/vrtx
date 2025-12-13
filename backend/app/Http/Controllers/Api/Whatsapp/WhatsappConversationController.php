<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappTemplate;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappConversationController extends Controller
{
    public function __construct(
        private WhatsappService $whatsappService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = WhatsappConversation::with([
            'connection:id,name,display_phone_number',
            'assignedUser:id,name',
        ])->withCount('messages');

        if ($request->filled('connection_id')) {
            $query->where('connection_id', $request->connection_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_to')) {
            if ($request->assigned_to === 'me') {
                $query->where('assigned_to', auth()->id());
            } elseif ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        if ($request->filled('module_api_name') && $request->filled('module_record_id')) {
            $query->where('module_api_name', $request->module_api_name)
                  ->where('module_record_id', $request->module_record_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        $conversations = $query->orderByDesc('last_message_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($conversations);
    }

    public function show(WhatsappConversation $conversation): JsonResponse
    {
        $conversation->load([
            'connection:id,name,display_phone_number',
            'assignedUser:id,name',
            'messages' => fn($q) => $q->latest()->limit(50),
        ]);

        // Mark as read
        $conversation->markAsRead();

        return response()->json(['data' => $conversation]);
    }

    public function messages(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->with(['sender:id,name', 'template:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 50));

        return response()->json($messages);
    }

    public function sendMessage(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:text,template,image,video,audio,document',
            'content' => 'required_if:type,text|nullable|string|max:4096',
            'template_id' => 'required_if:type,template|nullable|exists:whatsapp_templates,id',
            'template_params' => 'nullable|array',
            'media_url' => 'required_if:type,image,video,audio,document|nullable|url',
            'caption' => 'nullable|string|max:1024',
            'filename' => 'nullable|string|max:255',
        ]);

        $connection = $conversation->connection;

        // Check if we can send free-form messages (within 24-hour window)
        if (!in_array($validated['type'], ['template']) && !$conversation->canReceiveMessages()) {
            return response()->json([
                'message' => 'Cannot send free-form messages outside the 24-hour window. Please use a template.',
            ], 400);
        }

        $message = match ($validated['type']) {
            'text' => $this->whatsappService->sendTextMessage(
                $connection,
                $conversation->contact_wa_id,
                $validated['content'],
                auth()->id(),
                $conversation
            ),
            'template' => $this->sendTemplateMessage($conversation, $validated),
            default => $this->whatsappService->sendMediaMessage(
                $connection,
                $conversation->contact_wa_id,
                $validated['type'],
                $validated['media_url'],
                $validated['caption'] ?? null,
                $validated['filename'] ?? null,
                auth()->id(),
                $conversation
            ),
        };

        return response()->json(['data' => $message], 201);
    }

    public function assign(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation->assign($validated['user_id']);

        return response()->json([
            'data' => $conversation->fresh(['assignedUser:id,name']),
        ]);
    }

    public function close(WhatsappConversation $conversation): JsonResponse
    {
        $conversation->close();

        return response()->json(['data' => $conversation->fresh()]);
    }

    public function reopen(WhatsappConversation $conversation): JsonResponse
    {
        $conversation->reopen();

        return response()->json(['data' => $conversation->fresh()]);
    }

    public function linkToRecord(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'module_api_name' => 'required|string|max:100',
            'module_record_id' => 'required|integer',
        ]);

        $conversation = $this->whatsappService->linkToRecord(
            $conversation,
            $validated['module_api_name'],
            $validated['module_record_id']
        );

        return response()->json(['data' => $conversation]);
    }

    public function findByPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        $conversations = $this->whatsappService->findConversationsByPhone($validated['phone']);

        return response()->json(['data' => $conversations]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => 'required|exists:whatsapp_connections,id',
            'phone' => 'required|string|max:50',
            'name' => 'nullable|string|max:255',
            'template_id' => 'required|exists:whatsapp_templates,id',
            'template_params' => 'nullable|array',
            'module_api_name' => 'nullable|string|max:100',
            'module_record_id' => 'nullable|integer',
        ]);

        $connection = WhatsappConnection::findOrFail($validated['connection_id']);

        // Create or get conversation
        $conversation = $this->whatsappService->getOrCreateConversation(
            $connection,
            $validated['phone'],
            $validated['name'] ?? null
        );

        // Link to record if provided
        if (!empty($validated['module_api_name']) && !empty($validated['module_record_id'])) {
            $conversation->linkToRecord($validated['module_api_name'], $validated['module_record_id']);
        }

        // Send template message to initiate
        $template = WhatsappTemplate::findOrFail($validated['template_id']);
        $message = $this->whatsappService->sendTemplateMessage(
            $connection,
            $validated['phone'],
            $template,
            $validated['template_params'] ?? [],
            auth()->id(),
            $conversation
        );

        return response()->json([
            'data' => [
                'conversation' => $conversation->fresh(['connection:id,name']),
                'message' => $message,
            ],
        ], 201);
    }

    private function sendTemplateMessage(WhatsappConversation $conversation, array $validated)
    {
        $template = WhatsappTemplate::findOrFail($validated['template_id']);

        if (!$template->isUsable()) {
            throw new \Exception('Template is not approved for use.');
        }

        return $this->whatsappService->sendTemplateMessage(
            $conversation->connection,
            $conversation->contact_wa_id,
            $template,
            $validated['template_params'] ?? [],
            auth()->id(),
            $conversation
        );
    }
}
