<?php

declare(strict_types=1);

namespace App\Application\Services\LandingPage;

use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class LandingPageApplicationService
{
    public function __construct(
        private readonly LandingPageRepositoryInterface $repository,
        private readonly AuthContextInterface $authContext
    ) {}

    // =========================================================================
    // QUERY USE CASES - LANDING PAGES
    // =========================================================================

    /**
     * List landing pages with filtering and pagination.
     */
    public function listPages(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->listPages($filters, $perPage);
    }

    /**
     * Get a single landing page by ID.
     */
    public function getPage(int $id): ?array
    {
        return $this->repository->getPageById($id, [
            'template',
            'webForm',
            'campaign',
            'creator',
            'variants',
            'thankYouPage'
        ]);
    }

    /**
     * Get a landing page by slug.
     */
    public function getPageBySlug(string $slug): ?array
    {
        return $this->repository->getPageBySlug($slug, ['template', 'webForm', 'variants']);
    }

    /**
     * Get published pages.
     */
    public function getPublishedPages(): array
    {
        return $this->repository->getPublishedPages(['template:id,name']);
    }

    /**
     * Get draft pages.
     */
    public function getDraftPages(): array
    {
        return $this->repository->getDraftPages(['creator:id,name']);
    }

    // =========================================================================
    // QUERY USE CASES - TEMPLATES
    // =========================================================================

    /**
     * List landing page templates.
     */
    public function listTemplates(array $filters = []): array
    {
        return $this->repository->listTemplates($filters);
    }

    /**
     * Get a template by ID.
     */
    public function getTemplate(int $id): ?array
    {
        return $this->repository->getTemplateById($id, ['creator', 'pages']);
    }

    // =========================================================================
    // QUERY USE CASES - VARIANTS
    // =========================================================================

    /**
     * Get variants for a landing page.
     */
    public function getVariants(int $pageId): array
    {
        return $this->repository->getVariantsByPageId($pageId);
    }

    /**
     * Get a specific variant.
     */
    public function getVariant(int $id): ?array
    {
        return $this->repository->getVariantById($id, ['page', 'analytics']);
    }

    // =========================================================================
    // QUERY USE CASES - ANALYTICS
    // =========================================================================

    /**
     * Get analytics for a landing page.
     */
    public function getPageAnalytics(int $pageId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->repository->getPageAnalytics($pageId, $dateFrom, $dateTo);
    }

    /**
     * Get variant analytics for comparison.
     */
    public function getVariantAnalytics(int $pageId): array
    {
        return $this->repository->getVariantAnalytics($pageId);
    }

    /**
     * Get summary analytics for a page.
     */
    public function getPageSummary(int $pageId): array
    {
        return $this->repository->getPageSummary($pageId);
    }

    /**
     * Get time series data for a page.
     */
    public function getPageTimeSeries(int $pageId, int $days = 30): array
    {
        return $this->repository->getPageTimeSeries($pageId, $days);
    }

    /**
     * Get top referrers for a page.
     */
    public function getTopReferrers(int $pageId, int $limit = 10): array
    {
        return $this->repository->getTopReferrers($pageId, $limit);
    }

    /**
     * Get device breakdown for a page.
     */
    public function getDeviceBreakdown(int $pageId): array
    {
        return $this->repository->getDeviceBreakdown($pageId);
    }

    /**
     * Get location breakdown for a page.
     */
    public function getLocationBreakdown(int $pageId, int $limit = 20): array
    {
        return $this->repository->getLocationBreakdown($pageId, $limit);
    }

    // =========================================================================
    // QUERY USE CASES - VISITS
    // =========================================================================

    /**
     * Get recent visits for a page.
     */
    public function getRecentVisits(int $pageId, int $limit = 100): array
    {
        return $this->repository->getRecentVisits($pageId, $limit);
    }

    /**
     * Get converted visits for a page.
     */
    public function getConvertedVisits(int $pageId): array
    {
        return $this->repository->getConvertedVisits($pageId);
    }

    // =========================================================================
    // COMMAND USE CASES - LANDING PAGES
    // =========================================================================

    /**
     * Create a new landing page.
     */
    public function createPage(array $data): array
    {
        $data['created_by'] = $this->authContext->userId();
        return $this->repository->createPage($data);
    }

    /**
     * Update a landing page.
     */
    public function updatePage(int $id, array $data): array
    {
        return $this->repository->updatePage($id, $data);
    }

    /**
     * Delete a landing page.
     */
    public function deletePage(int $id): bool
    {
        return $this->repository->deletePage($id);
    }

    /**
     * Publish a landing page.
     */
    public function publishPage(int $id): array
    {
        return $this->repository->publishPage($id);
    }

    /**
     * Unpublish a landing page.
     */
    public function unpublishPage(int $id): array
    {
        return $this->repository->unpublishPage($id);
    }

    /**
     * Archive a landing page.
     */
    public function archivePage(int $id): array
    {
        return $this->repository->archivePage($id);
    }

    /**
     * Duplicate a landing page.
     */
    public function duplicatePage(int $id, string $newName): array
    {
        $userId = $this->authContext->userId();
        return $this->repository->duplicatePage($id, $newName, $userId);
    }

    // =========================================================================
    // COMMAND USE CASES - TEMPLATES
    // =========================================================================

    /**
     * Create a landing page template.
     */
    public function createTemplate(array $data): array
    {
        $data['created_by'] = $this->authContext->userId();
        return $this->repository->createTemplate($data);
    }

    /**
     * Update a template.
     */
    public function updateTemplate(int $id, array $data): array
    {
        return $this->repository->updateTemplate($id, $data);
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate(int $id): bool
    {
        return $this->repository->deleteTemplate($id);
    }

    // =========================================================================
    // COMMAND USE CASES - VARIANTS (A/B TESTING)
    // =========================================================================

    /**
     * Create a variant for A/B testing.
     */
    public function createVariant(int $pageId, array $data): array
    {
        return $this->repository->createVariant($pageId, $data);
    }

    /**
     * Update a variant.
     */
    public function updateVariant(int $id, array $data): array
    {
        return $this->repository->updateVariant($id, $data);
    }

    /**
     * Delete a variant.
     */
    public function deleteVariant(int $id): bool
    {
        return $this->repository->deleteVariant($id);
    }

    /**
     * Declare a variant as winner.
     */
    public function declareVariantWinner(int $variantId): array
    {
        return $this->repository->declareVariantWinner($variantId);
    }

    // =========================================================================
    // COMMAND USE CASES - VISIT TRACKING
    // =========================================================================

    /**
     * Record a page visit.
     */
    public function recordVisit(int $pageId, array $data): array
    {
        return $this->repository->recordVisit($pageId, $data);
    }

    /**
     * Update visit engagement metrics.
     */
    public function updateVisitEngagement(int $visitId, int $timeOnPage, int $scrollDepth): array
    {
        return $this->repository->updateVisitEngagement($visitId, $timeOnPage, $scrollDepth);
    }

    /**
     * Mark a visit as converted.
     */
    public function markVisitConverted(int $visitId, int $submissionId): array
    {
        return $this->repository->markVisitConverted($visitId, $submissionId);
    }

    // =========================================================================
    // ANALYTICS & REPORTING
    // =========================================================================

    /**
     * Get landing pages performance overview.
     */
    public function getPerformanceOverview(array $filters = []): array
    {
        return $this->repository->getPerformanceOverview($filters);
    }
}
