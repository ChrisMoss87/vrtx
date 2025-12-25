<?php

namespace App\Services\Renewal;

use Illuminate\Support\Facades\DB;

class HealthScoreService
{
    /**
     * Calculate health score for a customer
     */
    public function calculateHealthScore(string $module, int $recordId): CustomerHealthScore
    {
        $healthScore = CustomerHealthScore::firstOrNew([
            'related_module' => $module,
            'related_id' => $recordId,
        ]);

        // Calculate component scores
        $healthScore->engagement_score = $this->calculateEngagementScore($module, $recordId);
        $healthScore->support_score = $this->calculateSupportScore($module, $recordId);
        $healthScore->product_usage_score = $this->calculateProductUsageScore($module, $recordId);
        $healthScore->payment_score = $this->calculatePaymentScore($module, $recordId);
        $healthScore->relationship_score = $this->calculateRelationshipScore($module, $recordId);

        // Calculate overall score and status
        $healthScore->overall_score = $healthScore->calculateOverallScore();
        $healthScore->health_status = $healthScore->calculateHealthStatus();

        // Build score breakdown
        $healthScore->score_breakdown = $this->buildScoreBreakdown($healthScore);

        // Identify risk factors
        $healthScore->risk_factors = $this->identifyRiskFactors($healthScore, $module, $recordId);

        $healthScore->calculated_at = now();
        $healthScore->save();

        // Record history
        $healthScore->recordHistory();

        return $healthScore;
    }

    /**
     * Calculate engagement score based on activities
     */
    protected function calculateEngagementScore(string $module, int $recordId): int
    {
        // Get recent activities (last 90 days)
        $activityCount = DB::table('activities')->where('related_module', $module)
            ->where('related_id', $recordId)
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $lastActivityDays = DB::table('activities')->where('related_module', $module)
            ->where('related_id', $recordId)
            ->max('created_at');

        $daysSinceLastActivity = $lastActivityDays
            ? now()->diffInDays($lastActivityDays)
            : 90;

        // Score based on activity frequency
        $frequencyScore = min(50, $activityCount * 5);

        // Score based on recency
        $recencyScore = max(0, 50 - ($daysSinceLastActivity * 2));

        return min(100, $frequencyScore + $recencyScore);
    }

    /**
     * Calculate support score based on ticket history
     */
    protected function calculateSupportScore(string $module, int $recordId): int
    {
        // Check if SupportTicket model exists
        if (!class_exists(SupportTicket::class)) {
            return 70; // Default neutral score if no support module
        }

        $tickets = DB::table('support_tickets')->where('related_module', $module)
            ->where('related_id', $recordId)
            ->where('created_at', '>=', now()->subDays(180))
            ->get();

        if ($tickets->isEmpty()) {
            return 80; // No tickets is good
        }

        $totalTickets = $tickets->count();
        $resolvedTickets = $tickets->where('status', 'resolved')->count();
        $criticalTickets = $tickets->whereIn('priority', ['high', 'critical'])->count();

        // Resolution rate score
        $resolutionRate = $totalTickets > 0 ? ($resolvedTickets / $totalTickets) * 100 : 100;
        $resolutionScore = min(50, $resolutionRate * 0.5);

        // Penalty for many critical tickets
        $criticalPenalty = min(30, $criticalTickets * 10);

        // Penalty for too many tickets
        $volumePenalty = min(20, max(0, ($totalTickets - 5) * 4));

        return max(0, min(100, $resolutionScore + 50 - $criticalPenalty - $volumePenalty));
    }

    /**
     * Calculate product usage score
     * This is a placeholder - in a real implementation, this would integrate with
     * product analytics or usage tracking
     */
    protected function calculateProductUsageScore(string $module, int $recordId): int
    {
        // For now, return a default score
        // In production, this would pull from product analytics
        return 70;
    }

    /**
     * Calculate payment score based on contract and invoice history
     */
    protected function calculatePaymentScore(string $module, int $recordId): int
    {
        $contracts = Contract::forModule($module, $recordId)->get();

        if ($contracts->isEmpty()) {
            return 70; // Neutral score if no contracts
        }

        // Check for late payments (this would integrate with invoice tracking)
        $activeContracts = $contracts->where('status', 'active')->count();
        $expiredContracts = $contracts->where('status', 'expired')->count();

        // Contract renewal history
        $renewedContracts = $contracts->where('renewal_status', 'renewed')->count();
        $lostContracts = $contracts->where('renewal_status', 'lost')->count();

        // Calculate renewal rate
        $totalRenewable = $renewedContracts + $lostContracts;
        $renewalRate = $totalRenewable > 0 ? ($renewedContracts / $totalRenewable) * 100 : 100;

        // Score based on renewal history
        $renewalScore = min(60, $renewalRate * 0.6);

        // Score based on active contracts
        $activeScore = min(40, $activeContracts * 20);

        return min(100, $renewalScore + $activeScore);
    }

    /**
     * Calculate relationship score based on stakeholder engagement
     */
    protected function calculateRelationshipScore(string $module, int $recordId): int
    {
        // Count unique contacts/stakeholders engaged
        $contactCount = DB::table('activities')->where('related_module', $module)
            ->where('related_id', $recordId)
            ->whereNotNull('contact_id')
            ->distinct('contact_id')
            ->count('contact_id');

        // Score based on contact diversity
        $contactScore = min(50, $contactCount * 10);

        // Check for executive engagement (meetings, calls)
        $executiveEngagement = DB::table('activities')->where('related_module', $module)
            ->where('related_id', $recordId)
            ->whereIn('type', ['meeting', 'call'])
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $engagementScore = min(50, $executiveEngagement * 10);

        return min(100, $contactScore + $engagementScore);
    }

    /**
     * Build score breakdown for transparency
     */
    protected function buildScoreBreakdown(CustomerHealthScore $score): array
    {
        return [
            'engagement' => [
                'score' => $score->engagement_score,
                'weight' => 0.25,
                'weighted_score' => $score->engagement_score * 0.25,
            ],
            'support' => [
                'score' => $score->support_score,
                'weight' => 0.20,
                'weighted_score' => $score->support_score * 0.20,
            ],
            'product_usage' => [
                'score' => $score->product_usage_score,
                'weight' => 0.25,
                'weighted_score' => $score->product_usage_score * 0.25,
            ],
            'payment' => [
                'score' => $score->payment_score,
                'weight' => 0.15,
                'weighted_score' => $score->payment_score * 0.15,
            ],
            'relationship' => [
                'score' => $score->relationship_score,
                'weight' => 0.15,
                'weighted_score' => $score->relationship_score * 0.15,
            ],
        ];
    }

    /**
     * Identify risk factors for a customer
     */
    protected function identifyRiskFactors(CustomerHealthScore $score, string $module, int $recordId): array
    {
        $riskFactors = [];

        // Check engagement
        if ($score->engagement_score < 40) {
            $riskFactors[] = [
                'factor' => 'low_engagement',
                'severity' => 'high',
                'description' => 'Customer engagement has been low in the past 90 days',
                'recommendation' => 'Schedule a check-in call to re-engage the customer',
            ];
        }

        // Check support
        if ($score->support_score < 50) {
            $riskFactors[] = [
                'factor' => 'support_issues',
                'severity' => 'medium',
                'description' => 'Customer has had support issues or unresolved tickets',
                'recommendation' => 'Review open tickets and ensure timely resolution',
            ];
        }

        // Check product usage
        if ($score->product_usage_score < 40) {
            $riskFactors[] = [
                'factor' => 'low_product_usage',
                'severity' => 'high',
                'description' => 'Product usage has declined',
                'recommendation' => 'Offer training or feature walkthroughs to improve adoption',
            ];
        }

        // Check contracts expiring soon
        $expiringContracts = Contract::forModule($module, $recordId)
            ->expiring(60)
            ->count();

        if ($expiringContracts > 0) {
            $riskFactors[] = [
                'factor' => 'upcoming_renewal',
                'severity' => 'medium',
                'description' => "Customer has {$expiringContracts} contract(s) expiring in the next 60 days",
                'recommendation' => 'Initiate renewal discussion proactively',
            ];
        }

        // Check relationship depth
        if ($score->relationship_score < 40) {
            $riskFactors[] = [
                'factor' => 'shallow_relationship',
                'severity' => 'low',
                'description' => 'Limited stakeholder engagement',
                'recommendation' => 'Expand relationships with multiple contacts at the account',
            ];
        }

        return $riskFactors;
    }

    /**
     * Get health score summary statistics
     */
    public function getHealthSummary(): array
    {
        $scores = DB::table('customer_health_scores')->get();

        return [
            'total_customers' => $scores->count(),
            'healthy' => $scores->where('health_status', 'healthy')->count(),
            'at_risk' => $scores->where('health_status', 'at_risk')->count(),
            'critical' => $scores->where('health_status', 'critical')->count(),
            'average_score' => round($scores->avg('overall_score') ?? 0, 1),
        ];
    }

    /**
     * Get customers that need attention
     */
    public function getAtRiskCustomers(): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerHealthScore::with(['history' => fn($q) => $q->latest()->limit(5)])
            ->whereIn('health_status', ['at_risk', 'critical'])
            ->orderBy('overall_score')
            ->get();
    }

    /**
     * Bulk recalculate health scores
     */
    public function recalculateAllScores(): int
    {
        // Get all accounts/customers with contracts
        $customers = Contract::select('related_module', 'related_id')
            ->distinct()
            ->get();

        $count = 0;
        foreach ($customers as $customer) {
            $this->calculateHealthScore($customer->related_module, $customer->related_id);
            $count++;
        }

        return $count;
    }
}
