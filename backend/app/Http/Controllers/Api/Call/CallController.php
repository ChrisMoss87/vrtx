<?php

namespace App\Http\Controllers\Api\Call;

use App\Application\Services\Call\CallApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallProvider;
use App\Services\Call\CallService;
use App\Services\Call\TranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CallController extends Controller
{
    public function __construct(
        protected CallApplicationService $callApplicationService,
        protected CallService $callService,
        protected TranscriptionService $transcriptionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Call::with(['user', 'provider'])
            ->orderBy('created_at', 'desc');

        if ($request->has('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->boolean('with_recording')) {
            $query->withRecording();
        }

        $calls = $query->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => $calls->items(),
            'meta' => [
                'current_page' => $calls->currentPage(),
                'last_page' => $calls->lastPage(),
                'per_page' => $calls->perPage(),
                'total' => $calls->total(),
            ],
        ]);
    }

    public function show(Call $call): JsonResponse
    {
        $call->load(['user', 'provider', 'transcription']);

        return response()->json([
            'data' => [
                ...$call->toArray(),
                'formatted_duration' => $call->getFormattedDuration(),
                'has_recording' => $call->hasRecording(),
                'has_transcription' => $call->hasTranscription(),
            ],
        ]);
    }

    public function initiate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|exists:call_providers,id',
            'to_number' => 'required|string',
            'from_number' => 'nullable|string',
            'contact_id' => 'nullable|integer',
            'contact_module' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $provider = CallProvider::findOrFail($request->provider_id);

        if (!$provider->is_active) {
            return response()->json(['error' => 'Provider is not active'], 422);
        }

        $result = $this->callService->initiateCall(
            $provider,
            $request->to_number,
            $request->user(),
            [
                'from_number' => $request->from_number,
                'contact_id' => $request->contact_id,
                'contact_module' => $request->contact_module,
                'metadata' => $request->metadata,
            ]
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Call initiated successfully',
            'data' => [
                'call_id' => $result['call_id'],
                'external_call_id' => $result['external_call_id'],
            ],
        ]);
    }

    public function end(Call $call): JsonResponse
    {
        if (in_array($call->status, ['completed', 'canceled', 'failed'])) {
            return response()->json(['error' => 'Call already ended'], 422);
        }

        $this->callService->endCall($call);

        return response()->json([
            'message' => 'Call ended successfully',
        ]);
    }

    public function transfer(Request $request, Call $call): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = $this->callService->getProviderService($call->provider);

        if (!$service) {
            return response()->json(['error' => 'Provider not supported'], 422);
        }

        $success = $service->transferCall($call->external_call_id, $request->to_number);

        if (!$success) {
            return response()->json(['error' => 'Failed to transfer call'], 422);
        }

        $call->update([
            'metadata' => array_merge($call->metadata ?? [], [
                'transferred_to' => $request->to_number,
                'transferred_at' => now()->toISOString(),
            ]),
        ]);

        return response()->json([
            'message' => 'Call transferred successfully',
        ]);
    }

    public function hold(Call $call): JsonResponse
    {
        $service = $this->callService->getProviderService($call->provider);

        if (!$service) {
            return response()->json(['error' => 'Provider not supported'], 422);
        }

        $success = $service->holdCall($call->external_call_id);

        if (!$success) {
            return response()->json(['error' => 'Failed to hold call'], 422);
        }

        $call->update(['status' => 'on_hold']);

        return response()->json([
            'message' => 'Call placed on hold',
        ]);
    }

    public function mute(Request $request, Call $call): JsonResponse
    {
        $service = $this->callService->getProviderService($call->provider);

        if (!$service) {
            return response()->json(['error' => 'Provider not supported'], 422);
        }

        $muted = $request->boolean('muted', true);
        $success = $service->muteCall($call->external_call_id, $muted);

        if (!$success) {
            return response()->json(['error' => 'Failed to mute call'], 422);
        }

        return response()->json([
            'message' => $muted ? 'Call muted' : 'Call unmuted',
        ]);
    }

    public function logOutcome(Request $request, Call $call): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'outcome' => 'required|string|in:connected,voicemail,no_answer,busy,wrong_number,callback_scheduled,not_interested,qualified,other',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->callService->logCallOutcome($call, $request->outcome, $request->notes);

        return response()->json([
            'message' => 'Outcome logged successfully',
        ]);
    }

    public function linkContact(Request $request, Call $call): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_id' => 'required|integer',
            'contact_module' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $call->linkToContact($request->contact_id, $request->contact_module);

        return response()->json([
            'message' => 'Contact linked successfully',
        ]);
    }

    public function transcribe(Call $call): JsonResponse
    {
        if (!$call->hasRecording()) {
            return response()->json(['error' => 'Call has no recording'], 422);
        }

        if ($call->hasTranscription() && $call->transcription->isCompleted()) {
            return response()->json(['error' => 'Call already transcribed'], 422);
        }

        $transcription = $this->transcriptionService->transcribe($call);

        if (!$transcription) {
            return response()->json(['error' => 'Transcription failed'], 422);
        }

        return response()->json([
            'message' => 'Transcription completed',
            'data' => $transcription,
        ]);
    }

    public function getTranscription(Call $call): JsonResponse
    {
        if (!$call->hasTranscription()) {
            return response()->json(['error' => 'No transcription available'], 404);
        }

        return response()->json([
            'data' => $call->transcription,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        $stats = $this->callService->getCallStats($request->user(), $period);

        return response()->json([
            'data' => $stats,
        ]);
    }

    public function destroy(Call $call): JsonResponse
    {
        // Delete transcription first
        if ($call->transcription) {
            $call->transcription->delete();
        }

        // Delete recording from provider if exists
        if ($call->recording_sid) {
            $service = $this->callService->getProviderService($call->provider);
            $service?->deleteRecording($call->recording_sid);
        }

        $call->delete();

        return response()->json([
            'message' => 'Call deleted successfully',
        ]);
    }
}
