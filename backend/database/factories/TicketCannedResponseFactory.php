<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TicketCannedResponse;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketCannedResponse>
 */
class TicketCannedResponseFactory extends Factory
{
    protected $model = TicketCannedResponse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $responses = [
            [
                'name' => 'Greeting',
                'shortcut' => '/greet',
                'content' => "Hi {{customer_name}},\n\nThank you for reaching out to our support team. I'll be happy to help you with your inquiry.\n\n",
            ],
            [
                'name' => 'Request More Information',
                'shortcut' => '/moreinfo',
                'content' => "Thank you for contacting us. To better assist you, could you please provide the following information:\n\n1. Steps to reproduce the issue\n2. Any error messages you're seeing\n3. Your browser and operating system\n\nThis will help us investigate and resolve your issue more quickly.",
            ],
            [
                'name' => 'Password Reset Instructions',
                'shortcut' => '/pwreset',
                'content' => "To reset your password, please follow these steps:\n\n1. Go to the login page\n2. Click 'Forgot Password'\n3. Enter your email address\n4. Check your inbox for the reset link\n5. Follow the link to create a new password\n\nIf you don't receive the email within 5 minutes, please check your spam folder.",
            ],
            [
                'name' => 'Closing - Issue Resolved',
                'shortcut' => '/close',
                'content' => "I'm glad we could resolve this for you! If you have any other questions, feel free to reach out.\n\nThank you for your patience and have a great day!",
            ],
            [
                'name' => 'Escalation Notice',
                'shortcut' => '/escalate',
                'content' => "I'm escalating your ticket to our senior support team for further investigation. They will review your case and get back to you within 24 hours.\n\nThank you for your patience.",
            ],
            [
                'name' => 'Billing Inquiry',
                'shortcut' => '/billing',
                'content' => "Thank you for contacting us about your billing inquiry. I can see your account details and will help resolve this right away.\n\nCould you please confirm the last 4 digits of the payment method on file?",
            ],
        ];

        $selected = $this->faker->randomElement($responses);

        return [
            'name' => $selected['name'] . ' ' . $this->faker->unique()->numberBetween(1, 1000),
            'shortcut' => $selected['shortcut'] . $this->faker->unique()->numberBetween(1, 1000),
            'content' => $selected['content'],
            'category_id' => $this->faker->optional(0.5)->passthrough(TicketCategory::factory()),
            'created_by' => User::factory(),
            'is_shared' => $this->faker->boolean(70),
            'usage_count' => $this->faker->numberBetween(0, 500),
        ];
    }

    /**
     * Shared response.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shared' => true,
        ]);
    }

    /**
     * Personal response.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shared' => false,
        ]);
    }

    /**
     * Popular response (high usage).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(200, 1000),
        ]);
    }

    /**
     * Greeting response.
     */
    public function greeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Greeting',
            'shortcut' => '/greet',
            'content' => "Hi {{customer_name}},\n\nThank you for reaching out to our support team. I'll be happy to help you with your inquiry.\n\n",
        ]);
    }

    /**
     * Closing response.
     */
    public function closing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Closing - Issue Resolved',
            'shortcut' => '/close',
            'content' => "I'm glad we could resolve this for you! If you have any other questions, feel free to reach out.\n\nThank you for your patience and have a great day!",
        ]);
    }
}
