<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPage>
 */
class LandingPageFactory extends Factory
{
    protected $model = LandingPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Product Launch',
            'Free Trial Signup',
            'Webinar Registration',
            'E-book Download',
            'Demo Request',
            'Black Friday Sale',
            'Coming Soon',
            'Event Registration',
        ]) . ' ' . $this->faker->unique()->numberBetween(1, 1000);

        $status = $this->faker->randomElement(['draft', 'published', 'archived']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'status' => $status,
            'template_id' => LandingPageTemplate::factory(),
            'content' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'headline' => $this->faker->sentence(5),
                        'subheadline' => $this->faker->sentence(10),
                        'cta_text' => 'Get Started',
                        'background_image' => 'https://picsum.photos/1920/600',
                    ],
                    [
                        'type' => 'features',
                        'items' => [
                            ['icon' => 'check', 'title' => 'Feature 1', 'description' => $this->faker->sentence()],
                            ['icon' => 'star', 'title' => 'Feature 2', 'description' => $this->faker->sentence()],
                            ['icon' => 'rocket', 'title' => 'Feature 3', 'description' => $this->faker->sentence()],
                        ],
                    ],
                    [
                        'type' => 'form',
                        'title' => 'Get Started Today',
                        'form_id' => null,
                    ],
                ],
            ],
            'settings' => [
                'show_header' => true,
                'show_footer' => true,
                'tracking_enabled' => true,
            ],
            'seo_settings' => [
                'title' => $name,
                'description' => $this->faker->sentence(15),
                'keywords' => implode(', ', $this->faker->words(5)),
                'no_index' => false,
            ],
            'styles' => [
                'primary_color' => '#3B82F6',
                'secondary_color' => '#10B981',
                'font_family' => 'Inter',
            ],
            'custom_domain' => null,
            'custom_domain_verified' => false,
            'favicon_url' => null,
            'og_image_url' => 'https://picsum.photos/1200/630',
            'web_form_id' => WebForm::factory(),
            'thank_you_page_type' => 'message',
            'thank_you_message' => 'Thank you for your interest! We will be in touch soon.',
            'thank_you_redirect_url' => null,
            'thank_you_page_id' => null,
            'is_ab_testing_enabled' => false,
            'campaign_id' => null,
            'created_by' => User::factory(),
            'published_at' => $status === 'published' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
        ];
    }

    /**
     * Draft page.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Published page.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Archived page.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    /**
     * Product launch page.
     */
    public function productLaunch(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Product Launch',
            'slug' => 'product-launch',
            'content' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'headline' => 'Introducing Our New Product',
                        'subheadline' => 'The solution you have been waiting for',
                        'cta_text' => 'Learn More',
                    ],
                    [
                        'type' => 'countdown',
                        'launch_date' => now()->addDays(14)->toIso8601String(),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Webinar registration page.
     */
    public function webinar(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Webinar Registration',
            'slug' => 'webinar-registration',
            'content' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'headline' => 'Join Our Free Webinar',
                        'subheadline' => 'Learn from industry experts',
                        'cta_text' => 'Register Now',
                    ],
                    [
                        'type' => 'speakers',
                        'items' => [],
                    ],
                    [
                        'type' => 'form',
                        'title' => 'Reserve Your Spot',
                    ],
                ],
            ],
        ]);
    }

    /**
     * With A/B testing enabled.
     */
    public function withAbTesting(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ab_testing_enabled' => true,
        ]);
    }

    /**
     * With custom domain.
     */
    public function withCustomDomain(string $domain = null): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_domain' => $domain ?? 'landing.' . $this->faker->domainName(),
            'custom_domain_verified' => true,
        ]);
    }

    /**
     * Thank you page.
     */
    public function thankYouPage(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Thank You',
            'slug' => 'thank-you',
            'content' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'headline' => 'Thank You!',
                        'subheadline' => 'We appreciate your interest',
                    ],
                    [
                        'type' => 'text',
                        'content' => 'We will be in touch within 24 hours.',
                    ],
                ],
            ],
            'seo_settings' => [
                'no_index' => true,
            ],
        ]);
    }
}
