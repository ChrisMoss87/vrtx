<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalInvitation>
 */
class PortalInvitationFactory extends Factory
{
    protected $model = PortalInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'token' => bin2hex(random_bytes(32)),
            'contact_id' => $this->faker->optional(0.7)->numberBetween(1, 200),
            'account_id' => $this->faker->optional(0.5)->numberBetween(1, 100),
            'role' => $this->faker->randomElement(['viewer', 'collaborator', 'admin']),
            'invited_by' => User::factory(),
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+7 days'),
            'accepted_at' => null,
        ];
    }

    /**
     * Pending invitation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => null,
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+7 days'),
        ]);
    }

    /**
     * Accepted invitation.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Expired invitation.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => null,
            'expires_at' => $this->faker->dateTimeBetween('-14 days', '-1 day'),
        ]);
    }

    /**
     * Viewer role.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'viewer',
        ]);
    }

    /**
     * Collaborator role.
     */
    public function collaborator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'collaborator',
        ]);
    }

    /**
     * Admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * For specific contact.
     */
    public function forContact(int $contactId): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contactId,
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
