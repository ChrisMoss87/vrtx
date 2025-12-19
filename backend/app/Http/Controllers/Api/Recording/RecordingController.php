<?php

namespace App\Http\Controllers\Api\Recording;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Models\RecordingStep;
use App\Services\Recording\ActionCaptureService;
use App\Services\Recording\RecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    public function __construct(
        private RecordingService $recordingService,
        private ActionCaptureService $captureService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $recordings = $this->recordingService->getRecordings($request->user(), $status);

        return response()->json([
            'data' => $recordings->map(fn ($r) => $this->formatRecording($r)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $recording = $this->recordingService->getRecording($id);

        if (!$recording) {
            return response()->json(['error' => 'Recording not found'], 404);
        }

        return response()->json([
            'data' => $this->formatRecording($recording, true),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'nullable|integer|exists:modules,id',
            'initial_record_id' => 'nullable|integer',
        ]);

        $recording = $this->recordingService->startRecording(
            $request->user(),
            $validated['module_id'] ?? null,
            $validated['initial_record_id'] ?? null
        );

        $this->captureService->clearRecordingCache($request->user());

        return response()->json([
            'data' => $this->formatRecording($recording),
            'message' => 'Recording started',
        ]);
    }

    public function stop(Request $request, int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        $this->authorize('update', $recording);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $recording = $this->recordingService->stopRecording($recording, $validated['name'] ?? null);
        $this->captureService->clearRecordingCache($request->user());

        return response()->json([
            'data' => $this->formatRecording($recording, true),
            'message' => 'Recording stopped',
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        $this->authorize('update', $recording);

        $recording = $this->recordingService->pauseRecording($recording);
        $this->captureService->clearRecordingCache($request->user());

        return response()->json([
            'data' => $this->formatRecording($recording),
            'message' => 'Recording paused',
        ]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        $this->authorize('update', $recording);

        $recording = $this->recordingService->resumeRecording($recording);
        $this->captureService->clearRecordingCache($request->user());

        return response()->json([
            'data' => $this->formatRecording($recording),
            'message' => 'Recording resumed',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        $this->authorize('delete', $recording);

        $this->recordingService->deleteRecording($recording);

        return response()->json([
            'message' => 'Recording deleted',
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $recording = $this->captureService->getActiveRecording($request->user());

        return response()->json([
            'data' => $recording ? $this->formatRecording($recording, true) : null,
            'is_recording' => $recording !== null,
        ]);
    }

    public function steps(int $id): JsonResponse
    {
        $recording = Recording::with('steps')->findOrFail($id);

        return response()->json([
            'data' => $recording->steps->map(fn ($s) => $this->formatStep($s)),
        ]);
    }

    public function removeStep(int $id, int $stepId): JsonResponse
    {
        $step = RecordingStep::where('recording_id', $id)->findOrFail($stepId);

        $this->authorize('update', $step->recording);

        $this->recordingService->removeStep($step);

        return response()->json([
            'message' => 'Step removed',
        ]);
    }

    public function reorderSteps(Request $request, int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        $this->authorize('update', $recording);

        $validated = $request->validate([
            'step_ids' => 'required|array',
            'step_ids.*' => 'integer|exists:recording_steps,id',
        ]);

        $this->recordingService->reorderSteps($recording, $validated['step_ids']);

        return response()->json([
            'message' => 'Steps reordered',
        ]);
    }

    public function parameterizeStep(Request $request, int $id, int $stepId): JsonResponse
    {
        $step = RecordingStep::where('recording_id', $id)->findOrFail($stepId);

        $this->authorize('update', $step->recording);

        $validated = $request->validate([
            'field' => 'required|string',
            'reference_type' => 'required|string|in:field,current_user,owner,record_email,custom',
            'reference_field' => 'nullable|string',
        ]);

        $step = $this->recordingService->parameterizeStep(
            $step,
            $validated['field'],
            $validated['reference_type'],
            $validated['reference_field'] ?? null
        );

        return response()->json([
            'data' => $this->formatStep($step),
            'message' => 'Step parameterized',
        ]);
    }

    public function resetStepParameterization(int $id, int $stepId): JsonResponse
    {
        $step = RecordingStep::where('recording_id', $id)->findOrFail($stepId);

        $this->authorize('update', $step->recording);

        $step = $this->recordingService->resetStepParameterization($step);

        return response()->json([
            'data' => $this->formatStep($step),
            'message' => 'Parameterization reset',
        ]);
    }

    public function preview(int $id): JsonResponse
    {
        $recording = Recording::with('steps', 'module')->findOrFail($id);

        $preview = $this->recordingService->previewAsWorkflow($recording);

        return response()->json([
            'data' => $preview,
        ]);
    }

    public function generateWorkflow(Request $request, int $id): JsonResponse
    {
        $recording = Recording::with('steps')->findOrFail($id);

        $this->authorize('update', $recording);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|string|in:manual,record_created,field_change,stage_change,scheduled',
            'trigger_config' => 'nullable|array',
            'description' => 'nullable|string|max:1000',
        ]);

        $workflow = $this->recordingService->generateWorkflow(
            $recording,
            $validated['name'],
            $validated['trigger_type'],
            $validated['trigger_config'] ?? [],
            $validated['description'] ?? null
        );

        return response()->json([
            'data' => [
                'workflow_id' => $workflow->id,
                'workflow_name' => $workflow->name,
                'step_count' => $workflow->steps->count(),
            ],
            'message' => 'Workflow generated successfully',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        $this->authorize('view', $recording);

        $newRecording = $this->recordingService->duplicateRecording($recording);

        return response()->json([
            'data' => $this->formatRecording($newRecording, true),
            'message' => 'Recording duplicated',
        ]);
    }

    public function captureAction(Request $request): JsonResponse
    {
        $user = $request->user();
        $recording = $this->captureService->getActiveRecording($user);

        if (!$recording) {
            return response()->json([
                'captured' => false,
                'message' => 'No active recording',
            ]);
        }

        $validated = $request->validate([
            'action_type' => 'required|string',
            'module' => 'nullable|string',
            'record_id' => 'nullable|integer',
            'data' => 'required|array',
        ]);

        $step = $this->recordingService->captureAction(
            $recording,
            $validated['action_type'],
            $validated['data'],
            $validated['module'] ?? null,
            $validated['record_id'] ?? null
        );

        return response()->json([
            'captured' => $step !== null,
            'step' => $step ? $this->formatStep($step) : null,
        ]);
    }

    private function formatRecording(Recording $recording, bool $includeSteps = false): array
    {
        $data = [
            'id' => $recording->id,
            'name' => $recording->name,
            'status' => $recording->status,
            'started_at' => $recording->started_at?->toISOString(),
            'ended_at' => $recording->ended_at?->toISOString(),
            'duration' => $recording->getDuration(),
            'module_id' => $recording->module_id,
            'module_name' => $recording->module?->name,
            'initial_record_id' => $recording->initial_record_id,
            'workflow_id' => $recording->workflow_id,
            'step_count' => $recording->steps->count(),
            'action_counts' => $recording->getActionCounts(),
            'user' => $recording->user ? [
                'id' => $recording->user->id,
                'name' => $recording->user->name,
            ] : null,
            'created_at' => $recording->created_at->toISOString(),
        ];

        if ($includeSteps) {
            $data['steps'] = $recording->steps->map(fn ($s) => $this->formatStep($s));
        }

        return $data;
    }

    private function formatStep(RecordingStep $step): array
    {
        return [
            'id' => $step->id,
            'step_order' => $step->step_order,
            'action_type' => $step->action_type,
            'action_label' => $step->getActionLabel(),
            'description' => $step->getDescription(),
            'target_module' => $step->target_module,
            'target_record_id' => $step->target_record_id,
            'action_data' => $step->action_data,
            'parameterized_data' => $step->parameterized_data,
            'is_parameterized' => $step->is_parameterized,
            'captured_at' => $step->captured_at?->toISOString(),
        ];
    }
}
