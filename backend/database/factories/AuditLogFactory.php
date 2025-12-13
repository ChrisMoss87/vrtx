<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\ModuleRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'auditable_type' => ModuleRecord::class,
            'auditable_id' => ModuleRecord::factory(),
            'event' => $this->faker->randomElement(['created', 'updated', 'deleted']),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'url' => $this->faker->url(),
            'tags' => [],
            'batch_id' => null,
        ];
    }

    /**
     * Create a record created audit log.
     */
    public function created(array $newValues = []): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'created',
            'old_values' => null,
            'new_values' => $newValues ?: [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
            ],
        ]);
    }

    /**
     * Create a record updated audit log.
     */
    public function updated(array $oldValues = [], array $newValues = []): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'updated',
            'old_values' => $oldValues ?: ['status' => 'draft'],
            'new_values' => $newValues ?: ['status' => 'active'],
        ]);
    }

    /**
     * Create a record deleted audit log.
     */
    public function deleted(array $oldValues = []): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'deleted',
            'old_values' => $oldValues ?: [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
            ],
            'new_values' => null,
        ]);
    }

    /**
     * Add tags to the audit log.
     */
    public function withTags(array $tags): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => $tags,
        ]);
    }

    /**
     * Set a batch ID.
     */
    public function inBatch(string $batchId): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => $batchId,
        ]);
    }
}
