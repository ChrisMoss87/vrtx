<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LandingPageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPageTemplate>
 */
class LandingPageTemplateFactory extends Factory
{
    protected $model = LandingPageTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = array_keys(LandingPageTemplate::getCategories());
        $category = $this->faker->randomElement($categories);

        return [
            'name' => $this->faker->randomElement([
                'Modern Lead Capture',
                'Event Landing',
                'SaaS Product',
                'E-commerce Sale',
                'Webinar Sign-up',
                'Coming Soon',
                'Thank You',
                'Free Trial',
            ]) . ' ' . $this->faker->unique()->numberBetween(1, 1000),
            'category' => $category,
            'description' => $this->faker->sentence(),
            'thumbnail_url' => 'https://picsum.photos/400/300',
            'content' => [
                'sections' => [
                    ['type' => 'hero', 'editable' => true],
                    ['type' => 'features', 'editable' => true],
                    ['type' => 'form', 'editable' => true],
                    ['type' => 'footer', 'editable' => false],
                ],
            ],
            'styles' => [
                'primary_color' => $this->faker->hexColor(),
                'secondary_color' => $this->faker->hexColor(),
                'font_family' => $this->faker->randomElement(['Inter', 'Roboto', 'Open Sans', 'Lato']),
                'heading_font' => $this->faker->randomElement(['Poppins', 'Montserrat', 'Playfair Display']),
            ],
            'is_system' => $this->faker->boolean(30),
            'is_active' => true,
            'usage_count' => $this->faker->numberBetween(0, 500),
            'created_by' => User::factory(),
        ];
    }

    /**
     * System template.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
            'created_by' => null,
        ]);
    }

    /**
     * Custom template.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => false,
            'created_by' => User::factory(),
        ]);
    }

    /**
     * Active template.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
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

    /**
     * Lead capture template.
     */
    public function leadCapture(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Lead Capture Template',
            'category' => 'lead-capture',
            'description' => 'High-converting lead generation template',
        ]);
    }

    /**
     * Event template.
     */
    public function event(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Event Registration Template',
            'category' => 'event',
            'description' => 'Event and conference registration template',
        ]);
    }

    /**
     * Webinar template.
     */
    public function webinar(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Webinar Template',
            'category' => 'webinar',
            'description' => 'Webinar registration and promotion template',
        ]);
    }

    /**
     * Popular template (high usage).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(200, 1000),
        ]);
    }
}
