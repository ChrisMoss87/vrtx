<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomerHealthScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerHealthScore>
 */
class CustomerHealthScoreFactory extends Factory
{
    protected $model = CustomerHealthScore::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $engagementScore = $this->faker->numberBetween(20, 100);
        $supportScore = $this->faker->numberBetween(20, 100);
        $productUsageScore = $this->faker->numberBetween(20, 100);
        $paymentScore = $this->faker->numberBetween(50, 100);
        $relationshipScore = $this->faker->numberBetween(30, 100);

        // Calculate weighted overall score
        $overallScore = (int) round(
            $engagementScore * 0.25 +
            $supportScore * 0.20 +
            $productUsageScore * 0.25 +
            $paymentScore * 0.15 +
            $relationshipScore * 0.15
        );

        return [
            'related_module' => 'accounts',
            'related_id' => $this->faker->numberBetween(1, 100),
            'overall_score' => $overallScore,
            'engagement_score' => $engagementScore,
            'support_score' => $supportScore,
            'product_usage_score' => $productUsageScore,
            'payment_score' => $paymentScore,
            'relationship_score' => $relationshipScore,
            'health_status' => $overallScore >= 70 ? 'healthy' : ($overallScore >= 40 ? 'at_risk' : 'critical'),
            'score_breakdown' => [
                'engagement' => [
                    'email_opens' => $this->faker->numberBetween(5, 50),
                    'logins_last_30d' => $this->faker->numberBetween(0, 30),
                    'feature_adoption' => $this->faker->numberBetween(20, 100),
                ],
                'support' => [
                    'open_tickets' => $this->faker->numberBetween(0, 5),
                    'avg_resolution_time' => $this->faker->numberBetween(1, 72),
                    'satisfaction_rating' => $this->faker->randomFloat(1, 3, 5),
                ],
            ],
            'risk_factors' => $overallScore < 70 ? [
                'Low product usage in last 30 days',
                'Multiple support tickets unresolved',
            ] : [],
            'notes' => $this->faker->optional(0.3)->sentence(),
            'calculated_at' => now(),
        ];
    }

    /**
     * Healthy customer.
     */
    public function healthy(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->numberBetween(70, 100),
            'engagement_score' => $this->faker->numberBetween(70, 100),
            'support_score' => $this->faker->numberBetween(80, 100),
            'product_usage_score' => $this->faker->numberBetween(75, 100),
            'payment_score' => $this->faker->numberBetween(90, 100),
            'relationship_score' => $this->faker->numberBetween(70, 100),
            'health_status' => 'healthy',
            'risk_factors' => [],
        ]);
    }

    /**
     * At-risk customer.
     */
    public function atRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->numberBetween(40, 69),
            'engagement_score' => $this->faker->numberBetween(30, 60),
            'support_score' => $this->faker->numberBetween(40, 70),
            'product_usage_score' => $this->faker->numberBetween(30, 60),
            'payment_score' => $this->faker->numberBetween(60, 80),
            'relationship_score' => $this->faker->numberBetween(40, 60),
            'health_status' => 'at_risk',
            'risk_factors' => [
                'Declining product usage',
                'Delayed response to communications',
            ],
        ]);
    }

    /**
     * Critical customer.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->numberBetween(10, 39),
            'engagement_score' => $this->faker->numberBetween(10, 35),
            'support_score' => $this->faker->numberBetween(20, 40),
            'product_usage_score' => $this->faker->numberBetween(5, 30),
            'payment_score' => $this->faker->numberBetween(30, 60),
            'relationship_score' => $this->faker->numberBetween(20, 40),
            'health_status' => 'critical',
            'risk_factors' => [
                'No login in 30+ days',
                'Multiple escalated support tickets',
                'Missed payment',
                'Unresponsive to outreach',
            ],
        ]);
    }

    /**
     * For an account.
     */
    public function forAccount(int $accountId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'related_module' => 'accounts',
            'related_id' => $accountId ?? $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * For a deal.
     */
    public function forDeal(int $dealId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'related_module' => 'deals',
            'related_id' => $dealId ?? $this->faker->numberBetween(1, 100),
        ]);
    }
}
