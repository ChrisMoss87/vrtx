<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cadence\Entities\CadenceStep;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Cadence\Entities\CadenceStep>
 */
class CadenceStepFactory extends Factory
{
    protected $model = CadenceStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channel = $this->faker->randomElement([
            CadenceStep::CHANNEL_EMAIL,
            CadenceStep::CHANNEL_CALL,
            CadenceStep::CHANNEL_SMS,
            CadenceStep::CHANNEL_TASK,
        ]);

        return [
            'cadence_id' => Cadence::factory(),
            'step_order' => $this->faker->numberBetween(1, 10),
            'name' => $this->faker->randomElement([
                'Initial Outreach',
                'Follow-up Email',
                'Check-in Call',
                'Breakup Message',
                'Value Proposition',
                'Case Study Share',
            ]),
            'channel' => $channel,
            'delay_type' => $this->faker->randomElement([
                CadenceStep::DELAY_IMMEDIATE,
                CadenceStep::DELAY_DAYS,
                CadenceStep::DELAY_HOURS,
                CadenceStep::DELAY_BUSINESS_DAYS,
            ]),
            'delay_value' => $this->faker->numberBetween(1, 7),
            'preferred_time' => $this->faker->time('H:i'),
            'timezone' => 'America/New_York',
            'subject' => $channel === CadenceStep::CHANNEL_EMAIL
                ? $this->faker->randomElement([
                    'Quick question about {{company_name}}',
                    'Following up - {{first_name}}',
                    'Thought you might find this interesting',
                    'Re: Our conversation',
                ])
                : null,
            'content' => $this->generateContent($channel),
            'template_id' => null,
            'conditions' => [],
            'is_ab_test' => false,
            'is_active' => true,
        ];
    }

    private function generateContent(string $channel): string
    {
        return match ($channel) {
            CadenceStep::CHANNEL_EMAIL => "Hi {{first_name}},\n\n" . $this->faker->paragraphs(2, true) . "\n\nBest regards,\n{{sender_name}}",
            CadenceStep::CHANNEL_SMS => $this->faker->sentence() . " - {{sender_name}}",
            CadenceStep::CHANNEL_CALL => "Call script:\n- Introduction\n- Discovery questions\n- Value proposition\n- Next steps",
            default => $this->faker->paragraph(),
        };
    }

    /**
     * Email step state.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CadenceStep::CHANNEL_EMAIL,
            'subject' => 'Following up on our conversation',
            'content' => "Hi {{first_name}},\n\n" . $this->faker->paragraphs(2, true) . "\n\nBest,\n{{sender_name}}",
        ]);
    }

    /**
     * Call step state.
     */
    public function call(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CadenceStep::CHANNEL_CALL,
            'subject' => null,
            'content' => "Call script:\n1. Introduction\n2. Qualifying questions\n3. Present solution\n4. Handle objections\n5. Schedule next step",
        ]);
    }

    /**
     * SMS step state.
     */
    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CadenceStep::CHANNEL_SMS,
            'subject' => null,
            'content' => "Hi {{first_name}}, quick follow-up on my email. Would love to connect this week. - {{sender_name}}",
        ]);
    }

    /**
     * LinkedIn step state.
     */
    public function linkedin(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CadenceStep::CHANNEL_LINKEDIN,
            'linkedin_action' => CadenceStep::LINKEDIN_CONNECTION_REQUEST,
            'content' => "Hi {{first_name}}, I noticed we're both in the {{industry}} space. Would love to connect!",
        ]);
    }

    /**
     * A/B test variant.
     */
    public function abTest(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ab_test' => true,
            'ab_percentage' => 50,
        ]);
    }
}
