<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Competitor;
use App\Models\CompetitorObjection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompetitorObjection>
 */
class CompetitorObjectionFactory extends Factory
{
    protected $model = CompetitorObjection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $objections = [
            [
                'objection' => 'They\'re the market leader',
                'counter' => 'While they\'re established, our solution offers better ROI because of our modern architecture and faster implementation. Many customers have switched to us for 40% lower TCO.',
            ],
            [
                'objection' => 'They have more features',
                'counter' => 'Having more features often means more complexity. We focus on the features that drive results. Our customers report higher adoption rates because of our intuitive design.',
            ],
            [
                'objection' => 'We already use their ecosystem',
                'counter' => 'We integrate seamlessly with all major platforms. Migration is typically completed in 2 weeks with our dedicated onboarding team.',
            ],
            [
                'objection' => 'Their pricing is lower',
                'counter' => 'When you factor in implementation costs, training, and add-on fees, our total cost is actually 30% lower over 3 years. Let me show you a TCO comparison.',
            ],
            [
                'objection' => 'They have better brand recognition',
                'counter' => 'Recognition doesn\'t equal results. Our customers see 50% faster time-to-value. I can connect you with references in your industry.',
            ],
        ];

        $obj = $this->faker->randomElement($objections);
        $useCount = $this->faker->numberBetween(5, 50);
        $successCount = $this->faker->numberBetween(2, $useCount);

        return [
            'competitor_id' => Competitor::factory(),
            'objection' => $obj['objection'],
            'counter_script' => $obj['counter'],
            'effectiveness_score' => round(($successCount / $useCount) * 100, 2),
            'use_count' => $useCount,
            'success_count' => $successCount,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Highly effective objection handler.
     */
    public function highlyEffective(): static
    {
        return $this->state(fn (array $attributes) => [
            'effectiveness_score' => $this->faker->randomFloat(2, 75, 95),
            'use_count' => $this->faker->numberBetween(20, 50),
            'success_count' => $this->faker->numberBetween(16, 45),
        ]);
    }

    /**
     * New objection with no data.
     */
    public function new(): static
    {
        return $this->state(fn (array $attributes) => [
            'effectiveness_score' => null,
            'use_count' => 0,
            'success_count' => 0,
        ]);
    }
}
