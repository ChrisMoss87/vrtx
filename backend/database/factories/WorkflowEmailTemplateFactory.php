<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Workflow\Entities\WorkflowEmailTemplate;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<WorkflowEmailTemplate>
 */
class WorkflowEmailTemplateFactory extends Factory
{
    protected $model = WorkflowEmailTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['Onboarding', 'Sales', 'Support', 'Notifications', 'Marketing'];

        return [
            'name' => fake()->words(3, true) . ' Template',
            'description' => fake()->sentence(),
            'subject' => fake()->sentence(),
            'body_html' => '<div style="font-family: Arial, sans-serif;">'
                . '<h2>Hello {{record.name}},</h2>'
                . '<p>' . fake()->paragraph() . '</p>'
                . '<p>Best regards,<br>{{user.name}}</p>'
                . '</div>',
            'body_text' => fake()->paragraph(),
            'from_name' => fake()->optional()->name(),
            'from_email' => fake()->optional()->safeEmail(),
            'reply_to' => fake()->optional()->safeEmail(),
            'available_variables' => null,
            'category' => fake()->randomElement($categories),
            'is_system' => false,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Mark as system template.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Set the creator.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    /**
     * Set a specific category.
     */
    public function inCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Include all available variables.
     */
    public function withVariables(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_variables' => WorkflowEmailTemplate::getDefaultVariables(),
        ]);
    }

    /**
     * Create a welcome email template.
     */
    public function welcome(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Welcome Email',
            'category' => 'Onboarding',
            'subject' => 'Welcome to {{company.name}}, {{record.name}}!',
            'body_html' => '<div style="font-family: Arial, sans-serif; max-width: 600px;">'
                . '<h1>Welcome aboard, {{record.name}}!</h1>'
                . '<p>We\'re excited to have you join us.</p>'
                . '<p>If you have any questions, don\'t hesitate to reach out.</p>'
                . '<p>Best regards,<br>The Team</p>'
                . '</div>',
            'body_text' => "Welcome aboard, {{record.name}}!\n\nWe're excited to have you join us.\n\nBest regards,\nThe Team",
        ]);
    }
}
