<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->faker->randomElement(DocumentTemplate::CATEGORIES);

        return [
            'name' => $this->getTemplateName($category),
            'category' => $category,
            'description' => $this->faker->sentence(),
            'content' => $this->getTemplateContent($category),
            'merge_fields' => [
                ['name' => 'contact_name', 'path' => 'contact.name'],
                ['name' => 'company_name', 'path' => 'company.name'],
                ['name' => 'deal_amount', 'path' => 'deal.amount'],
                ['name' => 'today_date', 'path' => 'system.date'],
            ],
            'conditional_blocks' => [
                ['condition' => 'deal.amount > 10000', 'block' => 'enterprise_terms'],
            ],
            'output_format' => $this->faker->randomElement(DocumentTemplate::OUTPUT_FORMATS),
            'page_settings' => [
                'size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 20, 'bottom' => 20, 'left' => 25, 'right' => 25],
            ],
            'header_settings' => [
                'show_logo' => true,
                'show_page_number' => false,
            ],
            'footer_settings' => [
                'show_page_number' => true,
                'text' => 'Confidential',
            ],
            'thumbnail_url' => null,
            'is_active' => true,
            'is_shared' => $this->faker->boolean(60),
            'created_by' => User::factory(),
            'updated_by' => null,
            'version' => 1,
        ];
    }

    private function getTemplateName(string $category): string
    {
        return match ($category) {
            DocumentTemplate::CATEGORY_CONTRACT => $this->faker->randomElement([
                'Master Services Agreement',
                'Software License Agreement',
                'NDA Template',
                'Employment Contract',
            ]),
            DocumentTemplate::CATEGORY_PROPOSAL => $this->faker->randomElement([
                'Business Proposal',
                'Project Proposal',
                'Partnership Proposal',
            ]),
            DocumentTemplate::CATEGORY_QUOTE => $this->faker->randomElement([
                'Standard Quote',
                'Enterprise Quote',
                'Service Quote',
            ]),
            DocumentTemplate::CATEGORY_INVOICE => $this->faker->randomElement([
                'Standard Invoice',
                'Recurring Invoice',
                'Project Invoice',
            ]),
            DocumentTemplate::CATEGORY_LETTER => $this->faker->randomElement([
                'Welcome Letter',
                'Thank You Letter',
                'Follow-up Letter',
            ]),
            DocumentTemplate::CATEGORY_AGREEMENT => $this->faker->randomElement([
                'Service Level Agreement',
                'Partnership Agreement',
                'Subscription Agreement',
            ]),
            default => 'Custom Document Template',
        };
    }

    private function getTemplateContent(string $category): string
    {
        return match ($category) {
            DocumentTemplate::CATEGORY_CONTRACT => '<h1>{{company_name}}</h1><p>This agreement is entered into by and between {{company_name}} and {{contact_name}}...</p>',
            DocumentTemplate::CATEGORY_PROPOSAL => '<h1>Proposal for {{contact_name}}</h1><p>We are pleased to submit this proposal for your consideration...</p>',
            DocumentTemplate::CATEGORY_QUOTE => '<h1>Quote</h1><p>Prepared for: {{contact_name}}</p><p>Total: {{deal_amount}}</p>',
            DocumentTemplate::CATEGORY_INVOICE => '<h1>Invoice</h1><p>Bill to: {{contact_name}}</p><p>Date: {{today_date}}</p>',
            default => '<h1>Document</h1><p>Prepared for {{contact_name}} on {{today_date}}</p>',
        };
    }

    /**
     * Contract template.
     */
    public function contract(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplate::CATEGORY_CONTRACT,
            'name' => 'Master Services Agreement',
        ]);
    }

    /**
     * Proposal template.
     */
    public function proposal(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplate::CATEGORY_PROPOSAL,
            'name' => 'Business Proposal Template',
        ]);
    }

    /**
     * Quote template.
     */
    public function quote(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplate::CATEGORY_QUOTE,
            'name' => 'Standard Quote Template',
        ]);
    }

    /**
     * Invoice template.
     */
    public function invoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => DocumentTemplate::CATEGORY_INVOICE,
            'name' => 'Standard Invoice Template',
        ]);
    }

    /**
     * Shared template.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shared' => true,
        ]);
    }

    /**
     * Inactive template.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * PDF output.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'output_format' => DocumentTemplate::OUTPUT_PDF,
        ]);
    }

    /**
     * DOCX output.
     */
    public function docx(): static
    {
        return $this->state(fn (array $attributes) => [
            'output_format' => DocumentTemplate::OUTPUT_DOCX,
        ]);
    }
}
