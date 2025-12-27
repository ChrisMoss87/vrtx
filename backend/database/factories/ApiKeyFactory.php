<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ApiKey\Entities\ApiKey;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\ApiKey\Entities\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true) . ' API Key',
            'key' => 'vrtx_' . Str::random(32),
            'permissions' => ['read', 'write'],
            'rate_limit' => 1000,
            'is_active' => true,
            'last_used_at' => null,
            'expires_at' => null,
            'metadata' => [],
        ];
    }

    /**
     * Mark API key as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Mark API key as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set as read-only.
     */
    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => ['read'],
        ]);
    }

    /**
     * Set with full permissions.
     */
    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => ['read', 'write', 'delete'],
        ]);
    }

    /**
     * Set expiration date.
     */
    public function expiresAt(\DateTime $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $date,
        ]);
    }

    /**
     * Set as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Set rate limit.
     */
    public function rateLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_limit' => $limit,
        ]);
    }

    /**
     * Mark as recently used.
     */
    public function recentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => now(),
        ]);
    }
}
