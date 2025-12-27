<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Entities\QuoteTemplate;
use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Entities\QuoteTemplate>
 */
class QuoteTemplateFactory extends Factory
{
    protected $model = QuoteTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Standard Quote Template',
                'Enterprise Quote Template',
                'Professional Services Quote',
                'Subscription Quote Template',
            ]),
            'description' => $this->faker->sentence(),
            'content' => [
                'header' => [
                    'logo_position' => 'left',
                    'show_company_info' => true,
                ],
                'sections' => ['summary', 'line_items', 'terms'],
                'footer' => [
                    'show_signature_line' => true,
                    'show_validity_date' => true,
                ],
            ],
            'styling' => [
                'primary_color' => '#2563eb',
                'secondary_color' => '#64748b',
                'font_family' => 'Inter',
            ],
            'default_terms' => 'Net 30 days from invoice date. All prices in USD unless otherwise specified.',
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
