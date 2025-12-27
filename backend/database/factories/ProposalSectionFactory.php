<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Proposal\Entities\Proposal;
use App\Domain\Proposal\Entities\ProposalSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Proposal\Entities\ProposalSection>
 */
class ProposalSectionFactory extends Factory
{
    protected $model = ProposalSection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(ProposalSection::TYPES);

        return [
            'proposal_id' => Proposal::factory(),
            'section_type' => $type,
            'title' => $this->getSectionTitle($type),
            'content' => $this->getSectionContent($type),
            'settings' => [],
            'display_order' => $this->faker->numberBetween(1, 10),
            'is_visible' => true,
            'is_locked' => false,
        ];
    }

    private function getSectionTitle(string $type): string
    {
        return match ($type) {
            ProposalSection::TYPE_COVER => 'Cover Page',
            ProposalSection::TYPE_EXECUTIVE_SUMMARY => 'Executive Summary',
            ProposalSection::TYPE_SCOPE => 'Scope of Work',
            ProposalSection::TYPE_PRICING => 'Investment',
            ProposalSection::TYPE_TIMELINE => 'Project Timeline',
            ProposalSection::TYPE_TERMS => 'Terms & Conditions',
            ProposalSection::TYPE_TEAM => 'Your Team',
            ProposalSection::TYPE_CASE_STUDY => 'Success Story',
            default => 'Custom Section',
        };
    }

    private function getSectionContent(string $type): string
    {
        return match ($type) {
            ProposalSection::TYPE_EXECUTIVE_SUMMARY => $this->faker->paragraphs(3, true),
            ProposalSection::TYPE_SCOPE => "## Deliverables\n\n" . implode("\n", array_map(fn ($i) => "- " . $this->faker->sentence(), range(1, 5))),
            ProposalSection::TYPE_TIMELINE => "## Project Phases\n\n**Phase 1:** Discovery (2 weeks)\n**Phase 2:** Development (6 weeks)\n**Phase 3:** Testing (2 weeks)\n**Phase 4:** Launch (1 week)",
            ProposalSection::TYPE_TERMS => "## Payment Terms\n\nNet 30 days from invoice date.\n\n## Warranty\n\n90-day warranty on all deliverables.",
            ProposalSection::TYPE_TEAM => "## Meet Your Team\n\nOur experienced professionals are ready to help you succeed.",
            ProposalSection::TYPE_CASE_STUDY => "## Client Success: " . $this->faker->company() . "\n\n" . $this->faker->paragraphs(2, true),
            default => $this->faker->paragraphs(2, true),
        };
    }

    /**
     * Executive summary section.
     */
    public function executiveSummary(): static
    {
        return $this->state(fn (array $attributes) => [
            'section_type' => ProposalSection::TYPE_EXECUTIVE_SUMMARY,
            'title' => 'Executive Summary',
            'display_order' => 1,
        ]);
    }

    /**
     * Scope section.
     */
    public function scope(): static
    {
        return $this->state(fn (array $attributes) => [
            'section_type' => ProposalSection::TYPE_SCOPE,
            'title' => 'Scope of Work',
            'display_order' => 2,
        ]);
    }

    /**
     * Pricing section.
     */
    public function pricing(): static
    {
        return $this->state(fn (array $attributes) => [
            'section_type' => ProposalSection::TYPE_PRICING,
            'title' => 'Investment',
            'display_order' => 3,
        ]);
    }

    /**
     * Hidden section.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }
}
