<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Modules\Entities\ModuleRecord>
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
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
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
