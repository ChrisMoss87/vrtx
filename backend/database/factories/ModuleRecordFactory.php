<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModuleRecord>
 */
class ModuleRecordFactory extends Factory
{
    protected $model = ModuleRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'data' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'phone' => $this->faker->phoneNumber(),
                'status' => $this->faker->randomElement(['active', 'inactive', 'pending']),
            ],
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Set specific data for the record.
     */
    public function withData(array $data): static
    {
        return $this->state(fn (array $attributes) => [
            'data' => $data,
        ]);
    }
}
