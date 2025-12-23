<?php

declare(strict_types=1);

namespace App\Domain\Proposal\Repositories;

use App\Domain\Proposal\Entities\Proposal;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ProposalRepositoryInterface
{
    /**
     * Find a proposal by ID
     */
    public function findById(int $id): ?Proposal;

    /**
     * Find a proposal by ID with all related data (returns array)
     */
    public function findByIdAsArray(int $id, array $with = []): ?array;

    /**
     * Find a proposal by UUID
     */
    public function findByUuid(string $uuid, array $with = []): ?array;

    /**
     * List proposals with filtering and pagination
     */
    public function listProposals(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    /**
     * Get proposals for a specific deal
     */
    public function getProposalsForDeal(int $dealId): array;

    /**
     * Get recent proposals
     */
    public function getRecentProposals(int $limit = 10): array;

    /**
     * Get proposals needing attention
     */
    public function getProposalsNeedingAttention(): array;

    /**
     * Get proposal statistics for a date range
     */
    public function getProposalStats(?string $startDate = null, ?string $endDate = null): array;

    /**
     * Save a proposal entity
     */
    public function save(Proposal $entity): Proposal;

    /**
     * Create a new proposal
     */
    public function create(array $data): array;

    /**
     * Update a proposal
     */
    public function update(int $id, array $data): array;

    /**
     * Delete a proposal and all related data
     */
    public function delete(int $id): bool;

    /**
     * Duplicate a proposal
     */
    public function duplicate(int $id, int $userId): array;

    /**
     * Generate a unique proposal number
     */
    public function generateProposalNumber(): string;

    /**
     * Mark expired proposals
     */
    public function markExpiredProposals(): int;

    // Section methods
    public function addSection(int $proposalId, array $data): array;
    public function updateSection(int $sectionId, array $data): array;
    public function deleteSection(int $sectionId): bool;
    public function reorderSections(int $proposalId, array $sectionOrder): array;

    // Pricing item methods
    public function addPricingItem(int $proposalId, array $data): array;
    public function updatePricingItem(int $itemId, array $data): array;
    public function deletePricingItem(int $itemId): bool;

    // View tracking methods
    public function recordView(string $uuid, string $sessionId, ?string $email = null, ?string $name = null): ?array;
    public function endViewSession(int $viewId): array;
    public function trackSectionView(int $viewId, int $sectionId, int $seconds): array;

    // Status change methods
    public function sendProposal(int $id, string $recipientEmail): array;
    public function acceptProposal(string $uuid, string $acceptedBy, ?string $signature = null, ?string $ip = null): array;
    public function rejectProposal(string $uuid, string $rejectedBy, ?string $reason = null): array;
    public function createNewVersion(int $id): array;
    public function toggleItemSelection(string $proposalUuid, int $itemId): array;

    // Comment methods
    public function addComment(int $proposalId, array $data): array;
    public function getComments(int $proposalId): array;
    public function resolveComment(int $commentId, int $userId): array;
    public function unresolveComment(int $commentId): array;

    // Template methods
    public function listTemplates(array $filters = []): array;
    public function getTemplate(int $templateId): ?array;
    public function createTemplate(array $data): array;
    public function updateTemplate(int $templateId, array $data): array;
    public function deleteTemplate(int $templateId): bool;
    public function createTemplateFromProposal(int $proposalId, string $name, ?string $category = null): array;

    // Analytics methods
    public function getProposalEngagement(int $proposalId): array;
}
