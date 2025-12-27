<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Portal\Entities\PortalAccessToken;
use App\Domain\Portal\Entities\PortalUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Portal\Entities\PortalAccessToken>
 */
class PortalAccessTokenFactory extends Factory
{
    protected $model = PortalAccessToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portal_user_id' => PortalUser::factory(),
            'name' => $this->faker->randomElement(['Web Session', 'Mobile App', 'API Access', 'Browser']),
            'token' => hash('sha256', bin2hex(random_bytes(32))),
            'abilities' => ['*'],
            'last_used_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
        ];
    }

    /**
     * Expired token.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Never expires.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Recently used.
     */
    public function recentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Never used.
     */
    public function neverUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => null,
        ]);
    }

    /**
     * Read only abilities.
     */
    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'abilities' => ['view:deals', 'view:invoices', 'view:quotes', 'view:documents'],
        ]);
    }

    /**
     * Full access abilities.
     */
    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'abilities' => ['*'],
        ]);
    }

    /**
     * Mobile app token.
     */
    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Mobile App',
        ]);
    }

    /**
     * API token.
     */
    public function api(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'API Access',
        ]);
    }
}
