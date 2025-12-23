<?php

declare(strict_types=1);

namespace App\Domain\WebForm\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WebForm\Entities\WebForm;

interface WebFormRepositoryInterface
{
    // =========================================================================
    // FORM QUERIES
    // =========================================================================

    /**
     * Find a form by ID.
     *
     * @param int $id
     * @return WebForm|null Form entity or null if not found
     */
    public function findById(int $id): ?WebForm;

    /**
     * Find a form by ID as array (for backward compatibility).
     *
     * @param int $id
     * @param array $with Relations to eager load
     * @return array|null Form data as array or null if not found
     */
    public function findByIdAsArray(int $id, array $with = []): ?array;

    /**
     * Find a form by slug.
     *
     * @param string $slug
     * @param bool $activeOnly Only return active forms
     * @param array $with Relations to eager load
     * @return array|null Form data as array or null if not found
     */
    public function findBySlug(string $slug, bool $activeOnly = true, array $with = []): ?array;

    /**
     * List forms with filtering and pagination.
     *
     * @param array $filters Filter criteria (module_id, created_by, active, search, sort_by, sort_dir)
     * @param int $perPage Items per page
     * @param int $page Current page number
     * @return PaginatedResult Paginated forms as arrays
     */
    public function listForms(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get active forms for a specific module.
     *
     * @param int $moduleId
     * @param array $with Relations to eager load
     * @return array Array of form data
     */
    public function getActiveFormsForModule(int $moduleId, array $with = []): array;

    /**
     * Get top performing forms by submission count.
     *
     * @param int $days Number of days to look back
     * @param int $limit Maximum number of results
     * @return array Array of form data with submission counts
     */
    public function getTopPerformingForms(int $days = 30, int $limit = 10): array;

    // =========================================================================
    // FORM COMMANDS
    // =========================================================================

    /**
     * Create a new web form.
     *
     * @param array $data Form data
     * @param int $createdBy User ID who created the form
     * @return array Created form data
     */
    public function createForm(array $data, int $createdBy): array;

    /**
     * Save a web form entity.
     *
     * @param WebForm $entity
     * @return WebForm Saved form entity
     */
    public function save(WebForm $entity): WebForm;

    /**
     * Update an existing web form.
     *
     * @param int $id Form ID
     * @param array $data Updated form data
     * @return array Updated form data
     */
    public function updateForm(int $id, array $data): array;

    /**
     * Delete a web form.
     *
     * @param int $id Form ID
     * @return bool Success status
     */
    public function deleteForm(int $id): bool;

    /**
     * Duplicate a web form.
     *
     * @param int $id Original form ID
     * @param int $createdBy User ID who is duplicating
     * @return array Duplicated form data
     */
    public function duplicateForm(int $id, int $createdBy): array;

    // =========================================================================
    // FIELD QUERIES
    // =========================================================================

    /**
     * List all fields for a form.
     *
     * @param int $formId
     * @param array $with Relations to eager load
     * @return array Array of field data
     */
    public function listFields(int $formId, array $with = []): array;

    /**
     * Find a field by ID.
     *
     * @param int $id
     * @param array $with Relations to eager load
     * @return array|null Field data as array or null if not found
     */
    public function findFieldById(int $id, array $with = []): ?array;

    // =========================================================================
    // FIELD COMMANDS
    // =========================================================================

    /**
     * Create a form field.
     *
     * @param int $formId
     * @param array $data Field data
     * @return array Created field data
     */
    public function createField(int $formId, array $data): array;

    /**
     * Update a form field.
     *
     * @param int $id Field ID
     * @param array $data Updated field data
     * @return array Updated field data
     */
    public function updateField(int $id, array $data): array;

    /**
     * Delete a form field.
     *
     * @param int $id Field ID
     * @return bool Success status
     */
    public function deleteField(int $id): bool;

    /**
     * Reorder form fields.
     *
     * @param int $formId
     * @param array $fieldIdsInOrder Array of field IDs in desired order
     * @return bool Success status
     */
    public function reorderFields(int $formId, array $fieldIdsInOrder): bool;

    // =========================================================================
    // SUBMISSION QUERIES
    // =========================================================================

    /**
     * List submissions for a form with filtering and pagination.
     *
     * @param int $formId
     * @param array $filters Filter criteria (status, from_date, to_date, processed, failed, spam, search, sort_by, sort_dir)
     * @param int $perPage Items per page
     * @param int $page Current page number
     * @return PaginatedResult Paginated submissions as arrays
     */
    public function listSubmissions(int $formId, array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Find a submission by ID.
     *
     * @param int $id
     * @param array $with Relations to eager load
     * @return array|null Submission data as array or null if not found
     */
    public function findSubmissionById(int $id, array $with = []): ?array;

    /**
     * Get recent submissions for a form.
     *
     * @param int $formId
     * @param int $limit Maximum number of results
     * @return array Array of submission data
     */
    public function getRecentSubmissions(int $formId, int $limit = 10): array;

    // =========================================================================
    // SUBMISSION COMMANDS
    // =========================================================================

    /**
     * Create a form submission.
     *
     * @param int $formId
     * @param array $data Submission data
     * @param array|null $metadata Additional metadata (ip_address, user_agent, etc.)
     * @return array Created submission data
     */
    public function createSubmission(int $formId, array $data, ?array $metadata = null): array;

    /**
     * Update a submission.
     *
     * @param int $id Submission ID
     * @param array $data Updated submission data
     * @return array Updated submission data
     */
    public function updateSubmission(int $id, array $data): array;

    /**
     * Mark submission as spam.
     *
     * @param int $id Submission ID
     * @return array Updated submission data
     */
    public function markSubmissionAsSpam(int $id): array;

    /**
     * Delete a submission.
     *
     * @param int $id Submission ID
     * @return bool Success status
     */
    public function deleteSubmission(int $id): bool;

    // =========================================================================
    // ANALYTICS QUERIES
    // =========================================================================

    /**
     * Get form analytics summary.
     *
     * @param int $formId
     * @param string|null $fromDate Start date (Y-m-d format)
     * @param string|null $toDate End date (Y-m-d format)
     * @return array Analytics data
     */
    public function getFormAnalytics(int $formId, ?string $fromDate = null, ?string $toDate = null): array;

    /**
     * Get daily analytics for a form.
     *
     * @param int $formId
     * @param int $days Number of days to look back
     * @return array Array of daily analytics data
     */
    public function getDailyAnalytics(int $formId, int $days = 30): array;

    /**
     * Get conversion funnel data.
     *
     * @param int $formId
     * @param int $days Number of days to look back
     * @return array Funnel metrics
     */
    public function getConversionFunnel(int $formId, int $days = 30): array;

    /**
     * Compare performance of multiple forms.
     *
     * @param array $formIds Array of form IDs to compare
     * @param int $days Number of days to look back
     * @return array Array of comparison data for each form
     */
    public function compareFormPerformance(array $formIds, int $days = 30): array;

    // =========================================================================
    // ANALYTICS COMMANDS
    // =========================================================================

    /**
     * Track a form view.
     *
     * @param int $formId
     * @return void
     */
    public function trackView(int $formId): void;

    /**
     * Increment submission counts.
     *
     * @param int $formId
     * @param bool $successful Whether the submission was successful
     * @return void
     */
    public function incrementSubmissions(int $formId, bool $successful): void;

    /**
     * Increment spam blocked count.
     *
     * @param int $formId
     * @return void
     */
    public function incrementSpamBlocked(int $formId): void;
}
