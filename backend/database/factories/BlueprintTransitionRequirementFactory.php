<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Blueprint\Entities\BlueprintTransitionRequirement;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Blueprint\Entities\BlueprintTransitionRequirement>
 */
class BlueprintTransitionRequirementFactory extends Factory
{
    protected $model = BlueprintTransitionRequirement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transition_id' => BlueprintTransition::factory(),
            'type' => $this->faker->randomElement([
                BlueprintTransitionRequirement::TYPE_MANDATORY_FIELD,
                BlueprintTransitionRequirement::TYPE_NOTE,
                BlueprintTransitionRequirement::TYPE_ATTACHMENT,
            ]),
            'field_id' => null,
            'label' => $this->faker->randomElement([
                'Add closing notes',
                'Upload signed contract',
                'Complete checklist',
                'Enter amount',
            ]),
            'description' => $this->faker->sentence(),
            'is_required' => true,
            'config' => [],
            'display_order' => $this->faker->numberBetween(1, 5),
        ];
    }

    /**
     * Mandatory field requirement.
     */
    public function mandatoryField(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionRequirement::TYPE_MANDATORY_FIELD,
            'field_id' => Field::factory(),
            'label' => 'Fill required field',
        ]);
    }

    /**
     * Note requirement.
     */
    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionRequirement::TYPE_NOTE,
            'field_id' => null,
            'label' => 'Add a note',
            'config' => ['min_length' => 10],
        ]);
    }

    /**
     * Attachment requirement.
     */
    public function attachment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionRequirement::TYPE_ATTACHMENT,
            'field_id' => null,
            'label' => 'Upload a file',
            'config' => [
                'allowed_types' => ['pdf', 'docx'],
                'max_size' => 10485760,
            ],
        ]);
    }

    /**
     * Checklist requirement.
     */
    public function checklist(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionRequirement::TYPE_CHECKLIST,
            'field_id' => null,
            'label' => 'Complete checklist',
            'config' => [
                'items' => [
                    'Review all documents',
                    'Confirm pricing',
                    'Verify contact information',
                    'Schedule follow-up',
                ],
            ],
        ]);
    }

    /**
     * Optional requirement.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }
}
