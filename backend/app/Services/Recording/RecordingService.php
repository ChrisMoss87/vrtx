<?php

namespace App\Services\Recording;

use App\Models\Recording;
use App\Models\RecordingStep;
use App\Models\User;
use Illuminate\Support\Collection;

class RecordingService
{
    public function __construct(
        private WorkflowGeneratorService $workflowGenerator
    ) {}

    public function startRecording(User $user, ?int $moduleId = null, ?int $initialRecordId = null): Recording
    {
        // Stop any existing active recordings for this user
        $this->stopActiveRecordings($user);

        return Recording::create([
            'user_id' => $user->id,
            'module_id' => $moduleId,
            'initial_record_id' => $initialRecordId,
            'status' => Recording::STATUS_RECORDING,
            'started_at' => now(),
        ]);
    }

    public function stopActiveRecordings(User $user): void
    {
        Recording::forUser($user->id)
            ->active()
            ->each(fn ($recording) => $recording->stop());
    }

    public function getActiveRecording(User $user): ?Recording
    {
        return Recording::forUser($user->id)
            ->active()
            ->with('steps')
            ->first();
    }

    public function pauseRecording(Recording $recording): Recording
    {
        $recording->pause();
        return $recording->fresh();
    }

    public function resumeRecording(Recording $recording): Recording
    {
        $recording->resume();
        return $recording->fresh();
    }

    public function stopRecording(Recording $recording, ?string $name = null): Recording
    {
        $recording->stop();

        if ($name) {
            $recording->update(['name' => $name]);
        }

        return $recording->fresh(['steps']);
    }

    public function captureAction(
        Recording $recording,
        string $actionType,
        array $actionData,
        ?string $targetModule = null,
        ?int $targetRecordId = null
    ): ?RecordingStep {
        if (!$recording->canAddSteps()) {
            return null;
        }

        return RecordingStep::create([
            'recording_id' => $recording->id,
            'step_order' => $recording->getNextStepOrder(),
            'action_type' => $actionType,
            'target_module' => $targetModule,
            'target_record_id' => $targetRecordId,
            'action_data' => $actionData,
            'captured_at' => now(),
        ]);
    }

    public function removeStep(RecordingStep $step): void
    {
        $recording = $step->recording;
        $removedOrder = $step->step_order;

        $step->delete();

        // Reorder remaining steps
        RecordingStep::where('recording_id', $recording->id)
            ->where('step_order', '>', $removedOrder)
            ->decrement('step_order');
    }

    public function reorderSteps(Recording $recording, array $stepIds): void
    {
        foreach ($stepIds as $order => $stepId) {
            RecordingStep::where('id', $stepId)
                ->where('recording_id', $recording->id)
                ->update(['step_order' => $order + 1]);
        }
    }

    public function parameterizeStep(
        RecordingStep $step,
        string $field,
        string $referenceType,
        ?string $referenceField = null
    ): RecordingStep {
        $step->parameterize($field, $referenceType, $referenceField);
        return $step->fresh();
    }

    public function resetStepParameterization(RecordingStep $step): RecordingStep
    {
        $step->resetParameterization();
        return $step->fresh();
    }

    public function getRecordings(User $user, ?string $status = null): Collection
    {
        $query = Recording::forUser($user->id)
            ->with(['steps', 'module', 'workflow'])
            ->latest('started_at');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function getRecording(int $id): ?Recording
    {
        return Recording::with(['steps', 'module', 'workflow', 'user'])->find($id);
    }

    public function previewAsWorkflow(Recording $recording): array
    {
        return $this->workflowGenerator->preview($recording);
    }

    public function generateWorkflow(
        Recording $recording,
        string $name,
        string $triggerType,
        array $triggerConfig = [],
        ?string $description = null
    ): \App\Models\Workflow {
        $workflow = $this->workflowGenerator->generate(
            $recording,
            $name,
            $triggerType,
            $triggerConfig,
            $description
        );

        $recording->markConverted($workflow->id);

        return $workflow;
    }

    public function deleteRecording(Recording $recording): void
    {
        $recording->delete();
    }

    public function duplicateRecording(Recording $recording): Recording
    {
        $newRecording = $recording->replicate();
        $newRecording->name = ($recording->name ?? 'Recording') . ' (Copy)';
        $newRecording->status = Recording::STATUS_COMPLETED;
        $newRecording->workflow_id = null;
        $newRecording->save();

        foreach ($recording->steps as $step) {
            $newStep = $step->replicate();
            $newStep->recording_id = $newRecording->id;
            $newStep->save();
        }

        return $newRecording->fresh(['steps']);
    }
}
