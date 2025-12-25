<?php

namespace App\Http\Controllers\Api\TeamChat;

use App\Http\Controllers\Controller;
use App\Services\TeamChat\TeamChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TeamChatMessageController extends Controller
{
    protected TeamChatService $teamChatService;

    public function __construct(TeamChatService $teamChatService)
    {
        $this->teamChatService = $teamChatService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = TeamChatMessage::with([
            'connection:id,name,provider',
            'channel:id,name',
            'notification:id,name',
            'sender:id,name',
        ]);

        if ($request->filled('connection_id')) {
            $query->where('connection_id', $request->connection_id);
        }

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('module_api_name') && $request->filled('module_record_id')) {
            $query->forRecord($request->module_api_name, $request->module_record_id);
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => 'required|exists:team_chat_connections,id',
            'channel_id' => 'required|exists:team_chat_channels,id',
            'content' => 'required|string|max:4000',
            'module_record_id' => 'nullable|integer',
            'module_api_name' => 'nullable|string',
        ]);

        $connection = TeamChatConnection::findOrFail($validated['connection_id']);
        $channel = TeamChatChannel::findOrFail($validated['channel_id']);

        $message = $this->teamChatService->sendMessage(
            connection: $connection,
            channelId: $channel->channel_id,
            content: $validated['content'],
            recordId: $validated['module_record_id'] ?? null,
            moduleApiName: $validated['module_api_name'] ?? null
        );

        return response()->json(['data' => $message], 201);
    }

    public function show(TeamChatMessage $teamChatMessage): JsonResponse
    {
        $teamChatMessage->load([
            'connection:id,name,provider',
            'channel:id,name',
            'notification:id,name',
            'sender:id,name',
        ]);

        return response()->json(['data' => $teamChatMessage]);
    }

    public function forRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_api_name' => 'required|string',
            'module_record_id' => 'required|integer',
        ]);

        $messages = TeamChatMessage::forRecord($validated['module_api_name'], $validated['module_record_id'])
            ->with([
                'connection:id,name,provider',
                'channel:id,name',
                'sender:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function retry(TeamChatMessage $teamChatMessage): JsonResponse
    {
        if ($teamChatMessage->status !== 'failed') {
            return response()->json(['message' => 'Only failed messages can be retried'], 400);
        }

        $channel = $teamChatMessage->channel;
        if (!$channel) {
            return response()->json(['message' => 'Channel not found'], 400);
        }

        $newMessage = $this->teamChatService->sendMessage(
            connection: $teamChatMessage->connection,
            channelId: $channel->channel_id,
            content: $teamChatMessage->content,
            attachments: $teamChatMessage->attachments,
            recordId: $teamChatMessage->module_record_id,
            moduleApiName: $teamChatMessage->module_api_name,
            notificationId: $teamChatMessage->notification_id
        );

        return response()->json(['data' => $newMessage]);
    }
}
