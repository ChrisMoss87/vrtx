<?php

declare(strict_types=1);

namespace App\Application\Services\WebForm;

use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WebFormApplicationService
{
    public function __construct(
        private WebFormRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - FORMS
    // =========================================================================

    /**
     * List web forms with filtering and pagination.
     */
    public function listForms(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->listForms($filters, $perPage);
    }

    /**
     * Get a web form by ID.
     */
    public function getForm(int $id): ?array
    {
        return $this->repository->findById($id, [
            'creator:id,name,email',
            'module:id,name',
            'assignee:id,name,email',
            'fields'
        ]);
    }

    /**
     * Get a web form by slug.
     */
    public function getFormBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug, true, ['fields', 'module:id,name']);
    }

    /**
     * Get active forms for a module.
     */
    public function getActiveFormsForModule(int $moduleId): array
    {
        return $this->repository->getActiveFormsForModule($moduleId, ['fields']);
    }

    // =========================================================================
    // COMMAND USE CASES - FORMS
    // =========================================================================

    /**
     * Create a new web form.
     */
    public function createForm(array $data): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to create a form');
        }

        return $this->repository->createForm($data, $userId);
    }

    /**
     * Update a web form.
     */
    public function updateForm(int $id, array $data): array
    {
        return $this->repository->updateForm($id, $data);
    }

    /**
     * Delete a web form.
     */
    public function deleteForm(int $id): bool
    {
        return $this->repository->deleteForm($id);
    }

    /**
     * Duplicate a web form.
     */
    public function duplicateForm(int $id): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to duplicate a form');
        }

        return $this->repository->duplicateForm($id, $userId);
    }

    // =========================================================================
    // QUERY USE CASES - FIELDS
    // =========================================================================

    /**
     * List fields for a form.
     */
    public function listFields(int $formId): array
    {
        return $this->repository->listFields($formId, ['moduleField']);
    }

    /**
     * Get a field by ID.
     */
    public function getField(int $id): ?array
    {
        return $this->repository->findFieldById($id, ['webForm', 'moduleField']);
    }

    // =========================================================================
    // COMMAND USE CASES - FIELDS
    // =========================================================================

    /**
     * Create a form field.
     */
    public function createField(int $formId, array $data): array
    {
        return $this->repository->createField($formId, $data);
    }

    /**
     * Update a form field.
     */
    public function updateField(int $id, array $data): array
    {
        return $this->repository->updateField($id, $data);
    }

    /**
     * Delete a form field.
     */
    public function deleteField(int $id): bool
    {
        return $this->repository->deleteField($id);
    }

    /**
     * Reorder form fields.
     */
    public function reorderFields(int $formId, array $fieldIdsInOrder): bool
    {
        return $this->repository->reorderFields($formId, $fieldIdsInOrder);
    }

    // =========================================================================
    // QUERY USE CASES - SUBMISSIONS
    // =========================================================================

    /**
     * List submissions for a form with filtering.
     */
    public function listSubmissions(int $formId, array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->listSubmissions($formId, $filters, $perPage);
    }

    /**
     * Get a submission by ID.
     */
    public function getSubmission(int $id): ?array
    {
        return $this->repository->findSubmissionById($id, ['webForm.fields', 'record']);
    }

    /**
     * Get recent submissions for a form.
     */
    public function getRecentSubmissions(int $formId, int $limit = 10): array
    {
        return $this->repository->getRecentSubmissions($formId, $limit);
    }

    // =========================================================================
    // COMMAND USE CASES - SUBMISSIONS
    // =========================================================================

    /**
     * Process a form submission.
     */
    public function submitForm(int $formId, array $data, ?array $metadata = null): array
    {
        $form = $this->repository->findById($formId, ['fields']);

        if (!$form) {
            throw new \InvalidArgumentException('Form not found');
        }

        if (!$form['is_active']) {
            throw new \InvalidArgumentException('Form is not active');
        }

        return DB::transaction(function () use ($form, $formId, $data, $metadata) {
            // Validate submission data
            $validationRules = $this->buildValidationRules($form['fields']);
            $validator = Validator::make($data, $validationRules);

            // Create initial submission
            $submission = $this->repository->createSubmission($formId, $data, $metadata);

            if ($validator->fails()) {
                $submission = $this->repository->updateSubmission($submission['id'], [
                    'status' => 'failed',
                    'error_message' => $validator->errors()->first(),
                ]);

                $this->repository->incrementSubmissions($formId, false);

                return $submission;
            }

            // Check spam protection
            if ($this->hasSpamProtection($form) && $this->isSpam($data, $form)) {
                $submission = $this->repository->updateSubmission($submission['id'], [
                    'status' => 'spam',
                ]);
                $this->repository->incrementSpamBlocked($formId);

                return $submission;
            }

            // Process submission and create record
            try {
                $recordData = $this->mapSubmissionToRecord($form['fields'], $data);

                // TODO: Create module record
                // $record = ModuleRecord::create([...]);
                // $submission = $this->repository->updateSubmission($submission['id'], ['record_id' => $record->id]);

                $submission = $this->repository->updateSubmission($submission['id'], [
                    'status' => 'processed',
                ]);
                $this->repository->incrementSubmissions($formId, true);

                // TODO: Send notifications
                // TODO: Trigger workflows

            } catch (\Exception $e) {
                $submission = $this->repository->updateSubmission($submission['id'], [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $this->repository->incrementSubmissions($formId, false);
            }

            return $this->repository->findSubmissionById($submission['id'], ['webForm', 'record']);
        });
    }

    /**
     * Mark submission as spam.
     */
    public function markAsSpam(int $submissionId): array
    {
        return $this->repository->markSubmissionAsSpam($submissionId);
    }

    /**
     * Delete a submission.
     */
    public function deleteSubmission(int $id): bool
    {
        return $this->repository->deleteSubmission($id);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get form analytics summary.
     */
    public function getFormAnalytics(int $formId, ?string $fromDate = null, ?string $toDate = null): array
    {
        return $this->repository->getFormAnalytics($formId, $fromDate, $toDate);
    }

    /**
     * Track form view.
     */
    public function trackView(int $formId): void
    {
        $this->repository->trackView($formId);
    }

    /**
     * Get daily analytics for a form.
     */
    public function getDailyAnalytics(int $formId, int $days = 30): array
    {
        return $this->repository->getDailyAnalytics($formId, $days);
    }

    /**
     * Get top performing forms.
     */
    public function getTopPerformingForms(int $days = 30, int $limit = 10): array
    {
        return $this->repository->getTopPerformingForms($days, $limit);
    }

    /**
     * Get conversion funnel data.
     */
    public function getConversionFunnel(int $formId, int $days = 30): array
    {
        return $this->repository->getConversionFunnel($formId, $days);
    }

    /**
     * Get form performance comparison.
     */
    public function compareFormPerformance(array $formIds, int $days = 30): array
    {
        return $this->repository->compareFormPerformance($formIds, $days);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Build validation rules from form fields.
     */
    private function buildValidationRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? \Illuminate\Support\Str::snake($field['label']);
            $fieldRules = [];

            // Required validation
            if ($field['is_required']) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type-specific rules
            switch ($field['field_type']) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'number':
                case 'currency':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                case 'datetime':
                    $fieldRules[] = 'date';
                    break;
                case 'file':
                    $fieldRules[] = 'file';
                    $maxSize = $field['validation_rules']['max_size'] ?? 10240;
                    $fieldRules[] = "max:{$maxSize}";
                    if (!empty($field['validation_rules']['allowed_types'])) {
                        $fieldRules[] = 'mimes:' . implode(',', $field['validation_rules']['allowed_types']);
                    }
                    break;
                case 'select':
                case 'radio':
                    if (!empty($field['options'])) {
                        $values = array_column($field['options'], 'value');
                        $fieldRules[] = 'in:' . implode(',', $values);
                    }
                    break;
                case 'multi_select':
                    $fieldRules[] = 'array';
                    break;
            }

            // Custom validation rules
            if (!empty($field['validation_rules'])) {
                if (!empty($field['validation_rules']['min_length'])) {
                    $fieldRules[] = 'min:' . $field['validation_rules']['min_length'];
                }
                if (!empty($field['validation_rules']['max_length'])) {
                    $fieldRules[] = 'max:' . $field['validation_rules']['max_length'];
                }
                if (!empty($field['validation_rules']['pattern'])) {
                    $fieldRules[] = 'regex:' . $field['validation_rules']['pattern'];
                }
            }

            $rules[$fieldName] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Map submission data to module record data.
     */
    private function mapSubmissionToRecord(array $fields, array $submissionData): array
    {
        $recordData = [];

        foreach ($fields as $field) {
            if (!empty($field['module_field_id'])) {
                $fieldName = $field['name'] ?? \Illuminate\Support\Str::snake($field['label']);
                if (isset($submissionData[$fieldName])) {
                    $recordData[$field['module_field_id']] = $submissionData[$fieldName];
                }
            }
        }

        return $recordData;
    }

    /**
     * Check if form has spam protection enabled.
     */
    private function hasSpamProtection(array $form): bool
    {
        return !empty($form['spam_protection']['enabled']);
    }

    /**
     * Check if submission is spam.
     */
    private function isSpam(array $data, array $form): bool
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
