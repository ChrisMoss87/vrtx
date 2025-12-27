<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Competitor\Entities\Competitor;
use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Competitor\Entities\Competitor>
 */
class CompetitorFactory extends Factory
{
    protected $model = Competitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $competitors = [
            ['name' => 'Salesforce', 'position' => 'Market Leader'],
            ['name' => 'HubSpot', 'position' => 'Challenger'],
            ['name' => 'Pipedrive', 'position' => 'SMB Focus'],
            ['name' => 'Zoho CRM', 'position' => 'Value Player'],
            ['name' => 'Microsoft Dynamics', 'position' => 'Enterprise'],
            ['name' => 'Freshsales', 'position' => 'Emerging'],
            ['name' => 'Close.io', 'position' => 'Sales Focused'],
            ['name' => 'Copper', 'position' => 'Google Integration'],
        ];

        $competitor = $this->faker->randomElement($competitors);

        return [
            'name' => $competitor['name'],
            'website' => 'https://www.' . strtolower(str_replace(' ', '', $competitor['name'])) . '.com',
            'logo_url' => null,
            'description' => $this->faker->paragraph(),
            'market_position' => $competitor['position'],
            'pricing_info' => $this->faker->randomElement([
                'Starts at $25/user/month',
                'Custom enterprise pricing',
                'Free tier available, paid from $50/user/month',
                'Contact sales for pricing',
            ]),
            'last_updated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'last_updated_by' => User::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Active competitor.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive competitor.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * With battlecard sections.
     */
    public function withSections(int $count = 4): static
    {
        return $this->has(
            \App\Domain\Competitor\Entities\BattlecardSection::factory()->count($count),
            'sections'
        );
    }

    /**
     * With objections.
     */
    public function withObjections(int $count = 5): static
    {
        return $this->has(
            \App\Domain\Competitor\Entities\CompetitorObjection::factory()->count($count),
            'objections'
        );
    }
}
