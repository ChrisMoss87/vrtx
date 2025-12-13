<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Export;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Export>
 */
class ExportFactory extends Factory
{
    protected $model = Export::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true) . ' Export',
            'format' => $this->faker->randomElement(['csv', 'xlsx']),
            'status' => 'pending',
            'fields' => [],
            'filters' => [],
            'sorting' => [],
            'total_records' => 0,
            'file_path' => null,
            'file_size' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Set status to pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Set status to processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Set status to completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'file_path' => 'exports/' . $this->faker->uuid() . '.csv',
            'file_size' => $this->faker->numberBetween(1024, 1048576),
            'total_records' => $this->faker->numberBetween(100, 1000),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Set status to failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    /**
     * Set format to CSV.
     */
    public function csv(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'csv',
        ]);
    }

    /**
     * Set format to Excel.
     */
    public function excel(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'xlsx',
        ]);
    }

    /**
     * Set fields to export.
     */
    public function withFields(array $fields): static
    {
        return $this->state(fn (array $attributes) => [
            'fields' => $fields,
        ]);
    }

    /**
     * Set filters for export.
     */
    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => $filters,
        ]);
    }
}
