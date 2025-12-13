<?php

namespace Database\Factories;

use App\Models\DuplicateCandidate;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DuplicateCandidate>
 */
class DuplicateCandidateFactory extends Factory
{
    protected $model = DuplicateCandidate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'record_id_a' => ModuleRecord::factory(),
            'record_id_b' => ModuleRecord::factory(),
            'match_score' => $this->faker->randomFloat(4, 0.5, 1.0),
            'matched_rules' => [
                ['rule_id' => 1, 'field' => 'email', 'match_type' => 'exact', 'score' => 1.0],
            ],
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'dismiss_reason' => null,
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DuplicateCandidate::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    /**
     * Merged status.
     */
    public function merged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DuplicateCandidate::STATUS_MERGED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Dismissed status.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DuplicateCandidate::STATUS_DISMISSED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'dismiss_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * High match score (>= 0.9).
     */
    public function highMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_score' => $this->faker->randomFloat(4, 0.9, 1.0),
        ]);
    }

    /**
     * Medium match score (0.7 - 0.89).
     */
    public function mediumMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_score' => $this->faker->randomFloat(4, 0.7, 0.89),
        ]);
    }

    /**
     * Low match score (< 0.7).
     */
    public function lowMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_score' => $this->faker->randomFloat(4, 0.5, 0.69),
        ]);
    }
}
