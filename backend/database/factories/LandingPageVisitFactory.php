<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\LandingPage\Entities\LandingPageVisit;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\LandingPage\Entities\LandingPageVisit>
 */
class LandingPageVisitFactory extends Factory
{
    protected $model = LandingPageVisit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userAgent = $this->faker->userAgent();
        $parsed = LandingPageVisit::parseUserAgent($userAgent);
        $converted = $this->faker->boolean(15);

        return [
            'page_id' => LandingPage::factory(),
            'variant_id' => null,
            'visitor_id' => Str::uuid()->toString(),
            'session_id' => Str::uuid()->toString(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $userAgent,
            'referrer' => $this->faker->optional(0.6)->url(),
            'utm_source' => $this->faker->optional(0.5)->randomElement(['google', 'facebook', 'linkedin', 'twitter', 'email']),
            'utm_medium' => $this->faker->optional(0.5)->randomElement(['cpc', 'organic', 'social', 'email', 'referral']),
            'utm_campaign' => $this->faker->optional(0.4)->slug(3),
            'utm_term' => $this->faker->optional(0.3)->words(2, true),
            'utm_content' => $this->faker->optional(0.2)->slug(2),
            'device_type' => $parsed['device_type'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'country' => $this->faker->countryCode(),
            'city' => $this->faker->city(),
            'converted' => $converted,
            'converted_at' => $converted ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'submission_id' => $converted ? WebFormSubmission::factory() : null,
            'time_on_page' => $this->faker->numberBetween(5, 300),
            'scroll_depth' => $this->faker->numberBetween(10, 100),
        ];
    }

    /**
     * Converted visit.
     */
    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'converted' => true,
            'converted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'submission_id' => WebFormSubmission::factory(),
        ]);
    }

    /**
     * Non-converted visit.
     */
    public function notConverted(): static
    {
        return $this->state(fn (array $attributes) => [
            'converted' => false,
            'converted_at' => null,
            'submission_id' => null,
        ]);
    }

    /**
     * Bounced visit.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'converted' => false,
            'time_on_page' => $this->faker->numberBetween(1, 9),
            'scroll_depth' => $this->faker->numberBetween(0, 20),
        ]);
    }

    /**
     * Engaged visit.
     */
    public function engaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_on_page' => $this->faker->numberBetween(60, 300),
            'scroll_depth' => $this->faker->numberBetween(70, 100),
        ]);
    }

    /**
     * Mobile visit.
     */
    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'mobile',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
            'os' => 'iOS',
            'browser' => 'Safari',
        ]);
    }

    /**
     * Desktop visit.
     */
    public function desktop(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'desktop',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0',
            'os' => 'Windows',
            'browser' => 'Chrome',
        ]);
    }

    /**
     * From Google.
     */
    public function fromGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            'utm_source' => 'google',
            'utm_medium' => $this->faker->randomElement(['cpc', 'organic']),
            'referrer' => 'https://www.google.com/',
        ]);
    }

    /**
     * From social.
     */
    public function fromSocial(string $platform = null): static
    {
        $platform = $platform ?? $this->faker->randomElement(['facebook', 'linkedin', 'twitter']);

        return $this->state(fn (array $attributes) => [
            'utm_source' => $platform,
            'utm_medium' => 'social',
            'referrer' => "https://www.{$platform}.com/",
        ]);
    }

    /**
     * Direct visit (no referrer).
     */
    public function direct(): static
    {
        return $this->state(fn (array $attributes) => [
            'referrer' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
        ]);
    }

    /**
     * For specific variant.
     */
    public function forVariant(int $variantId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_id' => $variantId ?? LandingPageVariant::factory(),
        ]);
    }
}
