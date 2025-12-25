<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\MeetingType;
use App\Infrastructure\Persistence\Eloquent\Models\SchedulingPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\MeetingType>
 */
class MeetingTypeFactory extends Factory
{
    protected $model = MeetingType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            '30 Minute Meeting',
            'Discovery Call',
            'Product Demo',
            'Strategy Session',
            'Quick Check-in',
            '1-Hour Consultation',
        ]);

        return [
            'scheduling_page_id' => SchedulingPage::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'duration_minutes' => $this->faker->randomElement([15, 30, 45, 60]),
            'description' => $this->faker->sentence(),
            'location_type' => $this->faker->randomElement(['zoom', 'google_meet', 'phone', 'in_person']),
            'location_details' => null,
            'color' => $this->faker->hexColor(),
            'is_active' => true,
            'questions' => [
                [
                    'type' => 'text',
                    'label' => 'What would you like to discuss?',
                    'required' => false,
                ],
            ],
            'settings' => MeetingType::DEFAULT_SETTINGS,
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Short meeting (15 min).
     */
    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Quick Chat',
            'slug' => 'quick-chat',
            'duration_minutes' => 15,
        ]);
    }

    /**
     * Standard meeting (30 min).
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '30 Minute Meeting',
            'slug' => '30-minute-meeting',
            'duration_minutes' => 30,
        ]);
    }

    /**
     * Long meeting (60 min).
     */
    public function long(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Strategy Session',
            'slug' => 'strategy-session',
            'duration_minutes' => 60,
        ]);
    }

    /**
     * Zoom location.
     */
    public function zoom(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => 'zoom',
        ]);
    }

    /**
     * Phone call location.
     */
    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => 'phone',
        ]);
    }

    /**
     * In-person location.
     */
    public function inPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => 'in_person',
            'location_details' => '123 Main Street, Suite 100',
        ]);
    }

    /**
     * Active meeting type.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive meeting type.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * With custom questions.
     */
    public function withQuestions(): static
    {
        return $this->state(fn (array $attributes) => [
            'questions' => [
                ['type' => 'text', 'label' => 'Company name', 'required' => true],
                ['type' => 'text', 'label' => 'What do you want to discuss?', 'required' => true],
                ['type' => 'select', 'label' => 'Team size', 'required' => false, 'options' => ['1-10', '11-50', '51-200', '200+']],
            ],
        ]);
    }
}
