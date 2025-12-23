<?php

declare(strict_types=1);

namespace App\Application\Services\Competitor;

use App\Domain\Competitor\Repositories\CompetitorRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class CompetitorApplicationService
{
    public function __construct(
        private CompetitorRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // ==========================================
    // COMPETITOR QUERY USE CASES
    // ==========================================

    /**
     * List competitors with filtering and pagination.
     */
    public function listCompetitors(array $filters = [], int $perPage = 15): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->list($filters, $perPage, $page);
    }

    /**
     * Get a single competitor.
     */
    public function getCompetitor(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id);
    }

    /**
     * Get competitor with battlecard data.
     */
    public function getCompetitorBattlecard(int $id): array
    {
        return $this->repository->getCompetitorBattlecard($id);
    }

    /**
     * Get competitor stats.
     */
    public function getCompetitorStats(int $id): array
    {
        return $this->repository->getStats($id);
    }

    /**
     * Get all competitors for dropdown.
     */
    public function getCompetitorList(): array
    {
        return $this->repository->getActiveList();
    }

    /**
     * Search competitors.
     */
    public function searchCompetitors(string $query, int $limit = 10): array
    {
        return $this->repository->search($query, $limit);
    }

    // ==========================================
    // COMPETITOR COMMAND USE CASES
    // ==========================================

    /**
     * Create a competitor.
     */
    public function createCompetitor(array $data): array
    {
        $data['last_updated_by'] = $this->authContext->userId();
        $data['created_by'] = $this->authContext->userId();

        return $this->repository->create($data);
    }

    /**
     * Update a competitor.
     */
    public function updateCompetitor(int $id, array $data): array
    {
        $result = $this->repository->update($id, $data);
        $this->repository->markUpdated($id, $this->authContext->userId());
        return $result;
    }

    /**
     * Delete a competitor.
     */
    public function deleteCompetitor(int $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * Deactivate a competitor.
     */
    public function deactivateCompetitor(int $id): array
    {
        $result = $this->repository->update($id, ['is_active' => false]);
        return $result;
    }

    /**
     * Activate a competitor.
     */
    public function activateCompetitor(int $id): array
    {
        $result = $this->repository->update($id, ['is_active' => true]);
        return $result;
    }

    // ==========================================
    // BATTLECARD SECTION USE CASES
    // ==========================================

    /**
     * Get battlecard sections for a competitor.
     */
    public function getBattlecardSections(int $competitorId): array
    {
        return $this->repository->getBattlecardSections($competitorId);
    }

    /**
     * Update a battlecard section.
     */
    public function updateBattlecardSection(int $sectionId, array $data): array
    {
        $section = $this->repository->updateBattlecardSection($sectionId, $data);

        // Mark competitor as updated
        if (isset($section['competitor_id'])) {
            $this->repository->markUpdated($section['competitor_id'], $this->authContext->userId());
        }

        return $section;
    }

    /**
     * Create a custom battlecard section.
     */
    public function createBattlecardSection(int $competitorId, array $data): array
    {
        $data['created_by'] = $this->authContext->userId();

        $section = $this->repository->createBattlecardSection($competitorId, $data);

        $this->repository->markUpdated($competitorId, $this->authContext->userId());

        return $section;
    }

    /**
     * Delete a battlecard section.
     */
    public function deleteBattlecardSection(int $sectionId): void
    {
        // Get the section to find competitor_id before deleting
        $section = $this->repository->findBattlecardSectionById($sectionId);

        $this->repository->deleteBattlecardSection($sectionId);

        if ($section && isset($section['competitor_id'])) {
            $this->repository->markUpdated($section['competitor_id'], $this->authContext->userId());
        }
    }

    /**
     * Reorder battlecard sections.
     */
    public function reorderBattlecardSections(int $competitorId, array $sectionIds): void
    {
        $this->repository->reorderBattlecardSections($competitorId, $sectionIds);
        $this->repository->markUpdated($competitorId, $this->authContext->userId());
    }

    // ==========================================
    // OBJECTION USE CASES
    // ==========================================

    /**
     * List objections for a competitor.
     */
    public function listObjections(int $competitorId): array
    {
        return $this->repository->getObjections($competitorId);
    }

    /**
     * Get a single objection.
     */
    public function getObjection(int $id): ?array
    {
        return $this->repository->findObjectionById($id);
    }

    /**
     * Create an objection.
     */
    public function createObjection(int $competitorId, array $data): array
    {
        $data['created_by'] = $this->authContext->userId();

        $objection = $this->repository->createObjection($competitorId, $data);

        $this->repository->markUpdated($competitorId, $this->authContext->userId());

        return $objection;
    }

    /**
     * Update an objection.
     */
    public function updateObjection(int $id, array $data): array
    {
        $objection = $this->repository->updateObjection($id, $data);

        // Mark competitor as updated
        if (isset($objection['competitor_id'])) {
            $this->repository->markUpdated($objection['competitor_id'], $this->authContext->userId());
        }

        return $objection;
    }

    /**
     * Delete an objection.
     */
    public function deleteObjection(int $id): void
    {
        // Get the objection to find competitor_id before deleting
        $objection = $this->repository->findObjectionById($id);

        $this->repository->deleteObjection($id);

        if ($objection && isset($objection['competitor_id'])) {
            $this->repository->markUpdated($objection['competitor_id'], $this->authContext->userId());
        }
    }

    /**
     * Record objection usage.
     */
    public function recordObjectionUsage(int $id, bool $wasSuccessful): array
    {
        return $this->repository->recordObjectionUsage($id, $wasSuccessful);
    }

    /**
     * Get top effective objections across all competitors.
     */
    public function getTopEffectiveObjections(int $limit = 10): array
    {
        return $this->repository->getTopEffectiveObjections($limit);
    }

    // ==========================================
    // NOTE USE CASES
    // ==========================================

    /**
     * List notes for a competitor.
     */
    public function listNotes(int $competitorId, array $filters = []): array
    {
        return $this->repository->getNotes($competitorId, $filters);
    }

    /**
     * Create a note.
     */
    public function createNote(int $competitorId, array $data): array
    {
        $data['created_by'] = $this->authContext->userId();

        $note = $this->repository->createNote($competitorId, $data);

        $this->repository->markUpdated($competitorId, $this->authContext->userId());

        return $note;
    }

    /**
     * Update a note.
     */
    public function updateNote(int $id, array $data): array
    {
        $note = $this->repository->updateNote($id, $data);

        // Mark competitor as updated
        if (isset($note['competitor_id'])) {
            $this->repository->markUpdated($note['competitor_id'], $this->authContext->userId());
        }

        return $note;
    }

    /**
     * Delete a note.
     */
    public function deleteNote(int $id): void
    {
        // Get the note to find competitor_id before deleting
        $note = $this->repository->findNoteById($id);

        $this->repository->deleteNote($id);

        if ($note && isset($note['competitor_id'])) {
            $this->repository->markUpdated($note['competitor_id'], $this->authContext->userId());
        }
    }

    /**
     * Verify a note.
     */
    public function verifyNote(int $id): array
    {
        return $this->repository->verifyNote($id, $this->authContext->userId());
    }

    /**
     * Unverify a note.
     */
    public function unverifyNote(int $id): array
    {
        return $this->repository->unverifyNote($id);
    }

    // ==========================================
    // DEAL COMPETITOR USE CASES
    // ==========================================

    /**
     * Add competitor to a deal.
     */
    public function addCompetitorToDeal(int $dealId, int $competitorId, array $data = []): array
    {
        $data['added_by'] = $this->authContext->userId();

        return $this->repository->addCompetitorToDeal($dealId, $competitorId, $data);
    }

    /**
     * Update deal competitor.
     */
    public function updateDealCompetitor(int $id, array $data): array
    {
        return $this->repository->updateDealCompetitor($id, $data);
    }

    /**
     * Remove competitor from deal.
     */
    public function removeCompetitorFromDeal(int $id): void
    {
        $this->repository->removeCompetitorFromDeal($id);
    }

    /**
     * Get deals involving a competitor.
     */
    public function getCompetitorDeals(int $competitorId, array $filters = []): array
    {
        return $this->repository->getCompetitorDeals($competitorId, $filters);
    }

    // ==========================================
    // ANALYTICS USE CASES
    // ==========================================

    /**
     * Get competitor analytics dashboard.
     */
    public function getAnalyticsDashboard(): array
    {
        return $this->repository->getAnalyticsDashboard();
    }

    /**
     * Get win/loss analysis against a competitor.
     */
    public function getWinLossAnalysis(int $competitorId): array
    {
        return $this->repository->getWinLossAnalysis($competitorId);
    }

    /**
     * Get competitor comparison.
     */
    public function compareCompetitors(array $competitorIds): array
    {
        return $this->repository->compareCompetitors($competitorIds);
    }

    /**
     * Get market position distribution.
     */
    public function getMarketPositionDistribution(): array
    {
        return $this->repository->getMarketPositionDistribution();
    }
}
