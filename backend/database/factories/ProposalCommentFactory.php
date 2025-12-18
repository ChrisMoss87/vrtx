<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\ProposalComment;
use App\Models\ProposalSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProposalComment>
 */
class ProposalCommentFactory extends Factory
{
    protected $model = ProposalComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'section_id' => null,
            'user_id' => User::factory(),
            'external_name' => null,
            'external_email' => null,
            'content' => $this->faker->randomElement([
                'Can we discuss the pricing options on our call?',
                'This looks great! I have a few questions about the timeline.',
                'Need to review this with my team.',
                'Can you provide more details about the support plan?',
                'The scope looks accurate. Proceeding to final review.',
            ]),
            'is_internal' => $this->faker->boolean(30),
            'is_resolved' => $this->faker->boolean(20),
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }

    /**
     * Internal comment.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
            'content' => $this->faker->randomElement([
                'Client seems very interested - follow up tomorrow.',
                'They asked about competitor pricing.',
                'Decision maker is the CFO.',
                'Needs approval from their board.',
            ]),
        ]);
    }

    /**
     * External comment (from viewer).
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'external_name' => $this->faker->name(),
            'external_email' => $this->faker->email(),
            'is_internal' => false,
        ]);
    }

    /**
     * Resolved comment.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
        ]);
    }

    /**
     * On specific section.
     */
    public function onSection(): static
    {
        return $this->state(fn (array $attributes) => [
            'section_id' => ProposalSection::factory(),
        ]);
    }
}
