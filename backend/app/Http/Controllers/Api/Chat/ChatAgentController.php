<?php

namespace App\Http\Controllers\Api\Chat;

use App\Application\Services\Chat\ChatApplicationService;
use App\Http\Controllers\Controller;
use App\Models\ChatAgentStatus;
use App\Models\ChatCannedResponse;
use App\Services\Chat\ChatAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatAgentController extends Controller
{
    public function __construct(
        protected ChatApplicationService $chatApplicationService,
        protected ChatAnalyticsService $analyticsService
    ) {}

    // Agent Status Management
    public function getStatus(Request $request): JsonResponse
    {
        $status = ChatAgentStatus::getOrCreate($request->user()->id);

        return response()->json([
            'data' => $this->formatAgentStatus($status),
        ]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:online,away,busy,offline',
            'max_conversations' => 'sometimes|integer|min:1|max:20',
            'departments' => 'nullable|array',
        ]);

        $status = ChatAgentStatus::getOrCreate($request->user()->id);

        match ($validated['status']) {
            'online' => $status->setOnline(),
            'away' => $status->setAway(),
            'busy' => $status->setBusy(),
            'offline' => $status->setOffline(),
        };

        if (isset($validated['max_conversations'])) {
            $status->update(['max_conversations' => $validated['max_conversations']]);
        }

        if (array_key_exists('departments', $validated)) {
            $status->update(['departments' => $validated['departments']]);
        }

        return response()->json([
            'data' => $this->formatAgentStatus($status->fresh()),
            'message' => 'Status updated',
        ]);
    }

    public function listAgents(): JsonResponse
    {
        $agents = ChatAgentStatus::with('user')->get();

        return response()->json([
            'data' => $agents->map(fn($a) => $this->formatAgentStatus($a)),
        ]);
    }

    public function agentPerformance(Request $request): JsonResponse
    {
        $period = $request->query('period', 'month');

        return response()->json([
            'data' => $this->analyticsService->getAgentPerformance($period),
        ]);
    }

    // Canned Responses
    public function listCannedResponses(Request $request): JsonResponse
    {
        $responses = ChatCannedResponse::forUser($request->user()->id)
            ->orderBy('category')
            ->orderBy('shortcut')
            ->get();

        return response()->json([
            'data' => $responses->map(fn($r) => $this->formatCannedResponse($r)),
        ]);
    }

    public function searchCannedResponses(Request $request): JsonResponse
    {
        $query = $request->query('q', '');

        $responses = ChatCannedResponse::forUser($request->user()->id)
            ->search($query)
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $responses->map(fn($r) => $this->formatCannedResponse($r)),
        ]);
    }

    public function storeCannedResponse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shortcut' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'category' => 'nullable|string|max:100',
            'is_global' => 'sometimes|boolean',
        ]);

        $response = ChatCannedResponse::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'is_global' => $validated['is_global'] ?? false,
        ]);

        return response()->json([
            'data' => $this->formatCannedResponse($response),
            'message' => 'Canned response created',
        ], 201);
    }

    public function updateCannedResponse(Request $request, int $id): JsonResponse
    {
        $response = ChatCannedResponse::findOrFail($id);

        // Only creator or admin can update
        if ($response->created_by !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'shortcut' => 'sometimes|string|max:50',
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|max:5000',
            'category' => 'nullable|string|max:100',
            'is_global' => 'sometimes|boolean',
        ]);

        $response->update($validated);

        return response()->json([
            'data' => $this->formatCannedResponse($response),
            'message' => 'Canned response updated',
        ]);
    }

    public function destroyCannedResponse(Request $request, int $id): JsonResponse
    {
        $response = ChatCannedResponse::findOrFail($id);

        if ($response->created_by !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $response->delete();

        return response()->json(['message' => 'Canned response deleted']);
    }

    public function useCannedResponse(Request $request, int $id): JsonResponse
    {
        $response = ChatCannedResponse::findOrFail($id);
        $response->incrementUsage();

        $variables = $request->input('variables', []);
        $content = $response->renderContent($variables);

        return response()->json([
            'data' => [
                'content' => $content,
            ],
        ]);
    }

    private function formatAgentStatus(ChatAgentStatus $status): array
    {
        return [
            'user_id' => $status->user_id,
            'user_name' => $status->user?->name,
            'status' => $status->status,
            'max_conversations' => $status->max_conversations,
            'active_conversations' => $status->active_conversations,
            'is_available' => $status->isAvailable(),
            'departments' => $status->departments,
            'last_activity_at' => $status->last_activity_at?->toISOString(),
        ];
    }

    private function formatCannedResponse(ChatCannedResponse $response): array
    {
        return [
            'id' => $response->id,
            'shortcut' => $response->shortcut,
            'title' => $response->title,
            'content' => $response->content,
            'category' => $response->category,
            'is_global' => $response->is_global,
            'usage_count' => $response->usage_count,
            'created_by' => $response->created_by,
        ];
    }
}
