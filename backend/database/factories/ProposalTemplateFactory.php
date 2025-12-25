<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\ProposalTemplate;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\ProposalTemplate>
 */
class ProposalTemplateFactory extends Factory
{
    protected $model = ProposalTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Standard Business Proposal',
                'Enterprise Solution Proposal',
                'Professional Services Proposal',
                'SaaS Implementation Proposal',
            ]),
            'description' => $this->faker->sentence(),
            'thumbnail_url' => null,
            'sections' => [
                ['type' => 'cover', 'title' => 'Cover Page', 'order' => 1],
                ['type' => 'executive_summary', 'title' => 'Executive Summary', 'order' => 2],
                ['type' => 'scope', 'title' => 'Scope of Work', 'order' => 3],
                ['type' => 'pricing', 'title' => 'Investment', 'order' => 4],
                ['type' => 'timeline', 'title' => 'Timeline', 'order' => 5],
                ['type' => 'terms', 'title' => 'Terms & Conditions', 'order' => 6],
            ],
            'styling' => [
                'primary_color' => '#2563eb',
                'secondary_color' => '#64748b',
                'font_family' => 'Inter',
                'header_style' => 'modern',
            ],
            'default_content' => [
                'terms' => 'Net 30 days from acceptance. Valid for 30 days.',
            ],
            'is_default' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Default template.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Inactive template.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
