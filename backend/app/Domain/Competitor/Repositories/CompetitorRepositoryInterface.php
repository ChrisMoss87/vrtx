<?php

declare(strict_types=1);

namespace App\Domain\Competitor\Repositories;

use App\Domain\Competitor\Entities\Competitor;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CompetitorRepositoryInterface
{
    // ==========================================
    // COMPETITOR QUERIES
    // ==========================================

    /**
     * List competitors with filtering and pagination.
     */
    public function list(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    /**
     * Get a single competitor by ID.
     */
    public function findById(int $id): ?Competitor;

    /**
     * Get a single competitor by ID as array (for backward compatibility).
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Get competitor with all related data (sections, objections, last updated by).
     */
    public function findByIdWithRelations(int $id): ?array;

    /**
     * Get all active competitors for dropdown.
     */
    public function getActiveList(): array;

    /**
     * Search competitors by query string.
     */
    public function search(string $query, int $limit = 10): array;

    /**
     * Get competitor stats.
     */
    public function getStats(int $id): array;

    /**
     * Get competitor win rate.
     */
    public function getWinRate(int $id): ?float;

    // ==========================================
    // BATTLECARD SECTIONS
    // ==========================================

    /**
     * Get all battlecard sections for a competitor.
     */
    public function getBattlecardSections(int $competitorId): array;

    /**
     * Get a specific battlecard section by type.
     */
    public function getBattlecardSectionByType(int $competitorId, string $type): ?array;

    /**
     * Get a single battlecard section by ID.
     */
    public function findBattlecardSectionById(int $sectionId): ?array;

    /**
     * Get competitor with battlecard data.
     */
    public function getCompetitorBattlecard(int $id): array;

    /**
     * Update a battlecard section.
     */
    public function updateBattlecardSection(int $sectionId, array $data): array;

    /**
     * Create a battlecard section.
     */
    public function createBattlecardSection(int $competitorId, array $data): array;

    /**
     * Delete a battlecard section.
     */
    public function deleteBattlecardSection(int $sectionId): bool;

    /**
     * Reorder battlecard sections.
     */
    public function reorderBattlecardSections(int $competitorId, array $sectionIds): void;

    // ==========================================
    // OBJECTIONS
    // ==========================================

    /**
     * Get all objections for a competitor.
     */
    public function getObjections(int $competitorId): array;

    /**
     * Get a single objection by ID.
     */
    public function findObjectionById(int $id): ?array;

    /**
     * Create an objection.
     */
    public function createObjection(int $competitorId, array $data): array;

    /**
     * Update an objection.
     */
    public function updateObjection(int $id, array $data): array;

    /**
     * Delete an objection.
     */
    public function deleteObjection(int $id): bool;

    /**
     * Record objection usage.
     */
    public function recordObjectionUsage(int $id, bool $wasSuccessful): array;

    /**
     * Get top effective objections across all competitors.
     */
    public function getTopEffectiveObjections(int $limit = 10): array;

    // ==========================================
    // NOTES
    // ==========================================

    /**
     * Get all notes for a competitor.
     */
    public function getNotes(int $competitorId, array $filters = []): array;

    /**
     * Get a single note by ID.
     */
    public function findNoteById(int $id): ?array;

    /**
     * Create a note.
     */
    public function createNote(int $competitorId, array $data): array;

    /**
     * Update a note.
     */
    public function updateNote(int $id, array $data): array;

    /**
     * Delete a note.
     */
    public function deleteNote(int $id): bool;

    /**
     * Verify a note.
     */
    public function verifyNote(int $id, int $userId): array;

    /**
     * Unverify a note.
     */
    public function unverifyNote(int $id): array;

    // ==========================================
    // DEAL COMPETITORS
    // ==========================================

    /**
     * Add competitor to a deal.
     */
    public function addCompetitorToDeal(int $dealId, int $competitorId, array $data = []): array;

    /**
     * Update deal competitor.
     */
    public function updateDealCompetitor(int $id, array $data): array;

    /**
     * Remove competitor from deal.
     */
    public function removeCompetitorFromDeal(int $id): bool;

    /**
     * Get deals involving a competitor.
     */
    public function getCompetitorDeals(int $competitorId, array $filters = []): array;

    // ==========================================
    // COMPETITOR COMMANDS
    // ==========================================

    /**
     * Save a competitor entity (create or update).
     */
    public function save(Competitor $entity): Competitor;

    /**
     * Create a competitor with default battlecard sections.
     */
    public function create(array $data): array;

    /**
     * Update a competitor.
     */
    public function update(int $id, array $data): array;

    /**
     * Delete a competitor and all related data.
     */
    public function delete(int $id): bool;

    /**
     * Mark competitor as updated.
     */
    public function markUpdated(int $id, int $userId): void;

    // ==========================================
    // ANALYTICS
    // ==========================================

    /**
     * Get competitor analytics dashboard.
     */
    public function getAnalyticsDashboard(): array;

    /**
     * Get win/loss analysis for a competitor.
     */
    public function getWinLossAnalysis(int $competitorId): array;

    /**
     * Compare multiple competitors.
     */
    public function compareCompetitors(array $competitorIds): array;

    /**
     * Get market position distribution.
     */
    public function getMarketPositionDistribution(): array;
}
