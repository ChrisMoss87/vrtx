<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatVisitor;
use App\Models\ChatWidget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatVisitor>
 */
class ChatVisitorFactory extends Factory
{
    protected $model = ChatVisitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'widget_id' => ChatWidget::factory(),
            'contact_id' => null,
            'fingerprint' => Str::random(32),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'country' => $this->faker->countryCode(),
            'city' => $this->faker->city(),
            'name' => $this->faker->optional(0.6)->name(),
            'email' => $this->faker->optional(0.5)->email(),
            'custom_data' => [
                'plan' => $this->faker->randomElement(['free', 'pro', 'enterprise']),
            ],
            'pages_viewed' => [
                [
                    'url' => 'https://example.com/',
                    'title' => 'Home',
                    'timestamp' => now()->subMinutes(10)->toISOString(),
                ],
                [
                    'url' => 'https://example.com/pricing',
                    'title' => 'Pricing',
                    'timestamp' => now()->subMinutes(5)->toISOString(),
                ],
            ],
            'current_page' => 'https://example.com/pricing',
            'referrer' => $this->faker->optional(0.7)->url(),
            'first_seen_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'last_seen_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ];
    }

    /**
     * Identified visitor.
     */
    public function identified(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
        ]);
    }

    /**
     * Anonymous visitor.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'email' => null,
        ]);
    }

    /**
     * Returning visitor.
     */
    public function returning(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_seen_at' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
            'pages_viewed' => array_fill(0, 20, [
                'url' => $this->faker->url(),
                'title' => $this->faker->sentence(3),
                'timestamp' => $this->faker->dateTimeThisMonth()->format('c'),
            ]),
        ]);
    }

    /**
     * Currently active visitor.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => now(),
        ]);
    }

    /**
     * From specific country.
     */
    public function fromCountry(string $country): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => $country,
        ]);
    }
}
