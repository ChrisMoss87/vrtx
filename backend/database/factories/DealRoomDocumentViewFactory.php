<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DealRoomDocument;
use App\Models\DealRoomDocumentView;
use App\Models\DealRoomMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealRoomDocumentView>
 */
class DealRoomDocumentViewFactory extends Factory
{
    protected $model = DealRoomDocumentView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => DealRoomDocument::factory(),
            'member_id' => DealRoomMember::factory(),
            'time_spent_seconds' => $this->faker->numberBetween(30, 600),
        ];
    }

    /**
     * Quick view (less than a minute).
     */
    public function quickView(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_spent_seconds' => $this->faker->numberBetween(5, 60),
        ]);
    }

    /**
     * Engaged view (several minutes).
     */
    public function engaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_spent_seconds' => $this->faker->numberBetween(180, 900),
        ]);
    }
}
