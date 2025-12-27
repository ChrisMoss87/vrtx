<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Competitor\Entities\Competitor;
use App\Domain\Competitor\Entities\CompetitorNote;
use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Competitor\Entities\CompetitorNote>
 */
class CompetitorNoteFactory extends Factory
{
    protected $model = CompetitorNote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competitor_id' => Competitor::factory(),
            'content' => $this->faker->randomElement([
                'Heard from customer that they raised prices 20% this quarter.',
                'New feature launched: AI-powered forecasting. Still in beta.',
                'Won a deal against them by emphasizing our support response times.',
                'Their implementation team is backed up - 6 week wait for enterprise.',
                'They acquired a startup in the analytics space. Watch for new features.',
                'Customer feedback: their mobile app is buggy.',
                'They\'re offering aggressive discounts to win back churned customers.',
            ]),
            'created_by' => User::factory(),
        ];
    }
}
