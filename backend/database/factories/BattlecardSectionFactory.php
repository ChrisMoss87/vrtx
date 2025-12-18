<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BattlecardSection;
use App\Models\Competitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BattlecardSection>
 */
class BattlecardSectionFactory extends Factory
{
    protected $model = BattlecardSection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sectionTypes = [
            BattlecardSection::TYPE_STRENGTHS => "- Strong brand recognition\n- Large ecosystem of integrations\n- Comprehensive feature set",
            BattlecardSection::TYPE_WEAKNESSES => "- Complex pricing structure\n- Steep learning curve\n- Long implementation cycles",
            BattlecardSection::TYPE_OUR_ADVANTAGES => "- 50% faster implementation\n- More intuitive UI\n- Better mobile experience\n- Transparent pricing",
            BattlecardSection::TYPE_PRICING => "Professional: $75/user/month\nEnterprise: $150/user/month\nAdd-ons: API access +$50/mo, Advanced reporting +$30/mo",
            BattlecardSection::TYPE_RESOURCES => "- Competitor overview deck (link)\n- ROI calculator (link)\n- Customer testimonials (link)",
            BattlecardSection::TYPE_WIN_STORIES => "- Acme Corp switched and saw 40% productivity gain\n- TechCo reduced costs by $50K annually\n- GlobalInc improved win rates by 25%",
        ];

        $type = $this->faker->randomElement(array_keys($sectionTypes));

        return [
            'competitor_id' => Competitor::factory(),
            'section_type' => $type,
            'content' => $sectionTypes[$type],
            'display_order' => $this->faker->numberBetween(1, 6),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Strengths section.
     */
    public function strengths(): static
    {
        return $this->state(fn (array $attributes) => [
            'section_type' => BattlecardSection::TYPE_STRENGTHS,
            'content' => "- Strong brand recognition\n- Large partner ecosystem\n- Comprehensive feature set\n- Good enterprise scalability",
            'display_order' => 1,
        ]);
    }

    /**
     * Weaknesses section.
     */
    public function weaknesses(): static
    {
        return $this->state(fn (array $attributes) => [
            'section_type' => BattlecardSection::TYPE_WEAKNESSES,
            'content' => "- Complex and expensive\n- Poor mobile experience\n- Slow support response\n- Difficult customization",
            'display_order' => 2,
        ]);
    }

    /**
     * Our advantages section.
     */
    public function ourAdvantages(): static
    {
        return $this->state(fn (array $attributes) => [
            'section_type' => BattlecardSection::TYPE_OUR_ADVANTAGES,
            'content' => "- 50% faster implementation\n- Better mobile app\n- Included support\n- Simpler pricing",
            'display_order' => 3,
        ]);
    }
}
