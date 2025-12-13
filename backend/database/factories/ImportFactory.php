<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Import;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

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
            'file_name' => $this->faker->word() . '.csv',
            'file_path' => 'imports/' . $this->faker->uuid() . '.csv',
            'file_type' => 'csv',
            'file_size' => $this->faker->numberBetween(1024, 1048576),
            'status' => 'pending',
            'total_rows' => $this->faker->numberBetween(10, 1000),
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'field_mapping' => [],
            'duplicate_handling' => 'skip',
            'duplicate_check_field' => null,
            'settings' => [],
            'error_log' => [],
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Set status to pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_rows' => 0,
        ]);
    }

    /**
     * Set status to mapping.
     */
    public function mapping(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'mapping',
        ]);
    }

    /**
     * Set status to validating.
     */
    public function validating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'validating',
        ]);
    }

    /**
     * Set status to in progress.
     */
    public function inProgress(): static
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
        $totalRows = $this->faker->numberBetween(100, 1000);
        $failedRows = $this->faker->numberBetween(0, 10);

        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'total_rows' => $totalRows,
            'processed_rows' => $totalRows,
            'successful_rows' => $totalRows - $failedRows,
            'failed_rows' => $failedRows,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    /**
     * Set status to failed.
     */
    public function failed(string $error = 'Import failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'error_log' => [['message' => $error]],
        ]);
    }

    /**
     * Set duplicate handling mode.
     */
    public function duplicateHandling(string $mode = 'skip', ?string $field = null): static
    {
        return $this->state(fn (array $attributes) => [
            'duplicate_handling' => $mode,
            'duplicate_check_field' => $field,
        ]);
    }

    /**
     * Set field mapping.
     */
    public function withMapping(array $mapping): static
    {
        return $this->state(fn (array $attributes) => [
            'field_mapping' => $mapping,
        ]);
    }

    /**
     * Create an Excel import.
     */
    public function excel(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_name' => $this->faker->word() . '.xlsx',
            'file_path' => 'imports/' . $this->faker->uuid() . '.xlsx',
            'file_type' => 'xlsx',
        ]);
    }
}
