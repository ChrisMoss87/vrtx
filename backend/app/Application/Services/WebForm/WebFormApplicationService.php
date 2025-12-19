<?php

declare(strict_types=1);

namespace App\Application\Services\WebForm;

use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use App\Models\WebForm;
use App\Models\WebFormField;
use App\Models\WebFormSubmission;
use App\Models\WebFormAnalytics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WebFormApplicationService
{
    public function __construct(
        private WebFormRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - FORMS
    // =========================================================================

    /**
     * List web forms with filtering and pagination.
     */
    public function listForms(array $filters = [], int $perPage = 25): LengthAwarePaginator
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

        return $query->paginate($perPage);
    }

    /**
     * Get a web form by ID.
     */
    public function getForm(int $id): ?WebForm
    {
        return WebForm::with([
            'creator:id,name,email',
            'module:id,name',
            'assignee:id,name,email',
            'fields'
        ])->find($id);
    }

    /**
     * Get a web form by slug.
     */
    public function getFormBySlug(string $slug): ?WebForm
    {
        return WebForm::with(['fields', 'module:id,name'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get active forms for a module.
     */
    public function getActiveFormsForModule(int $moduleId): Collection
    {
        return WebForm::active()
            ->where('module_id', $moduleId)
            ->with('fields')
            ->orderBy('name')
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - FORMS
    // =========================================================================

    /**
     * Create a new web form.
     */
    public function createForm(array $data): WebForm
    {
        return DB::transaction(function () use ($data) {
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
                'created_by' => Auth::id(),
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

            return $form->fresh(['creator', 'module', 'fields']);
        });
    }

    /**
     * Update a web form.
     */
    public function updateForm(int $id, array $data): WebForm
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

        return $form->fresh();
    }

    /**
     * Delete a web form.
     */
    public function deleteForm(int $id): bool
    {
        $form = WebForm::findOrFail($id);
        return $form->delete();
    }

    /**
     * Duplicate a web form.
     */
    public function duplicateForm(int $id): WebForm
    {
        $original = WebForm::with('fields')->findOrFail($id);

        return DB::transaction(function () use ($original) {
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
                'created_by' => Auth::id(),
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

            return $newForm->fresh(['fields']);
        });
    }

    // =========================================================================
    // QUERY USE CASES - FIELDS
    // =========================================================================

    /**
     * List fields for a form.
     */
    public function listFields(int $formId): Collection
    {
        return WebFormField::where('web_form_id', $formId)
            ->with('moduleField')
            ->ordered()
            ->get();
    }

    /**
     * Get a field by ID.
     */
    public function getField(int $id): ?WebFormField
    {
        return WebFormField::with(['webForm', 'moduleField'])->find($id);
    }

    // =========================================================================
    // COMMAND USE CASES - FIELDS
    // =========================================================================

    /**
     * Create a form field.
     */
    public function createField(int $formId, array $data): WebFormField
    {
        return WebFormField::create([
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
    }

    /**
     * Update a form field.
     */
    public function updateField(int $id, array $data): WebFormField
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

        return $field->fresh();
    }

    /**
     * Delete a form field.
     */
    public function deleteField(int $id): bool
    {
        $field = WebFormField::findOrFail($id);
        return $field->delete();
    }

    /**
     * Reorder form fields.
     */
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
    // QUERY USE CASES - SUBMISSIONS
    // =========================================================================

    /**
     * List submissions for a form with filtering.
     */
    public function listSubmissions(int $formId, array $filters = [], int $perPage = 25): LengthAwarePaginator
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

        return $query->paginate($perPage);
    }

    /**
     * Get a submission by ID.
     */
    public function getSubmission(int $id): ?WebFormSubmission
    {
        return WebFormSubmission::with(['webForm.fields', 'record'])->find($id);
    }

    /**
     * Get recent submissions for a form.
     */
    public function getRecentSubmissions(int $formId, int $limit = 10): Collection
    {
        return WebFormSubmission::where('web_form_id', $formId)
            ->processed()
            ->orderBy('submitted_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - SUBMISSIONS
    // =========================================================================

    /**
     * Process a form submission.
     */
    public function submitForm(int $formId, array $data, ?array $metadata = null): WebFormSubmission
    {
        $form = WebForm::with('fields')->findOrFail($formId);

        if (!$form->is_active) {
            throw new \InvalidArgumentException('Form is not active');
        }

        return DB::transaction(function () use ($form, $data, $metadata) {
            // Validate submission data
            $validationRules = $this->buildValidationRules($form);
            $validator = Validator::make($data, $validationRules);

            $submission = WebFormSubmission::create([
                'web_form_id' => $form->id,
                'submission_data' => $data,
                'ip_address' => $metadata['ip_address'] ?? request()->ip(),
                'user_agent' => $metadata['user_agent'] ?? request()->userAgent(),
                'referrer' => $metadata['referrer'] ?? request()->header('referer'),
                'utm_params' => $metadata['utm_params'] ?? [],
                'status' => WebFormSubmission::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            if ($validator->fails()) {
                $submission->update([
                    'status' => WebFormSubmission::STATUS_FAILED,
                    'error_message' => $validator->errors()->first(),
                ]);

                WebFormAnalytics::incrementSubmissions($form->id, false);

                return $submission->fresh();
            }

            // Check spam protection
            if ($form->hasSpamProtection() && $this->isSpam($data, $form)) {
                $submission->update(['status' => WebFormSubmission::STATUS_SPAM]);
                WebFormAnalytics::incrementSpamBlocked($form->id);

                return $submission->fresh();
            }

            // Process submission and create record
            try {
                $recordData = $this->mapSubmissionToRecord($form, $data);

                // TODO: Create module record
                // $record = ModuleRecord::create([...]);
                // $submission->update(['record_id' => $record->id]);

                $submission->update(['status' => WebFormSubmission::STATUS_PROCESSED]);
                WebFormAnalytics::incrementSubmissions($form->id, true);

                // TODO: Send notifications
                // TODO: Trigger workflows

            } catch (\Exception $e) {
                $submission->update([
                    'status' => WebFormSubmission::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);

                WebFormAnalytics::incrementSubmissions($form->id, false);
            }

            return $submission->fresh(['webForm', 'record']);
        });
    }

    /**
     * Mark submission as spam.
     */
    public function markAsSpam(int $submissionId): WebFormSubmission
    {
        $submission = WebFormSubmission::findOrFail($submissionId);

        $submission->update(['status' => WebFormSubmission::STATUS_SPAM]);

        return $submission->fresh();
    }

    /**
     * Delete a submission.
     */
    public function deleteSubmission(int $id): bool
    {
        $submission = WebFormSubmission::findOrFail($id);
        return $submission->delete();
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get form analytics summary.
     */
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

    /**
     * Track form view.
     */
    public function trackView(int $formId): void
    {
        WebFormAnalytics::incrementViews($formId);
    }

    /**
     * Get daily analytics for a form.
     */
    public function getDailyAnalytics(int $formId, int $days = 30): Collection
    {
        return WebFormAnalytics::where('web_form_id', $formId)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get();
    }

    /**
     * Get top performing forms.
     */
    public function getTopPerformingForms(int $days = 30, int $limit = 10): Collection
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
            ->get();
    }

    /**
     * Get conversion funnel data.
     */
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

    /**
     * Get form performance comparison.
     */
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
    // HELPER METHODS
    // =========================================================================

    /**
     * Build validation rules from form fields.
     */
    private function buildValidationRules(WebForm $form): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            $fieldName = $field->field_name;
            $rules[$fieldName] = $field->getValidationRulesArray();
        }

        return $rules;
    }

    /**
     * Map submission data to module record data.
     */
    private function mapSubmissionToRecord(WebForm $form, array $submissionData): array
    {
        $recordData = [];

        foreach ($form->fields as $field) {
            if ($field->module_field_id) {
                $fieldName = $field->field_name;
                if (isset($submissionData[$fieldName])) {
                    $recordData[$field->module_field_id] = $submissionData[$fieldName];
                }
            }
        }

        return $recordData;
    }

    /**
     * Check if submission is spam.
     */
    private function isSpam(array $data, WebForm $form): bool
    {
        // Basic spam detection logic
        // TODO: Implement more sophisticated spam detection

        // Check for honeypot field
        if (!empty($data['_honeypot'])) {
            return true;
        }

        // Check for suspicious patterns
        $textContent = implode(' ', array_values($data));

        // Too many links
        if (substr_count(strtolower($textContent), 'http') > 3) {
            return true;
        }

        // Suspicious keywords
        $spamKeywords = ['viagra', 'casino', 'lottery', 'winner'];
        foreach ($spamKeywords as $keyword) {
            if (stripos($textContent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
