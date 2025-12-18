<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\User;
use App\Models\WebForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebForm>
 */
class WebFormFactory extends Factory
{
    protected $model = WebForm::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Contact Us',
            'Request a Demo',
            'Newsletter Signup',
            'Download Whitepaper',
            'Free Trial Request',
            'Get a Quote',
            'Event Registration',
            'Schedule Consultation',
        ]) . ' ' . $this->faker->unique()->numberBetween(1, 1000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'module_id' => fn () => Module::where('api_name', 'leads')->first()?->id ?? Module::first()?->id,
            'is_active' => true,
            'settings' => [
                'submit_button_text' => 'Submit',
                'show_title' => true,
                'show_description' => true,
                'notification_emails' => [$this->faker->safeEmail()],
                'auto_responder_enabled' => $this->faker->boolean(60),
                'redirect_after_submit' => false,
            ],
            'styling' => [
                'theme' => 'default',
                'primary_color' => '#3B82F6',
                'background_color' => '#FFFFFF',
                'font_family' => 'Inter',
                'border_radius' => '8px',
            ],
            'thank_you_config' => [
                'type' => 'message',
                'message' => 'Thank you for your submission! We will be in touch soon.',
                'redirect_url' => null,
            ],
            'spam_protection' => [
                'enabled' => true,
                'honeypot' => true,
                'recaptcha_enabled' => $this->faker->boolean(40),
                'recaptcha_site_key' => null,
            ],
            'created_by' => User::factory(),
            'assign_to_user_id' => $this->faker->optional(0.5)->passthrough(User::factory()),
        ];
    }

    /**
     * Active form.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive form.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Contact form.
     */
    public function contactForm(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Contact Us',
            'slug' => 'contact-us',
            'description' => 'Get in touch with our team',
        ]);
    }

    /**
     * Demo request form.
     */
    public function demoRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Request a Demo',
            'slug' => 'request-demo',
            'description' => 'Schedule a personalized demo of our platform',
        ]);
    }

    /**
     * Newsletter signup form.
     */
    public function newsletter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Newsletter Signup',
            'slug' => 'newsletter',
            'description' => 'Subscribe to our newsletter for updates',
            'settings' => [
                'submit_button_text' => 'Subscribe',
                'show_title' => true,
                'show_description' => false,
            ],
        ]);
    }

    /**
     * With redirect after submit.
     */
    public function withRedirect(string $url = null): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'redirect_after_submit' => true,
            ]),
            'thank_you_config' => [
                'type' => 'redirect',
                'message' => null,
                'redirect_url' => $url ?? 'https://example.com/thank-you',
            ],
        ]);
    }

    /**
     * With reCAPTCHA enabled.
     */
    public function withRecaptcha(): static
    {
        return $this->state(fn (array $attributes) => [
            'spam_protection' => [
                'enabled' => true,
                'honeypot' => true,
                'recaptcha_enabled' => true,
                'recaptcha_site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI', // Test key
            ],
        ]);
    }
}
