<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebForm;
use App\Models\WebFormField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebFormField>
 */
class WebFormFieldFactory extends Factory
{
    protected $model = WebFormField::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = $this->faker->randomElement([
            'First Name',
            'Last Name',
            'Email Address',
            'Phone Number',
            'Company',
            'Job Title',
            'Message',
            'How did you hear about us?',
        ]);

        $fieldType = $this->getFieldTypeForLabel($label);

        return [
            'web_form_id' => WebForm::factory(),
            'field_type' => $fieldType,
            'label' => $label,
            'name' => Str::snake($label),
            'placeholder' => "Enter your {$label}",
            'is_required' => $this->faker->boolean(70),
            'module_field_id' => null,
            'options' => $fieldType === 'select' ? $this->getDefaultOptions() : null,
            'validation_rules' => [],
            'display_order' => $this->faker->numberBetween(1, 20),
            'settings' => [],
        ];
    }

    /**
     * Get field type based on label.
     */
    private function getFieldTypeForLabel(string $label): string
    {
        return match (true) {
            str_contains(strtolower($label), 'email') => 'email',
            str_contains(strtolower($label), 'phone') => 'phone',
            str_contains(strtolower($label), 'message') => 'textarea',
            str_contains(strtolower($label), 'how did you') => 'select',
            default => 'text',
        };
    }

    /**
     * Get default options for select fields.
     */
    private function getDefaultOptions(): array
    {
        return [
            ['label' => 'Google Search', 'value' => 'google'],
            ['label' => 'Social Media', 'value' => 'social'],
            ['label' => 'Referral', 'value' => 'referral'],
            ['label' => 'Advertisement', 'value' => 'ad'],
            ['label' => 'Other', 'value' => 'other'],
        ];
    }

    /**
     * Text field.
     */
    public function text(string $label = 'Text Field'): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'text',
            'label' => $label,
            'name' => Str::snake($label),
        ]);
    }

    /**
     * Email field.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'email',
            'label' => 'Email Address',
            'name' => 'email',
            'placeholder' => 'you@example.com',
            'is_required' => true,
        ]);
    }

    /**
     * Phone field.
     */
    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'phone',
            'label' => 'Phone Number',
            'name' => 'phone',
            'placeholder' => '+1 (555) 000-0000',
        ]);
    }

    /**
     * Textarea field.
     */
    public function textarea(string $label = 'Message'): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'textarea',
            'label' => $label,
            'name' => Str::snake($label),
            'placeholder' => 'Enter your message...',
            'settings' => [
                'rows' => 4,
            ],
        ]);
    }

    /**
     * Select dropdown field.
     */
    public function select(string $label = 'Select Option', array $options = null): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'select',
            'label' => $label,
            'name' => Str::snake($label),
            'options' => $options ?? $this->getDefaultOptions(),
        ]);
    }

    /**
     * Checkbox field.
     */
    public function checkbox(string $label = 'I agree to the terms'): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'checkbox',
            'label' => $label,
            'name' => Str::snake($label),
        ]);
    }

    /**
     * Date field.
     */
    public function date(string $label = 'Date'): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'date',
            'label' => $label,
            'name' => Str::snake($label),
        ]);
    }

    /**
     * Required field.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Optional field.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }
}
