<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SignatureField>
 */
class SignatureFieldFactory extends Factory
{
    protected $model = SignatureField::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => SignatureRequest::factory(),
            'signer_id' => SignatureSigner::factory(),
            'field_type' => $this->faker->randomElement(SignatureField::TYPES),
            'page_number' => $this->faker->numberBetween(1, 3),
            'x_position' => $this->faker->randomFloat(2, 50, 500),
            'y_position' => $this->faker->randomFloat(2, 100, 700),
            'width' => $this->faker->randomFloat(2, 100, 250),
            'height' => $this->faker->randomFloat(2, 30, 80),
            'required' => true,
            'label' => null,
            'value' => null,
            'filled_at' => null,
        ];
    }

    /**
     * Signature field type.
     */
    public function signature(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => SignatureField::TYPE_SIGNATURE,
            'width' => 200,
            'height' => 50,
            'label' => 'Signature',
        ]);
    }

    /**
     * Initials field type.
     */
    public function initials(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => SignatureField::TYPE_INITIALS,
            'width' => 60,
            'height' => 30,
            'label' => 'Initials',
        ]);
    }

    /**
     * Date field type.
     */
    public function date(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => SignatureField::TYPE_DATE,
            'width' => 100,
            'height' => 30,
            'label' => 'Date',
        ]);
    }

    /**
     * Text field type.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => SignatureField::TYPE_TEXT,
            'width' => 200,
            'height' => 30,
            'label' => 'Name',
        ]);
    }

    /**
     * Checkbox field type.
     */
    public function checkbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => SignatureField::TYPE_CHECKBOX,
            'width' => 20,
            'height' => 20,
            'label' => 'I agree to the terms',
        ]);
    }

    /**
     * Optional field.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => false,
        ]);
    }

    /**
     * Filled field.
     */
    public function filled(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $this->getValueForType($attributes['field_type'] ?? SignatureField::TYPE_SIGNATURE),
            'filled_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * On specific page.
     */
    public function onPage(int $page): static
    {
        return $this->state(fn (array $attributes) => [
            'page_number' => $page,
        ]);
    }

    /**
     * At specific position.
     */
    public function atPosition(float $x, float $y): static
    {
        return $this->state(fn (array $attributes) => [
            'x_position' => $x,
            'y_position' => $y,
        ]);
    }

    private function getValueForType(string $type): string
    {
        return match ($type) {
            SignatureField::TYPE_SIGNATURE => 'base64_signature_data',
            SignatureField::TYPE_INITIALS => 'JD',
            SignatureField::TYPE_DATE => now()->format('Y-m-d'),
            SignatureField::TYPE_TEXT => $this->faker->name(),
            SignatureField::TYPE_CHECKBOX => 'true',
            default => '',
        };
    }
}
