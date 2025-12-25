<?php

namespace App\Http\Controllers\Api\TeamChat;

use App\Http\Controllers\Controller;
use App\Services\TeamChat\SlackService;
use App\Services\TeamChat\TeamsService;
use App\Services\TeamChat\TeamChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TeamChatConnectionController extends Controller
{
    protected TeamChatService $teamChatService;

    public function __construct(TeamChatService $teamChatService)
    {
        $this->teamChatService = $teamChatService;
    }

    public function index(): JsonResponse
    {
        $connections = TeamChatConnection::withCount(['channels', 'notifications', 'messages'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $connections]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|in:slack,teams',
            'access_token' => 'required|string',
            'bot_token' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'settings' => 'nullable|array',
        ]);

        $connection = DB::table('team_chat_connections')->insertGetId($validated);

        // Verify connection
        if ($validated['provider'] === 'slack') {
            $service = new SlackService($connection);
            $result = $service->verifyConnection();
            if ($result['success']) {
                $connection->update([
                    'is_verified' => true,
                    'workspace_id' => $result['team_id'] ?? null,
                    'workspace_name' => $result['team_name'] ?? null,
                    'bot_user_id' => $result['bot_id'] ?? null,
                ]);
            }
        } elseif ($validated['provider'] === 'teams') {
            $service = new TeamsService($connection);
            $result = $service->verifyConnection();
            if ($result['success']) {
                $connection->update([
                    'is_verified' => true,
                ]);
            }
        }

        return response()->json(['data' => $connection->refresh()], 201);
    }

    public function show(TeamChatConnection $teamChatConnection): JsonResponse
    {
        $teamChatConnection->loadCount(['channels', 'notifications', 'messages', 'userMappings']);

        return response()->json(['data' => $teamChatConnection]);
    }

    public function update(Request $request, TeamChatConnection $teamChatConnection): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'access_token' => 'sometimes|string',
            'bot_token' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array',
        ]);

        $teamChatConnection->update($validated);

        return response()->json(['data' => $teamChatConnection]);
    }

    public function destroy(TeamChatConnection $teamChatConnection): JsonResponse
    {
        $teamChatConnection->delete();

        return response()->json(null, 204);
    }

    public function verify(TeamChatConnection $teamChatConnection): JsonResponse
    {
        $result = [];

        if ($teamChatConnection->isSlack()) {
            $service = new SlackService($teamChatConnection);
            $result = $service->verifyConnection();
        } elseif ($teamChatConnection->isTeams()) {
            $service = new TeamsService($teamChatConnection);
            $result = $service->verifyConnection();
        }

        if ($result['success'] ?? false) {
            $teamChatConnection->update(['is_verified' => true]);
        }

        return response()->json([
            'data' => $teamChatConnection->refresh(),
            'verification' => $result,
        ]);
    }

    public function syncChannels(TeamChatConnection $teamChatConnection): JsonResponse
    {
        $channels = $this->teamChatService->syncChannels($teamChatConnection);

        return response()->json([
            'data' => $channels,
            'synced_count' => count($channels),
        ]);
    }

    public function syncUsers(TeamChatConnection $teamChatConnection): JsonResponse
    {
        $users = $this->teamChatService->syncUsers($teamChatConnection);

        return response()->json([
            'data' => $users,
            'synced_count' => count($users),
        ]);
    }

    public function channels(TeamChatConnection $teamChatConnection): JsonResponse
    {
        $channels = $teamChatConnection->channels()
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $channels]);
    }

    public function userMappings(TeamChatConnection $teamChatConnection): JsonResponse
    {
        $mappings = $teamChatConnection->userMappings()
            ->with('user:id,name,email')
            ->get();

        return response()->json(['data' => $mappings]);
    }
}
