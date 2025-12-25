<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailAccount>
 */
class EmailAccountFactory extends Factory
{
    protected $model = EmailAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true) . ' Email',
            'email_address' => $this->faker->unique()->safeEmail(),
            'provider' => $this->faker->randomElement(['gmail', 'outlook', 'imap']),
            'is_active' => true,
            'is_default' => false,
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'username' => $this->faker->userName(),
            'password' => encrypt('password'),
            'last_synced_at' => null,
            'sync_status' => 'idle',
            'settings' => [],
        ];
    }

    /**
     * Mark account as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Mark account as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Mark as default account.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Create a Gmail account.
     */
    public function gmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'gmail',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
        ]);
    }

    /**
     * Create an Outlook account.
     */
    public function outlook(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'outlook',
            'imap_host' => 'outlook.office365.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.office365.com',
            'smtp_port' => 587,
        ]);
    }

    /**
     * Set as synced.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ]);
    }

    /**
     * Set sync as failed.
     */
    public function syncFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'failed',
        ]);
    }
}
