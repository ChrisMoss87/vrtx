<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Chat\Entities\ChatWidget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Chat\Entities\ChatWidget>
 */
class ChatWidgetFactory extends Factory
{
    protected $model = ChatWidget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Main Website Chat',
                'Support Chat Widget',
                'Sales Chat',
                'Product Help Chat',
            ]),
            'widget_key' => Str::random(32),
            'is_active' => true,
            'settings' => [
                'position' => 'bottom-right',
                'greeting_message' => 'Hi! How can we help you today?',
                'offline_message' => "We're currently offline. Leave a message!",
                'require_email' => true,
                'require_name' => true,
                'show_avatar' => true,
                'sound_enabled' => true,
                'auto_open_delay' => 0,
            ],
            'styling' => [
                'primary_color' => '#3B82F6',
                'text_color' => '#FFFFFF',
                'background_color' => '#FFFFFF',
                'launcher_icon' => 'chat',
                'header_text' => 'Chat with us',
                'border_radius' => 12,
            ],
            'routing_rules' => [
                [
                    'condition' => 'page_url',
                    'operator' => 'contains',
                    'value' => '/pricing',
                    'assign_to' => 'sales',
                ],
            ],
            'business_hours' => [
                'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'tuesday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'thursday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'friday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'saturday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
            ],
            'allowed_domains' => ['*'],
        ];
    }

    /**
     * Active widget.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive widget.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * 24/7 availability.
     */
    public function alwaysOnline(): static
    {
        $hours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $hours[$day] = ['enabled' => true, 'start' => '00:00', 'end' => '23:59'];
        }

        return $this->state(fn (array $attributes) => [
            'business_hours' => $hours,
        ]);
    }

    /**
     * Restricted domains.
     */
    public function restrictedDomains(): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_domains' => ['example.com', 'www.example.com'],
        ]);
    }

    /**
     * Custom styling.
     */
    public function customStyled(): static
    {
        return $this->state(fn (array $attributes) => [
            'styling' => [
                'primary_color' => '#10B981',
                'text_color' => '#FFFFFF',
                'background_color' => '#F9FAFB',
                'launcher_icon' => 'message',
                'header_text' => 'Need help?',
                'border_radius' => 8,
            ],
        ]);
    }
}
