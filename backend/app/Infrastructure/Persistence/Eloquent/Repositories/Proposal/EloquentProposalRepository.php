<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Proposal;

use App\Domain\Proposal\Entities\Proposal as ProposalEntity;
use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class EloquentProposalRepository implements ProposalRepositoryInterface
{
    private const TABLE = 'proposals';
    private const TABLE_SECTIONS = 'proposal_sections';
    private const TABLE_PRICING_ITEMS = 'proposal_pricing_items';
    private const TABLE_COMMENTS = 'proposal_comments';
    private const TABLE_TEMPLATES = 'proposal_templates';
    private const TABLE_VIEWS = 'proposal_views';
    private const TABLE_USERS = 'users';

    // Status constants
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SENT = 'sent';
    private const STATUS_VIEWED = 'viewed';
    private const STATUS_ACCEPTED = 'accepted';
    private const STATUS_REJECTED = 'rejected';
    private const STATUS_EXPIRED = 'expired';

    // Section type constants
    private const SECTION_TYPE_CUSTOM = 'custom';

    // Pricing type constants
    private const PRICING_FIXED = 'fixed';

    // Author type constants
    private const AUTHOR_INTERNAL = 'internal';

    // Template category constants
    private const CATEGORY_OTHER = 'other';

    public function findById(int $id): ?ProposalEntity
    {
        $proposal = DB::table(self::TABLE)->where('id', $id)->first();
        return $proposal ? $this->toDomainEntity($proposal) : null;
    }

    public function findByIdAsArray(int $id, array $with = []): ?array
    {
        $proposal = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$proposal) {
            return null;
        }

        $result = $this->toArray($proposal);

        // Load relationships
        $result['template'] = $this->getTemplateById($proposal->template_id);
        $result['created_by_user'] = $this->getUserById($proposal->created_by);
        $result['assigned_to_user'] = $this->getUserById($proposal->assigned_to);
        $result['sections'] = $this->getSections($id);
        $result['pricing_items'] = $this->getPricingItems($id);
        $result['comments'] = $this->getComments($id);
        $result['views'] = DB::table(self::TABLE_VIEWS)
            ->where('proposal_id', $id)
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn($v) => $this->toArray($v))
            ->toArray();

        return $result;
    }

    public function findByUuid(string $uuid, array $with = []): ?array
    {
        $proposal = DB::table(self::TABLE)->where('uuid', $uuid)->first();

        if (!$proposal) {
            return null;
        }

        $result = $this->toArray($proposal);

        // Load relationships
        $result['sections'] = DB::table(self::TABLE_SECTIONS)
            ->where('proposal_id', $proposal->id)
            ->where('is_visible', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn($s) => $this->toArray($s))
            ->toArray();
        $result['pricing_items'] = $this->getPricingItems($proposal->id);

        return $result;
    }

    public function listProposals(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by multiple statuses
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        // Filter for draft only
        if (!empty($filters['draft_only'])) {
            $query->where('status', self::STATUS_DRAFT);
        }

        // Filter for active proposals (sent or viewed)
        if (!empty($filters['active_only'])) {
            $query->whereIn('status', [self::STATUS_SENT, self::STATUS_VIEWED]);
        }

        // Filter by deal
        if (!empty($filters['deal_id'])) {
            $query->where('deal_id', $filters['deal_id']);
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

        // Count total
        $total = (clone $query)->count();

        // Paginate
        $items = $query->forPage($page, $perPage)->get();

        // Load relationships for each item
        $mappedItems = $items->map(function ($item) {
            $arr = $this->toArray($item);
            $arr['template'] = $this->getTemplateById($item->template_id);
            $arr['created_by_user'] = $this->getUserById($item->created_by);
            $arr['assigned_to_user'] = $this->getUserById($item->assigned_to);
            return $arr;
        })->toArray();

        return PaginatedResult::create(
            items: $mappedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getProposalsForDeal(int $dealId): array
    {
        $proposals = DB::table(self::TABLE)
            ->where('deal_id', $dealId)
            ->orderByDesc('created_at')
            ->get();

        return $proposals->map(function ($proposal) {
            $arr = $this->toArray($proposal);
            $arr['template'] = $this->getTemplateById($proposal->template_id);
            $arr['created_by_user'] = $this->getUserById($proposal->created_by);
            return $arr;
        })->toArray();
    }

    public function getRecentProposals(int $limit = 10): array
    {
        $proposals = DB::table(self::TABLE)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return $proposals->map(function ($proposal) {
            $arr = $this->toArray($proposal);
            $arr['template'] = $this->getTemplateById($proposal->template_id);
            $arr['assigned_to_user'] = $this->getUserById($proposal->assigned_to);
            return $arr;
        })->toArray();
    }

    public function getProposalsNeedingAttention(): array
    {
        // Expiring soon
        $expiringSoon = DB::table(self::TABLE)
            ->whereIn('status', [self::STATUS_SENT, self::STATUS_VIEWED])
            ->whereBetween('valid_until', [now(), now()->addDays(7)])
            ->orderBy('valid_until')
            ->get()
            ->map(function ($p) {
                $arr = $this->toArray($p);
                $arr['assigned_to_user'] = $this->getUserById($p->assigned_to);
                return $arr;
            })->toArray();

        // Recently viewed
        $recentlyViewed = DB::table(self::TABLE)
            ->where('status', self::STATUS_VIEWED)
            ->where('last_viewed_at', '>=', now()->subHours(24))
            ->orderByDesc('last_viewed_at')
            ->get()
            ->map(function ($p) {
                $arr = $this->toArray($p);
                $arr['assigned_to_user'] = $this->getUserById($p->assigned_to);
                return $arr;
            })->toArray();

        // With unresolved comments
        $proposalIdsWithUnresolvedComments = DB::table(self::TABLE_COMMENTS)
            ->whereNull('resolved_at')
            ->distinct()
            ->pluck('proposal_id')
            ->toArray();

        $withComments = DB::table(self::TABLE)
            ->whereIn('status', [self::STATUS_SENT, self::STATUS_VIEWED])
            ->whereIn('id', $proposalIdsWithUnresolvedComments)
            ->get()
            ->map(function ($p) {
                $arr = $this->toArray($p);
                $arr['assigned_to_user'] = $this->getUserById($p->assigned_to);
                return $arr;
            })->toArray();

        return [
            'expiring_soon' => $expiringSoon,
            'recently_viewed' => $recentlyViewed,
            'with_comments' => $withComments,
        ];
    }

    public function getProposalStats(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $proposals = DB::table(self::TABLE)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $acceptedProposals = $proposals->where('status', self::STATUS_ACCEPTED);
        $rejectedProposals = $proposals->where('status', self::STATUS_REJECTED);
        $closedProposals = $acceptedProposals->count() + $rejectedProposals->count();

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_created' => $proposals->count(),
            'by_status' => [
                self::STATUS_DRAFT => $proposals->where('status', self::STATUS_DRAFT)->count(),
                self::STATUS_SENT => $proposals->where('status', self::STATUS_SENT)->count(),
                self::STATUS_VIEWED => $proposals->where('status', self::STATUS_VIEWED)->count(),
                self::STATUS_ACCEPTED => $acceptedProposals->count(),
                self::STATUS_REJECTED => $rejectedProposals->count(),
                self::STATUS_EXPIRED => $proposals->where('status', self::STATUS_EXPIRED)->count(),
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

    public function save(ProposalEntity $entity): ProposalEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId()) {
            $data['updated_at'] = now();
            DB::table(self::TABLE)->where('id', $entity->getId())->update($data);
            $proposal = DB::table(self::TABLE)->where('id', $entity->getId())->first();
        } else {
            $data['uuid'] = Str::uuid()->toString();
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table(self::TABLE)->insertGetId($data);
            $proposal = DB::table(self::TABLE)->where('id', $id)->first();
        }

        return $this->toDomainEntity($proposal);
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $insertData = [
                'uuid' => Str::uuid()->toString(),
                'name' => $data['name'],
                'proposal_number' => $data['proposal_number'] ?? $this->generateProposalNumber(),
                'template_id' => $data['template_id'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'cover_page' => isset($data['cover_page']) ? json_encode($data['cover_page']) : null,
                'styling' => isset($data['styling']) ? json_encode($data['styling']) : null,
                'currency' => $data['currency'] ?? 'USD',
                'valid_until' => $data['valid_until'] ?? now()->addDays(30),
                'created_by' => $data['created_by'],
                'assigned_to' => $data['assigned_to'] ?? $data['created_by'],
                'status' => self::STATUS_DRAFT,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $proposalId = DB::table(self::TABLE)->insertGetId($insertData);

            // Create sections from template if provided
            if (!empty($data['template_id'])) {
                $template = DB::table(self::TABLE_TEMPLATES)->where('id', $data['template_id'])->first();
                if ($template) {
                    $defaultSections = is_string($template->default_sections)
                        ? json_decode($template->default_sections, true)
                        : $template->default_sections;

                    if (!empty($defaultSections)) {
                        foreach ($defaultSections as $index => $section) {
                            DB::table(self::TABLE_SECTIONS)->insert([
                                'proposal_id' => $proposalId,
                                'section_type' => $section['type'] ?? self::SECTION_TYPE_CUSTOM,
                                'title' => $section['title'],
                                'content' => $section['content'] ?? '',
                                'settings' => json_encode($section['settings'] ?? []),
                                'display_order' => $index,
                                'is_visible' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // Apply template styling
                    if (!empty($template->styling)) {
                        DB::table(self::TABLE)->where('id', $proposalId)->update([
                            'styling' => is_string($template->styling) ? $template->styling : json_encode($template->styling),
                        ]);
                    }
                }
            }

            // Create custom sections if provided
            if (!empty($data['sections'])) {
                foreach ($data['sections'] as $index => $sectionData) {
                    DB::table(self::TABLE_SECTIONS)->insert([
                        'proposal_id' => $proposalId,
                        'section_type' => $sectionData['section_type'] ?? self::SECTION_TYPE_CUSTOM,
                        'title' => $sectionData['title'],
                        'content' => $sectionData['content'] ?? '',
                        'settings' => json_encode($sectionData['settings'] ?? []),
                        'display_order' => $sectionData['display_order'] ?? $index,
                        'is_visible' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $proposal = DB::table(self::TABLE)->where('id', $proposalId)->first();
            $result = $this->toArray($proposal);
            $result['sections'] = $this->getSections($proposalId);
            $result['template'] = $this->getTemplateById($proposal->template_id);

            return $result;
        });
    }

    public function update(int $id, array $data): array
    {
        $proposal = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$proposal) {
            throw new \RuntimeException("Proposal with ID {$id} not found");
        }

        if (!$this->isProposalEditable($proposal)) {
            throw new \RuntimeException('Proposal cannot be edited in its current status');
        }

        $updateData = ['updated_at' => now()];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['cover_page'])) $updateData['cover_page'] = is_array($data['cover_page']) ? json_encode($data['cover_page']) : $data['cover_page'];
        if (isset($data['styling'])) $updateData['styling'] = is_array($data['styling']) ? json_encode($data['styling']) : $data['styling'];
        if (isset($data['currency'])) $updateData['currency'] = $data['currency'];
        if (isset($data['valid_until'])) $updateData['valid_until'] = $data['valid_until'];
        if (isset($data['assigned_to'])) $updateData['assigned_to'] = $data['assigned_to'];

        DB::table(self::TABLE)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE)->where('id', $id)->first();
        $result = $this->toArray($updated);
        $result['sections'] = $this->getSections($id);
        $result['pricing_items'] = $this->getPricingItems($id);

        return $result;
    }

    public function delete(int $id): bool
    {
        $proposal = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$proposal) {
            throw new \RuntimeException("Proposal with ID {$id} not found");
        }

        return DB::transaction(function () use ($id) {
            DB::table(self::TABLE_SECTIONS)->where('proposal_id', $id)->delete();
            DB::table(self::TABLE_PRICING_ITEMS)->where('proposal_id', $id)->delete();
            DB::table(self::TABLE_VIEWS)->where('proposal_id', $id)->delete();
            DB::table(self::TABLE_COMMENTS)->where('proposal_id', $id)->delete();
            return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
        });
    }

    public function duplicate(int $id, int $userId): array
    {
        $proposal = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$proposal) {
            throw new \RuntimeException("Proposal with ID {$id} not found");
        }

        return DB::transaction(function () use ($proposal, $userId) {
            $newData = (array) $proposal;
            unset($newData['id']);
            $newData['uuid'] = Str::uuid()->toString();
            $newData['status'] = self::STATUS_DRAFT;
            $newData['proposal_number'] = $this->generateProposalNumber();
            $newData['created_by'] = $userId;
            $newData['sent_at'] = null;
            $newData['sent_to_email'] = null;
            $newData['first_viewed_at'] = null;
            $newData['last_viewed_at'] = null;
            $newData['view_count'] = 0;
            $newData['total_time_spent'] = 0;
            $newData['accepted_at'] = null;
            $newData['accepted_by'] = null;
            $newData['accepted_signature'] = null;
            $newData['accepted_ip'] = null;
            $newData['rejected_at'] = null;
            $newData['rejected_by'] = null;
            $newData['rejection_reason'] = null;
            $newData['created_at'] = now();
            $newData['updated_at'] = now();

            $newId = DB::table(self::TABLE)->insertGetId($newData);

            // Copy sections
            $sections = DB::table(self::TABLE_SECTIONS)->where('proposal_id', $proposal->id)->get();
            foreach ($sections as $section) {
                $sectionData = (array) $section;
                unset($sectionData['id']);
                $sectionData['proposal_id'] = $newId;
                $sectionData['created_at'] = now();
                $sectionData['updated_at'] = now();
                DB::table(self::TABLE_SECTIONS)->insert($sectionData);
            }

            // Copy pricing items
            $items = DB::table(self::TABLE_PRICING_ITEMS)->where('proposal_id', $proposal->id)->get();
            foreach ($items as $item) {
                $itemData = (array) $item;
                unset($itemData['id']);
                $itemData['proposal_id'] = $newId;
                $itemData['created_at'] = now();
                $itemData['updated_at'] = now();
                DB::table(self::TABLE_PRICING_ITEMS)->insert($itemData);
            }

            $newProposal = DB::table(self::TABLE)->where('id', $newId)->first();
            $result = $this->toArray($newProposal);
            $result['sections'] = $this->getSections($newId);
            $result['pricing_items'] = $this->getPricingItems($newId);

            return $result;
        });
    }

    public function generateProposalNumber(): string
    {
        $prefix = 'PROP';
        $year = date('Y');
        $lastNumber = DB::table(self::TABLE)
            ->where('proposal_number', 'like', "{$prefix}-{$year}-%")
            ->max(DB::raw("CAST(SUBSTRING(proposal_number, -4) AS UNSIGNED)"));

        $nextNumber = str_pad(($lastNumber ?? 0) + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$nextNumber}";
    }

    public function markExpiredProposals(): int
    {
        return DB::table(self::TABLE)
            ->whereIn('status', [self::STATUS_SENT, self::STATUS_VIEWED])
            ->where('valid_until', '<', now())
            ->update(['status' => self::STATUS_EXPIRED, 'updated_at' => now()]);
    }

    // Section methods
    public function addSection(int $proposalId, array $data): array
    {
        $proposal = Proposal::findOrFail($proposalId);

        if (!$proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        $maxOrder = $proposal->sections()->max('display_order') ?? 0;

        $section = $proposal->sections()->create([
            'section_type' => $data['section_type'] ?? ProposalSection::TYPE_CUSTOM,
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'settings' => $data['settings'] ?? [],
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
            'is_visible' => $data['is_visible'] ?? true,
        ]);

        return $section->toArray();
    }

    public function updateSection(int $sectionId, array $data): array
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

        return $section->fresh()->toArray();
    }

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

    public function reorderSections(int $proposalId, array $sectionOrder): array
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
                ->get()
                ->map(fn($section) => $section->toArray())
                ->all();
        });
    }

    // Pricing item methods
    public function addPricingItem(int $proposalId, array $data): array
    {
        $proposal = Proposal::findOrFail($proposalId);

        if (!$proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        $maxOrder = $proposal->pricingItems()->max('display_order') ?? 0;

        $item = $proposal->pricingItems()->create([
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

        return $item->toArray();
    }

    public function updatePricingItem(int $itemId, array $data): array
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

        return $item->fresh()->toArray();
    }

    public function deletePricingItem(int $itemId): bool
    {
        $item = ProposalPricingItem::with(['proposal'])->findOrFail($itemId);

        if (!$item->proposal->isEditable()) {
            throw new \RuntimeException('Proposal cannot be edited');
        }

        return $item->delete();
    }

    // View tracking methods
    public function recordView(string $uuid, string $sessionId, ?string $email = null, ?string $name = null): ?array
    {
        $proposal = Proposal::where('uuid', $uuid)->first();

        if (!$proposal) {
            return null;
        }

        $view = $proposal->recordView($sessionId, $email, $name);
        return $view->toArray();
    }

    public function endViewSession(int $viewId): array
    {
        $view = ProposalView::findOrFail($viewId);
        $view->endSession();
        return $view->toArray();
    }

    public function trackSectionView(int $viewId, int $sectionId, int $seconds): array
    {
        $view = ProposalView::findOrFail($viewId);
        $view->trackSectionView($sectionId, $seconds);
        return $view->toArray();
    }

    // Status change methods
    public function sendProposal(int $id, string $recipientEmail): array
    {
        $proposal = Proposal::findOrFail($id);

        if (!$proposal->canBeSent()) {
            throw new \RuntimeException('Proposal cannot be sent in its current status');
        }

        $proposal->send($recipientEmail);

        return $proposal->toArray();
    }

    public function acceptProposal(string $uuid, string $acceptedBy, ?string $signature = null, ?string $ip = null): array
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        if ($proposal->isExpired()) {
            throw new \RuntimeException('Proposal has expired');
        }

        if (!in_array($proposal->status, [Proposal::STATUS_SENT, Proposal::STATUS_VIEWED])) {
            throw new \RuntimeException('Proposal cannot be accepted in its current status');
        }

        $proposal->accept($acceptedBy, $signature, $ip);

        return $proposal->toArray();
    }

    public function rejectProposal(string $uuid, string $rejectedBy, ?string $reason = null): array
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        if (!in_array($proposal->status, [Proposal::STATUS_SENT, Proposal::STATUS_VIEWED])) {
            throw new \RuntimeException('Proposal cannot be rejected in its current status');
        }

        $proposal->reject($rejectedBy, $reason);

        return $proposal->toArray();
    }

    public function createNewVersion(int $id): array
    {
        $original = Proposal::with(['sections', 'pricingItems'])->findOrFail($id);

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

            return $newProposal->load(['sections', 'pricingItems'])->toArray();
        });
    }

    public function toggleItemSelection(string $proposalUuid, int $itemId): array
    {
        $proposal = Proposal::where('uuid', $proposalUuid)->firstOrFail();

        if (!in_array($proposal->status, [Proposal::STATUS_SENT, Proposal::STATUS_VIEWED])) {
            throw new \RuntimeException('Cannot modify items on this proposal');
        }

        $item = ProposalPricingItem::where('id', $itemId)
            ->where('proposal_id', $proposal->id)
            ->firstOrFail();

        $item->toggleSelection();

        return $item->fresh()->toArray();
    }

    // Comment methods
    public function addComment(int $proposalId, array $data): array
    {
        $comment = ProposalComment::create([
            'proposal_id' => $proposalId,
            'section_id' => $data['section_id'] ?? null,
            'comment' => $data['comment'],
            'author_email' => $data['author_email'],
            'author_name' => $data['author_name'],
            'author_type' => $data['author_type'] ?? ProposalComment::AUTHOR_INTERNAL,
            'reply_to_id' => $data['reply_to_id'] ?? null,
        ]);

        return $comment->toArray();
    }

    public function getComments(int $proposalId): array
    {
        return ProposalComment::where('proposal_id', $proposalId)
            ->topLevel()
            ->with(['replies', 'section'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($comment) => $comment->toArray())
            ->all();
    }

    public function resolveComment(int $commentId, int $userId): array
    {
        $comment = ProposalComment::findOrFail($commentId);
        $comment->resolve($userId);
        return $comment->toArray();
    }

    public function unresolveComment(int $commentId): array
    {
        $comment = ProposalComment::findOrFail($commentId);
        $comment->unresolve();
        return $comment->toArray();
    }

    // Template methods
    public function listTemplates(array $filters = []): array
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

        return $query->orderBy('name')
            ->get()
            ->map(fn($template) => $template->toArray())
            ->all();
    }

    public function getTemplate(int $templateId): ?array
    {
        $template = ProposalTemplate::with(['createdBy'])->find($templateId);
        return $template ? $template->toArray() : null;
    }

    public function createTemplate(array $data): array
    {
        $template = ProposalTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? ProposalTemplate::CATEGORY_OTHER,
            'default_sections' => $data['default_sections'] ?? [],
            'styling' => $data['styling'] ?? [],
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $data['created_by'],
        ]);

        return $template->toArray();
    }

    public function updateTemplate(int $templateId, array $data): array
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

        return $template->fresh()->toArray();
    }

    public function deleteTemplate(int $templateId): bool
    {
        return ProposalTemplate::findOrFail($templateId)->delete();
    }

    public function createTemplateFromProposal(int $proposalId, string $name, ?string $category = null): array
    {
        $proposal = Proposal::with(['sections'])->findOrFail($proposalId);

        $sections = $proposal->sections->map(fn($section) => [
            'type' => $section->section_type,
            'title' => $section->title,
            'content' => $section->content,
            'settings' => $section->settings,
        ])->toArray();

        $template = ProposalTemplate::create([
            'name' => $name,
            'category' => $category ?? ProposalTemplate::CATEGORY_OTHER,
            'default_sections' => $sections,
            'styling' => $proposal->styling,
            'is_active' => true,
            'created_by' => $proposal->created_by,
        ]);

        return $template->toArray();
    }

    // Analytics methods
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
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(Proposal $model): ProposalEntity
    {
        return ProposalEntity::reconstitute(
            id: $model->id,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at->toDateTimeString()) : null,
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );
    }

    private function toModelData(ProposalEntity $entity): array
    {
        $data = [];

        if ($entity->getCreatedAt()) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        if ($entity->getUpdatedAt()) {
            $data['updated_at'] = $entity->getUpdatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
