<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DealRoom;
use App\Models\ModuleRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealRoom>
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

    /**
     * With members.
     */
    public function withMembers(int $count = 3): static
    {
        return $this->has(
            \App\Models\DealRoomMember::factory()->count($count),
            'members'
        );
    }

    /**
     * With documents.
     */
    public function withDocuments(int $count = 3): static
    {
        return $this->has(
            \App\Models\DealRoomDocument::factory()->count($count),
            'documents'
        );
    }

    /**
     * With action items.
     */
    public function withActionItems(int $count = 5): static
    {
        return $this->has(
            \App\Models\DealRoomActionItem::factory()->count($count),
            'actionItems'
        );
    }
}
