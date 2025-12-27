<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Document\Entities\GeneratedDocument;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Document\Entities\GeneratedDocument>
 */
class GeneratedDocumentFactory extends Factory
{
    protected $model = GeneratedDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = DocumentTemplate::factory();

        return [
            'template_id' => $template,
            'record_type' => $this->faker->randomElement(['deals', 'contacts', 'quotes']),
            'record_id' => $this->faker->numberBetween(1, 100),
            'name' => $this->faker->randomElement([
                'Contract - Acme Corp',
                'Proposal - Tech Solutions',
                'Quote - Enterprise Deal',
                'Agreement - Partnership',
                'Invoice - Q4 Services',
            ]),
            'output_format' => $this->faker->randomElement(['pdf', 'docx']),
            'file_path' => 'documents/' . $this->faker->uuid() . '.pdf',
            'file_url' => $this->faker->url(),
            'file_size' => $this->faker->numberBetween(50000, 5000000),
            'merged_data' => [
                'contact_name' => $this->faker->name(),
                'company_name' => $this->faker->company(),
                'deal_amount' => $this->faker->randomFloat(2, 10000, 500000),
                'today_date' => now()->format('Y-m-d'),
            ],
            'status' => $this->faker->randomElement(GeneratedDocument::STATUSES),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Generated status.
     */
    public function generated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GeneratedDocument::STATUS_GENERATED,
        ]);
    }

    /**
     * Sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GeneratedDocument::STATUS_SENT,
        ]);
    }

    /**
     * Viewed status.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GeneratedDocument::STATUS_VIEWED,
        ]);
    }

    /**
     * Signed status.
     */
    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GeneratedDocument::STATUS_SIGNED,
        ]);
    }

    /**
     * For a deal record.
     */
    public function forDeal(int $dealId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'record_type' => 'deals',
            'record_id' => $dealId ?? $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * For a contact record.
     */
    public function forContact(int $contactId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'record_type' => 'contacts',
            'record_id' => $contactId ?? $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * For a quote record.
     */
    public function forQuote(int $quoteId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'record_type' => 'quotes',
            'record_id' => $quoteId ?? $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * PDF format.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'output_format' => 'pdf',
            'file_path' => 'documents/' . $this->faker->uuid() . '.pdf',
        ]);
    }

    /**
     * DOCX format.
     */
    public function docx(): static
    {
        return $this->state(fn (array $attributes) => [
            'output_format' => 'docx',
            'file_path' => 'documents/' . $this->faker->uuid() . '.docx',
        ]);
    }
}
