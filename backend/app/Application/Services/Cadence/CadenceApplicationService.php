<?php

declare(strict_types=1);

namespace App\Application\Services\Cadence;

use App\Domain\Cadence\Repositories\CadenceRepositoryInterface;
use App\Models\Cadence;
use App\Models\CadenceEnrollment;
use App\Models\CadenceStep;
use App\Models\CadenceTemplate;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Application Service for Cadence (Sales Sequence) operations.
 *
 * Cadences are automated multi-step outreach sequences that help sales teams
 * systematically engage with prospects through various channels (email, calls, etc.)
 */
class CadenceApplicationService
{
    public function __construct(
        private CadenceRepositoryInterface $repository,
    ) {}

    /**
     * List cadences with filters and pagination.
     */
    public function listCadences(array $filters = []): LengthAwarePaginator
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
     * Get a single cadence with all related data.
     */
    public function getCadence(int $id): Cadence
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
     * Create a new cadence.
     */
    public function createCadence(array $data): Cadence
    {
        return DB::transaction(function () use ($data) {
            $cadence = Cadence::create([
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

            if (!empty($data['steps'])) {
                foreach ($data['steps'] as $index => $stepData) {
                    $this->createStep($cadence->id, array_merge($stepData, ['step_order' => $index + 1]));
                }
            }

            return $cadence->load(['steps', 'module']);
        });
    }

    /**
     * Update a cadence.
     */
    public function updateCadence(int $id, array $data): Cadence
    {
        $cadence = Cadence::findOrFail($id);
        $cadence->update($data);
        return $cadence->fresh(['steps', 'module', 'owner']);
    }

    /**
     * Delete a cadence.
     */
    public function deleteCadence(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $cadence = Cadence::findOrFail($id);

            // Cancel all active enrollments
            $cadence->enrollments()->where('status', 'active')->update([
                'status' => 'completed',
                'completed_at' => now(),
                'exit_reason' => 'Cadence deleted',
            ]);

            return $cadence->delete();
        });
    }

    /**
     * Activate a cadence.
     */
    public function activateCadence(int $id): Cadence
    {
        $cadence = Cadence::findOrFail($id);

        if (!$cadence->steps()->where('is_active', true)->exists()) {
            throw new \InvalidArgumentException('Cannot activate cadence without active steps');
        }

        $cadence->update(['status' => Cadence::STATUS_ACTIVE]);
        return $cadence->fresh();
    }

    /**
     * Pause a cadence.
     */
    public function pauseCadence(int $id): Cadence
    {
        $cadence = Cadence::findOrFail($id);
        $cadence->update(['status' => Cadence::STATUS_PAUSED]);
        return $cadence->fresh();
    }

    /**
     * Archive a cadence.
     */
    public function archiveCadence(int $id): Cadence
    {
        return DB::transaction(function () use ($id) {
            $cadence = Cadence::findOrFail($id);

            // Complete all active enrollments
            $cadence->enrollments()->where('status', 'active')->update([
                'status' => 'completed',
                'completed_at' => now(),
                'exit_reason' => 'Cadence archived',
            ]);

            $cadence->update(['status' => Cadence::STATUS_ARCHIVED]);
            return $cadence->fresh();
        });
    }

    /**
     * Duplicate a cadence.
     */
    public function duplicateCadence(int $id): Cadence
    {
        return DB::transaction(function () use ($id) {
            $original = $this->getCadence($id);

            $newCadence = $original->replicate();
            $newCadence->name = $original->name . ' (Copy)';
            $newCadence->status = Cadence::STATUS_DRAFT;
            $newCadence->created_by = auth()->id();
            $newCadence->save();

            // Duplicate steps
            foreach ($original->steps as $step) {
                $newStep = $step->replicate();
                $newStep->cadence_id = $newCadence->id;
                $newStep->save();
            }

            return $newCadence->load(['steps', 'module']);
        });
    }

    // Step Management

    /**
     * Create a cadence step.
     */
    public function createStep(int $cadenceId, array $data): CadenceStep
    {
        $cadence = Cadence::findOrFail($cadenceId);

        // Get next step order if not provided
        if (!isset($data['step_order'])) {
            $data['step_order'] = ($cadence->steps()->max('step_order') ?? 0) + 1;
        }

        return CadenceStep::create([
            'cadence_id' => $cadenceId,
            'name' => $data['name'] ?? "Step {$data['step_order']}",
            'channel' => $data['channel'],
            'delay_type' => $data['delay_type'],
            'delay_value' => $data['delay_value'],
            'step_order' => $data['step_order'],
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
            'is_active' => true,
        ]);
    }

    /**
     * Update a cadence step.
     */
    public function updateStep(int $stepId, array $data): CadenceStep
    {
        $step = CadenceStep::findOrFail($stepId);
        $step->update($data);
        return $step->fresh();
    }

    /**
     * Delete a cadence step.
     */
    public function deleteStep(int $stepId): bool
    {
        return CadenceStep::findOrFail($stepId)->delete();
    }

    /**
     * Reorder cadence steps.
     */
    public function reorderSteps(int $cadenceId, array $stepIds): void
    {
        DB::transaction(function () use ($cadenceId, $stepIds) {
            foreach ($stepIds as $order => $stepId) {
                CadenceStep::where('id', $stepId)
                    ->where('cadence_id', $cadenceId)
                    ->update(['step_order' => $order + 1]);
            }
        });
    }

    // Enrollment Management

    /**
     * Enroll a record in a cadence.
     */
    public function enrollRecord(int $cadenceId, int $recordId): CadenceEnrollment
    {
        $cadence = Cadence::findOrFail($cadenceId);

        if (!$cadence->canEnroll()) {
            throw new \InvalidArgumentException('Cadence is not accepting enrollments');
        }

        // Check for existing enrollment
        $existing = CadenceEnrollment::where('cadence_id', $cadenceId)
            ->where('record_id', $recordId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('Record is already enrolled in this cadence');
        }

        // Check re-enrollment rules
        if (!$cadence->allow_re_enrollment) {
            $previousEnrollment = CadenceEnrollment::where('cadence_id', $cadenceId)
                ->where('record_id', $recordId)
                ->exists();

            if ($previousEnrollment) {
                throw new \InvalidArgumentException('Re-enrollment is not allowed for this cadence');
            }
        }

        $firstStep = $cadence->steps()->where('is_active', true)->orderBy('step_order')->first();

        return CadenceEnrollment::create([
            'cadence_id' => $cadenceId,
            'record_id' => $recordId,
            'status' => 'active',
            'current_step_id' => $firstStep?->id,
            'enrolled_at' => now(),
            'enrolled_by' => auth()->id(),
            'next_step_at' => $this->calculateNextStepTime($firstStep),
        ]);
    }

    /**
     * Bulk enroll records.
     */
    public function bulkEnroll(int $cadenceId, array $recordIds): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($recordIds as $recordId) {
            try {
                $this->enrollRecord($cadenceId, $recordId);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[$recordId] = $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Unenroll from a cadence.
     */
    public function unenroll(int $enrollmentId, string $reason = 'Manually removed'): CadenceEnrollment
    {
        $enrollment = CadenceEnrollment::findOrFail($enrollmentId);

        $enrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'exit_reason' => $reason,
        ]);

        return $enrollment->fresh();
    }

    /**
     * Pause an enrollment.
     */
    public function pauseEnrollment(int $enrollmentId): CadenceEnrollment
    {
        $enrollment = CadenceEnrollment::findOrFail($enrollmentId);

        $enrollment->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $enrollment->fresh();
    }

    /**
     * Resume a paused enrollment.
     */
    public function resumeEnrollment(int $enrollmentId): CadenceEnrollment
    {
        $enrollment = CadenceEnrollment::findOrFail($enrollmentId);

        if ($enrollment->status !== 'paused') {
            throw new \InvalidArgumentException('Enrollment is not paused');
        }

        $enrollment->update([
            'status' => 'active',
            'paused_at' => null,
            'next_step_at' => $this->calculateNextStepTime($enrollment->currentStep),
        ]);

        return $enrollment->fresh();
    }

    /**
     * Get enrollments for a cadence.
     */
    public function getEnrollments(int $cadenceId, array $filters = []): LengthAwarePaginator
    {
        $query = CadenceEnrollment::with(['currentStep', 'enrolledBy'])
            ->where('cadence_id', $cadenceId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('enrolled_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    // Analytics

    /**
     * Get analytics for a cadence.
     */
    public function getAnalytics(int $cadenceId, ?string $startDate = null, ?string $endDate = null): array
    {
        $cadence = Cadence::with(['steps', 'enrollments', 'metrics'])->findOrFail($cadenceId);

        $query = CadenceEnrollment::where('cadence_id', $cadenceId);

        if ($startDate) {
            $query->where('enrolled_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->where('enrolled_at', '<=', Carbon::parse($endDate));
        }

        $enrollments = $query->get();

        $summary = [
            'total_enrollments' => $enrollments->count(),
            'active_enrollments' => $enrollments->where('status', 'active')->count(),
            'completed_enrollments' => $enrollments->where('status', 'completed')->count(),
            'replied_enrollments' => $enrollments->whereNotNull('replied_at')->count(),
            'bounced_enrollments' => $enrollments->where('status', 'bounced')->count(),
            'reply_rate' => $enrollments->count() > 0
                ? round($enrollments->whereNotNull('replied_at')->count() / $enrollments->count() * 100, 1)
                : 0,
        ];

        // Step performance
        $stepPerformance = $cadence->steps->map(function ($step) use ($cadenceId) {
            $executions = $step->executions()->count();
            $completed = $step->executions()->where('status', 'completed')->count();
            $replies = $step->executions()->whereNotNull('replied_at')->count();

            return [
                'step_id' => $step->id,
                'step_name' => $step->name,
                'channel' => $step->channel,
                'executions' => $executions,
                'completed' => $completed,
                'replies' => $replies,
                'reply_rate' => $completed > 0 ? round($replies / $completed * 100, 1) : 0,
            ];
        });

        return [
            'summary' => $summary,
            'step_performance' => $stepPerformance,
        ];
    }

    // Templates

    /**
     * Get cadence templates.
     */
    public function getTemplates(?string $category = null): Collection
    {
        $query = CadenceTemplate::with(['steps']);

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Create a cadence from a template.
     */
    public function createFromTemplate(int $templateId, int $moduleId, string $name): Cadence
    {
        return DB::transaction(function () use ($templateId, $moduleId, $name) {
            $template = CadenceTemplate::with('steps')->findOrFail($templateId);

            $cadence = Cadence::create([
                'name' => $name,
                'description' => $template->description,
                'module_id' => $moduleId,
                'status' => Cadence::STATUS_DRAFT,
                'settings' => $template->settings ?? [],
                'owner_id' => auth()->id(),
                'created_by' => auth()->id(),
            ]);

            // Copy template steps
            foreach ($template->steps as $templateStep) {
                CadenceStep::create([
                    'cadence_id' => $cadence->id,
                    'name' => $templateStep->name,
                    'channel' => $templateStep->channel,
                    'delay_type' => $templateStep->delay_type,
                    'delay_value' => $templateStep->delay_value,
                    'step_order' => $templateStep->step_order,
                    'subject' => $templateStep->subject,
                    'content' => $templateStep->content,
                    'conditions' => $templateStep->conditions,
                    'is_active' => true,
                ]);
            }

            return $cadence->load(['steps', 'module']);
        });
    }

    /**
     * Save a cadence as a template.
     */
    public function saveAsTemplate(int $cadenceId, string $name, ?string $category = null): CadenceTemplate
    {
        return DB::transaction(function () use ($cadenceId, $name, $category) {
            $cadence = $this->getCadence($cadenceId);

            $template = CadenceTemplate::create([
                'name' => $name,
                'description' => $cadence->description,
                'category' => $category,
                'settings' => $cadence->settings,
                'created_by' => auth()->id(),
            ]);

            // Copy steps to template
            foreach ($cadence->steps as $step) {
                $template->steps()->create([
                    'name' => $step->name,
                    'channel' => $step->channel,
                    'delay_type' => $step->delay_type,
                    'delay_value' => $step->delay_value,
                    'step_order' => $step->step_order,
                    'subject' => $step->subject,
                    'content' => $step->content,
                    'conditions' => $step->conditions,
                ]);
            }

            return $template->load('steps');
        });
    }

    /**
     * Calculate when the next step should execute.
     */
    private function calculateNextStepTime(?CadenceStep $step): ?Carbon
    {
        if (!$step) {
            return null;
        }

        $now = now();

        return match ($step->delay_type) {
            'immediate' => $now,
            'hours' => $now->addHours($step->delay_value),
            'days' => $now->addDays($step->delay_value),
            'business_days' => $this->addBusinessDays($now, $step->delay_value),
            default => $now,
        };
    }

    /**
     * Add business days to a date.
     */
    private function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $added = 0;
        $result = $date->copy();

        while ($added < $days) {
            $result->addDay();
            if (!$result->isWeekend()) {
                $added++;
            }
        }

        return $result;
    }
}
