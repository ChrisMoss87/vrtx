<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IncomingWebhook;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomingWebhook>
 */
class IncomingWebhookFactory extends Factory
{
    protected $model = IncomingWebhook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true) . ' Webhook',
            'description' => fake()->optional()->sentence(),
            'token' => IncomingWebhook::generateToken(),
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'field_mapping' => [
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
            ],
            'is_active' => true,
            'action' => IncomingWebhook::ACTION_CREATE,
            'upsert_field' => null,
            'last_received_at' => null,
            'received_count' => 0,
        ];
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set action to create.
     */
    public function createAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => IncomingWebhook::ACTION_CREATE,
        ]);
    }

    /**
     * Set action to update.
     */
    public function updateAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => IncomingWebhook::ACTION_UPDATE,
        ]);
    }

    /**
     * Set action to upsert.
     */
    public function upsertAction(string $field = 'email'): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => IncomingWebhook::ACTION_UPSERT,
            'upsert_field' => $field,
        ]);
    }

    /**
     * Set field mapping.
     */
    public function withFieldMapping(array $mapping): static
    {
        return $this->state(fn (array $attributes) => [
            'field_mapping' => $mapping,
        ]);
    }

    /**
     * Mark as having received webhooks.
     */
    public function withReceivedHistory(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'last_received_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'received_count' => $count,
        ]);
    }

    /**
     * Attach to specific module.
     */
    public function forModule(Module $module): static
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
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
