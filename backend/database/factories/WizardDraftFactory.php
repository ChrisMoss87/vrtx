<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Wizard\Entities\WizardDraft;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Wizard\Entities\WizardDraft>
 */
class WizardDraftFactory extends Factory
{
    protected $model = WizardDraft::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $wizardTypes = ['module_creation', 'record_creation', 'import', 'settings'];
        $stepsCount = $this->faker->numberBetween(3, 6);
        $currentStep = $this->faker->numberBetween(0, $stepsCount - 1);

        return [
            'user_id' => User::factory(),
            'wizard_type' => $this->faker->randomElement($wizardTypes),
            'reference_id' => $this->faker->boolean(30) ? (string) $this->faker->numberBetween(1, 100) : null,
            'name' => $this->faker->boolean(70) ? $this->faker->words(3, true) : null,
            'form_data' => $this->generateFormData(),
            'steps_state' => $this->generateStepsState($stepsCount, $currentStep),
            'current_step_index' => $currentStep,
            'expires_at' => $this->faker->boolean(80) ? now()->addDays($this->faker->numberBetween(7, 60)) : null,
        ];
    }

    /**
     * Generate sample form data.
     */
    protected function generateFormData(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'email' => $this->faker->email(),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['personal', 'business']),
            'settings' => [
                'enabled' => $this->faker->boolean(),
                'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            ],
        ];
    }

    /**
     * Generate steps state array.
     */
    protected function generateStepsState(int $stepsCount, int $currentStep): array
    {
        $steps = [];
        $stepTitles = ['Basic Info', 'Details', 'Configuration', 'Review', 'Confirmation', 'Complete'];

        for ($i = 0; $i < $stepsCount; $i++) {
            $steps[] = [
                'id' => 'step-' . ($i + 1),
                'title' => $stepTitles[$i] ?? 'Step ' . ($i + 1),
                'isValid' => $i < $currentStep,
                'isComplete' => $i < $currentStep,
                'isSkipped' => false,
            ];
        }

        return $steps;
    }

    /**
     * Set a specific wizard type.
     */
    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'wizard_type' => $type,
        ]);
    }

    /**
     * Make the draft for module creation.
     */
    public function moduleCreation(): static
    {
        return $this->state(fn (array $attributes) => [
            'wizard_type' => 'module_creation',
            'form_data' => [
                'moduleName' => $this->faker->words(2, true),
                'singularName' => $this->faker->word(),
                'description' => $this->faker->sentence(),
                'icon' => $this->faker->randomElement(['users', 'briefcase', 'calendar']),
                'fields' => [],
            ],
        ]);
    }

    /**
     * Make the draft for record creation.
     */
    public function recordCreation(int $moduleId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'wizard_type' => 'record_creation',
            'reference_id' => $moduleId ? (string) $moduleId : null,
            'form_data' => [
                'field_1' => $this->faker->word(),
                'field_2' => $this->faker->email(),
                'field_3' => $this->faker->numberBetween(1, 100),
            ],
        ]);
    }

    /**
     * Make the draft expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ]);
    }

    /**
     * Make the draft permanent (no expiration).
     */
    public function permanent(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Make the draft nearly complete.
     */
    public function nearlyComplete(): static
    {
        return $this->state(function (array $attributes) {
            $stepsCount = 4;
            $currentStep = $stepsCount - 1;

            return [
                'current_step_index' => $currentStep,
                'steps_state' => $this->generateStepsState($stepsCount, $currentStep),
            ];
        });
    }

    /**
     * Make the draft just started.
     */
    public function justStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_step_index' => 0,
            'steps_state' => [
                ['id' => 'step-1', 'title' => 'Step 1', 'isValid' => false, 'isComplete' => false, 'isSkipped' => false],
                ['id' => 'step-2', 'title' => 'Step 2', 'isValid' => false, 'isComplete' => false, 'isSkipped' => false],
                ['id' => 'step-3', 'title' => 'Step 3', 'isValid' => false, 'isComplete' => false, 'isSkipped' => false],
            ],
        ]);
    }

    /**
     * Assign draft to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
