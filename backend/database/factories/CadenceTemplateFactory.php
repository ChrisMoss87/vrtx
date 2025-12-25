<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CadenceTemplate>
 */
class CadenceTemplateFactory extends Factory
{
    protected $model = CadenceTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Cold Outreach Standard',
                'Enterprise Sales Sequence',
                'SMB Quick Touch',
                'Re-engagement Drip',
                'Event Follow-up',
                'Inbound Lead Nurture',
            ]),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement([
                'prospecting',
                'nurturing',
                'renewal',
            'onboarding',
                'win-back',
            ]),
            'steps_config' => $this->generateStepsConfig(),
            'settings' => [
                'send_window' => ['start' => '09:00', 'end' => '17:00'],
                'timezone' => 'America/New_York',
                'exclude_weekends' => true,
            ],
            'is_system' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    private function generateStepsConfig(): array
    {
        return [
            [
                'name' => 'Initial Outreach',
                'channel' => 'email',
                'delay_type' => 'immediate',
                'delay_value' => 0,
                'subject' => 'Quick question about {{company_name}}',
                'content' => "Hi {{first_name}},\n\nI noticed {{company_name}} is growing rapidly. I'd love to share how we've helped similar companies.\n\nWould you be open to a brief call?\n\nBest,\n{{sender_name}}",
            ],
            [
                'name' => 'Follow-up',
                'channel' => 'email',
                'delay_type' => 'business_days',
                'delay_value' => 2,
                'subject' => 'Re: Quick question',
                'content' => "Hi {{first_name}},\n\nJust following up on my previous email. I know you're busy, but I think this could be valuable for {{company_name}}.\n\nBest,\n{{sender_name}}",
            ],
            [
                'name' => 'Call Attempt',
                'channel' => 'call',
                'delay_type' => 'business_days',
                'delay_value' => 2,
                'content' => "Call script: Introduction, value prop, ask for meeting",
            ],
            [
                'name' => 'LinkedIn Touch',
                'channel' => 'linkedin',
                'delay_type' => 'days',
                'delay_value' => 1,
                'content' => "Connect and engage with their recent post",
            ],
            [
                'name' => 'Breakup Email',
                'channel' => 'email',
                'delay_type' => 'business_days',
                'delay_value' => 3,
                'subject' => 'Should I close your file?',
                'content' => "Hi {{first_name}},\n\nI haven't heard back, so I'll assume the timing isn't right. If things change, feel free to reach out.\n\nBest,\n{{sender_name}}",
            ],
        ];
    }

    /**
     * System template state.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Prospecting category.
     */
    public function prospecting(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'prospecting',
            'name' => 'Prospecting Sequence',
        ]);
    }

    /**
     * Nurturing category.
     */
    public function nurturing(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'nurturing',
            'name' => 'Lead Nurturing Sequence',
        ]);
    }
}
