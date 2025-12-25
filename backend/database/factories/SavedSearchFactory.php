<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modules = ['contacts', 'companies', 'deals', 'leads'];

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'query' => fake()->words(2, true),
            'type' => 'global',
            'module_api_name' => fake()->optional()->randomElement($modules),
            'filters' => null,
            'is_pinned' => false,
            'use_count' => fake()->numberBetween(0, 50),
            'last_used_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Mark as pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Set as global search.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'global',
            'module_api_name' => null,
        ]);
    }

    /**
     * Set as module-specific search.
     */
    public function forModuleApi(string $moduleApiName): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'module',
            'module_api_name' => $moduleApiName,
        ]);
    }

    /**
     * Add filters.
     */
    public function withFilters(array $filters = null): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => $filters ?? [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ],
        ]);
    }

    /**
     * Mark as frequently used.
     */
    public function frequentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'use_count' => fake()->numberBetween(20, 100),
            'last_used_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Mark as never used.
     */
    public function neverUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'use_count' => 0,
            'last_used_at' => null,
        ]);
    }

    /**
     * Attach to specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
