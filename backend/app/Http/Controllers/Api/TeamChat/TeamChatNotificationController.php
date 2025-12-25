<?php

namespace App\Http\Controllers\Api\TeamChat;

use App\Http\Controllers\Controller;
use App\Services\TeamChat\TeamChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TeamChatNotificationController extends Controller
{
    protected TeamChatService $teamChatService;

    public function __construct(TeamChatService $teamChatService)
    {
        $this->teamChatService = $teamChatService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = TeamChatNotification::with(['connection:id,name,provider', 'channel:id,name', 'creator:id,name']);

        if ($request->filled('connection_id')) {
            $query->where('connection_id', $request->connection_id);
        }

        if ($request->filled('trigger_event')) {
            $query->where('trigger_event', $request->trigger_event);
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $notifications]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => 'required|exists:team_chat_connections,id',
            'channel_id' => 'nullable|exists:team_chat_channels,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string|max:100',
            'trigger_module' => 'nullable|string|max:100',
            'trigger_conditions' => 'nullable|array',
            'message_template' => 'required|string',
            'include_mentions' => 'boolean',
            'mention_field' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = auth()->id();

        $notification = DB::table('team_chat_notifications')->insertGetId($validated);

        return response()->json(['data' => $notification], 201);
    }

    public function show(TeamChatNotification $teamChatNotification): JsonResponse
    {
        $teamChatNotification->load(['connection:id,name,provider', 'channel:id,name', 'creator:id,name']);
        $teamChatNotification->loadCount('messages');

        return response()->json(['data' => $teamChatNotification]);
    }

    public function update(Request $request, TeamChatNotification $teamChatNotification): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => 'sometimes|exists:team_chat_connections,id',
            'channel_id' => 'nullable|exists:team_chat_channels,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'sometimes|string|max:100',
            'trigger_module' => 'nullable|string|max:100',
            'trigger_conditions' => 'nullable|array',
            'message_template' => 'sometimes|string',
            'include_mentions' => 'boolean',
            'mention_field' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $teamChatNotification->update($validated);

        return response()->json(['data' => $teamChatNotification]);
    }

    public function destroy(TeamChatNotification $teamChatNotification): JsonResponse
    {
        $teamChatNotification->delete();

        return response()->json(null, 204);
    }

    public function test(Request $request, TeamChatNotification $teamChatNotification): JsonResponse
    {
        $validated = $request->validate([
            'sample_data' => 'nullable|array',
        ]);

        $sampleData = $validated['sample_data'] ?? [
            'name' => 'Test Record',
            'amount' => '$10,000',
            'stage' => 'Closed Won',
            'owner_name' => 'John Doe',
        ];

        $content = $teamChatNotification->renderMessage($sampleData);

        // Get channel ID
        $channelId = $teamChatNotification->channel?->channel_id;
        if (!$channelId) {
            return response()->json(['message' => 'No channel configured'], 400);
        }

        try {
            $message = $this->teamChatService->sendMessage(
                connection: $teamChatNotification->connection,
                channelId: $channelId,
                content: "[TEST] {$content}",
                notificationId: $teamChatNotification->id
            );

            return response()->json([
                'data' => $message,
                'rendered_content' => $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send test message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function events(): JsonResponse
    {
        // Return available trigger events
        $events = [
            ['value' => 'record.created', 'label' => 'Record Created'],
            ['value' => 'record.updated', 'label' => 'Record Updated'],
            ['value' => 'record.deleted', 'label' => 'Record Deleted'],
            ['value' => 'deal.won', 'label' => 'Deal Won'],
            ['value' => 'deal.lost', 'label' => 'Deal Lost'],
            ['value' => 'deal.stage_changed', 'label' => 'Deal Stage Changed'],
            ['value' => 'task.completed', 'label' => 'Task Completed'],
            ['value' => 'task.overdue', 'label' => 'Task Overdue'],
            ['value' => 'activity.created', 'label' => 'Activity Created'],
            ['value' => 'lead.converted', 'label' => 'Lead Converted'],
            ['value' => 'quote.accepted', 'label' => 'Quote Accepted'],
            ['value' => 'quote.rejected', 'label' => 'Quote Rejected'],
            ['value' => 'approval.requested', 'label' => 'Approval Requested'],
            ['value' => 'approval.approved', 'label' => 'Approval Approved'],
            ['value' => 'approval.rejected', 'label' => 'Approval Rejected'],
        ];

        return response()->json(['data' => $events]);
    }

    public function duplicate(TeamChatNotification $teamChatNotification): JsonResponse
    {
        $newNotification = $teamChatNotification->replicate();
        $newNotification->name = $teamChatNotification->name . ' (Copy)';
        $newNotification->triggered_count = 0;
        $newNotification->last_triggered_at = null;
        $newNotification->created_by = auth()->id();
        $newNotification->save();

        return response()->json(['data' => $newNotification], 201);
    }
}
