<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WebForm\Entities\WebFormSubmission;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\WebForm\Entities\WebFormSubmission>
 */
class WebFormSubmissionFactory extends Factory
{
    protected $model = WebFormSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'web_form_id' => WebForm::factory(),
            'record_id' => null, // Set only when processed with an actual record
            'submission_data' => [
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'email' => $this->faker->safeEmail(),
                'phone' => $this->faker->phoneNumber(),
                'company' => $this->faker->company(),
                'message' => $this->faker->paragraph(),
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'referrer' => $this->faker->optional(0.7)->url(),
            'utm_params' => $this->faker->optional(0.5)->passthrough([
                'utm_source' => $this->faker->randomElement(['google', 'facebook', 'linkedin', 'twitter']),
                'utm_medium' => $this->faker->randomElement(['cpc', 'organic', 'social', 'email']),
                'utm_campaign' => $this->faker->slug(3),
            ]),
            'status' => WebFormSubmission::STATUS_PROCESSED,
            'error_message' => null,
            'submitted_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Processed submission (without record_id, set it manually if needed).
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebFormSubmission::STATUS_PROCESSED,
            'record_id' => null, // Set manually to an existing record
            'error_message' => null,
        ]);
    }

    /**
     * Failed submission.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebFormSubmission::STATUS_FAILED,
            'record_id' => null,
            'error_message' => $this->faker->randomElement([
                'Database connection failed',
                'Required field missing',
                'Invalid email format',
                'Rate limit exceeded',
            ]),
        ]);
    }

    /**
     * Spam submission.
     */
    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebFormSubmission::STATUS_SPAM,
            'record_id' => null,
            'submission_data' => [
                'first_name' => 'BUY NOW!!!',
                'email' => 'spam@' . $this->faker->domainName(),
                'message' => 'Free money! Click here: http://spam.example.com',
            ],
        ]);
    }

    /**
     * Pending submission.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebFormSubmission::STATUS_PENDING,
            'record_id' => null,
        ]);
    }

    /**
     * With UTM params.
     */
    public function withUtm(string $source = 'google', string $medium = 'cpc', string $campaign = 'brand'): static
    {
        return $this->state(fn (array $attributes) => [
            'utm_params' => [
                'utm_source' => $source,
                'utm_medium' => $medium,
                'utm_campaign' => $campaign,
                'utm_term' => $this->faker->optional()->words(2, true),
                'utm_content' => $this->faker->optional()->slug(2),
            ],
        ]);
    }

    /**
     * Recent submission.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Contact form submission.
     */
    public function contactSubmission(): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_data' => [
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'email' => $this->faker->safeEmail(),
                'phone' => $this->faker->phoneNumber(),
                'subject' => $this->faker->sentence(4),
                'message' => $this->faker->paragraphs(2, true),
            ],
        ]);
    }
}
