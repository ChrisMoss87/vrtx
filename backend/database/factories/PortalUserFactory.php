<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PortalUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalUser>
 */
class PortalUserFactory extends Factory
{
    protected $model = PortalUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'name' => $this->faker->name(),
            'phone' => $this->faker->optional(0.6)->phoneNumber(),
            'avatar' => null,
            'contact_id' => $this->faker->optional(0.7)->numberBetween(1, 200),
            'contact_module' => 'contacts',
            'account_id' => $this->faker->optional(0.5)->numberBetween(1, 100),
            'status' => PortalUser::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'verification_token' => null,
            'last_login_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'last_login_ip' => $this->faker->ipv4(),
            'preferences' => [
                'notifications' => [
                    'email' => true,
                    'deals' => true,
                    'invoices' => true,
                ],
                'language' => 'en',
            ],
            'timezone' => $this->faker->randomElement(['America/New_York', 'America/Los_Angeles', 'Europe/London', 'UTC']),
            'locale' => 'en',
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ];
    }

    /**
     * Pending user.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PortalUser::STATUS_PENDING,
            'email_verified_at' => null,
            'verification_token' => Str::random(64),
            'last_login_at' => null,
        ]);
    }

    /**
     * Active user.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PortalUser::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'verification_token' => null,
        ]);
    }

    /**
     * Suspended user.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PortalUser::STATUS_SUSPENDED,
        ]);
    }

    /**
     * With 2FA enabled.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt(Str::random(32)),
        ]);
    }

    /**
     * Never logged in.
     */
    public function neverLoggedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_login_at' => null,
            'last_login_ip' => null,
        ]);
    }

    /**
     * Recently active.
     */
    public function recentlyActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_login_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * For specific contact.
     */
    public function forContact(int $contactId, string $module = 'contacts'): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contactId,
            'contact_module' => $module,
        ]);
    }

    /**
     * For specific account.
     */
    public function forAccount(int $accountId): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $accountId,
        ]);
    }
}
