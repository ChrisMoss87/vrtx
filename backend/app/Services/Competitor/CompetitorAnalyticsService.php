<?php

namespace App\Services\Competitor;

use App\Models\Competitor;
use App\Models\DealCompetitor;
use App\Models\ModuleRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitorAnalyticsService
{
    public function getCompetitorAnalytics(Competitor $competitor): array
    {
        $dealCompetitors = DealCompetitor::where('competitor_id', $competitor->id)
            ->whereIn('outcome', ['won', 'lost'])
            ->get();

        $total = $dealCompetitors->count();
        $won = $dealCompetitors->where('outcome', 'won')->count();
        $lost = $dealCompetitors->where('outcome', 'lost')->count();

        // Get deal amounts if possible
        $dealIds = $dealCompetitors->pluck('deal_id')->toArray();
        $dealAmounts = $this->getDealAmounts($dealIds);

        $wonAmount = $dealAmounts->filter(fn ($d) =>
            $dealCompetitors->where('deal_id', $d['id'])->first()?->outcome === 'won'
        )->sum('amount');

        $lostAmount = $dealAmounts->filter(fn ($d) =>
            $dealCompetitors->where('deal_id', $d['id'])->first()?->outcome === 'lost'
        )->sum('amount');

        // Win rate by deal size
        $byDealSize = $this->getWinRateByDealSize($dealCompetitors, $dealAmounts);

        // Top objections
        $topObjections = $competitor->objections()
            ->where('use_count', '>=', 3)
            ->orderByDesc('effectiveness_score')
            ->take(5)
            ->get()
            ->map(fn ($o) => [
                'objection' => $o->objection,
                'effectiveness' => $o->effectiveness_score,
                'uses' => $o->use_count,
            ]);

        // Trend data (by month)
        $monthlyTrend = $this->getMonthlyTrend($competitor->id);

        return [
            'summary' => [
                'total_deals' => $total,
                'won' => $won,
                'lost' => $lost,
                'win_rate' => $total > 0 ? round(($won / $total) * 100, 1) : null,
                'won_amount' => $wonAmount,
                'lost_amount' => $lostAmount,
            ],
            'by_deal_size' => $byDealSize,
            'top_objections' => $topObjections,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    public function compareCompetitors(array $competitorIds): array
    {
        $competitors = Competitor::whereIn('id', $competitorIds)
            ->with(['sections', 'objections'])
            ->get();

        $comparison = [];

        foreach ($competitors as $competitor) {
            $dealCompetitors = DealCompetitor::where('competitor_id', $competitor->id)
                ->whereIn('outcome', ['won', 'lost'])
                ->get();

            $total = $dealCompetitors->count();
            $won = $dealCompetitors->where('outcome', 'won')->count();

            $comparison[] = [
                'id' => $competitor->id,
                'name' => $competitor->name,
                'logo_url' => $competitor->logo_url,
                'win_rate' => $total > 0 ? round(($won / $total) * 100, 1) : null,
                'total_deals' => $total,
                'won_deals' => $won,
                'strengths' => $competitor->getSectionByType('strengths')?->getContentLines() ?? [],
                'weaknesses' => $competitor->getSectionByType('weaknesses')?->getContentLines() ?? [],
                'our_advantages' => $competitor->getSectionByType('our_advantages')?->getContentLines() ?? [],
                'top_objection' => $competitor->objections->first()?->only(['objection', 'counter_script', 'effectiveness_score']),
            ];
        }

        return $comparison;
    }

    public function getDealCompetitors(int $dealId): Collection
    {
        return DealCompetitor::with('competitor')
            ->forDeal($dealId)
            ->get()
            ->map(fn ($dc) => [
                'id' => $dc->id,
                'competitor_id' => $dc->competitor_id,
                'competitor_name' => $dc->competitor->name,
                'competitor_logo' => $dc->competitor->logo_url,
                'is_primary' => $dc->is_primary,
                'notes' => $dc->notes,
                'outcome' => $dc->outcome,
                'win_rate' => $dc->competitor->getWinRate(),
            ]);
    }

    private function getDealAmounts(array $dealIds): Collection
    {
        if (empty($dealIds)) {
            return collect();
        }

        // Try to get deal amounts from the deals module
        return ModuleRecord::whereIn('id', $dealIds)
            ->get(['id', 'data'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'amount' => $r->data['amount'] ?? $r->data['deal_value'] ?? 0,
            ]);
    }

    private function getWinRateByDealSize(Collection $dealCompetitors, Collection $dealAmounts): array
    {
        $brackets = [
            'small' => ['label' => '< $10k', 'min' => 0, 'max' => 10000, 'won' => 0, 'total' => 0],
            'medium' => ['label' => '$10k - $50k', 'min' => 10000, 'max' => 50000, 'won' => 0, 'total' => 0],
            'large' => ['label' => '> $50k', 'min' => 50000, 'max' => PHP_INT_MAX, 'won' => 0, 'total' => 0],
        ];

        foreach ($dealCompetitors as $dc) {
            $deal = $dealAmounts->firstWhere('id', $dc->deal_id);
            $amount = $deal['amount'] ?? 0;

            foreach ($brackets as $key => &$bracket) {
                if ($amount >= $bracket['min'] && $amount < $bracket['max']) {
                    $bracket['total']++;
                    if ($dc->outcome === 'won') {
                        $bracket['won']++;
                    }
                    break;
                }
            }
        }

        return array_map(fn ($b) => [
            'label' => $b['label'],
            'won' => $b['won'],
            'total' => $b['total'],
            'win_rate' => $b['total'] > 0 ? round(($b['won'] / $b['total']) * 100, 1) : null,
        ], array_values($brackets));
    }

    private function getMonthlyTrend(int $competitorId): array
    {
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth();

        $results = DealCompetitor::where('competitor_id', $competitorId)
            ->whereIn('outcome', ['won', 'lost'])
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("
                DATE_TRUNC('month', created_at) as month,
                outcome,
                COUNT(*) as count
            ")
            ->groupBy(DB::raw("DATE_TRUNC('month', created_at)"), 'outcome')
            ->orderBy('month')
            ->get();

        $trend = [];
        $current = clone $sixMonthsAgo;

        for ($i = 0; $i < 6; $i++) {
            $monthKey = $current->format('Y-m');
            $monthLabel = $current->format('M Y');

            $won = $results->first(fn ($r) =>
                $r->month && date('Y-m', strtotime($r->month)) === $monthKey && $r->outcome === 'won'
            )?->count ?? 0;

            $lost = $results->first(fn ($r) =>
                $r->month && date('Y-m', strtotime($r->month)) === $monthKey && $r->outcome === 'lost'
            )?->count ?? 0;

            $total = $won + $lost;

            $trend[] = [
                'month' => $monthLabel,
                'won' => $won,
                'lost' => $lost,
                'total' => $total,
                'win_rate' => $total > 0 ? round(($won / $total) * 100, 1) : null,
            ];

            $current->addMonth();
        }

        return $trend;
    }
}
