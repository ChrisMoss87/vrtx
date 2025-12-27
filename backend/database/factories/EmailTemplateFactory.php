<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Email\Entities\EmailTemplate;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Email\Entities\EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true) . ' Template',
            'subject' => $this->faker->sentence(),
            'body_html' => '<p>Hello {{contact.name}},</p><p>' . $this->faker->paragraph() . '</p><p>Best regards,<br>{{user.name}}</p>',
            'body_text' => 'Hello {{contact.name}}, ' . $this->faker->paragraph() . ' Best regards, {{user.name}}',
            'category' => $this->faker->randomElement(['sales', 'support', 'marketing', 'general']),
            'is_active' => true,
            'variables' => ['contact.name', 'contact.email', 'user.name', 'company.name'],
            'metadata' => [],
        ];
    }

    /**
     * Mark template as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Mark template as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set template category.
     */
    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Create a sales template.
     */
    public function sales(): static
    {
        return $this->category('sales');
    }

    /**
     * Create a support template.
     */
    public function support(): static
    {
        return $this->category('support');
    }

    /**
     * Create a marketing template.
     */
    public function marketing(): static
    {
        return $this->category('marketing');
    }

    /**
     * Set custom variables.
     */
    public function withVariables(array $variables): static
    {
        return $this->state(fn (array $attributes) => [
            'variables' => $variables,
        ]);
    }
}
