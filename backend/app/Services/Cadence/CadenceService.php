<?php

namespace App\Services\Cadence;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CadenceService
{
    /**
     * List cadences with filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Cadence::with(['module', 'owner', 'creator'])
            ->withCount(['steps', 'enrollments as active_enrollments_count' => function ($q) {
                $q->where('status', 'active');
            }]);

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get a single cadence with full details
     */
    public function get(int $id): Cadence
    {
        return Cadence::with([
            'module',
            'owner',
            'creator',
            'steps' => fn($q) => $q->orderBy('step_order'),
            'steps.template',
        ])->findOrFail($id);
    }

    /**
     * Create a new cadence
     */
    public function create(array $data): Cadence
    {
        return DB::transaction(function () use ($data) {
            $cadence = DB::table('cadences')->insertGetId([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'module_id' => $data['module_id'],
                'status' => Cadence::STATUS_DRAFT,
                'entry_criteria' => $data['entry_criteria'] ?? null,
                'exit_criteria' => $data['exit_criteria'] ?? null,
                'settings' => $data['settings'] ?? [],
                'auto_enroll' => $data['auto_enroll'] ?? false,
                'allow_re_enrollment' => $data['allow_re_enrollment'] ?? false,
                're_enrollment_days' => $data['re_enrollment_days'] ?? null,
                'max_enrollments_per_day' => $data['max_enrollments_per_day'] ?? null,
                'owner_id' => $data['owner_id'] ?? auth()->id(),
                'created_by' => auth()->id(),
            ]);

            // Create initial steps if provided
            if (!empty($data['steps'])) {
                foreach ($data['steps'] as $index => $stepData) {
                    $this->createStep($cadence->id, array_merge($stepData, ['step_order' => $index + 1]));
                }
            }

            return $cadence->load(['steps', 'module']);
        });
    }

    /**
     * Update a cadence
     */
    public function update(int $id, array $data): Cadence
    {
        $cadence = DB::table('cadences')->where('id', $id)->first();

        $cadence->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'entry_criteria' => $data['entry_criteria'] ?? null,
            'exit_criteria' => $data['exit_criteria'] ?? null,
            'settings' => $data['settings'] ?? null,
            'auto_enroll' => $data['auto_enroll'] ?? null,
            'allow_re_enrollment' => $data['allow_re_enrollment'] ?? null,
            're_enrollment_days' => $data['re_enrollment_days'] ?? null,
            'max_enrollments_per_day' => $data['max_enrollments_per_day'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
        ], fn($v) => $v !== null));

        return $cadence->load(['steps', 'module']);
    }

    /**
     * Delete a cadence
     */
    public function delete(int $id): void
    {
        $cadence = DB::table('cadences')->where('id', $id)->first();

        // Check for active enrollments
        if ($cadence->enrollments()->where('status', 'active')->exists()) {
            throw new \Exception('Cannot delete cadence with active enrollments');
        }

        $cadence->delete();
    }

    /**
     * Activate a cadence
     */
    public function activate(int $id): Cadence
    {
        $cadence = DB::table('cadences')->where('id', $id)->first();

        if (!$cadence->steps()->where('is_active', true)->exists()) {
            throw new \Exception('Cadence must have at least one active step');
        }

        $cadence->update(['status' => Cadence::STATUS_ACTIVE]);

        return $cadence;
    }

    /**
     * Pause a cadence
     */
    public function pause(int $id): Cadence
    {
        $cadence = DB::table('cadences')->where('id', $id)->first();
        $cadence->update(['status' => Cadence::STATUS_PAUSED]);

        return $cadence;
    }

    /**
     * Archive a cadence
     */
    public function archive(int $id): Cadence
    {
        $cadence = DB::table('cadences')->where('id', $id)->first();

        // Exit all active enrollments
        $cadence->enrollments()
            ->where('status', 'active')
            ->update([
                'status' => CadenceEnrollment::STATUS_MANUALLY_REMOVED,
                'exit_reason' => 'Cadence archived',
                'completed_at' => now(),
            ]);

        $cadence->update(['status' => Cadence::STATUS_ARCHIVED]);

        return $cadence;
    }

    /**
     * Duplicate a cadence
     */
    public function duplicate(int $id): Cadence
    {
        $original = $this->get($id);

        return DB::transaction(function () use ($original) {
            $cadence = $original->replicate();
            $cadence->name = $original->name . ' (Copy)';
            $cadence->status = Cadence::STATUS_DRAFT;
            $cadence->created_by = auth()->id();
            $cadence->save();

            // Duplicate steps
            foreach ($original->steps as $step) {
                $newStep = $step->replicate();
                $newStep->cadence_id = $cadence->id;
                $newStep->save();
            }

            return $cadence->load(['steps', 'module']);
        });
    }

    // Step Management

    /**
     * Create a cadence step
     */
    public function createStep(int $cadenceId, array $data): CadenceStep
    {
        $cadence = DB::table('cadences')->where('id', $cadenceId)->first();

        // Get next step order if not provided
        $stepOrder = $data['step_order'] ?? ($cadence->steps()->max('step_order') ?? 0) + 1;

        return DB::table('cadence_steps')->insertGetId([
            'cadence_id' => $cadenceId,
            'step_order' => $stepOrder,
            'name' => $data['name'] ?? null,
            'channel' => $data['channel'] ?? 'email',
            'delay_type' => $data['delay_type'] ?? 'days',
            'delay_value' => $data['delay_value'] ?? 1,
            'preferred_time' => $data['preferred_time'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'on_reply_goto_step' => $data['on_reply_goto_step'] ?? null,
            'on_click_goto_step' => $data['on_click_goto_step'] ?? null,
            'on_no_response_goto_step' => $data['on_no_response_goto_step'] ?? null,
            'is_ab_test' => $data['is_ab_test'] ?? false,
            'ab_variant_of' => $data['ab_variant_of'] ?? null,
            'ab_percentage' => $data['ab_percentage'] ?? null,
            'linkedin_action' => $data['linkedin_action'] ?? null,
            'task_type' => $data['task_type'] ?? null,
            'task_assigned_to' => $data['task_assigned_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a cadence step
     */
    public function updateStep(int $stepId, array $data): CadenceStep
    {
        $step = DB::table('cadence_steps')->where('id', $stepId)->first();

        $step->update(array_filter([
            'name' => $data['name'] ?? null,
            'channel' => $data['channel'] ?? null,
            'delay_type' => $data['delay_type'] ?? null,
            'delay_value' => $data['delay_value'] ?? null,
            'preferred_time' => $data['preferred_time'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'on_reply_goto_step' => $data['on_reply_goto_step'] ?? null,
            'on_click_goto_step' => $data['on_click_goto_step'] ?? null,
            'on_no_response_goto_step' => $data['on_no_response_goto_step'] ?? null,
            'is_ab_test' => $data['is_ab_test'] ?? null,
            'ab_variant_of' => $data['ab_variant_of'] ?? null,
            'ab_percentage' => $data['ab_percentage'] ?? null,
            'linkedin_action' => $data['linkedin_action'] ?? null,
            'task_type' => $data['task_type'] ?? null,
            'task_assigned_to' => $data['task_assigned_to'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($v) => $v !== null));

        return $step;
    }

    /**
     * Delete a cadence step
     */
    public function deleteStep(int $stepId): void
    {
        $step = DB::table('cadence_steps')->where('id', $stepId)->first();

        // Check if any enrollments are on this step
        if (DB::table('cadence_enrollments')->where('current_step_id', $stepId)->where('status', 'active')->exists()) {
            throw new \Exception('Cannot delete step with active enrollments');
        }

        $step->delete();

        // Reorder remaining steps
        $step->cadence->steps()
            ->where('step_order', '>', $step->step_order)
            ->decrement('step_order');
    }

    /**
     * Reorder cadence steps
     */
    public function reorderSteps(int $cadenceId, array $stepIds): void
    {
        DB::transaction(function () use ($cadenceId, $stepIds) {
            foreach ($stepIds as $index => $stepId) {
                DB::table('cadence_steps')->where('id', $stepId)
                    ->where('cadence_id', $cadenceId)
                    ->update(['step_order' => $index + 1]);
            }
        });
    }

    // Enrollment Management

    /**
     * Enroll a record in a cadence
     */
    public function enroll(int $cadenceId, int $recordId, ?int $enrolledBy = null): CadenceEnrollment
    {
        $cadence = DB::table('cadences')->where('id', $cadenceId)->first();

        if (!$cadence->canEnroll()) {
            throw new \Exception('Cadence is not accepting enrollments');
        }

        // Check for existing enrollment
        $existing = DB::table('cadence_enrollments')->where('cadence_id', $cadenceId)
            ->where('record_id', $recordId)
            ->first();

        if ($existing) {
            if ($existing->status === 'active') {
                throw new \Exception('Record is already enrolled in this cadence');
            }

            if (!$cadence->allow_re_enrollment) {
                throw new \Exception('Re-enrollment is not allowed for this cadence');
            }

            // Check re-enrollment cooldown
            if ($cadence->re_enrollment_days && $existing->completed_at) {
                $cooldownEnd = $existing->completed_at->addDays($cadence->re_enrollment_days);
                if (now()->lt($cooldownEnd)) {
                    throw new \Exception('Re-enrollment cooldown period has not elapsed');
                }
            }

            // Delete old enrollment
            $existing->delete();
        }

        // Get first step
        $firstStep = $cadence->steps()->where('is_active', true)->orderBy('step_order')->first();

        if (!$firstStep) {
            throw new \Exception('Cadence has no active steps');
        }

        return DB::transaction(function () use ($cadence, $recordId, $firstStep, $enrolledBy) {
            $enrollment = DB::table('cadence_enrollments')->insertGetId([
                'cadence_id' => $cadence->id,
                'record_id' => $recordId,
                'current_step_id' => $firstStep->id,
                'status' => CadenceEnrollment::STATUS_ACTIVE,
                'enrolled_at' => now(),
                'next_step_at' => $this->calculateNextStepTime($firstStep, $recordId),
                'enrolled_by' => $enrolledBy ?? auth()->id(),
            ]);

            // Schedule first step execution
            DB::table('cadence_step_executions')->insertGetId([
                'enrollment_id' => $enrollment->id,
                'step_id' => $firstStep->id,
                'scheduled_at' => $enrollment->next_step_at,
                'status' => CadenceStepExecution::STATUS_SCHEDULED,
            ]);

            // Update metrics
            CadenceMetric::incrementMetric($cadence->id, 'enrollments');

            return $enrollment;
        });
    }

    /**
     * Bulk enroll records
     */
    public function bulkEnroll(int $cadenceId, array $recordIds): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($recordIds as $recordId) {
            try {
                $this->enroll($cadenceId, $recordId);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$recordId] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Unenroll a record from a cadence
     */
    public function unenroll(int $enrollmentId, string $reason = 'Manually removed'): CadenceEnrollment
    {
        $enrollment = DB::table('cadence_enrollments')->where('id', $enrollmentId)->first();

        $enrollment->exitWithReason(CadenceEnrollment::STATUS_MANUALLY_REMOVED, $reason);

        // Cancel pending executions
        DB::table('cadence_step_executions')->where('enrollment_id', $enrollmentId)
            ->where('status', CadenceStepExecution::STATUS_SCHEDULED)
            ->update(['status' => CadenceStepExecution::STATUS_CANCELLED]);

        return $enrollment;
    }

    /**
     * Pause an enrollment
     */
    public function pauseEnrollment(int $enrollmentId): CadenceEnrollment
    {
        $enrollment = DB::table('cadence_enrollments')->where('id', $enrollmentId)->first();
        $enrollment->pause();

        return $enrollment;
    }

    /**
     * Resume an enrollment
     */
    public function resumeEnrollment(int $enrollmentId): CadenceEnrollment
    {
        $enrollment = DB::table('cadence_enrollments')->where('id', $enrollmentId)->first();
        $enrollment->resume();

        // Reschedule next step
        if ($enrollment->current_step_id) {
            $enrollment->update([
                'next_step_at' => $this->calculateNextStepTime(
                    $enrollment->currentStep,
                    $enrollment->record_id
                ),
            ]);
        }

        return $enrollment;
    }

    /**
     * Get enrollments for a cadence
     */
    public function getEnrollments(int $cadenceId, array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('cadence_enrollments')->where('cadence_id', $cadenceId)
            ->with(['currentStep', 'enrolledBy']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('enrolled_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Calculate optimal send time for a step
     */
    private function calculateNextStepTime(CadenceStep $step, int $recordId): Carbon
    {
        $baseTime = now();

        // Add delay
        $baseTime = match ($step->delay_type) {
            CadenceStep::DELAY_IMMEDIATE => $baseTime,
            CadenceStep::DELAY_HOURS => $baseTime->addHours($step->delay_value),
            CadenceStep::DELAY_DAYS => $baseTime->addDays($step->delay_value),
            CadenceStep::DELAY_BUSINESS_DAYS => $this->addBusinessDays($baseTime, $step->delay_value),
            default => $baseTime->addDays($step->delay_value),
        };

        // Apply preferred time if set
        if ($step->preferred_time) {
            $preferredHour = (int) Carbon::parse($step->preferred_time)->format('H');
            $baseTime->setHour($preferredHour)->setMinute(0)->setSecond(0);

            // If we're past the preferred time today, move to next day
            if ($baseTime->lt(now())) {
                $baseTime->addDay();
            }
        }

        // Check for AI-optimized send time
        $prediction = SendTimePrediction::getBestSendTime($recordId, $step->channel);
        if ($prediction && $prediction['confidence'] >= 0.5) {
            $baseTime->setHour($prediction['hour']);
        }

        return $baseTime;
    }

    /**
     * Add business days to a date
     */
    private function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $added = 0;
        while ($added < $days) {
            $date->addDay();
            if ($date->isWeekday()) {
                $added++;
            }
        }
        return $date;
    }

    // Analytics

    /**
     * Get cadence analytics
     */
    public function getAnalytics(int $cadenceId, ?string $startDate = null, ?string $endDate = null): array
    {
        $cadence = DB::table('cadences')->where('id', $cadenceId)->first();

        $query = DB::table('cadence_enrollments')->where('cadence_id', $cadenceId);

        // Overall stats
        $totalEnrollments = $query->count();
        $activeEnrollments = (clone $query)->where('status', 'active')->count();
        $completedEnrollments = (clone $query)->where('status', 'completed')->count();
        $repliedEnrollments = (clone $query)->where('status', 'replied')->count();
        $meetingsBooked = (clone $query)->where('status', 'meeting_booked')->count();

        // Step analytics
        $stepStats = CadenceStepExecution::selectRaw('
            step_id,
            COUNT(*) as total,
            SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN result = \'sent\' OR result = \'delivered\' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN result = \'opened\' THEN 1 ELSE 0 END) as opened,
            SUM(CASE WHEN result = \'clicked\' THEN 1 ELSE 0 END) as clicked,
            SUM(CASE WHEN result = \'replied\' THEN 1 ELSE 0 END) as replied,
            SUM(CASE WHEN result = \'bounced\' THEN 1 ELSE 0 END) as bounced
        ')
            ->whereHas('enrollment', fn($q) => $q->where('cadence_id', $cadenceId))
            ->groupBy('step_id')
            ->get()
            ->keyBy('step_id');

        // Daily metrics
        $metricsQuery = DB::table('cadence_metrics')->where('cadence_id', $cadenceId)
            ->whereNull('step_id');

        if ($startDate) {
            $metricsQuery->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $metricsQuery->where('date', '<=', $endDate);
        }

        $dailyMetrics = $metricsQuery->orderBy('date')->get();

        return [
            'summary' => [
                'total_enrollments' => $totalEnrollments,
                'active_enrollments' => $activeEnrollments,
                'completed_enrollments' => $completedEnrollments,
                'replied_enrollments' => $repliedEnrollments,
                'meetings_booked' => $meetingsBooked,
                'completion_rate' => $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 1) : 0,
                'reply_rate' => $totalEnrollments > 0 ? round(($repliedEnrollments / $totalEnrollments) * 100, 1) : 0,
                'meeting_rate' => $totalEnrollments > 0 ? round(($meetingsBooked / $totalEnrollments) * 100, 1) : 0,
            ],
            'steps' => $cadence->steps->map(fn($step) => [
                'id' => $step->id,
                'name' => $step->getDisplayName(),
                'channel' => $step->channel,
                'stats' => $stepStats->get($step->id) ?? [
                    'total' => 0,
                    'completed' => 0,
                    'sent' => 0,
                    'opened' => 0,
                    'clicked' => 0,
                    'replied' => 0,
                    'bounced' => 0,
                ],
            ]),
            'daily_metrics' => $dailyMetrics,
        ];
    }

    // Templates

    /**
     * Get cadence templates
     */
    public function getTemplates(?string $category = null): Collection
    {
        $query = CadenceTemplate::active();

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get();
    }

    /**
     * Create cadence from template
     */
    public function createFromTemplate(int $templateId, int $moduleId, string $name): Cadence
    {
        $template = DB::table('cadence_templates')->where('id', $templateId)->first();

        return $template->createCadence($moduleId, $name, auth()->id());
    }

    /**
     * Save cadence as template
     */
    public function saveAsTemplate(int $cadenceId, string $name, ?string $category = null): CadenceTemplate
    {
        $cadence = $this->get($cadenceId);

        $stepsConfig = $cadence->steps->map(fn($step) => [
            'name' => $step->name,
            'channel' => $step->channel,
            'delay_type' => $step->delay_type,
            'delay_value' => $step->delay_value,
            'subject' => $step->subject,
            'content' => $step->content,
            'conditions' => $step->conditions,
        ])->toArray();

        return DB::table('cadence_templates')->insertGetId([
            'name' => $name,
            'description' => $cadence->description,
            'category' => $category,
            'steps_config' => $stepsConfig,
            'settings' => $cadence->settings,
            'created_by' => auth()->id(),
        ]);
    }
}
