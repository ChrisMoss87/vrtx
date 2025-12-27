<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cadence\Entities\Cadence;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Cadence\Entities\Cadence>
 */
class CadenceFactory extends Factory
{
    protected $model = Cadence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'New Lead Outreach',
                'Re-engagement Campaign',
                'Post-Demo Follow-up',
                'Cold Outreach Sequence',
                'Renewal Reminder',
                'Onboarding Welcome',
                'Win-Back Campaign',
            ]) . ' ' . $this->faker->unique()->numberBetween(1, 1000),
            'description' => $this->faker->paragraph(),
            'module_id' => fn () => DB::table('modules')->where('api_name', 'contacts')->first()?->id ?? DB::table('modules')->first()?->id,
            'status' => $this->faker->randomElement([
                Cadence::STATUS_DRAFT,
                Cadence::STATUS_ACTIVE,
                Cadence::STATUS_PAUSED,
            ]),
            'entry_criteria' => [],
            'exit_criteria' => [],
            'settings' => [
                'send_window' => [
                    'start' => '09:00',
                    'end' => '17:00',
                ],
                'timezone' => 'America/New_York',
                'exclude_weekends' => true,
            ],
            'auto_enroll' => $this->faker->boolean(30),
            'allow_re_enrollment' => $this->faker->boolean(20),
            're_enrollment_days' => $this->faker->numberBetween(30, 90),
            'max_enrollments_per_day' => $this->faker->numberBetween(50, 200),
            'created_by' => User::factory(),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Active cadence state.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Cadence::STATUS_ACTIVE,
        ]);
    }

    /**
     * Draft cadence state.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Cadence::STATUS_DRAFT,
        ]);
    }

    /**
     * Paused cadence state.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Cadence::STATUS_PAUSED,
        ]);
    }

    /**
     * Auto-enroll enabled.
     */
    public function autoEnroll(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_enroll' => true,
            'entry_criteria' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'new'],
            ],
        ]);
    }
}
