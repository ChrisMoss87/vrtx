<?php

namespace App\Services\Recording;

use App\Models\Recording;
use App\Models\RecordingStep;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ActionCaptureService
{
    private const ACTIVE_RECORDING_CACHE_KEY = 'user_active_recording_';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private RecordingService $recordingService
    ) {}

    public function isRecording(User $user): bool
    {
        return $this->getActiveRecording($user) !== null;
    }

    public function getActiveRecording(User $user): ?Recording
    {
        $cacheKey = self::ACTIVE_RECORDING_CACHE_KEY . $user->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $this->recordingService->getActiveRecording($user);
        });
    }

    public function clearRecordingCache(User $user): void
    {
        Cache::forget(self::ACTIVE_RECORDING_CACHE_KEY . $user->id);
    }

    public function captureCreateRecord(
        User $user,
        string $module,
        int $recordId,
        array $data
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        // Filter sensitive fields
        $filteredData = $this->filterSensitiveData($data);

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_CREATE_RECORD,
            [
                'module' => $module,
                'data' => $filteredData,
            ],
            $module,
            $recordId
        );
    }

    public function captureUpdateField(
        User $user,
        string $module,
        int $recordId,
        string $field,
        mixed $oldValue,
        mixed $newValue
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        // Skip sensitive fields
        if ($this->isSensitiveField($field)) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_UPDATE_FIELD,
            [
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ],
            $module,
            $recordId
        );
    }

    public function captureStageChange(
        User $user,
        string $module,
        int $recordId,
        ?int $oldStageId,
        int $newStageId,
        ?string $oldStageName,
        string $newStageName
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_CHANGE_STAGE,
            [
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $newStageId,
                'old_stage' => $oldStageName,
                'new_stage' => $newStageName,
            ],
            $module,
            $recordId
        );
    }

    public function captureSendEmail(
        User $user,
        string $module,
        int $recordId,
        string $recipient,
        string $subject,
        ?int $templateId = null
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_SEND_EMAIL,
            [
                'recipient' => $recipient,
                'subject' => $subject,
                'template_id' => $templateId,
                'recipient_type' => 'specific', // Will be parameterized later
            ],
            $module,
            $recordId
        );
    }

    public function captureCreateTask(
        User $user,
        string $module,
        int $recordId,
        string $title,
        ?string $dueDate = null,
        ?string $priority = null,
        ?int $assigneeId = null
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        // Calculate due_days from due_date
        $dueDays = null;
        if ($dueDate) {
            $dueDays = now()->diffInDays($dueDate, false);
            if ($dueDays < 0) {
                $dueDays = 0;
            }
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_CREATE_TASK,
            [
                'title' => $title,
                'due_date' => $dueDate,
                'due_days' => $dueDays ?? 3,
                'priority' => $priority ?? 'normal',
                'assignee_id' => $assigneeId,
            ],
            $module,
            $recordId
        );
    }

    public function captureAddNote(
        User $user,
        string $module,
        int $recordId,
        string $content
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_ADD_NOTE,
            [
                'content' => $this->truncateContent($content),
            ],
            $module,
            $recordId
        );
    }

    public function captureAddTag(
        User $user,
        string $module,
        int $recordId,
        string $tag
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_ADD_TAG,
            [
                'tag' => $tag,
            ],
            $module,
            $recordId
        );
    }

    public function captureRemoveTag(
        User $user,
        string $module,
        int $recordId,
        string $tag
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_REMOVE_TAG,
            [
                'tag' => $tag,
            ],
            $module,
            $recordId
        );
    }

    public function captureAssignUser(
        User $user,
        string $module,
        int $recordId,
        int $assigneeId,
        string $assigneeName
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_ASSIGN_USER,
            [
                'user_id' => $assigneeId,
                'user_name' => $assigneeName,
                'assignment_type' => 'specific',
            ],
            $module,
            $recordId
        );
    }

    public function captureLogActivity(
        User $user,
        string $module,
        int $recordId,
        string $activityType,
        array $activityData
    ): ?RecordingStep {
        $recording = $this->getActiveRecording($user);
        if (!$recording) {
            return null;
        }

        return $this->recordingService->captureAction(
            $recording,
            RecordingStep::ACTION_LOG_ACTIVITY,
            [
                'activity_type' => $activityType,
                'data' => $this->filterSensitiveData($activityData),
            ],
            $module,
            $recordId
        );
    }

    private function filterSensitiveData(array $data): array
    {
        $sensitiveFields = [
            'password', 'password_confirmation', 'secret', 'token',
            'api_key', 'api_secret', 'credit_card', 'ssn', 'social_security',
        ];

        return array_filter($data, function ($key) use ($sensitiveFields) {
            return !in_array(strtolower($key), $sensitiveFields);
        }, ARRAY_FILTER_USE_KEY);
    }

    private function isSensitiveField(string $field): bool
    {
        $sensitiveFields = [
            'password', 'secret', 'token', 'api_key', 'api_secret',
            'credit_card', 'ssn', 'social_security',
        ];

        return in_array(strtolower($field), $sensitiveFields);
    }

    private function truncateContent(string $content, int $maxLength = 500): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        return substr($content, 0, $maxLength) . '...';
    }
}
