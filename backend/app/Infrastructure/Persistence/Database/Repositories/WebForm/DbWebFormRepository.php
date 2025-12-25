<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\WebForm;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WebForm\Entities\WebForm as WebFormEntity;
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class DbWebFormRepository implements WebFormRepositoryInterface
{
    private const TABLE_WEB_FORMS = 'web_forms';
    private const TABLE_WEB_FORM_FIELDS = 'web_form_fields';
    private const TABLE_WEB_FORM_SUBMISSIONS = 'web_form_submissions';
    private const TABLE_WEB_FORM_ANALYTICS = 'web_form_analytics';
    private const TABLE_USERS = 'users';
    private const TABLE_MODULES = 'modules';
    private const TABLE_MODULE_RECORDS = 'module_records';

    // Submission status constants
    private const STATUS_PROCESSED = 'processed';
    private const STATUS_FAILED = 'failed';
    private const STATUS_SPAM = 'spam';
    private const STATUS_PENDING = 'pending';

    // =========================================================================
    // FORM QUERIES
    // =========================================================================

    public function findById(int $id): ?WebFormEntity
    {
        $model = DB::table(self::TABLE_WEB_FORMS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findByIdAsArray(int $id, array $with = []): ?array
    {
        $form = DB::table(self::TABLE_WEB_FORMS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$form) {
            return null;
        }

        $result = $this->toArray($form);

        // Load relations if requested
        if (!empty($with)) {
            $result = $this->loadRelations($result, $with);
        }

        return $result;
    }

    public function findBySlug(string $slug, bool $activeOnly = true, array $with = []): ?array
    {
        $query = DB::table(self::TABLE_WEB_FORMS)
            ->where('slug', $slug)
            ->whereNull('deleted_at');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $form = $query->first();

        if (!$form) {
            return null;
        }

        $result = $this->toArray($form);

        if (!empty($with)) {
            $result = $this->loadRelations($result, $with);
        }

        return $result;
    }

    public function listForms(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_WEB_FORMS)
            ->whereNull('deleted_at');

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
            $query->where('is_active', true);
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

        // Get total count
        $total = $query->count();

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $offset = ($page - 1) * $perPage;
        $items = $query->offset($offset)->limit($perPage)->get();

        $itemsArray = [];
        foreach ($items as $item) {
            $formArray = $this->toArray($item);
            // Load relations
            $formArray = $this->loadRelations($formArray, ['creator:id,name,email', 'module:id,name', 'assignee:id,name,email']);
            $itemsArray[] = $formArray;
        }

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getActiveFormsForModule(int $moduleId, array $with = []): array
    {
        $forms = DB::table(self::TABLE_WEB_FORMS)
            ->where('is_active', true)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $result = [];
        foreach ($forms as $form) {
            $formArray = $this->toArray($form);
            if (!empty($with)) {
                $formArray = $this->loadRelations($formArray, $with);
            }
            $result[] = $formArray;
        }

        return $result;
    }

    public function getTopPerformingForms(int $days = 30, int $limit = 10): array
    {
        $fromDate = now()->subDays($days)->toDateString();

        $forms = DB::table(self::TABLE_WEB_FORMS)
            ->leftJoin(self::TABLE_WEB_FORM_SUBMISSIONS, self::TABLE_WEB_FORMS . '.id', '=', self::TABLE_WEB_FORM_SUBMISSIONS . '.web_form_id')
            ->select(
                self::TABLE_WEB_FORMS . '.*',
                DB::raw('COUNT(CASE WHEN ' . self::TABLE_WEB_FORM_SUBMISSIONS . '.submitted_at >= ? THEN 1 END) as total_submissions'),
                DB::raw('COUNT(CASE WHEN ' . self::TABLE_WEB_FORM_SUBMISSIONS . '.submitted_at >= ? AND ' . self::TABLE_WEB_FORM_SUBMISSIONS . '.status = ? THEN 1 END) as successful_submissions')
            )
            ->whereNull(self::TABLE_WEB_FORMS . '.deleted_at')
            ->groupBy(self::TABLE_WEB_FORMS . '.id')
            ->havingRaw('total_submissions > 0')
            ->orderBy('total_submissions', 'desc')
            ->limit($limit)
            ->setBindings([$fromDate, $fromDate, self::STATUS_PROCESSED])
            ->get();

        $result = [];
        foreach ($forms as $form) {
            $result[] = $this->toArray($form);
        }

        return $result;
    }

    // =========================================================================
    // FORM COMMANDS
    // =========================================================================

    public function createForm(array $data, int $createdBy): array
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $slug = $data['slug'] ?? $this->generateUniqueSlug($data['name']);
            $now = now();

            $formId = DB::table(self::TABLE_WEB_FORMS)->insertGetId([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'module_id' => $data['module_id'],
                'is_active' => $data['is_active'] ?? true,
                'settings' => json_encode($data['settings'] ?? []),
                'styling' => json_encode($data['styling'] ?? []),
                'thank_you_config' => json_encode($data['thank_you_config'] ?? []),
                'spam_protection' => json_encode($data['spam_protection'] ?? []),
                'created_by' => $createdBy,
                'assign_to_user_id' => $data['assign_to_user_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Create fields if provided
            if (!empty($data['fields']) && is_array($data['fields'])) {
                foreach ($data['fields'] as $index => $fieldData) {
                    $this->createField($formId, array_merge($fieldData, [
                        'display_order' => $fieldData['display_order'] ?? $index,
                    ]));
                }
            }

            // Fetch and return the created form with relations
            $form = $this->findByIdAsArray($formId, ['creator', 'module', 'fields']);
            return $form;
        });
    }

    public function updateForm(int $id, array $data): array
    {
        $form = DB::table(self::TABLE_WEB_FORMS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$form) {
            throw new \Exception("WebForm not found with id: {$id}");
        }

        $formArray = $this->toArray($form);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        if (isset($data['assign_to_user_id'])) {
            $updateData['assign_to_user_id'] = $data['assign_to_user_id'];
        }

        // Merge JSON fields
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode(array_merge($formArray['settings'] ?? [], $data['settings']));
        }
        if (isset($data['styling'])) {
            $updateData['styling'] = json_encode(array_merge($formArray['styling'] ?? [], $data['styling']));
        }
        if (isset($data['thank_you_config'])) {
            $updateData['thank_you_config'] = json_encode(array_merge($formArray['thank_you_config'] ?? [], $data['thank_you_config']));
        }
        if (isset($data['spam_protection'])) {
            $updateData['spam_protection'] = json_encode(array_merge($formArray['spam_protection'] ?? [], $data['spam_protection']));
        }

        $updateData['updated_at'] = now();

        DB::table(self::TABLE_WEB_FORMS)
            ->where('id', $id)
            ->update($updateData);

        return $this->findByIdAsArray($id);
    }

    public function deleteForm(int $id): bool
    {
        $form = DB::table(self::TABLE_WEB_FORMS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$form) {
            throw new \Exception("WebForm not found with id: {$id}");
        }

        // Soft delete
        return DB::table(self::TABLE_WEB_FORMS)
            ->where('id', $id)
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function duplicateForm(int $id, int $createdBy): array
    {
        $original = DB::table(self::TABLE_WEB_FORMS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$original) {
            throw new \Exception("WebForm not found with id: {$id}");
        }

        $fields = DB::table(self::TABLE_WEB_FORM_FIELDS)
            ->where('web_form_id', $id)
            ->orderBy('display_order')
            ->get();

        return DB::transaction(function () use ($original, $fields, $createdBy) {
            $now = now();
            $originalArray = $this->toArray($original);

            $newFormId = DB::table(self::TABLE_WEB_FORMS)->insertGetId([
                'name' => $originalArray['name'] . ' (Copy)',
                'slug' => $this->generateUniqueSlug($originalArray['name'] . ' (Copy)'),
                'description' => $originalArray['description'],
                'module_id' => $originalArray['module_id'],
                'is_active' => false, // Start as inactive
                'settings' => json_encode($originalArray['settings']),
                'styling' => json_encode($originalArray['styling']),
                'thank_you_config' => json_encode($originalArray['thank_you_config']),
                'spam_protection' => json_encode($originalArray['spam_protection']),
                'created_by' => $createdBy,
                'assign_to_user_id' => $originalArray['assign_to_user_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Copy fields
            foreach ($fields as $field) {
                $fieldArray = $this->toArray($field);
                DB::table(self::TABLE_WEB_FORM_FIELDS)->insert([
                    'web_form_id' => $newFormId,
                    'field_type' => $fieldArray['field_type'],
                    'label' => $fieldArray['label'],
                    'name' => $fieldArray['name'],
                    'placeholder' => $fieldArray['placeholder'],
                    'is_required' => $fieldArray['is_required'],
                    'module_field_id' => $fieldArray['module_field_id'],
                    'options' => json_encode($fieldArray['options']),
                    'validation_rules' => json_encode($fieldArray['validation_rules']),
                    'display_order' => $fieldArray['display_order'],
                    'settings' => json_encode($fieldArray['settings']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $this->findByIdAsArray($newFormId, ['fields']);
        });
    }

    // =========================================================================
    // FIELD QUERIES
    // =========================================================================

    public function listFields(int $formId, array $with = []): array
    {
        $fields = DB::table(self::TABLE_WEB_FORM_FIELDS)
            ->where('web_form_id', $formId)
            ->orderBy('display_order')
            ->get();

        $result = [];
        foreach ($fields as $field) {
            $fieldArray = $this->toArray($field);
            if (!empty($with)) {
                $fieldArray = $this->loadFieldRelations($fieldArray, $with);
            }
            $result[] = $fieldArray;
        }

        return $result;
    }

    public function findFieldById(int $id, array $with = []): ?array
    {
        $field = DB::table(self::TABLE_WEB_FORM_FIELDS)
            ->where('id', $id)
            ->first();

        if (!$field) {
            return null;
        }

        $result = $this->toArray($field);

        if (!empty($with)) {
            $result = $this->loadFieldRelations($result, $with);
        }

        return $result;
    }

    // =========================================================================
    // FIELD COMMANDS
    // =========================================================================

    public function createField(int $formId, array $data): array
    {
        $now = now();

        $fieldId = DB::table(self::TABLE_WEB_FORM_FIELDS)->insertGetId([
            'web_form_id' => $formId,
            'field_type' => $data['field_type'],
            'label' => $data['label'],
            'name' => $data['name'] ?? null,
            'placeholder' => $data['placeholder'] ?? null,
            'is_required' => $data['is_required'] ?? false,
            'module_field_id' => $data['module_field_id'] ?? null,
            'options' => json_encode($data['options'] ?? []),
            'validation_rules' => json_encode($data['validation_rules'] ?? []),
            'display_order' => $data['display_order'] ?? 0,
            'settings' => json_encode($data['settings'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findFieldById($fieldId);
    }

    public function updateField(int $id, array $data): array
    {
        $field = DB::table(self::TABLE_WEB_FORM_FIELDS)
            ->where('id', $id)
            ->first();

        if (!$field) {
            throw new \Exception("WebFormField not found with id: {$id}");
        }

        $fieldArray = $this->toArray($field);

        $updateData = [];

        if (isset($data['field_type'])) {
            $updateData['field_type'] = $data['field_type'];
        }
        if (isset($data['label'])) {
            $updateData['label'] = $data['label'];
        }
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['placeholder'])) {
            $updateData['placeholder'] = $data['placeholder'];
        }
        if (isset($data['is_required'])) {
            $updateData['is_required'] = $data['is_required'];
        }
        if (isset($data['module_field_id'])) {
            $updateData['module_field_id'] = $data['module_field_id'];
        }
        if (isset($data['options'])) {
            $updateData['options'] = json_encode($data['options']);
        }
        if (isset($data['validation_rules'])) {
            $updateData['validation_rules'] = json_encode($data['validation_rules']);
        }
        if (isset($data['display_order'])) {
            $updateData['display_order'] = $data['display_order'];
        }
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode(array_merge($fieldArray['settings'] ?? [], $data['settings']));
        }

        $updateData['updated_at'] = now();

        DB::table(self::TABLE_WEB_FORM_FIELDS)
            ->where('id', $id)
            ->update($updateData);

        return $this->findFieldById($id);
    }

    public function deleteField(int $id): bool
    {
        $field = DB::table(self::TABLE_WEB_FORM_FIELDS)
            ->where('id', $id)
            ->first();

        if (!$field) {
            throw new \Exception("WebFormField not found with id: {$id}");
        }

        return DB::table(self::TABLE_WEB_FORM_FIELDS)
            ->where('id', $id)
            ->delete() > 0;
    }

    public function reorderFields(int $formId, array $fieldIdsInOrder): bool
    {
        return DB::transaction(function () use ($formId, $fieldIdsInOrder) {
            foreach ($fieldIdsInOrder as $index => $fieldId) {
                DB::table(self::TABLE_WEB_FORM_FIELDS)
                    ->where('id', $fieldId)
                    ->where('web_form_id', $formId)
                    ->update([
                        'display_order' => $index,
                        'updated_at' => now(),
                    ]);
            }
            return true;
        });
    }

    // =========================================================================
    // SUBMISSION QUERIES
    // =========================================================================

    public function listSubmissions(int $formId, array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('web_form_id', $formId);

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
            $query->where('status', self::STATUS_PROCESSED);
        }

        // Filter failed only
        if (!empty($filters['failed'])) {
            $query->where('status', self::STATUS_FAILED);
        }

        // Filter spam only
        if (!empty($filters['spam'])) {
            $query->where('status', self::STATUS_SPAM);
        }

        // Search in submission data
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('submission_data', 'like', "%{$search}%");
        }

        // Get total count
        $total = $query->count();

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'submitted_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $offset = ($page - 1) * $perPage;
        $items = $query->offset($offset)->limit($perPage)->get();

        $itemsArray = [];
        foreach ($items as $item) {
            $submissionArray = $this->toArray($item);
            // Load relations
            $submissionArray = $this->loadSubmissionRelations($submissionArray, ['webForm:id,name', 'record']);
            $itemsArray[] = $submissionArray;
        }

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findSubmissionById(int $id, array $with = []): ?array
    {
        $submission = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('id', $id)
            ->first();

        if (!$submission) {
            return null;
        }

        $result = $this->toArray($submission);

        if (!empty($with)) {
            $result = $this->loadSubmissionRelations($result, $with);
        }

        return $result;
    }

    public function getRecentSubmissions(int $formId, int $limit = 10): array
    {
        $submissions = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('web_form_id', $formId)
            ->where('status', self::STATUS_PROCESSED)
            ->orderBy('submitted_at', 'desc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($submissions as $submission) {
            $result[] = $this->toArray($submission);
        }

        return $result;
    }

    // =========================================================================
    // SUBMISSION COMMANDS
    // =========================================================================

    public function createSubmission(int $formId, array $data, ?array $metadata = null): array
    {
        $now = now();

        $submissionId = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)->insertGetId([
            'web_form_id' => $formId,
            'submission_data' => json_encode($data),
            'ip_address' => $metadata['ip_address'] ?? request()->ip(),
            'user_agent' => $metadata['user_agent'] ?? request()->userAgent(),
            'referrer' => $metadata['referrer'] ?? request()->header('referer'),
            'utm_params' => json_encode($metadata['utm_params'] ?? []),
            'status' => $metadata['status'] ?? self::STATUS_PENDING,
            'submitted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findSubmissionById($submissionId);
    }

    public function updateSubmission(int $id, array $data): array
    {
        $submission = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('id', $id)
            ->first();

        if (!$submission) {
            throw new \Exception("WebFormSubmission not found with id: {$id}");
        }

        $updateData = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['submission_data', 'utm_params'])) {
                $updateData[$key] = is_array($value) ? json_encode($value) : $value;
            } else {
                $updateData[$key] = $value;
            }
        }

        $updateData['updated_at'] = now();

        DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('id', $id)
            ->update($updateData);

        return $this->findSubmissionById($id);
    }

    public function markSubmissionAsSpam(int $id): array
    {
        $submission = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('id', $id)
            ->first();

        if (!$submission) {
            throw new \Exception("WebFormSubmission not found with id: {$id}");
        }

        DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_SPAM,
                'updated_at' => now(),
            ]);

        return $this->findSubmissionById($id);
    }

    public function deleteSubmission(int $id): bool
    {
        $submission = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('id', $id)
            ->first();

        if (!$submission) {
            throw new \Exception("WebFormSubmission not found with id: {$id}");
        }

        return DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('id', $id)
            ->delete() > 0;
    }

    // =========================================================================
    // ANALYTICS QUERIES
    // =========================================================================

    public function getFormAnalytics(int $formId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ?? now()->subDays(30)->toDateString();
        $to = $toDate ?? now()->toDateString();

        $summary = $this->getAnalyticsSummary($formId, $from, $to);

        // Get submission status breakdown
        $submissions = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('web_form_id', $formId)
            ->whereBetween('submitted_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get source breakdown (UTM sources)
        $submissionsWithUtm = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('web_form_id', $formId)
            ->whereBetween('submitted_at', [$from, $to])
            ->whereNotNull('utm_params')
            ->get();

        $sources = [];
        foreach ($submissionsWithUtm as $submission) {
            $utmParams = json_decode($submission->utm_params, true) ?? [];
            $source = $utmParams['utm_source'] ?? 'Direct';
            $sources[$source] = ($sources[$source] ?? 0) + 1;
        }

        return array_merge($summary, [
            'by_status' => $submissions,
            'by_source' => $sources,
        ]);
    }

    public function getDailyAnalytics(int $formId, int $days = 30): array
    {
        $analytics = DB::table(self::TABLE_WEB_FORM_ANALYTICS)
            ->where('web_form_id', $formId)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get();

        $result = [];
        foreach ($analytics as $item) {
            $result[] = $this->toArray($item);
        }

        return $result;
    }

    public function getConversionFunnel(int $formId, int $days = 30): array
    {
        $fromDate = now()->subDays($days)->toDateString();

        $views = DB::table(self::TABLE_WEB_FORM_ANALYTICS)
            ->where('web_form_id', $formId)
            ->where('date', '>=', $fromDate)
            ->sum('views');

        $submissions = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('web_form_id', $formId)
            ->where('submitted_at', '>=', $fromDate)
            ->count();

        $successful = DB::table(self::TABLE_WEB_FORM_SUBMISSIONS)
            ->where('web_form_id', $formId)
            ->where('status', self::STATUS_PROCESSED)
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
            $form = DB::table(self::TABLE_WEB_FORMS)
                ->where('id', $formId)
                ->whereNull('deleted_at')
                ->first();

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
        $this->incrementAnalyticsViews($formId);
    }

    public function incrementSubmissions(int $formId, bool $successful): void
    {
        $this->incrementAnalyticsSubmissions($formId, $successful);
    }

    public function incrementSpamBlocked(int $formId): void
    {
        $this->incrementAnalyticsSpamBlocked($formId);
    }

    // =========================================================================
    // DDD METHODS
    // =========================================================================

    public function save(WebFormEntity $entity): WebFormEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId()) {
            $existing = DB::table(self::TABLE_WEB_FORMS)
                ->where('id', $entity->getId())
                ->whereNull('deleted_at')
                ->first();

            if (!$existing) {
                throw new \Exception("WebForm not found with id: {$entity->getId()}");
            }

            $data['updated_at'] = now();

            DB::table(self::TABLE_WEB_FORMS)
                ->where('id', $entity->getId())
                ->update($data);

            $model = DB::table(self::TABLE_WEB_FORMS)
                ->where('id', $entity->getId())
                ->first();
        } else {
            $now = now();
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            $id = DB::table(self::TABLE_WEB_FORMS)->insertGetId($data);

            $model = DB::table(self::TABLE_WEB_FORMS)
                ->where('id', $id)
                ->first();
        }

        return $this->toDomainEntity($model);
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $model): WebFormEntity
    {
        return WebFormEntity::reconstitute(
            id: $model->id,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at) : null,
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

    /**
     * Convert stdClass to array and handle JSON decoding
     */
    private function toArray(stdClass $object): array
    {
        $array = (array) $object;

        // Decode JSON fields
        $jsonFields = ['settings', 'styling', 'thank_you_config', 'spam_protection', 'options', 'validation_rules', 'submission_data', 'utm_params'];

        foreach ($jsonFields as $field) {
            if (isset($array[$field]) && is_string($array[$field])) {
                $array[$field] = json_decode($array[$field], true) ?? [];
            }
        }

        // Convert boolean fields
        $boolFields = ['is_active', 'is_required'];
        foreach ($boolFields as $field) {
            if (isset($array[$field])) {
                $array[$field] = (bool) $array[$field];
            }
        }

        return $array;
    }

    /**
     * Load relations for a form
     */
    private function loadRelations(array $form, array $with): array
    {
        foreach ($with as $relation) {
            // Parse relation with columns (e.g., "creator:id,name,email")
            [$relationName, $columns] = array_pad(explode(':', $relation), 2, null);
            $selectColumns = $columns ? explode(',', $columns) : ['*'];

            switch ($relationName) {
                case 'creator':
                    if ($form['created_by'] ?? null) {
                        $creator = DB::table(self::TABLE_USERS)
                            ->where('id', $form['created_by'])
                            ->select($selectColumns)
                            ->first();
                        $form['creator'] = $creator ? $this->toArray($creator) : null;
                    }
                    break;

                case 'module':
                    if ($form['module_id'] ?? null) {
                        $module = DB::table(self::TABLE_MODULES)
                            ->where('id', $form['module_id'])
                            ->select($selectColumns)
                            ->first();
                        $form['module'] = $module ? $this->toArray($module) : null;
                    }
                    break;

                case 'assignee':
                    if ($form['assign_to_user_id'] ?? null) {
                        $assignee = DB::table(self::TABLE_USERS)
                            ->where('id', $form['assign_to_user_id'])
                            ->select($selectColumns)
                            ->first();
                        $form['assignee'] = $assignee ? $this->toArray($assignee) : null;
                    }
                    break;

                case 'fields':
                    $fields = DB::table(self::TABLE_WEB_FORM_FIELDS)
                        ->where('web_form_id', $form['id'])
                        ->orderBy('display_order')
                        ->get();
                    $form['fields'] = [];
                    foreach ($fields as $field) {
                        $form['fields'][] = $this->toArray($field);
                    }
                    break;
            }
        }

        return $form;
    }

    /**
     * Load relations for a field
     */
    private function loadFieldRelations(array $field, array $with): array
    {
        foreach ($with as $relation) {
            [$relationName, $columns] = array_pad(explode(':', $relation), 2, null);
            $selectColumns = $columns ? explode(',', $columns) : ['*'];

            switch ($relationName) {
                case 'webForm':
                    if ($field['web_form_id'] ?? null) {
                        $form = DB::table(self::TABLE_WEB_FORMS)
                            ->where('id', $field['web_form_id'])
                            ->select($selectColumns)
                            ->first();
                        $field['web_form'] = $form ? $this->toArray($form) : null;
                    }
                    break;

                case 'moduleField':
                    if ($field['module_field_id'] ?? null) {
                        $moduleField = DB::table('fields')
                            ->where('id', $field['module_field_id'])
                            ->select($selectColumns)
                            ->first();
                        $field['module_field'] = $moduleField ? $this->toArray($moduleField) : null;
                    }
                    break;
            }
        }

        return $field;
    }

    /**
     * Load relations for a submission
     */
    private function loadSubmissionRelations(array $submission, array $with): array
    {
        foreach ($with as $relation) {
            [$relationName, $columns] = array_pad(explode(':', $relation), 2, null);
            $selectColumns = $columns ? explode(',', $columns) : ['*'];

            switch ($relationName) {
                case 'webForm':
                    if ($submission['web_form_id'] ?? null) {
                        $form = DB::table(self::TABLE_WEB_FORMS)
                            ->where('id', $submission['web_form_id'])
                            ->select($selectColumns)
                            ->first();
                        $submission['web_form'] = $form ? $this->toArray($form) : null;
                    }
                    break;

                case 'record':
                    if ($submission['record_id'] ?? null) {
                        $record = DB::table(self::TABLE_MODULE_RECORDS)
                            ->where('id', $submission['record_id'])
                            ->first();
                        $submission['record'] = $record ? $this->toArray($record) : null;
                    }
                    break;
            }
        }

        return $submission;
    }

    /**
     * Generate unique slug for form
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (DB::table(self::TABLE_WEB_FORMS)->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Get or create analytics record for a form and date
     */
    private function getOrCreateAnalyticsForDate(int $formId, ?string $date = null): stdClass
    {
        $date = $date ?? now()->toDateString();

        $analytics = DB::table(self::TABLE_WEB_FORM_ANALYTICS)
            ->where('web_form_id', $formId)
            ->where('date', $date)
            ->first();

        if (!$analytics) {
            $now = now();
            $id = DB::table(self::TABLE_WEB_FORM_ANALYTICS)->insertGetId([
                'web_form_id' => $formId,
                'date' => $date,
                'views' => 0,
                'submissions' => 0,
                'successful_submissions' => 0,
                'spam_blocked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $analytics = DB::table(self::TABLE_WEB_FORM_ANALYTICS)
                ->where('id', $id)
                ->first();
        }

        return $analytics;
    }

    /**
     * Increment analytics views
     */
    private function incrementAnalyticsViews(int $formId): void
    {
        $analytics = $this->getOrCreateAnalyticsForDate($formId);

        DB::table(self::TABLE_WEB_FORM_ANALYTICS)
            ->where('id', $analytics->id)
            ->increment('views');
    }

    /**
     * Increment analytics submissions
     */
    private function incrementAnalyticsSubmissions(int $formId, bool $successful = true): void
    {
        $analytics = $this->getOrCreateAnalyticsForDate($formId);

        DB::table(self::TABLE_WEB_FORM_ANALYTICS)
            ->where('id', $analytics->id)
            ->increment('submissions');

        if ($successful) {
            DB::table(self::TABLE_WEB_FORM_ANALYTICS)
                ->where('id', $analytics->id)
                ->increment('successful_submissions');
        }
    }

    /**
     * Increment analytics spam blocked
     */
    private function incrementAnalyticsSpamBlocked(int $formId): void
    {
        $analytics = $this->getOrCreateAnalyticsForDate($formId);

        DB::table(self::TABLE_WEB_FORM_ANALYTICS)
            ->where('id', $analytics->id)
            ->increment('spam_blocked');
    }

    /**
     * Get analytics summary for a form over a date range
     */
    private function getAnalyticsSummary(int $formId, string $startDate, string $endDate): array
    {
        $analytics = DB::table(self::TABLE_WEB_FORM_ANALYTICS)
            ->where('web_form_id', $formId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalViews = 0;
        $totalSubmissions = 0;
        $totalSuccessful = 0;
        $totalSpam = 0;
        $daily = [];

        foreach ($analytics as $item) {
            $totalViews += $item->views;
            $totalSubmissions += $item->submissions;
            $totalSuccessful += $item->successful_submissions;
            $totalSpam += $item->spam_blocked;

            $conversionRate = $item->views > 0
                ? round(($item->successful_submissions / $item->views) * 100, 2)
                : 0;

            $daily[] = [
                'date' => $item->date,
                'views' => $item->views,
                'submissions' => $item->submissions,
                'successful' => $item->successful_submissions,
                'spam' => $item->spam_blocked,
                'conversion_rate' => $conversionRate,
            ];
        }

        return [
            'total_views' => $totalViews,
            'total_submissions' => $totalSubmissions,
            'successful_submissions' => $totalSuccessful,
            'spam_blocked' => $totalSpam,
            'conversion_rate' => $totalViews > 0
                ? round(($totalSuccessful / $totalViews) * 100, 2)
                : 0,
            'daily' => $daily,
        ];
    }
}
