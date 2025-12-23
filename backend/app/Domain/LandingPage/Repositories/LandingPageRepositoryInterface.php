<?php

declare(strict_types=1);

namespace App\Domain\LandingPage\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;

interface LandingPageRepositoryInterface
{
    // =========================================================================
    // QUERY METHODS - LANDING PAGES
    // =========================================================================

    /**
     * List landing pages with filtering and pagination.
     */
    public function listPages(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get a single landing page by ID with relations.
     */
    public function getPageById(int $id, array $relations = []): ?array;

    /**
     * Get a landing page by slug with relations.
     */
    public function getPageBySlug(string $slug, array $relations = []): ?array;

    /**
     * Get published pages.
     */
    public function getPublishedPages(array $relations = []): array;

    /**
     * Get draft pages.
     */
    public function getDraftPages(array $relations = []): array;

    // =========================================================================
    // QUERY METHODS - TEMPLATES
    // =========================================================================

    /**
     * List landing page templates with optional filters.
     */
    public function listTemplates(array $filters = []): array;

    /**
     * Get a template by ID with relations.
     */
    public function getTemplateById(int $id, array $relations = []): ?array;

    // =========================================================================
    // QUERY METHODS - VARIANTS
    // =========================================================================

    /**
     * Get variants for a landing page.
     */
    public function getVariantsByPageId(int $pageId): array;

    /**
     * Get a specific variant by ID with relations.
     */
    public function getVariantById(int $id, array $relations = []): ?array;

    // =========================================================================
    // QUERY METHODS - ANALYTICS
    // =========================================================================

    /**
     * Get analytics for a landing page with optional date range.
     */
    public function getPageAnalytics(int $pageId, ?string $dateFrom = null, ?string $dateTo = null): array;

    /**
     * Get variant analytics for comparison.
     */
    public function getVariantAnalytics(int $pageId): array;

    /**
     * Get summary analytics for a page.
     */
    public function getPageSummary(int $pageId): array;

    /**
     * Get time series data for a page.
     */
    public function getPageTimeSeries(int $pageId, int $days = 30): array;

    /**
     * Get top referrers for a page.
     */
    public function getTopReferrers(int $pageId, int $limit = 10): array;

    /**
     * Get device breakdown for a page.
     */
    public function getDeviceBreakdown(int $pageId): array;

    /**
     * Get location breakdown for a page.
     */
    public function getLocationBreakdown(int $pageId, int $limit = 20): array;

    // =========================================================================
    // QUERY METHODS - VISITS
    // =========================================================================

    /**
     * Get recent visits for a page.
     */
    public function getRecentVisits(int $pageId, int $limit = 100): array;

    /**
     * Get converted visits for a page.
     */
    public function getConvertedVisits(int $pageId): array;

    // =========================================================================
    // QUERY METHODS - REPORTING
    // =========================================================================

    /**
     * Get landing pages performance overview.
     */
    public function getPerformanceOverview(array $filters = []): array;

    // =========================================================================
    // COMMAND METHODS - LANDING PAGES
    // =========================================================================

    /**
     * Create a new landing page.
     */
    public function createPage(array $data): array;

    /**
     * Update a landing page.
     */
    public function updatePage(int $id, array $data): array;

    /**
     * Delete a landing page.
     */
    public function deletePage(int $id): bool;

    /**
     * Publish a landing page.
     */
    public function publishPage(int $id): array;

    /**
     * Unpublish a landing page.
     */
    public function unpublishPage(int $id): array;

    /**
     * Archive a landing page.
     */
    public function archivePage(int $id): array;

    /**
     * Duplicate a landing page.
     */
    public function duplicatePage(int $id, string $newName, int $userId): array;

    // =========================================================================
    // COMMAND METHODS - TEMPLATES
    // =========================================================================

    /**
     * Create a landing page template.
     */
    public function createTemplate(array $data): array;

    /**
     * Update a template.
     */
    public function updateTemplate(int $id, array $data): array;

    /**
     * Delete a template.
     */
    public function deleteTemplate(int $id): bool;

    // =========================================================================
    // COMMAND METHODS - VARIANTS
    // =========================================================================

    /**
     * Create a variant for A/B testing.
     */
    public function createVariant(int $pageId, array $data): array;

    /**
     * Update a variant.
     */
    public function updateVariant(int $id, array $data): array;

    /**
     * Delete a variant.
     */
    public function deleteVariant(int $id): bool;

    /**
     * Declare a variant as winner.
     */
    public function declareVariantWinner(int $variantId): array;

    // =========================================================================
    // COMMAND METHODS - VISIT TRACKING
    // =========================================================================

    /**
     * Record a page visit.
     */
    public function recordVisit(int $pageId, array $data): array;

    /**
     * Update visit engagement metrics.
     */
    public function updateVisitEngagement(int $visitId, int $timeOnPage, int $scrollDepth): array;

    /**
     * Mark a visit as converted.
     */
    public function markVisitConverted(int $visitId, int $submissionId): array;
}
