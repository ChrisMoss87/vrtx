<?php

namespace App\Http\Controllers\Api\Competitor;

use App\Application\Services\Competitor\CompetitorApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\CompetitorObjection;
use App\Models\DealCompetitor;
use App\Services\Competitor\CompetitorService;
use App\Services\Competitor\CompetitorAnalyticsService;
use App\Services\Competitor\ObjectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function __construct(
        private CompetitorApplicationService $competitorApplicationService,
        private CompetitorService $competitorService,
        private CompetitorAnalyticsService $analyticsService,
        private ObjectionService $objectionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $activeOnly = $request->boolean('active_only', true);

        $competitors = $this->competitorService->getCompetitors($search, $activeOnly);

        return response()->json([
            'data' => $competitors->map(fn ($c) => $this->formatCompetitor($c)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $competitor = $this->competitorService->getCompetitor($id);

        if (!$competitor) {
            return response()->json(['error' => 'Competitor not found'], 404);
        }

        return response()->json([
            'data' => $this->formatCompetitorFull($competitor),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'website' => 'nullable|url|max:500',
            'logo_url' => 'nullable|url|max:500',
            'description' => 'nullable|string',
            'market_position' => 'nullable|string',
            'pricing_info' => 'nullable|string',
        ]);

        $competitor = $this->competitorService->createCompetitor($validated, $request->user());

        return response()->json([
            'data' => $this->formatCompetitor($competitor),
            'message' => 'Competitor created',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'website' => 'nullable|url|max:500',
            'logo_url' => 'nullable|url|max:500',
            'description' => 'nullable|string',
            'market_position' => 'nullable|string',
            'pricing_info' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $competitor = $this->competitorService->updateCompetitor($competitor, $validated, $request->user());

        return response()->json([
            'data' => $this->formatCompetitor($competitor),
            'message' => 'Competitor updated',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);
        $this->competitorService->deleteCompetitor($competitor);

        return response()->json(['message' => 'Competitor deleted']);
    }

    public function battlecard(int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);
        $battlecard = $this->competitorService->getBattlecard($competitor);

        return response()->json(['data' => $battlecard]);
    }

    public function updateSection(Request $request, int $id, int $sectionId): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $section = $this->competitorService->updateSection(
            $competitor,
            $sectionId,
            $validated['content'],
            $request->user()
        );

        return response()->json([
            'data' => [
                'id' => $section->id,
                'type' => $section->section_type,
                'content' => $section->content,
            ],
            'message' => 'Section updated',
        ]);
    }

    public function storeSection(Request $request, int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'content' => 'required|string',
        ]);

        $section = $this->competitorService->createSection(
            $competitor,
            $validated['type'],
            $validated['content'],
            $request->user()
        );

        return response()->json([
            'data' => [
                'id' => $section->id,
                'type' => $section->section_type,
                'content' => $section->content,
            ],
            'message' => 'Section created',
        ], 201);
    }

    public function objections(int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);
        $objections = $this->objectionService->getObjections($competitor);

        return response()->json([
            'data' => $objections->map(fn ($o) => $this->formatObjection($o)),
        ]);
    }

    public function storeObjection(Request $request, int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);

        $validated = $request->validate([
            'objection' => 'required|string',
            'counter_script' => 'required|string',
        ]);

        $objection = $this->objectionService->createObjection(
            $competitor,
            $validated['objection'],
            $validated['counter_script'],
            $request->user()
        );

        return response()->json([
            'data' => $this->formatObjection($objection),
            'message' => 'Objection handler created',
        ], 201);
    }

    public function updateObjection(Request $request, int $id, int $objectionId): JsonResponse
    {
        $objection = CompetitorObjection::where('competitor_id', $id)->findOrFail($objectionId);

        $validated = $request->validate([
            'objection' => 'sometimes|string',
            'counter_script' => 'sometimes|string',
        ]);

        $objection = $this->objectionService->updateObjection($objection, $validated);

        return response()->json([
            'data' => $this->formatObjection($objection),
            'message' => 'Objection handler updated',
        ]);
    }

    public function objectionFeedback(Request $request, int $id, int $objectionId): JsonResponse
    {
        $objection = CompetitorObjection::where('competitor_id', $id)->findOrFail($objectionId);

        $validated = $request->validate([
            'was_successful' => 'required|boolean',
            'deal_id' => 'nullable|integer',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $feedback = $this->objectionService->recordFeedback(
            $objection,
            $validated['was_successful'],
            $request->user(),
            $validated['deal_id'] ?? null,
            $validated['feedback'] ?? null
        );

        return response()->json([
            'data' => [
                'effectiveness_score' => $objection->fresh()->effectiveness_score,
                'use_count' => $objection->fresh()->use_count,
            ],
            'message' => 'Feedback recorded',
        ]);
    }

    public function notes(int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);

        return response()->json([
            'data' => $competitor->notes()->with(['createdBy', 'verifiedBy'])->latest()->get()->map(fn ($n) => [
                'id' => $n->id,
                'content' => $n->content,
                'source' => $n->source,
                'is_verified' => $n->is_verified,
                'created_by' => $n->createdBy?->name,
                'verified_by' => $n->verifiedBy?->name,
                'created_at' => $n->created_at->toISOString(),
            ]),
        ]);
    }

    public function storeNote(Request $request, int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
            'source' => 'nullable|string|max:255',
        ]);

        $note = $this->competitorService->addNote(
            $competitor,
            $validated['content'],
            $request->user(),
            $validated['source'] ?? null
        );

        return response()->json([
            'data' => [
                'id' => $note->id,
                'content' => $note->content,
                'source' => $note->source,
                'is_verified' => $note->is_verified,
                'created_by' => $note->createdBy?->name,
                'created_at' => $note->created_at->toISOString(),
            ],
            'message' => 'Note added',
        ], 201);
    }

    public function analytics(int $id): JsonResponse
    {
        $competitor = Competitor::findOrFail($id);
        $analytics = $this->analyticsService->getCompetitorAnalytics($competitor);

        return response()->json(['data' => $analytics]);
    }

    public function comparison(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = array_map('intval', explode(',', $validated['ids']));
        $comparison = $this->analyticsService->compareCompetitors($ids);

        return response()->json(['data' => $comparison]);
    }

    // Deal-Competitor linking
    public function addToDeal(Request $request, int $dealId): JsonResponse
    {
        $validated = $request->validate([
            'competitor_id' => 'required|integer|exists:competitors,id',
            'is_primary' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $dealCompetitor = DealCompetitor::updateOrCreate(
            [
                'deal_id' => $dealId,
                'competitor_id' => $validated['competitor_id'],
            ],
            [
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($validated['is_primary'] ?? false) {
            $dealCompetitor->setPrimary();
        }

        return response()->json([
            'data' => $this->formatDealCompetitor($dealCompetitor),
            'message' => 'Competitor added to deal',
        ]);
    }

    public function removeFromDeal(int $dealId, int $competitorId): JsonResponse
    {
        DealCompetitor::where('deal_id', $dealId)
            ->where('competitor_id', $competitorId)
            ->delete();

        return response()->json(['message' => 'Competitor removed from deal']);
    }

    public function updateDealOutcome(Request $request, int $dealId, int $competitorId): JsonResponse
    {
        $validated = $request->validate([
            'outcome' => 'required|string|in:won,lost,unknown',
        ]);

        $dealCompetitor = DealCompetitor::where('deal_id', $dealId)
            ->where('competitor_id', $competitorId)
            ->firstOrFail();

        $dealCompetitor->setOutcome($validated['outcome']);

        return response()->json([
            'data' => $this->formatDealCompetitor($dealCompetitor),
            'message' => 'Outcome updated',
        ]);
    }

    public function getDealCompetitors(int $dealId): JsonResponse
    {
        $competitors = $this->analyticsService->getDealCompetitors($dealId);

        return response()->json(['data' => $competitors]);
    }

    private function formatCompetitor(Competitor $competitor): array
    {
        return [
            'id' => $competitor->id,
            'name' => $competitor->name,
            'website' => $competitor->website,
            'logo_url' => $competitor->logo_url,
            'description' => $competitor->description,
            'is_active' => $competitor->is_active,
            'win_rate' => $competitor->getWinRate(),
            'total_deals' => $competitor->getTotalDeals(),
            'last_updated_at' => $competitor->last_updated_at?->toISOString(),
        ];
    }

    private function formatCompetitorFull(Competitor $competitor): array
    {
        return array_merge($this->formatCompetitor($competitor), [
            'market_position' => $competitor->market_position,
            'pricing_info' => $competitor->pricing_info,
            'sections' => $competitor->sections->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->section_type,
                'type_label' => $s->getTypeLabel(),
                'content' => $s->content,
                'display_order' => $s->display_order,
            ]),
            'objection_count' => $competitor->objections->count(),
            'note_count' => $competitor->notes->count(),
            'won_deals' => $competitor->getWonDeals(),
            'lost_deals' => $competitor->getLostDeals(),
        ]);
    }

    private function formatObjection(CompetitorObjection $objection): array
    {
        return [
            'id' => $objection->id,
            'objection' => $objection->objection,
            'counter_script' => $objection->counter_script,
            'effectiveness_score' => $objection->effectiveness_score,
            'effectiveness_label' => $objection->getEffectivenessLabel(),
            'use_count' => $objection->use_count,
            'success_count' => $objection->success_count,
            'created_by' => $objection->createdBy?->name,
            'created_at' => $objection->created_at->toISOString(),
        ];
    }

    private function formatDealCompetitor(DealCompetitor $dc): array
    {
        return [
            'id' => $dc->id,
            'deal_id' => $dc->deal_id,
            'competitor_id' => $dc->competitor_id,
            'competitor_name' => $dc->competitor->name,
            'is_primary' => $dc->is_primary,
            'notes' => $dc->notes,
            'outcome' => $dc->outcome,
        ];
    }
}
