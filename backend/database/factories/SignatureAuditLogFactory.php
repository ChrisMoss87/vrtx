<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SignatureAuditLog;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SignatureAuditLog>
 */
class SignatureAuditLogFactory extends Factory
{
    protected $model = SignatureAuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventType = $this->faker->randomElement([
            SignatureAuditLog::EVENT_CREATED,
            SignatureAuditLog::EVENT_SENT,
            SignatureAuditLog::EVENT_VIEWED,
            SignatureAuditLog::EVENT_SIGNED,
        ]);

        return [
            'request_id' => SignatureRequest::factory(),
            'signer_id' => null,
            'event_type' => $eventType,
            'event_description' => $this->getDescriptionForEvent($eventType),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'metadata' => [],
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    private function getDescriptionForEvent(string $eventType): string
    {
        return match ($eventType) {
            SignatureAuditLog::EVENT_CREATED => 'Signature request created',
            SignatureAuditLog::EVENT_SENT => 'Signature request sent to signers',
            SignatureAuditLog::EVENT_VIEWED => 'Document viewed by signer',
            SignatureAuditLog::EVENT_SIGNED => 'Document signed by signer',
            SignatureAuditLog::EVENT_DECLINED => 'Signer declined to sign',
            SignatureAuditLog::EVENT_COMPLETED => 'All signatures collected',
            SignatureAuditLog::EVENT_VOIDED => 'Request voided by sender',
            SignatureAuditLog::EVENT_REMINDED => 'Reminder sent to signer',
            SignatureAuditLog::EVENT_EXPIRED => 'Request expired',
            default => 'Event occurred',
        };
    }

    /**
     * Created event.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_CREATED,
            'event_description' => 'Signature request created',
            'signer_id' => null,
        ]);
    }

    /**
     * Sent event.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_SENT,
            'event_description' => 'Signature request sent to signers',
            'signer_id' => null,
        ]);
    }

    /**
     * Viewed event.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_VIEWED,
            'event_description' => 'Document viewed by signer',
            'signer_id' => SignatureSigner::factory(),
        ]);
    }

    /**
     * Signed event.
     */
    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_SIGNED,
            'event_description' => 'Document signed by signer',
            'signer_id' => SignatureSigner::factory(),
            'metadata' => ['ip' => $this->faker->ipv4()],
        ]);
    }

    /**
     * Declined event.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_DECLINED,
            'event_description' => 'Signer declined to sign',
            'signer_id' => SignatureSigner::factory(),
            'metadata' => ['reason' => 'Terms not acceptable'],
        ]);
    }

    /**
     * Completed event.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_COMPLETED,
            'event_description' => 'All signatures collected',
            'signer_id' => null,
        ]);
    }

    /**
     * Voided event.
     */
    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_VOIDED,
            'event_description' => 'Request voided by sender',
            'signer_id' => null,
            'metadata' => ['reason' => 'Deal cancelled'],
        ]);
    }

    /**
     * Reminded event.
     */
    public function reminded(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_REMINDED,
            'event_description' => 'Reminder sent to signer',
            'signer_id' => SignatureSigner::factory(),
        ]);
    }

    /**
     * Expired event.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => SignatureAuditLog::EVENT_EXPIRED,
            'event_description' => 'Request expired',
            'signer_id' => null,
        ]);
    }

    /**
     * For specific signer.
     */
    public function forSigner(SignatureSigner $signer): static
    {
        return $this->state(fn (array $attributes) => [
            'signer_id' => $signer->id,
            'request_id' => $signer->request_id,
        ]);
    }
}
