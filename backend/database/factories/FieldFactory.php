<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Field>
 */
class FieldFactory extends Factory
{
    protected $model = Field::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = $this->faker->words(2, true);

        return [
            'module_id' => fn () => DB::table('modules')->where('api_name', 'deals')->first()?->id ?? DB::table('modules')->first()?->id,
            'block_id' => null,
            'label' => ucwords($label),
            'api_name' => strtolower(str_replace(' ', '_', $label)) . '_' . $this->faker->unique()->numberBetween(1, 9999),
            'type' => $this->faker->randomElement(['text', 'email', 'phone', 'number', 'select']),
            'description' => $this->faker->optional()->sentence(),
            'help_text' => $this->faker->optional()->sentence(),
            'placeholder' => $this->faker->optional()->words(3, true),
            'is_required' => $this->faker->boolean(30),
            'is_unique' => $this->faker->boolean(10),
            'is_searchable' => true,
            'is_filterable' => true,
            'is_sortable' => true,
            'validation_rules' => [],
            'settings' => [],
            'default_value' => null,
            'display_order' => $this->faker->numberBetween(0, 20),
            'width' => $this->faker->randomElement([25, 33, 50, 100]),
        ];
    }

    /**
     * Indicate that the field is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Indicate that the field is unique.
     */
    public function unique(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_unique' => true,
        ]);
    }

    /**
     * Create a text field.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
        ]);
    }

    /**
     * Create an email field.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
        ]);
    }

    /**
     * Create a select field.
     */
    public function select(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'select',
        ]);
    }

    /**
     * Create a lookup field.
     */
    public function lookup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'lookup',
            'lookup_settings' => [
                'target_module' => 'contacts',
                'relationship_type' => 'belongs_to',
                'display_field' => 'name',
            ],
        ]);
    }

    /**
     * Create a formula field.
     */
    public function formula(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'formula',
            'formula_definition' => [
                'expression' => 'field1 + field2',
                'return_type' => 'number',
            ],
        ]);
    }

    /**
     * Add conditional visibility to the field.
     */
    public function withConditionalVisibility(): static
    {
        return $this->state(fn (array $attributes) => [
            'conditional_visibility' => [
                'enabled' => true,
                'operator' => 'and',
                'conditions' => [
                    [
                        'field' => 'status',
                        'operator' => 'equals',
                        'value' => 'active',
                    ],
                ],
            ],
        ]);
    }
}
