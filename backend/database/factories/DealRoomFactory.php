<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\DealRoom\Entities\DealRoom;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\DealRoom\Entities\DealRoom>
 */
class DealRoomFactory extends Factory
{
    protected $model = DealRoom::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company() . ' - ' . $this->faker->randomElement([
            'Enterprise Agreement',
            'Partnership Proposal',
            'Q4 Contract',
            'Implementation Project',
            'Expansion Deal',
        ]);

        return [
            'deal_record_id' => ModuleRecord::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement([
                DealRoom::STATUS_ACTIVE,
                DealRoom::STATUS_WON,
                DealRoom::STATUS_LOST,
            ]),
            'branding' => [
                'primary_color' => $this->faker->hexColor(),
                'logo_url' => null,
                'custom_css' => null,
            ],
            'settings' => [
                'allow_external_messaging' => true,
                'require_nda' => false,
                'show_pricing' => true,
            ],
            'created_by' => User::factory(),
        ];
    }

    /**
     * Active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealRoom::STATUS_ACTIVE,
        ]);
    }

    /**
     * Won status.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealRoom::STATUS_WON,
        ]);
    }

    /**
     * Lost status.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealRoom::STATUS_LOST,
        ]);
    }
}
