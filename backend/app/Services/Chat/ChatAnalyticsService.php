<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\DB;

class ChatAnalyticsService
{
    public function getOverview(?int $widgetId = null, ?string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);

        $query = DB::table('chat_conversations');
        if ($widgetId) {
            $query->where('widget_id', $widgetId);
        }

        $currentPeriod = (clone $query)
            ->whereBetween('created_at', [$dateRange['current']['start'], $dateRange['current']['end']])
            ->get();

        $previousPeriod = (clone $query)
            ->whereBetween('created_at', [$dateRange['previous']['start'], $dateRange['previous']['end']])
            ->get();

        $totalConversations = $currentPeriod->count();
        $previousTotal = $previousPeriod->count();

        $closedConversations = $currentPeriod->where('status', ChatConversation::STATUS_CLOSED);
        $avgResponseTime = $closedConversations->avg(fn($c) => $c->getFirstResponseTimeMinutes()) ?? 0;
        $avgResolutionTime = $closedConversations->avg(fn($c) => $c->getResolutionTimeMinutes()) ?? 0;
        $avgRating = $closedConversations->whereNotNull('rating')->avg('rating');

        return [
            'total_conversations' => $totalConversations,
            'change_percent' => $previousTotal > 0
                ? round((($totalConversations - $previousTotal) / $previousTotal) * 100, 1)
                : null,
            'open_conversations' => $currentPeriod->where('status', ChatConversation::STATUS_OPEN)->count(),
            'closed_conversations' => $closedConversations->count(),
            'avg_first_response_minutes' => round($avgResponseTime, 1),
            'avg_resolution_minutes' => round($avgResolutionTime, 1),
            'avg_rating' => $avgRating ? round($avgRating, 1) : null,
            'total_messages' => ChatMessage::whereIn('conversation_id', $currentPeriod->pluck('id'))->count(),
            'period' => $period,
        ];
    }

    public function getAgentPerformance(?string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);

        $agents = ChatAgentStatus::with('user')->get();
        $performance = [];

        foreach ($agents as $agent) {
            $conversations = DB::table('chat_conversations')->where('assigned_to', $agent->user_id)
                ->whereBetween('created_at', [$dateRange['current']['start'], $dateRange['current']['end']])
                ->get();

            $closed = $conversations->where('status', ChatConversation::STATUS_CLOSED);

            $performance[] = [
                'user_id' => $agent->user_id,
                'user_name' => $agent->user->name,
                'status' => $agent->status,
                'total_conversations' => $conversations->count(),
                'closed_conversations' => $closed->count(),
                'avg_first_response_minutes' => round($closed->avg(fn($c) => $c->getFirstResponseTimeMinutes()) ?? 0, 1),
                'avg_resolution_minutes' => round($closed->avg(fn($c) => $c->getResolutionTimeMinutes()) ?? 0, 1),
                'avg_rating' => $closed->whereNotNull('rating')->count() > 0
                    ? round($closed->whereNotNull('rating')->avg('rating'), 1)
                    : null,
                'total_messages' => ChatMessage::whereIn('conversation_id', $conversations->pluck('id'))
                    ->where('sender_type', ChatMessage::SENDER_AGENT)
                    ->count(),
            ];
        }

        // Sort by total conversations descending
        usort($performance, fn($a, $b) => $b['total_conversations'] - $a['total_conversations']);

        return $performance;
    }

    public function getConversationsByHour(?int $widgetId = null, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $query = ChatConversation::selectRaw('EXTRACT(HOUR FROM created_at) as hour, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('hour')
            ->orderBy('hour');

        if ($widgetId) {
            $query->where('widget_id', $widgetId);
        }

        $results = $query->get()->keyBy('hour');

        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $hourly[] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'count' => (int)($results[$h]->count ?? 0),
            ];
        }

        return $hourly;
    }

    public function getRatingDistribution(?int $widgetId = null, ?string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);

        $query = ChatConversation::selectRaw('rating, COUNT(*) as count')
            ->whereNotNull('rating')
            ->whereBetween('created_at', [$dateRange['current']['start'], $dateRange['current']['end']])
            ->groupBy('rating')
            ->orderBy('rating');

        if ($widgetId) {
            $query->where('widget_id', $widgetId);
        }

        $results = $query->get();

        $distribution = [];
        for ($r = 1; $r <= 5; $r++) {
            $distribution[] = [
                'rating' => $r,
                'count' => $results->firstWhere('rating', $r)?->count ?? 0,
            ];
        }

        return $distribution;
    }

    public function getVisitorInsights(?int $widgetId = null, ?string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);

        $query = ChatConversation::with('visitor')
            ->whereBetween('created_at', [$dateRange['current']['start'], $dateRange['current']['end']]);

        if ($widgetId) {
            $query->where('widget_id', $widgetId);
        }

        $conversations = $query->get();

        // Country breakdown
        $countries = $conversations->groupBy(fn($c) => $c->visitor->country ?? 'Unknown')
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->take(10)
            ->toArray();

        // Returning vs new visitors
        $visitorIds = $conversations->pluck('visitor_id')->unique();
        $returningVisitors = ChatConversation::whereIn('visitor_id', $visitorIds)
            ->where('created_at', '<', $dateRange['current']['start'])
            ->distinct('visitor_id')
            ->count('visitor_id');

        return [
            'total_visitors' => $visitorIds->count(),
            'new_visitors' => $visitorIds->count() - $returningVisitors,
            'returning_visitors' => $returningVisitors,
            'countries' => $countries,
        ];
    }

    private function getDateRange(string $period): array
    {
        $current = match ($period) {
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'quarter' => [
                'start' => now()->firstOfQuarter(),
                'end' => now()->lastOfQuarter(),
            ],
            default => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
        };

        $previous = match ($period) {
            'week' => [
                'start' => now()->subWeek()->startOfWeek(),
                'end' => now()->subWeek()->endOfWeek(),
            ],
            'quarter' => [
                'start' => now()->subQuarter()->firstOfQuarter(),
                'end' => now()->subQuarter()->lastOfQuarter(),
            ],
            default => [
                'start' => now()->subMonth()->startOfMonth(),
                'end' => now()->subMonth()->endOfMonth(),
            ],
        };

        return ['current' => $current, 'previous' => $previous];
    }
}
