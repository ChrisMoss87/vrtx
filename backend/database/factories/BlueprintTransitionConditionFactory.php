<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Blueprint\Entities\BlueprintTransitionCondition;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Blueprint\Entities\BlueprintTransitionCondition>
 */
class BlueprintTransitionConditionFactory extends Factory
{
    protected $model = BlueprintTransitionCondition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transition_id' => BlueprintTransition::factory(),
            'field_id' => Field::factory(),
            'operator' => $this->faker->randomElement([
                BlueprintTransitionCondition::OPERATOR_EQUALS,
                BlueprintTransitionCondition::OPERATOR_NOT_EQUALS,
                BlueprintTransitionCondition::OPERATOR_IS_NOT_EMPTY,
            ]),
            'value' => $this->faker->word(),
            'logical_group' => 'AND',
            'display_order' => $this->faker->numberBetween(1, 5),
        ];
    }

    /**
     * Equals condition.
     */
    public function equals(string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'operator' => BlueprintTransitionCondition::OPERATOR_EQUALS,
            'value' => $value,
        ]);
    }

    /**
     * Not equals condition.
     */
    public function notEquals(string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'operator' => BlueprintTransitionCondition::OPERATOR_NOT_EQUALS,
            'value' => $value,
        ]);
    }

    /**
     * Greater than condition.
     */
    public function greaterThan(string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'operator' => BlueprintTransitionCondition::OPERATOR_GREATER_THAN,
            'value' => $value,
        ]);
    }

    /**
     * Is not empty condition.
     */
    public function isNotEmpty(): static
    {
        return $this->state(fn (array $attributes) => [
            'operator' => BlueprintTransitionCondition::OPERATOR_IS_NOT_EMPTY,
            'value' => null,
        ]);
    }

    /**
     * OR logical group.
     */
    public function orGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'logical_group' => 'OR',
        ]);
    }
}
