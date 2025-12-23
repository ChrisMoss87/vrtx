<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Cadence;

use App\Domain\Cadence\Entities\Cadence as CadenceEntity;
use App\Domain\Cadence\Repositories\CadenceRepositoryInterface;
use App\Domain\Cadence\ValueObjects\CadenceStatus;
use App\Domain\Cadence\ValueObjects\DelayType;
use App\Domain\Cadence\ValueObjects\EnrollmentStatus;
use App\Domain\Cadence\ValueObjects\ExecutionResult;
use App\Domain\Cadence\ValueObjects\ExecutionStatus;
use App\Domain\Cadence\ValueObjects\LinkedInAction;
use App\Domain\Cadence\ValueObjects\StepChannel;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentCadenceRepository implements CadenceRepositoryInterface
{
    private const TABLE_CADENCES = 'cadences';
    private const TABLE_CADENCE_STEPS = 'cadence_steps';
    private const TABLE_CADENCE_ENROLLMENTS = 'cadence_enrollments';
    private const TABLE_CADENCE_STEP_EXECUTIONS = 'cadence_step_executions';
    private const TABLE_CADENCE_TEMPLATES = 'cadence_templates';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?CadenceEntity
    {
        $row = DB::table(self::TABLE_CADENCES)->where('id', $id)->first();
        return $row ? $this->toDomainEntity($row) : null;
    }

    public function save(CadenceEntity $cadence): CadenceEntity
    {
        $data = $this->toRowData($cadence);

        if ($cadence->getId() !== null) {
            DB::table(self::TABLE_CADENCES)
                ->where('id', $cadence->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $cadence->getId();
        } else {
            $id = DB::table(self::TABLE_CADENCES)->insertGetId(array_merge(
                $data,
                ['created_at' => now(), 'updated_at' => now()]
            ));
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $cadence = DB::table(self::TABLE_CADENCES)->where('id', $id)->first();

            if (!$cadence) {
                throw new \RuntimeException("Cadence not found: {$id}");
            }

            // Cancel all active enrollments
            DB::table(self::TABLE_CADENCE_ENROLLMENTS)
                ->where('cadence_id', $id)
                ->where('status', 'active')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'exit_reason' => 'Cadence deleted',
                    'updated_at' => now(),
                ]);

            return (bool) DB::table(self::TABLE_CADENCES)->where('id', $id)->delete();
        });
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE_CADENCES)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $cadence = DB::table(self::TABLE_CADENCES)->where('id', $id)->first();

        if (!$cadence) {
            return null;
        }

        $result = (array) $cadence;

        // Add module relation
        if ($cadence->module_id) {
            $module = DB::table('modules')->where('id', $cadence->module_id)->first();
            $result['module'] = $module ? (array) $module : null;
        }

        // Add owner relation
        if ($cadence->owner_id) {
            $owner = DB::table('users')->where('id', $cadence->owner_id)->first();
            $result['owner'] = $owner ? (array) $owner : null;
        }

        // Add creator relation
        if ($cadence->created_by) {
            $creator = DB::table('users')->where('id', $cadence->created_by)->first();
            $result['creator'] = $creator ? (array) $creator : null;
        }

        // Add steps relation
        $steps = DB::table(self::TABLE_CADENCE_STEPS)
            ->where('cadence_id', $id)
            ->orderBy('step_order')
            ->get();

        $result['steps'] = array_map(function ($step) {
            $stepArray = (array) $step;

            // Add template relation if exists
            if ($step->template_id) {
                $template = DB::table('email_templates')->where('id', $step->template_id)->first();
                $stepArray['template'] = $template ? (array) $template : null;
            }

            return $stepArray;
        }, $steps->toArray());

        return $result;
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE_CADENCES)->insertGetId(array_merge(
            $data,
            ['created_at' => now(), 'updated_at' => now()]
        ));

        return $this->findByIdWithRelations($id);
    }

    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE_CADENCES)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return $this->findByIdWithRelations($id);
    }

    // =========================================================================
    // QUERY METHODS - CADENCES
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 20): PaginatedResult
    {
        $query = DB::table(self::TABLE_CADENCES);

        // Apply filters
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

        // Count total before pagination
        $total = $query->count();

        // Add sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        // Paginate
        $currentPage = $filters['page'] ?? 1;
        $offset = ($currentPage - 1) * $perPage;
        $items = $query->offset($offset)->limit($perPage)->get();

        // Enrich with relations and counts
        $enrichedItems = array_map(function ($cadence) {
            $result = (array) $cadence;

            // Add module
            if ($cadence->module_id) {
                $module = DB::table('modules')->where('id', $cadence->module_id)->first();
                $result['module'] = $module ? (array) $module : null;
            }

            // Add owner
            if ($cadence->owner_id) {
                $owner = DB::table('users')->where('id', $cadence->owner_id)->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            // Add creator
            if ($cadence->created_by) {
                $creator = DB::table('users')->where('id', $cadence->created_by)->first();
                $result['creator'] = $creator ? (array) $creator : null;
            }

            // Add counts
            $result['steps_count'] = DB::table(self::TABLE_CADENCE_STEPS)
                ->where('cadence_id', $cadence->id)
                ->count();

            $result['active_enrollments_count'] = DB::table(self::TABLE_CADENCE_ENROLLMENTS)
                ->where('cadence_id', $cadence->id)
                ->where('status', 'active')
                ->count();

            return $result;
        }, $items->toArray());

        return PaginatedResult::create(
            items: $enrichedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
        );
    }

    public function findAll(): array
    {
        $cadences = DB::table(self::TABLE_CADENCES)
            ->orderBy('name')
            ->get();

        return array_map(function ($cadence) {
            $result = (array) $cadence;

            if ($cadence->module_id) {
                $module = DB::table('modules')->where('id', $cadence->module_id)->first();
                $result['module'] = $module ? (array) $module : null;
            }

            if ($cadence->owner_id) {
                $owner = DB::table('users')->where('id', $cadence->owner_id)->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            return $result;
        }, $cadences->toArray());
    }

    public function findActive(): array
    {
        $cadences = DB::table(self::TABLE_CADENCES)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return array_map(function ($cadence) {
            $result = (array) $cadence;

            if ($cadence->module_id) {
                $module = DB::table('modules')->where('id', $cadence->module_id)->first();
                $result['module'] = $module ? (array) $module : null;
            }

            if ($cadence->owner_id) {
                $owner = DB::table('users')->where('id', $cadence->owner_id)->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            return $result;
        }, $cadences->toArray());
    }

    public function findForModule(int $moduleId, bool $activeOnly = false): array
    {
        $query = DB::table(self::TABLE_CADENCES)->where('module_id', $moduleId);

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        $cadences = $query->orderBy('name')->get();

        return array_map(function ($cadence) {
            $result = (array) $cadence;

            if ($cadence->owner_id) {
                $owner = DB::table('users')->where('id', $cadence->owner_id)->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            return $result;
        }, $cadences->toArray());
    }

    public function findByOwner(int $ownerId, bool $activeOnly = false): array
    {
        $query = DB::table(self::TABLE_CADENCES)->where('owner_id', $ownerId);

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        $cadences = $query->orderBy('name')->get();

        return array_map(function ($cadence) {
            $result = (array) $cadence;

            if ($cadence->module_id) {
                $module = DB::table('modules')->where('id', $cadence->module_id)->first();
                $result['module'] = $module ? (array) $module : null;
            }

            return $result;
        }, $cadences->toArray());
    }

    public function canEnroll(int $cadenceId): bool
    {
        $cadence = DB::table(self::TABLE_CADENCES)->where('id', $cadenceId)->first();

        if (!$cadence) {
            return false;
        }

        return $cadence->status === 'active' && $cadence->deleted_at === null;
    }

    public function duplicate(int $id, string $newName, int $createdBy): array
    {
        return DB::transaction(function () use ($id, $newName, $createdBy) {
            $original = DB::table(self::TABLE_CADENCES)->where('id', $id)->first();

            if (!$original) {
                throw new \RuntimeException("Cadence not found: {$id}");
            }

            // Create new cadence
            $newData = (array) $original;
            unset($newData['id'], $newData['created_at'], $newData['updated_at'], $newData['deleted_at']);
            $newData['name'] = $newName;
            $newData['status'] = 'draft';
            $newData['created_by'] = $createdBy;

            $newCadenceId = DB::table(self::TABLE_CADENCES)->insertGetId(array_merge(
                $newData,
                ['created_at' => now(), 'updated_at' => now()]
            ));

            // Duplicate steps
            $steps = DB::table(self::TABLE_CADENCE_STEPS)
                ->where('cadence_id', $id)
                ->orderBy('step_order')
                ->get();

            foreach ($steps as $step) {
                $stepData = (array) $step;
                unset($stepData['id'], $stepData['created_at'], $stepData['updated_at']);
                $stepData['cadence_id'] = $newCadenceId;

                DB::table(self::TABLE_CADENCE_STEPS)->insert(array_merge(
                    $stepData,
                    ['created_at' => now(), 'updated_at' => now()]
                ));
            }

            return $this->findByIdWithRelations($newCadenceId);
        });
    }

    // =========================================================================
    // BASIC CRUD - STEPS
    // =========================================================================

    public function findStepById(int $stepId): ?array
    {
        $step = DB::table(self::TABLE_CADENCE_STEPS)->where('id', $stepId)->first();
        return $step ? (array) $step : null;
    }

    public function createStep(int $cadenceId, array $data): array
    {
        $cadence = DB::table(self::TABLE_CADENCES)->where('id', $cadenceId)->first();

        if (!$cadence) {
            throw new \RuntimeException("Cadence not found: {$cadenceId}");
        }

        // Get next step order if not provided
        if (!isset($data['step_order'])) {
            $maxOrder = DB::table(self::TABLE_CADENCE_STEPS)
                ->where('cadence_id', $cadenceId)
                ->max('step_order');
            $data['step_order'] = ($maxOrder ?? 0) + 1;
        }

        $data['cadence_id'] = $cadenceId;

        $stepId = DB::table(self::TABLE_CADENCE_STEPS)->insertGetId(array_merge(
            $data,
            ['created_at' => now(), 'updated_at' => now()]
        ));

        return $this->findStepById($stepId);
    }

    public function updateStep(int $stepId, array $data): array
    {
        DB::table(self::TABLE_CADENCE_STEPS)
            ->where('id', $stepId)
            ->update(array_merge($data, ['updated_at' => now()]));

        $step = DB::table(self::TABLE_CADENCE_STEPS)->where('id', $stepId)->first();

        if (!$step) {
            throw new \RuntimeException("Step not found: {$stepId}");
        }

        return (array) $step;
    }

    public function deleteStep(int $stepId): bool
    {
        return (bool) DB::table(self::TABLE_CADENCE_STEPS)->where('id', $stepId)->delete();
    }

    public function reorderSteps(int $cadenceId, array $stepIds): void
    {
        DB::transaction(function () use ($cadenceId, $stepIds) {
            foreach ($stepIds as $order => $stepId) {
                DB::table(self::TABLE_CADENCE_STEPS)
                    ->where('id', $stepId)
                    ->where('cadence_id', $cadenceId)
                    ->update([
                        'step_order' => $order + 1,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function findStepsForCadence(int $cadenceId, bool $activeOnly = false): array
    {
        $query = DB::table(self::TABLE_CADENCE_STEPS)->where('cadence_id', $cadenceId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return array_map(fn($step) => (array) $step, $query->orderBy('step_order')->get()->toArray());
    }

    // =========================================================================
    // BASIC CRUD - ENROLLMENTS
    // =========================================================================

    public function findEnrollmentById(int $id): ?array
    {
        $enrollment = DB::table(self::TABLE_CADENCE_ENROLLMENTS)->where('id', $id)->first();
        return $enrollment ? (array) $enrollment : null;
    }

    public function createEnrollment(array $data): array
    {
        $enrollmentId = DB::table(self::TABLE_CADENCE_ENROLLMENTS)->insertGetId(array_merge(
            $data,
            ['created_at' => now(), 'updated_at' => now()]
        ));

        $enrollment = DB::table(self::TABLE_CADENCE_ENROLLMENTS)->where('id', $enrollmentId)->first();
        $result = (array) $enrollment;

        // Add currentStep relation
        if ($enrollment->current_step_id) {
            $currentStep = DB::table(self::TABLE_CADENCE_STEPS)
                ->where('id', $enrollment->current_step_id)
                ->first();
            $result['currentStep'] = $currentStep ? (array) $currentStep : null;
        }

        // Add enrolledBy relation
        if ($enrollment->enrolled_by) {
            $enrolledBy = DB::table('users')->where('id', $enrollment->enrolled_by)->first();
            $result['enrolledBy'] = $enrolledBy ? (array) $enrolledBy : null;
        }

        return $result;
    }

    public function updateEnrollment(int $id, array $data): array
    {
        DB::table(self::TABLE_CADENCE_ENROLLMENTS)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $enrollment = DB::table(self::TABLE_CADENCE_ENROLLMENTS)->where('id', $id)->first();

        if (!$enrollment) {
            throw new \RuntimeException("Enrollment not found: {$id}");
        }

        $result = (array) $enrollment;

        // Add currentStep relation
        if ($enrollment->current_step_id) {
            $currentStep = DB::table(self::TABLE_CADENCE_STEPS)
                ->where('id', $enrollment->current_step_id)
                ->first();
            $result['currentStep'] = $currentStep ? (array) $currentStep : null;
        }

        // Add enrolledBy relation
        if ($enrollment->enrolled_by) {
            $enrolledBy = DB::table('users')->where('id', $enrollment->enrolled_by)->first();
            $result['enrolledBy'] = $enrolledBy ? (array) $enrolledBy : null;
        }

        return $result;
    }

    public function deleteEnrollment(int $id): bool
    {
        return (bool) DB::table(self::TABLE_CADENCE_ENROLLMENTS)->where('id', $id)->delete();
    }

    public function findEnrollments(int $cadenceId, array $filters, int $perPage = 20): PaginatedResult
    {
        $query = DB::table(self::TABLE_CADENCE_ENROLLMENTS)
            ->where('cadence_id', $cadenceId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $total = $query->count();

        $currentPage = $filters['page'] ?? 1;
        $offset = ($currentPage - 1) * $perPage;
        $items = $query->orderBy('enrolled_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Enrich with relations
        $enrichedItems = array_map(function ($enrollment) {
            $result = (array) $enrollment;

            if ($enrollment->current_step_id) {
                $currentStep = DB::table(self::TABLE_CADENCE_STEPS)
                    ->where('id', $enrollment->current_step_id)
                    ->first();
                $result['currentStep'] = $currentStep ? (array) $currentStep : null;
            }

            if ($enrollment->enrolled_by) {
                $enrolledBy = DB::table('users')->where('id', $enrollment->enrolled_by)->first();
                $result['enrolledBy'] = $enrolledBy ? (array) $enrolledBy : null;
            }

            return $result;
        }, $items->toArray());

        return PaginatedResult::create(
            items: $enrichedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
        );
    }

    public function findActiveEnrollmentForRecord(int $cadenceId, int $recordId): ?array
    {
        $enrollment = DB::table(self::TABLE_CADENCE_ENROLLMENTS)
            ->where('cadence_id', $cadenceId)
            ->where('record_id', $recordId)
            ->where('status', 'active')
            ->first();

        return $enrollment ? (array) $enrollment : null;
    }

    public function findPreviousEnrollment(int $cadenceId, int $recordId): ?array
    {
        $enrollment = DB::table(self::TABLE_CADENCE_ENROLLMENTS)
            ->where('cadence_id', $cadenceId)
            ->where('record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->first();

        return $enrollment ? (array) $enrollment : null;
    }

    public function countActiveEnrollments(int $cadenceId): int
    {
        return DB::table(self::TABLE_CADENCE_ENROLLMENTS)
            ->where('cadence_id', $cadenceId)
            ->where('status', 'active')
            ->count();
    }

    // =========================================================================
    // BASIC CRUD - TEMPLATES
    // =========================================================================

    public function findTemplateById(int $id): ?array
    {
        $template = DB::table(self::TABLE_CADENCE_TEMPLATES)->where('id', $id)->first();

        if (!$template) {
            return null;
        }

        $result = (array) $template;

        // Parse steps from steps_config JSON
        if (isset($template->steps_config)) {
            $result['steps'] = is_string($template->steps_config)
                ? json_decode($template->steps_config, true)
                : $template->steps_config;
        }

        return $result;
    }

    public function findTemplates(?string $category = null): array
    {
        $query = DB::table(self::TABLE_CADENCE_TEMPLATES);

        if ($category) {
            $query->where('category', $category);
        }

        $templates = $query->orderBy('name')->get();

        return array_map(function ($template) {
            $result = (array) $template;

            // Parse steps from steps_config JSON
            if (isset($template->steps_config)) {
                $result['steps'] = is_string($template->steps_config)
                    ? json_decode($template->steps_config, true)
                    : $template->steps_config;
            }

            return $result;
        }, $templates->toArray());
    }

    public function createTemplate(array $data): array
    {
        $templateId = DB::table(self::TABLE_CADENCE_TEMPLATES)->insertGetId(array_merge(
            $data,
            ['created_at' => now(), 'updated_at' => now()]
        ));

        return $this->findTemplateById($templateId);
    }

    public function updateTemplate(int $id, array $data): array
    {
        DB::table(self::TABLE_CADENCE_TEMPLATES)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $template = $this->findTemplateById($id);

        if (!$template) {
            throw new \RuntimeException("Template not found: {$id}");
        }

        return $template;
    }

    public function deleteTemplate(int $id): bool
    {
        return (bool) DB::table(self::TABLE_CADENCE_TEMPLATES)->where('id', $id)->delete();
    }

    public function createTemplateStep(int $templateId, array $data): array
    {
        $template = DB::table(self::TABLE_CADENCE_TEMPLATES)->where('id', $templateId)->first();

        if (!$template) {
            throw new \RuntimeException("Template not found: {$templateId}");
        }

        $stepsConfig = $template->steps_config
            ? (is_string($template->steps_config) ? json_decode($template->steps_config, true) : $template->steps_config)
            : [];

        $stepsConfig[] = $data;

        DB::table(self::TABLE_CADENCE_TEMPLATES)
            ->where('id', $templateId)
            ->update([
                'steps_config' => json_encode($stepsConfig),
                'updated_at' => now(),
            ]);

        return $this->findTemplateById($templateId);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function getAnalytics(int $cadenceId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table(self::TABLE_CADENCE_ENROLLMENTS)
            ->where('cadence_id', $cadenceId);

        if ($startDate) {
            $query->where('enrolled_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->where('enrolled_at', '<=', Carbon::parse($endDate));
        }

        $enrollments = $query->get();

        $totalEnrollments = $enrollments->count();
        $activeEnrollments = $enrollments->where('status', 'active')->count();
        $completedEnrollments = $enrollments->where('status', 'completed')->count();
        $repliedEnrollments = $enrollments->where('status', 'replied')->count();
        $bouncedEnrollments = $enrollments->where('status', 'bounced')->count();

        return [
            'total_enrollments' => $totalEnrollments,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'replied_enrollments' => $repliedEnrollments,
            'bounced_enrollments' => $bouncedEnrollments,
            'reply_rate' => $totalEnrollments > 0
                ? round($repliedEnrollments / $totalEnrollments * 100, 1)
                : 0,
        ];
    }

    public function getStepPerformance(int $cadenceId): array
    {
        $steps = DB::table(self::TABLE_CADENCE_STEPS)
            ->where('cadence_id', $cadenceId)
            ->orderBy('step_order')
            ->get();

        return array_map(function ($step) {
            $executions = DB::table(self::TABLE_CADENCE_STEP_EXECUTIONS)
                ->where('step_id', $step->id)
                ->get();

            $executionsCount = $executions->count();
            $completed = $executions->where('status', 'completed')->count();
            $replies = $executions->whereNotNull('replied_at')->count();

            return [
                'step_id' => $step->id,
                'step_name' => $step->name,
                'channel' => $step->channel,
                'executions' => $executionsCount,
                'completed' => $completed,
                'replies' => $replies,
                'reply_rate' => $completed > 0 ? round($replies / $completed * 100, 1) : 0,
            ];
        }, $steps->toArray());
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function calculateNextStepTime(int $stepId): ?\DateTimeInterface
    {
        $step = DB::table(self::TABLE_CADENCE_STEPS)->where('id', $stepId)->first();

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

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): CadenceEntity
    {
        return CadenceEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            moduleId: (int) $row->module_id,
            status: CadenceStatus::from($row->status),
            entryCriteria: $row->entry_criteria ? json_decode($row->entry_criteria, true) : [],
            exitCriteria: $row->exit_criteria ? json_decode($row->exit_criteria, true) : [],
            settings: $row->settings ? json_decode($row->settings, true) : [],
            autoEnroll: (bool) $row->auto_enroll,
            allowReEnrollment: (bool) $row->allow_re_enrollment,
            reEnrollmentDays: $row->re_enrollment_days,
            maxEnrollmentsPerDay: $row->max_enrollments_per_day,
            createdBy: (int) $row->created_by,
            ownerId: (int) $row->owner_id,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(CadenceEntity $cadence): array
    {
        return [
            'name' => $cadence->getName(),
            'description' => $cadence->getDescription(),
            'module_id' => $cadence->getModuleId(),
            'status' => $cadence->getStatus()->value,
            'entry_criteria' => json_encode($cadence->getEntryCriteria()),
            'exit_criteria' => json_encode($cadence->getExitCriteria()),
            'settings' => json_encode($cadence->getSettings()),
            'auto_enroll' => $cadence->isAutoEnroll(),
            'allow_re_enrollment' => $cadence->allowsReEnrollment(),
            're_enrollment_days' => $cadence->getReEnrollmentDays(),
            'max_enrollments_per_day' => $cadence->getMaxEnrollmentsPerDay(),
            'created_by' => $cadence->getCreatedBy(),
            'owner_id' => $cadence->getOwnerId(),
        ];
    }
}
