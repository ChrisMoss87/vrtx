<?php

declare(strict_types=1);

namespace App\Application\Services\Proposal;

use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use App\Models\Proposal;
use App\Models\ProposalComment;
use App\Models\ProposalPricingItem;
use App\Models\ProposalSection;
use App\Models\ProposalTemplate;
use App\Models\ProposalView;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProposalApplicationService
{
    public function __construct(
        private ProposalRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // PROPOSAL QUERY USE CASES
    // =========================================================================

    /**
     * List proposals with filtering and pagination
     */
    public function listProposals(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Proposal::query()->with(['template', 'createdBy', 'assignedTo']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // Filter by multiple statuses
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        // Filter for draft only
        if (!empty($filters['draft_only'])) {
            $query->draft();
        }

        // Filter for active proposals (sent or viewed)
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        // Filter by deal
        if (!empty($filters['deal_id'])) {
            $query->forDeal($filters['deal_id']);
        }

        // Filter by contact
        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        // Filter by company
        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        // Filter by template
        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        // Filter by assigned to
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Filter by created by
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Filter by date range
        if (!empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        // Filter by valid_until
        if (!empty($filters['expiring_soon'])) {
            $query->whereBetween('valid_until', [now(), now()->addDays(7)]);
        }

        // Filter expired
        if (!empty($filters['expired'])) {
            $query->where('valid_until', '<', now());
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('proposal_number', 'ilike', "%{$search}%");
            });
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single proposal with all related data
     */
    public function getProposal(int $proposalId): ?Proposal
    {
        return Proposal::with([
            'template',
            'createdBy',
            'assignedTo',
            'sections' => fn($q) => $q->orderBy('display_order'),
            'pricingItems' => fn($q) => $q->orderBy('display_order'),
            'comments' => fn($q) => $q->topLevel()->with('replies'),
            'views' => fn($q) => $q->latest('started_at')->limit(20),
        ])->find($proposalId);
    }

    /**
     * Get proposal by UUID (for public access)
     */
    public function getProposalByUuid(string $uuid): ?Proposal
    {
        return Proposal::with([
            'sections' => fn($q) => $q->visible()->orderBy('display_order'),
            'pricingItems' => fn($q) => $q->orderBy('display_order'),
        ])->where('uuid', $uuid)->first();
    }

    /**
     * Get proposals for a deal
     */
    public function getProposalsForDeal(int $dealId): Collection
    {
        return Proposal::forDeal($dealId)
            ->with(['template', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get recent proposals
     */
    public function getRecentProposals(int $limit = 10): Collection
    {
        return Proposal::with(['template', 'assignedTo'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get proposals needing attention
     */
    public function getProposalsNeedingAttention(): array
    {
        return [
            'expiring_soon' => Proposal::active()
                ->whereBetween('valid_until', [now(), now()->addDays(7)])
                ->with(['assignedTo'])
                ->orderBy('valid_until')
                ->get(),
            'recently_viewed' => Proposal::status(Proposal::STATUS_VIEWED)
                ->where('last_viewed_at', '>=', now()->subHours(24))
                ->with(['assignedTo'])
                ->orderByDesc('last_viewed_at')
                ->get(),
            'with_comments' => Proposal::active()
                ->whereHas('comments', fn($q) => $q->unresolved())
                ->with(['assignedTo'])
                ->get(),
        ];
    }

    /**
     * Get proposal statistics
     */
    public function getProposalStats(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $proposals = Proposal::whereBetween('created_at', [$start, $end])->get();
        $acceptedProposals = $proposals->where('status', Proposal::STATUS_ACCEPTED);
        $rejectedProposals = $proposals->where('status', Proposal::STATUS_REJECTED);
        $closedProposals = $acceptedProposals->count() + $rejectedProposals->count();

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_created' => $proposals->count(),
            'by_status' => [
                Proposal::STATUS_DRAFT => $proposals->where('status', Proposal::STATUS_DRAFT)->count(),
                Proposal::STATUS_SENT => $proposals->where('status', Proposal::STATUS_SENT)->count(),
                Proposal::STATUS_VIEWED => $proposals->where('status', Proposal::STATUS_VIEWED)->count(),
                Proposal::STATUS_ACCEPTED => $acceptedProposals->count(),
                Proposal::STATUS_REJECTED => $rejectedProposals->count(),
                Proposal::STATUS_EXPIRED => $proposals->where('status', Proposal::STATUS_EXPIRED)->count(),
            ],
            'acceptance_rate' => $closedProposals > 0
                ? round(($acceptedProposals->count() / $closedProposals) * 100, 1)
                : 0,
            'total_value' => [
                'created' => round($proposals->sum('total_value'), 2),
                'accepted' => round($acceptedProposals->sum('total_value'), 2),
            ],
            'avg_value' => [
                'all' => round($proposals->avg('total_value') ?? 0, 2),
                'accepted' => round($acceptedProposals->avg('total_value') ?? 0, 2),
            ],
            'view_stats' => [
                'total_views' => $proposals->sum('view_count'),
                'avg_views' => round($proposals->avg('view_count') ?? 0, 1),
                'avg_time_spent_seconds' => round($proposals->avg('total_time_spent') ?? 0),
            ],
        ];
    }

    // =========================================================================
    // PROPOSAL COMMAND USE CASES
    // =========================================================================

    /**
     * Create a new proposal
     */
    public function createProposal(array $data): Proposal
    {
        return DB::transaction(function () use ($data) {
            $proposal = Proposal::create([
                'name' => $data['name'],
                'proposal_number' => $data['proposal_number'] ?? $this->generateProposalNumber(),
                'template_id' => $data['template_id'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'cover_page' => $data['cover_page'] ?? null,
                'styling' => $data['styling'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'valid_until' => $data['valid_until'] ?? now()->addDays(30),
                'created_by' => Auth::id(),
                'assigned_to' => $data['assigned_to'] ?? Auth::id(),
            ]);

            // Create sections from template if provided
            if (!empty($data['template_id'])) {
                $template = ProposalTemplate::find($data['template_id']);
                if ($template && !empty($template->default_sections)) {
                    foreach ($template->default_sections as $index => $section) {
                        $proposal->sections()->create([
                            'section_type' => $section['type'] ?? ProposalSection::TYPE_CUSTOM,
                            'title' => $section['title'],
                            'content' => $section['content'] ?? '',
                            'settings' => $section['settings'] ?? [],
                            'display_order' => $index,
                        ]);
                    }
                }
                // Apply template styling
                if ($template && !empty($template->styling)) {
                    $proposal->update(['styling' => $template->styling]);
                }
            }

            // Create custom sections if provided
            if (!empty($data['sections'])) {
                foreach ($data['sections'] as $index => $sectionData) {
                    $proposal->sections()->create([
                        'section_type' => $sectionData['section_type'] ?? ProposalSection::TYPE_CUSTOM,
                        'title' => $sectionData['title'],
                        'content' => $sectionData['content'] ?? '',
                        'settings' => $sectionData['settings'] ?? [],
                        'display_order' => $sectionData['display_order'] ?? $index,
                    ]);
                }
            }

            return $proposal->load(['sections', 'template']);
        });
    }

    /**
     * Update a proposal
     */
    public function updateProposal(int $proposalId, array $data): Proposal
    {
        $proposal = Proposal::findOrFail($proposalId);

        if (!$proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited in its current status');
        }

        $proposal->update([
            'name' => $data['name'] ?? $proposal->name,
            'cover_page' => $data['cover_page'] ?? $proposal->cover_page,
            'styling' => $data['styling'] ?? $proposal->styling,
            'currency' => $data['currency'] ?? $proposal->currency,
            'valid_until' => $data['valid_until'] ?? $proposal->valid_until,
            'assigned_to' => $data['assigned_to'] ?? $proposal->assigned_to,
        ]);

        return $proposal->fresh(['sections', 'pricingItems']);
    }

    /**
     * Delete a proposal
     */
    public function deleteProposal(int $proposalId): bool
    {
        $proposal = Proposal::findOrFail($proposalId);

        return DB::transaction(function () use ($proposal) {
            $proposal->sections()->delete();
            $proposal->pricingItems()->delete();
            $proposal->views()->delete();
            $proposal->comments()->delete();
            return $proposal->delete();
        });
    }

    /**
     * Duplicate a proposal
     */
    public function duplicateProposal(int $proposalId): Proposal
    {
        $proposal = Proposal::with(['sections', 'pricingItems'])->findOrFail($proposalId);
        return $proposal->duplicate(Auth::id());
    }

    /**
     * Send a proposal
     */
    public function sendProposal(int $proposalId, string $recipientEmail, ?string $message = null): Proposal
    {
        $proposal = Proposal::findOrFail($proposalId);

        if (!$proposal->canBeSent()) {
            throw new \RuntimeException('Proposal cannot be sent in its current status');
        }

        $proposal->send($recipientEmail);

        // Here you would also dispatch an email job
        // SendProposalEmail::dispatch($proposal, $recipientEmail, $message);

        return $proposal;
    }

    /**
     * Record a view (for public access)
     */
    public function recordView(string $uuid, string $sessionId, ?string $email = null, ?string $name = null): ?ProposalView
    {
        $proposal = Proposal::where('uuid', $uuid)->first();

        if (!$proposal) {
            return null;
        }

        return $proposal->recordView($sessionId, $email, $name);
    }

    /**
     * End a viewing session
     */
    public function endViewSession(int $viewId): ProposalView
    {
        $view = ProposalView::findOrFail($viewId);
        $view->endSession();
        return $view;
    }

    /**
     * Track section view time
     */
    public function trackSectionView(int $viewId, int $sectionId, int $seconds): ProposalView
    {
        $view = ProposalView::findOrFail($viewId);
        $view->trackSectionView($sectionId, $seconds);
        return $view;
    }

    /**
     * Accept a proposal
     */
    public function acceptProposal(string $uuid, string $acceptedBy, ?string $signature = null): Proposal
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        if ($proposal->isExpired()) {
            throw new \RuntimeException('Proposal has expired');
        }

        if (!in_array($proposal->status, [Proposal::STATUS_SENT, Proposal::STATUS_VIEWED])) {
            throw new \RuntimeException('Proposal cannot be accepted in its current status');
        }

        $proposal->accept($acceptedBy, $signature, request()->ip());

        return $proposal;
    }

    /**
     * Reject a proposal
     */
    public function rejectProposal(string $uuid, string $rejectedBy, ?string $reason = null): Proposal
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        if (!in_array($proposal->status, [Proposal::STATUS_SENT, Proposal::STATUS_VIEWED])) {
            throw new \RuntimeException('Proposal cannot be rejected in its current status');
        }

        $proposal->reject($rejectedBy, $reason);

        return $proposal;
    }

    /**
     * Create a new version of a proposal
     */
    public function createNewVersion(int $proposalId): Proposal
    {
        $original = Proposal::with(['sections', 'pricingItems'])->findOrFail($proposalId);

        return DB::transaction(function () use ($original) {
            // Increment version on original
            $newVersion = $original->version + 1;

            // Create new proposal based on original
            $newProposal = $original->replicate([
                'uuid', 'status', 'sent_at', 'sent_to_email',
                'first_viewed_at', 'last_viewed_at', 'view_count', 'total_time_spent',
                'accepted_at', 'accepted_by', 'accepted_signature', 'accepted_ip',
                'rejected_at', 'rejected_by', 'rejection_reason'
            ]);

            $newProposal->uuid = Str::uuid()->toString();
            $newProposal->status = Proposal::STATUS_DRAFT;
            $newProposal->version = $newVersion;
            $newProposal->save();

            // Copy sections
            foreach ($original->sections as $section) {
                $newProposal->sections()->create($section->toArray());
            }

            // Copy pricing items
            foreach ($original->pricingItems as $item) {
                $newProposal->pricingItems()->create($item->toArray());
            }

            return $newProposal->load(['sections', 'pricingItems']);
        });
    }

    /**
     * Mark expired proposals
     */
    public function markExpiredProposals(): int
    {
        return Proposal::whereIn('status', [Proposal::STATUS_SENT, Proposal::STATUS_VIEWED])
            ->where('valid_until', '<', now())
            ->update(['status' => Proposal::STATUS_EXPIRED]);
    }

    // =========================================================================
    // SECTION USE CASES
    // =========================================================================

    /**
     * Add a section to a proposal
     */
    public function addSection(int $proposalId, array $data): ProposalSection
    {
        $proposal = Proposal::findOrFail($proposalId);

        if (!$proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        $maxOrder = $proposal->sections()->max('display_order') ?? 0;

        return $proposal->sections()->create([
            'section_type' => $data['section_type'] ?? ProposalSection::TYPE_CUSTOM,
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'settings' => $data['settings'] ?? [],
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
            'is_visible' => $data['is_visible'] ?? true,
        ]);
    }

    /**
     * Update a section
     */
    public function updateSection(int $sectionId, array $data): ProposalSection
    {
        $section = ProposalSection::with(['proposal'])->findOrFail($sectionId);

        if (!$section->proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        $section->update([
            'title' => $data['title'] ?? $section->title,
            'content' => $data['content'] ?? $section->content,
            'settings' => $data['settings'] ?? $section->settings,
            'is_visible' => $data['is_visible'] ?? $section->is_visible,
        ]);

        return $section->fresh();
    }

    /**
     * Delete a section
     */
    public function deleteSection(int $sectionId): bool
    {
        $section = ProposalSection::with(['proposal'])->findOrFail($sectionId);

        if (!$section->proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        if ($section->is_locked) {
            throw new \RuntimeException('Section is locked and cannot be deleted');
        }

        return $section->delete();
    }

    /**
     * Reorder sections
     */
    public function reorderSections(int $proposalId, array $sectionOrder): Collection
    {
        $proposal = Proposal::findOrFail($proposalId);

        if (!$proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        return DB::transaction(function () use ($proposalId, $sectionOrder) {
            foreach ($sectionOrder as $order => $sectionId) {
                ProposalSection::where('id', $sectionId)
                    ->where('proposal_id', $proposalId)
                    ->update(['display_order' => $order]);
            }

            return ProposalSection::where('proposal_id', $proposalId)
                ->orderBy('display_order')
                ->get();
        });
    }

    // =========================================================================
    // PRICING ITEM USE CASES
    // =========================================================================

    /**
     * Add a pricing item
     */
    public function addPricingItem(int $proposalId, array $data): ProposalPricingItem
    {
        $proposal = Proposal::findOrFail($proposalId);

        if (!$proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        $maxOrder = $proposal->pricingItems()->max('display_order') ?? 0;

        return $proposal->pricingItems()->create([
            'section_id' => $data['section_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'unit' => $data['unit'] ?? null,
            'unit_price' => $data['unit_price'],
            'discount_percent' => $data['discount_percent'] ?? 0,
            'is_optional' => $data['is_optional'] ?? false,
            'is_selected' => $data['is_selected'] ?? true,
            'pricing_type' => $data['pricing_type'] ?? ProposalPricingItem::PRICING_FIXED,
            'billing_frequency' => $data['billing_frequency'] ?? null,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
            'product_id' => $data['product_id'] ?? null,
        ]);
    }

    /**
     * Update a pricing item
     */
    public function updatePricingItem(int $itemId, array $data): ProposalPricingItem
    {
        $item = ProposalPricingItem::with(['proposal'])->findOrFail($itemId);

        if (!$item->proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        $item->update([
            'name' => $data['name'] ?? $item->name,
            'description' => $data['description'] ?? $item->description,
            'quantity' => $data['quantity'] ?? $item->quantity,
            'unit' => $data['unit'] ?? $item->unit,
            'unit_price' => $data['unit_price'] ?? $item->unit_price,
            'discount_percent' => $data['discount_percent'] ?? $item->discount_percent,
            'is_optional' => $data['is_optional'] ?? $item->is_optional,
        ]);

        return $item->fresh();
    }

    /**
     * Delete a pricing item
     */
    public function deletePricingItem(int $itemId): bool
    {
        $item = ProposalPricingItem::with(['proposal'])->findOrFail($itemId);

        if (!$item->proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        return $item->delete();
    }

    /**
     * Toggle optional item selection (for client)
     */
    public function toggleItemSelection(string $proposalUuid, int $itemId): ProposalPricingItem
    {
        $proposal = Proposal::where('uuid', $proposalUuid)->firstOrFail();

        if (!in_array($proposal->status, [Proposal::STATUS_SENT, Proposal::STATUS_VIEWED])) {
            throw new \RuntimeException('Cannot modify items on this proposal');
        }

        $item = ProposalPricingItem::where('id', $itemId)
            ->where('proposal_id', $proposal->id)
            ->firstOrFail();

        $item->toggleSelection();

        return $item->fresh();
    }

    // =========================================================================
    // COMMENT USE CASES
    // =========================================================================

    /**
     * Add a comment
     */
    public function addComment(int $proposalId, array $data): ProposalComment
    {
        return ProposalComment::create([
            'proposal_id' => $proposalId,
            'section_id' => $data['section_id'] ?? null,
            'comment' => $data['comment'],
            'author_email' => $data['author_email'],
            'author_name' => $data['author_name'],
            'author_type' => $data['author_type'] ?? ProposalComment::AUTHOR_INTERNAL,
            'reply_to_id' => $data['reply_to_id'] ?? null,
        ]);
    }

    /**
     * Add client comment (via public link)
     */
    public function addClientComment(string $proposalUuid, array $data): ProposalComment
    {
        $proposal = Proposal::where('uuid', $proposalUuid)->firstOrFail();

        return ProposalComment::create([
            'proposal_id' => $proposal->id,
            'section_id' => $data['section_id'] ?? null,
            'comment' => $data['comment'],
            'author_email' => $data['author_email'],
            'author_name' => $data['author_name'],
            'author_type' => ProposalComment::AUTHOR_CLIENT,
            'reply_to_id' => $data['reply_to_id'] ?? null,
        ]);
    }

    /**
     * Get comments for a proposal
     */
    public function getComments(int $proposalId): Collection
    {
        return ProposalComment::where('proposal_id', $proposalId)
            ->topLevel()
            ->with(['replies', 'section'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Resolve a comment
     */
    public function resolveComment(int $commentId): ProposalComment
    {
        $comment = ProposalComment::findOrFail($commentId);
        $comment->resolve(Auth::id());
        return $comment;
    }

    /**
     * Unresolve a comment
     */
    public function unresolveComment(int $commentId): ProposalComment
    {
        $comment = ProposalComment::findOrFail($commentId);
        $comment->unresolve();
        return $comment;
    }

    // =========================================================================
    // TEMPLATE USE CASES
    // =========================================================================

    /**
     * List templates
     */
    public function listTemplates(array $filters = []): Collection
    {
        $query = ProposalTemplate::query();

        if (!empty($filters['active_only'])) {
            $query->active();
        }

        if (!empty($filters['category'])) {
            $query->category($filters['category']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a template
     */
    public function getTemplate(int $templateId): ?ProposalTemplate
    {
        return ProposalTemplate::with(['createdBy'])->find($templateId);
    }

    /**
     * Create a template
     */
    public function createTemplate(array $data): ProposalTemplate
    {
        return ProposalTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? ProposalTemplate::CATEGORY_OTHER,
            'default_sections' => $data['default_sections'] ?? [],
            'styling' => $data['styling'] ?? [],
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Update a template
     */
    public function updateTemplate(int $templateId, array $data): ProposalTemplate
    {
        $template = ProposalTemplate::findOrFail($templateId);

        $template->update([
            'name' => $data['name'] ?? $template->name,
            'description' => $data['description'] ?? $template->description,
            'category' => $data['category'] ?? $template->category,
            'default_sections' => $data['default_sections'] ?? $template->default_sections,
            'styling' => $data['styling'] ?? $template->styling,
            'is_active' => $data['is_active'] ?? $template->is_active,
        ]);

        return $template->fresh();
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(int $templateId): bool
    {
        return ProposalTemplate::findOrFail($templateId)->delete();
    }

    /**
     * Create template from proposal
     */
    public function createTemplateFromProposal(int $proposalId, string $name, ?string $category = null): ProposalTemplate
    {
        $proposal = Proposal::with(['sections'])->findOrFail($proposalId);

        $sections = $proposal->sections->map(fn($section) => [
            'type' => $section->section_type,
            'title' => $section->title,
            'content' => $section->content,
            'settings' => $section->settings,
        ])->toArray();

        return ProposalTemplate::create([
            'name' => $name,
            'category' => $category ?? ProposalTemplate::CATEGORY_OTHER,
            'default_sections' => $sections,
            'styling' => $proposal->styling,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get proposal engagement analytics
     */
    public function getProposalEngagement(int $proposalId): array
    {
        $proposal = Proposal::with(['views', 'sections'])->findOrFail($proposalId);
        $views = $proposal->views;

        // Time spent per section
        $sectionEngagement = [];
        foreach ($views as $view) {
            foreach ($view->sections_viewed ?? [] as $sectionId => $seconds) {
                if (!isset($sectionEngagement[$sectionId])) {
                    $sectionEngagement[$sectionId] = [
                        'total_time' => 0,
                        'view_count' => 0,
                    ];
                }
                $sectionEngagement[$sectionId]['total_time'] += $seconds;
                $sectionEngagement[$sectionId]['view_count']++;
            }
        }

        // Add section names
        foreach ($proposal->sections as $section) {
            if (isset($sectionEngagement[$section->id])) {
                $sectionEngagement[$section->id]['section_name'] = $section->title;
                $sectionEngagement[$section->id]['avg_time'] = round(
                    $sectionEngagement[$section->id]['total_time'] / $sectionEngagement[$section->id]['view_count']
                );
            }
        }

        // Device breakdown
        $deviceBreakdown = $views->groupBy(fn($v) => $v->detectDeviceType())
            ->map(fn($group) => $group->count());

        return [
            'overview' => [
                'total_views' => $proposal->view_count,
                'unique_sessions' => $views->unique('session_id')->count(),
                'total_time_spent' => $proposal->total_time_spent,
                'avg_time_per_view' => $proposal->view_count > 0
                    ? round($proposal->total_time_spent / $proposal->view_count)
                    : 0,
                'first_viewed' => $proposal->first_viewed_at?->toIso8601String(),
                'last_viewed' => $proposal->last_viewed_at?->toIso8601String(),
            ],
            'section_engagement' => array_values($sectionEngagement),
            'device_breakdown' => $deviceBreakdown->toArray(),
            'recent_views' => $views->take(10)->map(fn($v) => [
                'started_at' => $v->started_at->toIso8601String(),
                'time_spent' => $v->time_spent,
                'viewer_email' => $v->viewer_email,
                'device' => $v->detectDeviceType(),
            ])->toArray(),
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Generate a unique proposal number
     */
    private function generateProposalNumber(): string
    {
        $prefix = 'PROP';
        $year = date('Y');
        $lastNumber = Proposal::where('proposal_number', 'like', "{$prefix}-{$year}-%")
            ->max(DB::raw("CAST(SUBSTRING(proposal_number, -4) AS UNSIGNED)"));

        $nextNumber = str_pad(($lastNumber ?? 0) + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$nextNumber}";
    }
}
