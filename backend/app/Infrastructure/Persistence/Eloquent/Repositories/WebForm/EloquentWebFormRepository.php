<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\WebForm;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WebForm\Entities\WebForm as WebFormEntity;
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use App\Models\WebForm;
use App\Models\WebFormAnalytics;
use App\Models\WebFormField;
use App\Models\WebFormSubmission;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class EloquentWebFormRepository implements WebFormRepositoryInterface
{
    // =========================================================================
    // FORM QUERIES
    // =========================================================================

    public function findById(int $id): ?WebFormEntity
    {
        $model = WebForm::find($id);

        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findByIdAsArray(int $id, array $with = []): ?array
    {
        $query = WebForm::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $form = $query->find($id);

        return $form ? $form->toArray() : null;
    }

    public function findBySlug(string $slug, bool $activeOnly = true, array $with = []): ?array
    {
        $query = WebForm::where('slug', $slug);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if (!empty($with)) {
            $query->with($with);
        }

        $form = $query->first();

        return $form ? $form->toArray() : null;
    }

    public function listForms(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = WebForm::query()
            ->with(['creator:id,name,email', 'module:id,name', 'assignee:id,name,email']);

        // Filter by module
        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        // Filter by creator
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->active();
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function getActiveFormsForModule(int $moduleId, array $with = []): array
    {
        $query = WebForm::active()
            ->where('module_id', $moduleId)
            ->orderBy('name');

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get()->map(fn($form) => $form->toArray())->toArray();
    }

    public function getTopPerformingForms(int $days = 30, int $limit = 10): array
    {
        $fromDate = now()->subDays($days)->toDateString();

        return WebForm::query()
            ->withCount([
                'submissions as total_submissions' => function ($q) use ($fromDate) {
                    $q->where('submitted_at', '>=', $fromDate);
                },
                'submissions as successful_submissions' => function ($q) use ($fromDate) {
                    $q->processed()->where('submitted_at', '>=', $fromDate);
                },
            ])
            ->having('total_submissions', '>', 0)
            ->orderBy('total_submissions', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($form) => $form->toArray())
            ->toArray();
    }

    // =========================================================================
    // FORM COMMANDS
    // =========================================================================

    public function createForm(array $data, int $createdBy): array
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $form = WebForm::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'module_id' => $data['module_id'],
                'is_active' => $data['is_active'] ?? true,
                'settings' => $data['settings'] ?? [],
                'styling' => $data['styling'] ?? [],
                'thank_you_config' => $data['thank_you_config'] ?? [],
                'spam_protection' => $data['spam_protection'] ?? [],
                'created_by' => $createdBy,
                'assign_to_user_id' => $data['assign_to_user_id'] ?? null,
            ]);

            // Create fields if provided
            if (!empty($data['fields']) && is_array($data['fields'])) {
                foreach ($data['fields'] as $index => $fieldData) {
                    $this->createField($form->id, array_merge($fieldData, [
                        'display_order' => $fieldData['display_order'] ?? $index,
                    ]));
                }
            }

            return $form->fresh(['creator', 'module', 'fields'])->toArray();
        });
    }

    public function updateForm(int $id, array $data): array
    {
        $form = WebForm::findOrFail($id);

        $form->update([
            'name' => $data['name'] ?? $form->name,
            'description' => $data['description'] ?? $form->description,
            'is_active' => $data['is_active'] ?? $form->is_active,
            'settings' => array_merge($form->settings ?? [], $data['settings'] ?? []),
            'styling' => array_merge($form->styling ?? [], $data['styling'] ?? []),
            'thank_you_config' => array_merge($form->thank_you_config ?? [], $data['thank_you_config'] ?? []),
            'spam_protection' => array_merge($form->spam_protection ?? [], $data['spam_protection'] ?? []),
            'assign_to_user_id' => $data['assign_to_user_id'] ?? $form->assign_to_user_id,
        ]);

        return $form->fresh()->toArray();
    }

    public function deleteForm(int $id): bool
    {
        $form = WebForm::findOrFail($id);
        return $form->delete();
    }

    public function duplicateForm(int $id, int $createdBy): array
    {
        $original = WebForm::with('fields')->findOrFail($id);

        return DB::transaction(function () use ($original, $createdBy) {
            $newForm = WebForm::create([
                'name' => $original->name . ' (Copy)',
                'slug' => null, // Will be auto-generated
                'description' => $original->description,
                'module_id' => $original->module_id,
                'is_active' => false, // Start as inactive
                'settings' => $original->settings,
                'styling' => $original->styling,
                'thank_you_config' => $original->thank_you_config,
                'spam_protection' => $original->spam_protection,
                'created_by' => $createdBy,
                'assign_to_user_id' => $original->assign_to_user_id,
            ]);

            // Copy fields
            foreach ($original->fields as $field) {
                WebFormField::create([
                    'web_form_id' => $newForm->id,
                    'field_type' => $field->field_type,
                    'label' => $field->label,
                    'name' => $field->name,
                    'placeholder' => $field->placeholder,
                    'is_required' => $field->is_required,
                    'module_field_id' => $field->module_field_id,
                    'options' => $field->options,
                    'validation_rules' => $field->validation_rules,
                    'display_order' => $field->display_order,
                    'settings' => $field->settings,
                ]);
            }

            return $newForm->fresh(['fields'])->toArray();
        });
    }

    // =========================================================================
    // FIELD QUERIES
    // =========================================================================

    public function listFields(int $formId, array $with = []): array
    {
        $query = WebFormField::where('web_form_id', $formId)->ordered();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get()->map(fn($field) => $field->toArray())->toArray();
    }

    public function findFieldById(int $id, array $with = []): ?array
    {
        $query = WebFormField::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $field = $query->find($id);

        return $field ? $field->toArray() : null;
    }

    // =========================================================================
    // FIELD COMMANDS
    // =========================================================================

    public function createField(int $formId, array $data): array
    {
        $field = WebFormField::create([
            'web_form_id' => $formId,
            'field_type' => $data['field_type'],
            'label' => $data['label'],
            'name' => $data['name'] ?? null,
            'placeholder' => $data['placeholder'] ?? null,
            'is_required' => $data['is_required'] ?? false,
            'module_field_id' => $data['module_field_id'] ?? null,
            'options' => $data['options'] ?? [],
            'validation_rules' => $data['validation_rules'] ?? [],
            'display_order' => $data['display_order'] ?? 0,
            'settings' => $data['settings'] ?? [],
        ]);

        return $field->toArray();
    }

    public function updateField(int $id, array $data): array
    {
        $field = WebFormField::findOrFail($id);

        $field->update([
            'field_type' => $data['field_type'] ?? $field->field_type,
            'label' => $data['label'] ?? $field->label,
            'name' => $data['name'] ?? $field->name,
            'placeholder' => $data['placeholder'] ?? $field->placeholder,
            'is_required' => $data['is_required'] ?? $field->is_required,
            'module_field_id' => $data['module_field_id'] ?? $field->module_field_id,
            'options' => $data['options'] ?? $field->options,
            'validation_rules' => $data['validation_rules'] ?? $field->validation_rules,
            'display_order' => $data['display_order'] ?? $field->display_order,
            'settings' => array_merge($field->settings ?? [], $data['settings'] ?? []),
        ]);

        return $field->fresh()->toArray();
    }

    public function deleteField(int $id): bool
    {
        $field = WebFormField::findOrFail($id);
        return $field->delete();
    }

    public function reorderFields(int $formId, array $fieldIdsInOrder): bool
    {
        return DB::transaction(function () use ($formId, $fieldIdsInOrder) {
            foreach ($fieldIdsInOrder as $index => $fieldId) {
                WebFormField::where('id', $fieldId)
                    ->where('web_form_id', $formId)
                    ->update(['display_order' => $index]);
            }
            return true;
        });
    }

    // =========================================================================
    // SUBMISSION QUERIES
    // =========================================================================

    public function listSubmissions(int $formId, array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = WebFormSubmission::where('web_form_id', $formId)
            ->with(['webForm:id,name', 'record']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('submitted_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('submitted_at', '<=', $filters['to_date']);
        }

        // Filter processed only
        if (!empty($filters['processed'])) {
            $query->processed();
        }

        // Filter failed only
        if (!empty($filters['failed'])) {
            $query->failed();
        }

        // Filter spam only
        if (!empty($filters['spam'])) {
            $query->spam();
        }

        // Search in submission data
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('submission_data', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'submitted_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function findSubmissionById(int $id, array $with = []): ?array
    {
        $query = WebFormSubmission::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $submission = $query->find($id);

        return $submission ? $submission->toArray() : null;
    }

    public function getRecentSubmissions(int $formId, int $limit = 10): array
    {
        return WebFormSubmission::where('web_form_id', $formId)
            ->processed()
            ->orderBy('submitted_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($submission) => $submission->toArray())
            ->toArray();
    }

    // =========================================================================
    // SUBMISSION COMMANDS
    // =========================================================================

    public function createSubmission(int $formId, array $data, ?array $metadata = null): array
    {
        $submission = WebFormSubmission::create([
            'web_form_id' => $formId,
            'submission_data' => $data,
            'ip_address' => $metadata['ip_address'] ?? request()->ip(),
            'user_agent' => $metadata['user_agent'] ?? request()->userAgent(),
            'referrer' => $metadata['referrer'] ?? request()->header('referer'),
            'utm_params' => $metadata['utm_params'] ?? [],
            'status' => $metadata['status'] ?? WebFormSubmission::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        return $submission->toArray();
    }

    public function updateSubmission(int $id, array $data): array
    {
        $submission = WebFormSubmission::findOrFail($id);
        $submission->update($data);

        return $submission->fresh()->toArray();
    }

    public function markSubmissionAsSpam(int $id): array
    {
        $submission = WebFormSubmission::findOrFail($id);
        $submission->update(['status' => WebFormSubmission::STATUS_SPAM]);

        return $submission->fresh()->toArray();
    }

    public function deleteSubmission(int $id): bool
    {
        $submission = WebFormSubmission::findOrFail($id);
        return $submission->delete();
    }

    // =========================================================================
    // ANALYTICS QUERIES
    // =========================================================================

    public function getFormAnalytics(int $formId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ?? now()->subDays(30)->toDateString();
        $to = $toDate ?? now()->toDateString();

        $summary = WebFormAnalytics::getSummary($formId, $from, $to);

        // Get submission status breakdown
        $submissions = WebFormSubmission::where('web_form_id', $formId)
            ->whereBetween('submitted_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get source breakdown (UTM sources)
        $sources = WebFormSubmission::where('web_form_id', $formId)
            ->whereBetween('submitted_at', [$from, $to])
            ->whereNotNull('utm_params')
            ->get()
            ->groupBy(fn($s) => $s->getUtmParam('utm_source') ?? 'Direct')
            ->map(fn($group) => $group->count())
            ->toArray();

        return array_merge($summary, [
            'by_status' => $submissions,
            'by_source' => $sources,
        ]);
    }

    public function getDailyAnalytics(int $formId, int $days = 30): array
    {
        return WebFormAnalytics::where('web_form_id', $formId)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn($analytics) => $analytics->toArray())
            ->toArray();
    }

    public function getConversionFunnel(int $formId, int $days = 30): array
    {
        $fromDate = now()->subDays($days)->toDateString();

        $views = WebFormAnalytics::where('web_form_id', $formId)
            ->where('date', '>=', $fromDate)
            ->sum('views');

        $submissions = WebFormSubmission::where('web_form_id', $formId)
            ->where('submitted_at', '>=', $fromDate)
            ->count();

        $successful = WebFormSubmission::where('web_form_id', $formId)
            ->processed()
            ->where('submitted_at', '>=', $fromDate)
            ->count();

        return [
            'views' => $views,
            'submissions' => $submissions,
            'successful' => $successful,
            'view_to_submit_rate' => $views > 0 ? round(($submissions / $views) * 100, 2) : 0,
            'submit_to_success_rate' => $submissions > 0 ? round(($successful / $submissions) * 100, 2) : 0,
            'overall_conversion_rate' => $views > 0 ? round(($successful / $views) * 100, 2) : 0,
        ];
    }

    public function compareFormPerformance(array $formIds, int $days = 30): array
    {
        $fromDate = now()->subDays($days)->toDateString();

        $comparison = [];

        foreach ($formIds as $formId) {
            $form = WebForm::find($formId);
            if (!$form) {
                continue;
            }

            $analytics = $this->getFormAnalytics($formId, $fromDate);

            $comparison[] = [
                'form_id' => $formId,
                'form_name' => $form->name,
                'views' => $analytics['total_views'],
                'submissions' => $analytics['total_submissions'],
                'successful' => $analytics['successful_submissions'],
                'conversion_rate' => $analytics['conversion_rate'],
            ];
        }

        return $comparison;
    }

    // =========================================================================
    // ANALYTICS COMMANDS
    // =========================================================================

    public function trackView(int $formId): void
    {
        WebFormAnalytics::incrementViews($formId);
    }

    public function incrementSubmissions(int $formId, bool $successful): void
    {
        WebFormAnalytics::incrementSubmissions($formId, $successful);
    }

    public function incrementSpamBlocked(int $formId): void
    {
        WebFormAnalytics::incrementSpamBlocked($formId);
    }

    // =========================================================================
    // DDD METHODS
    // =========================================================================

    public function save(WebFormEntity $entity): WebFormEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId()) {
            $model = WebForm::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = WebForm::create($data);
        }

        return $this->toDomainEntity($model);
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(WebForm $model): WebFormEntity
    {
        return WebFormEntity::reconstitute(
            id: $model->id,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at->toDateTimeString()) : null,
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );
    }

    private function toModelData(WebFormEntity $entity): array
    {
        $data = [];

        if ($entity->getCreatedAt()) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        if ($entity->getUpdatedAt()) {
            $data['updated_at'] = $entity->getUpdatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
