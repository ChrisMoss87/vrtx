<?php

declare(strict_types=1);

namespace App\Application\Services\Competitor;

use App\Domain\Competitor\Repositories\CompetitorRepositoryInterface;
use App\Models\BattlecardSection;
use App\Models\Competitor;
use App\Models\CompetitorNote;
use App\Models\CompetitorObjection;
use App\Models\DealCompetitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompetitorApplicationService
{
    public function __construct(
        private CompetitorRepositoryInterface $repository,
    ) {}

    // ==========================================
    // COMPETITOR QUERY USE CASES
    // ==========================================

    /**
     * List competitors with filtering and pagination.
     */
    public function listCompetitors(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Competitor::query()
            ->with('lastUpdatedBy')
            ->withCount('dealCompetitors');

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['market_position'])) {
            $query->where('market_position', $filters['market_position']);
        }

        $sortField = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single competitor.
     */
    public function getCompetitor(int $id): ?Competitor
    {
        return Competitor::with(['sections', 'objections.createdBy', 'lastUpdatedBy'])->find($id);
    }

    /**
     * Get competitor with battlecard data.
     */
    public function getCompetitorBattlecard(int $id): array
    {
        $competitor = Competitor::with(['sections', 'objections'])
            ->findOrFail($id);

        return [
            'competitor' => $competitor,
            'strengths' => $competitor->getSectionByType(BattlecardSection::TYPE_STRENGTHS),
            'weaknesses' => $competitor->getSectionByType(BattlecardSection::TYPE_WEAKNESSES),
            'our_advantages' => $competitor->getSectionByType(BattlecardSection::TYPE_OUR_ADVANTAGES),
            'pricing' => $competitor->getSectionByType(BattlecardSection::TYPE_PRICING),
            'resources' => $competitor->getSectionByType(BattlecardSection::TYPE_RESOURCES),
            'win_stories' => $competitor->getSectionByType(BattlecardSection::TYPE_WIN_STORIES),
            'top_objections' => $competitor->objections()->orderByDesc('effectiveness_score')->limit(5)->get(),
            'win_rate' => $competitor->getWinRate(),
            'total_deals' => $competitor->getTotalDeals(),
        ];
    }

    /**
     * Get competitor stats.
     */
    public function getCompetitorStats(int $id): array
    {
        $competitor = Competitor::findOrFail($id);

        return [
            'win_rate' => $competitor->getWinRate(),
            'total_deals' => $competitor->getTotalDeals(),
            'won_deals' => $competitor->getWonDeals(),
            'lost_deals' => $competitor->getLostDeals(),
            'active_deals' => $competitor->dealCompetitors()->where('outcome', 'active')->count(),
            'objection_count' => $competitor->objections()->count(),
            'note_count' => $competitor->notes()->count(),
            'avg_objection_effectiveness' => $competitor->objections()
                ->whereNotNull('effectiveness_score')
                ->avg('effectiveness_score'),
        ];
    }

    /**
     * Get all competitors for dropdown.
     */
    public function getCompetitorList(): Collection
    {
        return Competitor::active()
            ->orderBy('name')
            ->get(['id', 'name', 'logo_url']);
    }

    /**
     * Search competitors.
     */
    public function searchCompetitors(string $query, int $limit = 10): Collection
    {
        return Competitor::search($query)
            ->active()
            ->limit($limit)
            ->get(['id', 'name', 'logo_url', 'website']);
    }

    // ==========================================
    // COMPETITOR COMMAND USE CASES
    // ==========================================

    /**
     * Create a competitor.
     */
    public function createCompetitor(array $data): Competitor
    {
        return DB::transaction(function () use ($data) {
            $competitor = Competitor::create([
                'name' => $data['name'],
                'website' => $data['website'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'description' => $data['description'] ?? null,
                'market_position' => $data['market_position'] ?? null,
                'pricing_info' => $data['pricing_info'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'last_updated_at' => now(),
                'last_updated_by' => Auth::id(),
            ]);

            // Create default battlecard sections
            foreach (BattlecardSection::getTypes() as $type => $label) {
                $competitor->sections()->create([
                    'section_type' => $type,
                    'content' => '',
                    'display_order' => array_search($type, array_keys(BattlecardSection::getTypes())),
                    'created_by' => Auth::id(),
                ]);
            }

            return $competitor;
        });
    }

    /**
     * Update a competitor.
     */
    public function updateCompetitor(int $id, array $data): Competitor
    {
        $competitor = Competitor::findOrFail($id);

        $competitor->update([
            'name' => $data['name'] ?? $competitor->name,
            'website' => $data['website'] ?? $competitor->website,
            'logo_url' => $data['logo_url'] ?? $competitor->logo_url,
            'description' => $data['description'] ?? $competitor->description,
            'market_position' => $data['market_position'] ?? $competitor->market_position,
            'pricing_info' => $data['pricing_info'] ?? $competitor->pricing_info,
            'is_active' => $data['is_active'] ?? $competitor->is_active,
        ]);

        $competitor->markUpdated(Auth::id());

        return $competitor->fresh();
    }

    /**
     * Delete a competitor.
     */
    public function deleteCompetitor(int $id): void
    {
        $competitor = Competitor::findOrFail($id);

        DB::transaction(function () use ($competitor) {
            $competitor->sections()->delete();
            $competitor->objections()->delete();
            $competitor->notes()->delete();
            $competitor->dealCompetitors()->delete();
            $competitor->delete();
        });
    }

    /**
     * Deactivate a competitor.
     */
    public function deactivateCompetitor(int $id): Competitor
    {
        $competitor = Competitor::findOrFail($id);
        $competitor->update(['is_active' => false]);
        return $competitor->fresh();
    }

    /**
     * Activate a competitor.
     */
    public function activateCompetitor(int $id): Competitor
    {
        $competitor = Competitor::findOrFail($id);
        $competitor->update(['is_active' => true]);
        return $competitor->fresh();
    }

    // ==========================================
    // BATTLECARD SECTION USE CASES
    // ==========================================

    /**
     * Get battlecard sections for a competitor.
     */
    public function getBattlecardSections(int $competitorId): Collection
    {
        return BattlecardSection::where('competitor_id', $competitorId)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Update a battlecard section.
     */
    public function updateBattlecardSection(int $sectionId, array $data): BattlecardSection
    {
        $section = BattlecardSection::findOrFail($sectionId);

        $section->update([
            'content' => $data['content'] ?? $section->content,
            'display_order' => $data['display_order'] ?? $section->display_order,
        ]);

        // Mark competitor as updated
        $section->competitor->markUpdated(Auth::id());

        return $section->fresh();
    }

    /**
     * Create a custom battlecard section.
     */
    public function createBattlecardSection(int $competitorId, array $data): BattlecardSection
    {
        $competitor = Competitor::findOrFail($competitorId);

        $section = $competitor->sections()->create([
            'section_type' => $data['section_type'],
            'content' => $data['content'] ?? '',
            'display_order' => $data['display_order'] ?? 99,
            'created_by' => Auth::id(),
        ]);

        $competitor->markUpdated(Auth::id());

        return $section;
    }

    /**
     * Delete a battlecard section.
     */
    public function deleteBattlecardSection(int $sectionId): void
    {
        $section = BattlecardSection::findOrFail($sectionId);
        $competitor = $section->competitor;

        $section->delete();

        $competitor->markUpdated(Auth::id());
    }

    /**
     * Reorder battlecard sections.
     */
    public function reorderBattlecardSections(int $competitorId, array $sectionIds): void
    {
        foreach ($sectionIds as $order => $sectionId) {
            BattlecardSection::where('id', $sectionId)
                ->where('competitor_id', $competitorId)
                ->update(['display_order' => $order]);
        }

        Competitor::findOrFail($competitorId)->markUpdated(Auth::id());
    }

    // ==========================================
    // OBJECTION USE CASES
    // ==========================================

    /**
     * List objections for a competitor.
     */
    public function listObjections(int $competitorId): Collection
    {
        return CompetitorObjection::where('competitor_id', $competitorId)
            ->with('createdBy')
            ->orderByDesc('effectiveness_score')
            ->get();
    }

    /**
     * Get a single objection.
     */
    public function getObjection(int $id): ?CompetitorObjection
    {
        return CompetitorObjection::with(['competitor', 'createdBy'])->find($id);
    }

    /**
     * Create an objection.
     */
    public function createObjection(int $competitorId, array $data): CompetitorObjection
    {
        $competitor = Competitor::findOrFail($competitorId);

        $objection = $competitor->objections()->create([
            'objection' => $data['objection'],
            'counter_script' => $data['counter_script'],
            'created_by' => Auth::id(),
        ]);

        $competitor->markUpdated(Auth::id());

        return $objection;
    }

    /**
     * Update an objection.
     */
    public function updateObjection(int $id, array $data): CompetitorObjection
    {
        $objection = CompetitorObjection::findOrFail($id);

        $objection->update([
            'objection' => $data['objection'] ?? $objection->objection,
            'counter_script' => $data['counter_script'] ?? $objection->counter_script,
        ]);

        $objection->competitor->markUpdated(Auth::id());

        return $objection->fresh();
    }

    /**
     * Delete an objection.
     */
    public function deleteObjection(int $id): void
    {
        $objection = CompetitorObjection::findOrFail($id);
        $competitor = $objection->competitor;

        $objection->delete();
        $competitor->markUpdated(Auth::id());
    }

    /**
     * Record objection usage.
     */
    public function recordObjectionUsage(int $id, bool $wasSuccessful): CompetitorObjection
    {
        $objection = CompetitorObjection::findOrFail($id);
        $objection->recordUsage($wasSuccessful);
        return $objection->fresh();
    }

    /**
     * Get top effective objections across all competitors.
     */
    public function getTopEffectiveObjections(int $limit = 10): Collection
    {
        return CompetitorObjection::with('competitor')
            ->whereNotNull('effectiveness_score')
            ->where('use_count', '>=', 3)
            ->orderByDesc('effectiveness_score')
            ->limit($limit)
            ->get();
    }

    // ==========================================
    // NOTE USE CASES
    // ==========================================

    /**
     * List notes for a competitor.
     */
    public function listNotes(int $competitorId, array $filters = []): Collection
    {
        $query = CompetitorNote::where('competitor_id', $competitorId)
            ->with(['createdBy', 'verifiedBy']);

        if (isset($filters['verified_only']) && $filters['verified_only']) {
            $query->verified();
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Create a note.
     */
    public function createNote(int $competitorId, array $data): CompetitorNote
    {
        $competitor = Competitor::findOrFail($competitorId);

        $note = $competitor->notes()->create([
            'content' => $data['content'],
            'source' => $data['source'] ?? null,
            'created_by' => Auth::id(),
            'is_verified' => false,
        ]);

        $competitor->markUpdated(Auth::id());

        return $note;
    }

    /**
     * Update a note.
     */
    public function updateNote(int $id, array $data): CompetitorNote
    {
        $note = CompetitorNote::findOrFail($id);

        $note->update([
            'content' => $data['content'] ?? $note->content,
            'source' => $data['source'] ?? $note->source,
        ]);

        $note->competitor->markUpdated(Auth::id());

        return $note->fresh();
    }

    /**
     * Delete a note.
     */
    public function deleteNote(int $id): void
    {
        $note = CompetitorNote::findOrFail($id);
        $competitor = $note->competitor;

        $note->delete();
        $competitor->markUpdated(Auth::id());
    }

    /**
     * Verify a note.
     */
    public function verifyNote(int $id): CompetitorNote
    {
        $note = CompetitorNote::findOrFail($id);
        $note->verify(Auth::id());
        return $note->fresh();
    }

    /**
     * Unverify a note.
     */
    public function unverifyNote(int $id): CompetitorNote
    {
        $note = CompetitorNote::findOrFail($id);
        $note->unverify();
        return $note->fresh();
    }

    // ==========================================
    // DEAL COMPETITOR USE CASES
    // ==========================================

    /**
     * Add competitor to a deal.
     */
    public function addCompetitorToDeal(int $dealId, int $competitorId, array $data = []): DealCompetitor
    {
        return DealCompetitor::create([
            'deal_id' => $dealId,
            'competitor_id' => $competitorId,
            'outcome' => $data['outcome'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'added_by' => Auth::id(),
        ]);
    }

    /**
     * Update deal competitor.
     */
    public function updateDealCompetitor(int $id, array $data): DealCompetitor
    {
        $dealCompetitor = DealCompetitor::findOrFail($id);

        $dealCompetitor->update([
            'outcome' => $data['outcome'] ?? $dealCompetitor->outcome,
            'notes' => $data['notes'] ?? $dealCompetitor->notes,
        ]);

        return $dealCompetitor->fresh();
    }

    /**
     * Remove competitor from deal.
     */
    public function removeCompetitorFromDeal(int $id): void
    {
        DealCompetitor::findOrFail($id)->delete();
    }

    /**
     * Get deals involving a competitor.
     */
    public function getCompetitorDeals(int $competitorId, array $filters = []): Collection
    {
        $query = DealCompetitor::where('competitor_id', $competitorId)
            ->with('deal');

        if (!empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    // ==========================================
    // ANALYTICS USE CASES
    // ==========================================

    /**
     * Get competitor analytics dashboard.
     */
    public function getAnalyticsDashboard(): array
    {
        $competitors = Competitor::active()
            ->withCount('dealCompetitors')
            ->get();

        $totalDeals = DealCompetitor::count();
        $wonDeals = DealCompetitor::where('outcome', 'won')->count();
        $lostDeals = DealCompetitor::where('outcome', 'lost')->count();

        return [
            'total_competitors' => $competitors->count(),
            'active_competitors' => $competitors->where('is_active', true)->count(),
            'total_competitive_deals' => $totalDeals,
            'overall_win_rate' => $totalDeals > 0
                ? round(($wonDeals / ($wonDeals + $lostDeals)) * 100, 1)
                : null,
            'most_encountered' => $competitors
                ->sortByDesc('deal_competitors_count')
                ->take(5)
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'deal_count' => $c->deal_competitors_count,
                ]),
            'win_rates' => $competitors->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'win_rate' => $c->getWinRate(),
                'total_deals' => $c->getTotalDeals(),
            ])->filter(fn ($c) => $c['total_deals'] >= 3)
                ->sortByDesc('win_rate')
                ->take(10)
                ->values(),
        ];
    }

    /**
     * Get win/loss analysis against a competitor.
     */
    public function getWinLossAnalysis(int $competitorId): array
    {
        $competitor = Competitor::findOrFail($competitorId);
        $deals = $competitor->dealCompetitors()->with('deal')->get();

        $wonDeals = $deals->where('outcome', 'won');
        $lostDeals = $deals->where('outcome', 'lost');

        return [
            'competitor' => $competitor->name,
            'total_deals' => $deals->count(),
            'won' => $wonDeals->count(),
            'lost' => $lostDeals->count(),
            'active' => $deals->where('outcome', 'active')->count(),
            'win_rate' => $competitor->getWinRate(),
            'won_value' => $wonDeals->sum(fn ($dc) => $dc->deal?->amount ?? 0),
            'lost_value' => $lostDeals->sum(fn ($dc) => $dc->deal?->amount ?? 0),
            'recent_wins' => $wonDeals->sortByDesc('created_at')->take(5)->map(fn ($dc) => [
                'deal_id' => $dc->deal_id,
                'deal_name' => $dc->deal?->name,
                'amount' => $dc->deal?->amount,
                'closed_at' => $dc->updated_at,
            ])->values(),
            'recent_losses' => $lostDeals->sortByDesc('created_at')->take(5)->map(fn ($dc) => [
                'deal_id' => $dc->deal_id,
                'deal_name' => $dc->deal?->name,
                'amount' => $dc->deal?->amount,
                'closed_at' => $dc->updated_at,
            ])->values(),
        ];
    }

    /**
     * Get competitor comparison.
     */
    public function compareCompetitors(array $competitorIds): array
    {
        $competitors = Competitor::whereIn('id', $competitorIds)
            ->with('sections')
            ->get();

        $comparison = [];

        foreach ($competitors as $competitor) {
            $comparison[] = [
                'id' => $competitor->id,
                'name' => $competitor->name,
                'market_position' => $competitor->market_position,
                'win_rate' => $competitor->getWinRate(),
                'total_deals' => $competitor->getTotalDeals(),
                'strengths' => $competitor->getSectionByType(BattlecardSection::TYPE_STRENGTHS)?->getContentLines() ?? [],
                'weaknesses' => $competitor->getSectionByType(BattlecardSection::TYPE_WEAKNESSES)?->getContentLines() ?? [],
                'our_advantages' => $competitor->getSectionByType(BattlecardSection::TYPE_OUR_ADVANTAGES)?->getContentLines() ?? [],
            ];
        }

        return $comparison;
    }

    /**
     * Get market position distribution.
     */
    public function getMarketPositionDistribution(): array
    {
        return Competitor::active()
            ->selectRaw('market_position, count(*) as count')
            ->groupBy('market_position')
            ->pluck('count', 'market_position')
            ->toArray();
    }
}
