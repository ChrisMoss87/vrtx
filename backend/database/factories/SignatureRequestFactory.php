<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GeneratedDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SignatureRequest>
 */
class SignatureRequestFactory extends Factory
{
    protected $model = SignatureRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'title' => $this->faker->randomElement([
                'Contract Signature Request',
                'NDA Signature Required',
                'Agreement for Review and Signature',
                'Sales Contract',
                'Partnership Agreement',
            ]),
            'description' => $this->faker->sentence(),
            'document_id' => GeneratedDocument::factory(),
            'source_type' => $this->faker->randomElement([
                SignatureRequest::SOURCE_QUOTE,
                SignatureRequest::SOURCE_PROPOSAL,
                SignatureRequest::SOURCE_CUSTOM,
            ]),
            'source_id' => $this->faker->numberBetween(1, 100),
            'file_path' => 'signatures/' . $this->faker->uuid() . '.pdf',
            'file_url' => $this->faker->url(),
            'signed_file_path' => null,
            'signed_file_url' => null,
            'status' => $this->faker->randomElement(SignatureRequest::STATUSES),
            'sent_at' => null,
            'completed_at' => null,
            'expires_at' => $this->faker->optional(0.7)->dateTimeBetween('+7 days', '+30 days'),
            'voided_at' => null,
            'void_reason' => null,
            'settings' => [
                'reminder_enabled' => true,
                'reminder_days' => 3,
                'sequential_signing' => true,
            ],
            'external_provider' => null,
            'external_id' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureRequest::STATUS_DRAFT,
            'sent_at' => null,
        ]);
    }

    /**
     * Pending status (sent but not started).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureRequest::STATUS_PENDING,
            'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * In progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureRequest::STATUS_IN_PROGRESS,
            'sent_at' => $this->faker->dateTimeBetween('-14 days', '-7 days'),
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-30 days', '-7 days');

        return $this->state(fn (array $attributes) => [
            'status' => SignatureRequest::STATUS_COMPLETED,
            'sent_at' => $sentAt,
            'completed_at' => $this->faker->dateTimeBetween($sentAt, 'now'),
            'signed_file_path' => 'signatures/signed/' . $this->faker->uuid() . '.pdf',
            'signed_file_url' => $this->faker->url(),
        ]);
    }

    /**
     * Declined status.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureRequest::STATUS_DECLINED,
            'sent_at' => $this->faker->dateTimeBetween('-14 days', '-3 days'),
        ]);
    }

    /**
     * Expired status.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureRequest::STATUS_EXPIRED,
            'sent_at' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
            'expires_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    /**
     * Voided status.
     */
    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureRequest::STATUS_VOIDED,
            'sent_at' => $this->faker->dateTimeBetween('-30 days', '-7 days'),
            'voided_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'void_reason' => $this->faker->randomElement([
                'Deal cancelled',
                'Incorrect document',
                'Terms changed',
                'Superseded by new agreement',
            ]),
        ]);
    }

    /**
     * From quote source.
     */
    public function fromQuote(int $quoteId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => SignatureRequest::SOURCE_QUOTE,
            'source_id' => $quoteId ?? $this->faker->numberBetween(1, 100),
            'title' => 'Quote Signature Request',
        ]);
    }

    /**
     * From proposal source.
     */
    public function fromProposal(int $proposalId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => SignatureRequest::SOURCE_PROPOSAL,
            'source_id' => $proposalId ?? $this->faker->numberBetween(1, 100),
            'title' => 'Proposal Signature Request',
        ]);
    }

    /**
     * With signers relationship.
     */
    public function withSigners(int $count = 2): static
    {
        return $this->has(
            SignatureSigner::factory()->count($count),
            'signers'
        );
    }

    /**
     * With sequential signing.
     */
    public function sequential(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'sequential_signing' => true,
            ]),
        ]);
    }

    /**
     * With parallel signing.
     */
    public function parallel(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'sequential_signing' => false,
            ]),
        ]);
    }
}
