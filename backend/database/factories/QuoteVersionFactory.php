<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Quote;
use App\Infrastructure\Persistence\Eloquent\Models\QuoteVersion;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\QuoteVersion>
 */
class QuoteVersionFactory extends Factory
{
    protected $model = QuoteVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'version' => $this->faker->numberBetween(1, 5),
            'snapshot' => [
                'title' => 'Quote Snapshot',
                'subtotal' => $this->faker->randomFloat(2, 5000, 100000),
                'total' => $this->faker->randomFloat(2, 5500, 110000),
                'line_items' => [
                    ['description' => 'Software License', 'quantity' => 5, 'unit_price' => 1000],
                    ['description' => 'Support Plan', 'quantity' => 1, 'unit_price' => 2500],
                ],
            ],
            'change_summary' => $this->faker->randomElement([
                'Updated pricing',
                'Added new line items',
                'Applied discount',
                'Revised terms',
            ]),
            'created_by' => User::factory(),
        ];
    }
}
