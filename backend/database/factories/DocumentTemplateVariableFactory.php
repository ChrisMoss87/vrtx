<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Document\Entities\DocumentTemplateVariable;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Document\Entities\DocumentTemplateVariable>
 */
class DocumentTemplateVariableFactory extends Factory
{
    protected $model = DocumentTemplateVariable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->faker->randomElement(DocumentTemplateVariable::CATEGORIES);

        return [
            'name' => $this->getVariableName($category),
            'api_name' => $this->faker->unique()->slug(2),
            'category' => $category,
            'field_path' => $this->getFieldPath($category),
            'default_value' => null,
            'format' => null,
            'is_system' => $this->faker->boolean(30),
        ];
    }

    private function getVariableName(string $category): string
    {
        return match ($category) {
            DocumentTemplateVariable::CATEGORY_CONTACT => $this->faker->randomElement([
                'Contact Name', 'Contact Email', 'Contact Phone', 'Contact Title',
            ]),
            DocumentTemplateVariable::CATEGORY_COMPANY => $this->faker->randomElement([
                'Company Name', 'Company Address', 'Company Phone', 'Company Website',
            ]),
            DocumentTemplateVariable::CATEGORY_DEAL => $this->faker->randomElement([
                'Deal Amount', 'Deal Stage', 'Close Date', 'Deal Name',
            ]),
            DocumentTemplateVariable::CATEGORY_USER => $this->faker->randomElement([
                'User Name', 'User Email', 'User Phone', 'User Title',
            ]),
            DocumentTemplateVariable::CATEGORY_SYSTEM => $this->faker->randomElement([
                'Current Date', 'Current Time', 'Company Logo', 'Document Number',
            ]),
            default => 'Custom Field',
        };
    }

    private function getFieldPath(string $category): string
    {
        return match ($category) {
            DocumentTemplateVariable::CATEGORY_CONTACT => 'contact.' . $this->faker->randomElement(['name', 'email', 'phone']),
            DocumentTemplateVariable::CATEGORY_COMPANY => 'company.' . $this->faker->randomElement(['name', 'address', 'website']),
            DocumentTemplateVariable::CATEGORY_DEAL => 'deal.' . $this->faker->randomElement(['amount', 'stage', 'close_date']),
            DocumentTemplateVariable::CATEGORY_USER => 'user.' . $this->faker->randomElement(['name', 'email', 'phone']),
            DocumentTemplateVariable::CATEGORY_SYSTEM => 'system.' . $this->faker->randomElement(['date', 'time', 'logo']),
            default => 'custom.field',
        };
    }

    /**
     * Contact variable.
     */
    public function contact(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplateVariable::CATEGORY_CONTACT,
            'name' => 'Contact Name',
            'api_name' => 'contact_name',
            'field_path' => 'contact.name',
        ]);
    }

    /**
     * Company variable.
     */
    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplateVariable::CATEGORY_COMPANY,
            'name' => 'Company Name',
            'api_name' => 'company_name',
            'field_path' => 'company.name',
        ]);
    }

    /**
     * Deal variable.
     */
    public function deal(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplateVariable::CATEGORY_DEAL,
            'name' => 'Deal Amount',
            'api_name' => 'deal_amount',
            'field_path' => 'deal.amount',
            'format' => 'currency',
        ]);
    }

    /**
     * System variable.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplateVariable::CATEGORY_SYSTEM,
            'is_system' => true,
        ]);
    }

    /**
     * With date format.
     */
    public function dateFormat(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'date',
            'default_value' => 'N/A',
        ]);
    }

    /**
     * With currency format.
     */
    public function currencyFormat(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'currency',
            'default_value' => '$0.00',
        ]);
    }
}
