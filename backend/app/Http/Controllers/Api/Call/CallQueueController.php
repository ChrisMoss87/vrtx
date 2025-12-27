<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Call;

use App\Application\Services\Call\CallApplicationService;
use App\Domain\Call\Repositories\CallQueueRepositoryInterface;
use App\Domain\Call\Repositories\CallQueueMemberRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Call\CallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CallQueueController extends Controller
{
    public function __construct(
        private readonly CallApplicationService $callApplicationService,
        private readonly CallService $callService,
        private readonly CallQueueRepositoryInterface $queueRepository,
        private readonly CallQueueMemberRepositoryInterface $memberRepository
    ) {}

    public function index(): JsonResponse
    {
        $queues = $this->queueRepository->findAllWithRelations();

        return response()->json([
            'data' => $queues,
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

        $queue = $this->queueRepository->create($validator->validated());

        return response()->json([
            'message' => 'Queue created successfully',
            'data' => $queue,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $queue = $this->queueRepository->findByIdWithRelations($id);

        if (!$queue) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        $queue['stats'] = $this->callService->getQueueStatsById($id);

        return response()->json([
            'data' => $queue,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->queueRepository->exists($id)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

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

        $queue = $this->queueRepository->update($id, $validator->validated());

        return response()->json([
            'message' => 'Queue updated successfully',
            'data' => $queue,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->queueRepository->exists($id)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        $this->queueRepository->delete($id);

        return response()->json([
            'message' => 'Queue deleted successfully',
        ]);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $queue = $this->queueRepository->toggleActive($id);

        if (!$queue) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        return response()->json([
            'message' => $queue['is_active'] ? 'Queue activated' : 'Queue deactivated',
            'data' => ['is_active' => $queue['is_active']],
        ]);
    }

    public function addMember(Request $request, int $queueId): JsonResponse
    {
        if (!$this->queueRepository->exists($queueId)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'priority' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($this->memberRepository->exists($queueId, (int) $request->user_id)) {
            return response()->json(['error' => 'User already in queue'], 422);
        }

        $member = $this->memberRepository->create($queueId, [
            'user_id' => (int) $request->user_id,
            'priority' => $request->input('priority', 5),
            'is_active' => true,
            'status' => 'offline',
            'calls_handled_today' => 0,
        ]);

        return response()->json([
            'message' => 'Member added to queue',
            'data' => $member,
        ], 201);
    }

    public function removeMember(int $queueId, int $userId): JsonResponse
    {
        if (!$this->queueRepository->exists($queueId)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        if (!$this->memberRepository->exists($queueId, $userId)) {
            return response()->json(['error' => 'Member not found in queue'], 404);
        }

        $this->memberRepository->delete($queueId, $userId);

        return response()->json([
            'message' => 'Member removed from queue',
        ]);
    }

    public function updateMember(Request $request, int $queueId, int $userId): JsonResponse
    {
        if (!$this->queueRepository->exists($queueId)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        if (!$this->memberRepository->exists($queueId, $userId)) {
            return response()->json(['error' => 'Member not found in queue'], 404);
        }

        $validator = Validator::make($request->all(), [
            'priority' => 'nullable|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $member = $this->memberRepository->update($queueId, $userId, $validator->validated());

        return response()->json([
            'message' => 'Member updated',
            'data' => $member,
        ]);
    }

    public function setMemberStatus(Request $request, int $queueId, int $userId): JsonResponse
    {
        if (!$this->queueRepository->exists($queueId)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        if (!$this->memberRepository->exists($queueId, $userId)) {
            return response()->json(['error' => 'Member not found in queue'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:online,offline,busy,break',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->memberRepository->setStatus($queueId, $userId, $request->status);

        return response()->json([
            'message' => 'Status updated',
            'data' => ['status' => $request->status],
        ]);
    }

    public function myStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = $this->memberRepository->findByUserId((int) $user->id);

        return response()->json([
            'data' => $memberships,
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
        $queueId = $request->has('queue_id') ? (int) $request->queue_id : null;

        $this->memberRepository->setStatusForUser((int) $user->id, $request->status, $queueId);

        return response()->json([
            'message' => 'Status updated for all queues',
        ]);
    }

    public function stats(int $id): JsonResponse
    {
        if (!$this->queueRepository->exists($id)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        return response()->json([
            'data' => $this->callService->getQueueStatsById($id),
        ]);
    }

    public function resetDailyStats(int $id): JsonResponse
    {
        if (!$this->queueRepository->exists($id)) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        $this->memberRepository->resetDailyStats($id);

        return response()->json([
            'message' => 'Daily stats reset for all members',
        ]);
    }
}
