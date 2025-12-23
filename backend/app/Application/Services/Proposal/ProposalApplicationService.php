<?php

declare(strict_types=1);

namespace App\Application\Services\Proposal;

use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class ProposalApplicationService
{
    public function __construct(
        private ProposalRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // PROPOSAL QUERY USE CASES
    // =========================================================================

    /**
     * List proposals with filtering and pagination
     */
    public function listProposals(array $filters = [], int $perPage = 15): PaginatedResult
    {
        return $this->repository->listProposals($filters, $perPage);
    }

    /**
     * Get a single proposal with all related data
     */
    public function getProposal(int $proposalId): ?array
    {
        return $this->repository->findById($proposalId);
    }

    /**
     * Get proposal by UUID (for public access)
     */
    public function getProposalByUuid(string $uuid): ?array
    {
        return $this->repository->findByUuid($uuid);
    }

    /**
     * Get proposals for a deal
     */
    public function getProposalsForDeal(int $dealId): array
    {
        return $this->repository->getProposalsForDeal($dealId);
    }

    /**
     * Get recent proposals
     */
    public function getRecentProposals(int $limit = 10): array
    {
        return $this->repository->getRecentProposals($limit);
    }

    /**
     * Get proposals needing attention
     */
    public function getProposalsNeedingAttention(): array
    {
        return $this->repository->getProposalsNeedingAttention();
    }

    /**
     * Get proposal statistics
     */
    public function getProposalStats(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->repository->getProposalStats($startDate, $endDate);
    }

    // =========================================================================
    // PROPOSAL COMMAND USE CASES
    // =========================================================================

    /**
     * Create a new proposal
     */
    public function createProposal(array $data): array
    {
        $data['created_by'] = $this->authContext->userId();
        $data['assigned_to'] = $data['assigned_to'] ?? $this->authContext->userId();

        return $this->repository->create($data);
    }

    /**
     * Update a proposal
     */
    public function updateProposal(int $proposalId, array $data): array
    {
        return $this->repository->update($proposalId, $data);
    }

    /**
     * Delete a proposal
     */
    public function deleteProposal(int $proposalId): bool
    {
        return $this->repository->delete($proposalId);
    }

    /**
     * Duplicate a proposal
     */
    public function duplicateProposal(int $proposalId): array
    {
        return $this->repository->duplicate($proposalId, $this->authContext->userId());
    }

    /**
     * Send a proposal
     */
    public function sendProposal(int $proposalId, string $recipientEmail, ?string $message = null): array
    {
        $proposal = $this->repository->sendProposal($proposalId, $recipientEmail);

        // Here you would also dispatch an email job
        // SendProposalEmail::dispatch($proposal, $recipientEmail, $message);

        return $proposal;
    }

    /**
     * Record a view (for public access)
     */
    public function recordView(string $uuid, string $sessionId, ?string $email = null, ?string $name = null): ?array
    {
        return $this->repository->recordView($uuid, $sessionId, $email, $name);
    }

    /**
     * End a viewing session
     */
    public function endViewSession(int $viewId): array
    {
        return $this->repository->endViewSession($viewId);
    }

    /**
     * Track section view time
     */
    public function trackSectionView(int $viewId, int $sectionId, int $seconds): array
    {
        return $this->repository->trackSectionView($viewId, $sectionId, $seconds);
    }

    /**
     * Accept a proposal
     */
    public function acceptProposal(string $uuid, string $acceptedBy, ?string $signature = null): array
    {
        return $this->repository->acceptProposal($uuid, $acceptedBy, $signature, request()->ip());
    }

    /**
     * Reject a proposal
     */
    public function rejectProposal(string $uuid, string $rejectedBy, ?string $reason = null): array
    {
        return $this->repository->rejectProposal($uuid, $rejectedBy, $reason);
    }

    /**
     * Create a new version of a proposal
     */
    public function createNewVersion(int $proposalId): array
    {
        return $this->repository->createNewVersion($proposalId);
    }

    /**
     * Mark expired proposals
     */
    public function markExpiredProposals(): int
    {
        return $this->repository->markExpiredProposals();
    }

    // =========================================================================
    // SECTION USE CASES
    // =========================================================================

    /**
     * Add a section to a proposal
     */
    public function addSection(int $proposalId, array $data): array
    {
        return $this->repository->addSection($proposalId, $data);
    }

    /**
     * Update a section
     */
    public function updateSection(int $sectionId, array $data): array
    {
        return $this->repository->updateSection($sectionId, $data);
    }

    /**
     * Delete a section
     */
    public function deleteSection(int $sectionId): bool
    {
        return $this->repository->deleteSection($sectionId);
    }

    /**
     * Reorder sections
     */
    public function reorderSections(int $proposalId, array $sectionOrder): array
    {
        return $this->repository->reorderSections($proposalId, $sectionOrder);
    }

    // =========================================================================
    // PRICING ITEM USE CASES
    // =========================================================================

    /**
     * Add a pricing item
     */
    public function addPricingItem(int $proposalId, array $data): array
    {
        return $this->repository->addPricingItem($proposalId, $data);
    }

    /**
     * Update a pricing item
     */
    public function updatePricingItem(int $itemId, array $data): array
    {
        return $this->repository->updatePricingItem($itemId, $data);
    }

    /**
     * Delete a pricing item
     */
    public function deletePricingItem(int $itemId): bool
    {
        return $this->repository->deletePricingItem($itemId);
    }

    /**
     * Toggle optional item selection (for client)
     */
    public function toggleItemSelection(string $proposalUuid, int $itemId): array
    {
        return $this->repository->toggleItemSelection($proposalUuid, $itemId);
    }

    // =========================================================================
    // COMMENT USE CASES
    // =========================================================================

    /**
     * Add a comment
     */
    public function addComment(int $proposalId, array $data): array
    {
        return $this->repository->addComment($proposalId, $data);
    }

    /**
     * Add client comment (via public link)
     */
    public function addClientComment(string $proposalUuid, array $data): array
    {
        $proposal = $this->repository->findByUuid($proposalUuid);

        if (!$proposal) {
            throw new \RuntimeException('Proposal not found');
        }

        $data['author_type'] = 'client'; // ProposalComment::AUTHOR_CLIENT constant value

        return $this->repository->addComment($proposal['id'], $data);
    }

    /**
     * Get comments for a proposal
     */
    public function getComments(int $proposalId): array
    {
        return $this->repository->getComments($proposalId);
    }

    /**
     * Resolve a comment
     */
    public function resolveComment(int $commentId): array
    {
        return $this->repository->resolveComment($commentId, $this->authContext->userId());
    }

    /**
     * Unresolve a comment
     */
    public function unresolveComment(int $commentId): array
    {
        return $this->repository->unresolveComment($commentId);
    }

    // =========================================================================
    // TEMPLATE USE CASES
    // =========================================================================

    /**
     * List templates
     */
    public function listTemplates(array $filters = []): array
    {
        return $this->repository->listTemplates($filters);
    }

    /**
     * Get a template
     */
    public function getTemplate(int $templateId): ?array
    {
        return $this->repository->getTemplate($templateId);
    }

    /**
     * Create a template
     */
    public function createTemplate(array $data): array
    {
        $data['created_by'] = $this->authContext->userId();
        return $this->repository->createTemplate($data);
    }

    /**
     * Update a template
     */
    public function updateTemplate(int $templateId, array $data): array
    {
        return $this->repository->updateTemplate($templateId, $data);
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(int $templateId): bool
    {
        return $this->repository->deleteTemplate($templateId);
    }

    /**
     * Create template from proposal
     */
    public function createTemplateFromProposal(int $proposalId, string $name, ?string $category = null): array
    {
        return $this->repository->createTemplateFromProposal($proposalId, $name, $category);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get proposal engagement analytics
     */
    public function getProposalEngagement(int $proposalId): array
    {
        return $this->repository->getProposalEngagement($proposalId);
    }
}
