<?php

namespace Database\Factories;

use App\Models\DuplicateRule;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DuplicateRule>
 */
class DuplicateRuleFactory extends Factory
{
    protected $model = DuplicateRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'name' => $this->faker->words(3, true) . ' Rule',
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'action' => $this->faker->randomElement(['warn', 'block']),
            'conditions' => [
                'logic' => 'or',
                'rules' => [
                    ['field' => 'email', 'match_type' => 'exact'],
                ],
            ],
            'priority' => $this->faker->numberBetween(0, 100),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Active rule.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive rule.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Warning action.
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => DuplicateRule::ACTION_WARN,
        ]);
    }

    /**
     * Block action.
     */
    public function blocking(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => DuplicateRule::ACTION_BLOCK,
        ]);
    }

    /**
     * Email exact match rule.
     */
    public function emailExact(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Email Exact Match',
            'conditions' => [
                'logic' => 'or',
                'rules' => [
                    ['field' => 'email', 'match_type' => 'exact'],
                ],
            ],
        ]);
    }

    /**
     * Name fuzzy match rule.
     */
    public function nameFuzzy(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Name Fuzzy Match',
            'conditions' => [
                'logic' => 'and',
                'rules' => [
                    ['field' => 'first_name', 'match_type' => 'fuzzy', 'threshold' => 0.8],
                    ['field' => 'last_name', 'match_type' => 'fuzzy', 'threshold' => 0.8],
                ],
            ],
        ]);
    }

    /**
     * Complex rule with multiple conditions.
     */
    public function complex(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Complex Match Rule',
            'conditions' => [
                'logic' => 'or',
                'rules' => [
                    ['field' => 'email', 'match_type' => 'exact'],
                    [
                        'logic' => 'and',
                        'rules' => [
                            ['field' => 'first_name', 'match_type' => 'phonetic'],
                            ['field' => 'last_name', 'match_type' => 'exact'],
                            ['field' => 'company', 'match_type' => 'fuzzy', 'threshold' => 0.7],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
