<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\MergeLog;
use App\Infrastructure\Persistence\Eloquent\Models\Module;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleRecord;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\MergeLog>
 */
class MergeLogFactory extends Factory
{
    protected $model = MergeLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'surviving_record_id' => ModuleRecord::factory(),
            'merged_record_ids' => [$this->faker->numberBetween(1, 1000)],
            'field_selections' => [
                'email' => 'a',
                'phone' => 'b',
                'name' => 'a',
            ],
            'merged_data' => [
                [
                    'id' => $this->faker->numberBetween(1, 1000),
                    'data' => ['email' => $this->faker->email(), 'name' => $this->faker->name()],
                ],
            ],
            'merged_by' => User::factory(),
        ];
    }

    /**
     * Merge of two records.
     */
    public function twoRecords(): static
    {
        return $this->state(fn (array $attributes) => [
            'merged_record_ids' => [$this->faker->numberBetween(1, 1000)],
        ]);
    }

    /**
     * Merge of multiple records.
     */
    public function multipleRecords(int $count = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'merged_record_ids' => $this->faker->randomElements(
                range(1, 1000),
                $count
            ),
        ]);
    }
}
