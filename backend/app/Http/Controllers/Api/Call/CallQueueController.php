<?php

namespace App\Http\Controllers\Api\Call;

use App\Http\Controllers\Controller;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Models\User;
use App\Services\Call\CallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CallQueueController extends Controller
{
    public function __construct(protected CallService $callService) {}

    public function index(): JsonResponse
    {
        $queues = CallQueue::with(['provider', 'members.user'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $queues->map(fn($queue) => [
                ...$queue->toArray(),
                'online_agent_count' => $queue->getOnlineAgentCount(),
                'is_within_business_hours' => $queue->isWithinBusinessHours(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider_id' => 'required|exists:call_providers,id',
            'phone_number' => 'nullable|string',
            'routing_strategy' => 'required|in:round_robin,longest_idle,skills_based,random',
            'max_wait_time_seconds' => 'nullable|integer|min:30|max:3600',
            'max_queue_size' => 'nullable|integer|min:1|max:100',
            'welcome_message' => 'nullable|string',
            'hold_music_url' => 'nullable|url',
            'voicemail_greeting' => 'nullable|string',
            'voicemail_enabled' => 'boolean',
            'business_hours' => 'nullable|array',
            'after_hours_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $queue = CallQueue::create([
            ...$validator->validated(),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Queue created successfully',
            'data' => $queue,
        ], 201);
    }

    public function show(CallQueue $callQueue): JsonResponse
    {
        $callQueue->load(['provider', 'members.user']);

        return response()->json([
            'data' => [
                ...$callQueue->toArray(),
                'online_agent_count' => $callQueue->getOnlineAgentCount(),
                'is_within_business_hours' => $callQueue->isWithinBusinessHours(),
                'stats' => $this->callService->getQueueStats($callQueue),
            ],
        ]);
    }

    public function update(Request $request, CallQueue $callQueue): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'routing_strategy' => 'sometimes|in:round_robin,longest_idle,skills_based,random',
            'max_wait_time_seconds' => 'nullable|integer|min:30|max:3600',
            'max_queue_size' => 'nullable|integer|min:1|max:100',
            'welcome_message' => 'nullable|string',
            'hold_music_url' => 'nullable|url',
            'voicemail_greeting' => 'nullable|string',
            'voicemail_enabled' => 'boolean',
            'business_hours' => 'nullable|array',
            'after_hours_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $callQueue->update($validator->validated());

        return response()->json([
            'message' => 'Queue updated successfully',
            'data' => $callQueue->fresh(),
        ]);
    }

    public function destroy(CallQueue $callQueue): JsonResponse
    {
        $callQueue->members()->delete();
        $callQueue->delete();

        return response()->json([
            'message' => 'Queue deleted successfully',
        ]);
    }

    public function toggleActive(CallQueue $callQueue): JsonResponse
    {
        $callQueue->update([
            'is_active' => !$callQueue->is_active,
        ]);

        return response()->json([
            'message' => $callQueue->is_active ? 'Queue activated' : 'Queue deactivated',
            'data' => ['is_active' => $callQueue->is_active],
        ]);
    }

    public function addMember(Request $request, CallQueue $callQueue): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'priority' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = $callQueue->members()->where('user_id', $request->user_id)->first();

        if ($existing) {
            return response()->json(['error' => 'User already in queue'], 422);
        }

        $member = $callQueue->members()->create([
            'user_id' => $request->user_id,
            'priority' => $request->input('priority', 5),
            'is_active' => true,
            'status' => 'offline',
            'calls_handled_today' => 0,
        ]);

        return response()->json([
            'message' => 'Member added to queue',
            'data' => $member->load('user'),
        ], 201);
    }

    public function removeMember(CallQueue $callQueue, int $userId): JsonResponse
    {
        $member = $callQueue->members()->where('user_id', $userId)->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found in queue'], 404);
        }

        $member->delete();

        return response()->json([
            'message' => 'Member removed from queue',
        ]);
    }

    public function updateMember(Request $request, CallQueue $callQueue, int $userId): JsonResponse
    {
        $member = $callQueue->members()->where('user_id', $userId)->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found in queue'], 404);
        }

        $validator = Validator::make($request->all(), [
            'priority' => 'nullable|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $member->update($validator->validated());

        return response()->json([
            'message' => 'Member updated',
            'data' => $member->fresh()->load('user'),
        ]);
    }

    public function setMemberStatus(Request $request, CallQueue $callQueue, int $userId): JsonResponse
    {
        $member = $callQueue->members()->where('user_id', $userId)->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found in queue'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:online,offline,busy,break',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $member->setStatus($request->status);

        return response()->json([
            'message' => 'Status updated',
            'data' => ['status' => $member->status],
        ]);
    }

    public function myStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = CallQueueMember::where('user_id', $user->id)
            ->with('queue')
            ->get();

        return response()->json([
            'data' => $memberships->map(fn($m) => [
                'queue_id' => $m->queue_id,
                'queue_name' => $m->queue->name,
                'status' => $m->status,
                'is_active' => $m->is_active,
                'priority' => $m->priority,
                'calls_handled_today' => $m->calls_handled_today,
                'last_call_at' => $m->last_call_at,
            ]),
        ]);
    }

    public function setMyStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'queue_id' => 'nullable|exists:call_queues,id',
            'status' => 'required|in:online,offline,busy,break',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $query = CallQueueMember::where('user_id', $user->id);

        if ($request->has('queue_id')) {
            $query->where('queue_id', $request->queue_id);
        }

        $query->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status updated for all queues',
        ]);
    }

    public function stats(CallQueue $callQueue): JsonResponse
    {
        return response()->json([
            'data' => $this->callService->getQueueStats($callQueue),
        ]);
    }

    public function resetDailyStats(CallQueue $callQueue): JsonResponse
    {
        $callQueue->members()->update(['calls_handled_today' => 0]);

        return response()->json([
            'message' => 'Daily stats reset for all members',
        ]);
    }
}
