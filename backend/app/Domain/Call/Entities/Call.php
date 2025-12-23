<?php

declare(strict_types=1);

namespace App\Domain\Call\Entities;

use App\Domain\Call\ValueObjects\CallDirection;
use App\Domain\Call\ValueObjects\CallStatus;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

/**
 * Call domain entity representing a telephony call.
 *
 * Calls can be inbound or outbound, and track duration, recording,
 * and outcome for CRM integration.
 */
final class Call implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private ?int $providerId,
        private ?string $externalCallId,
        private CallDirection $direction,
        private CallStatus $status,
        private string $fromNumber,
        private string $toNumber,
        private ?int $userId,
        private ?int $contactId,
        private ?string $contactModule,
        private ?int $durationSeconds,
        private ?int $ringDurationSeconds,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $answeredAt,
        private ?DateTimeImmutable $endedAt,
        private ?string $recordingUrl,
        private ?string $recordingSid,
        private ?int $recordingDurationSeconds,
        private ?string $recordingStatus,
        private ?string $notes,
        private ?string $outcome,
        private array $customFields,
        private array $metadata,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Create a new outbound call.
     */
    public static function createOutbound(
        int $userId,
        string $fromNumber,
        string $toNumber,
        ?int $contactId = null,
        ?string $contactModule = null,
    ): self {
        return new self(
            id: null,
            providerId: null,
            externalCallId: null,
            direction: CallDirection::Outbound,
            status: CallStatus::Initiated,
            fromNumber: $fromNumber,
            toNumber: $toNumber,
            userId: $userId,
            contactId: $contactId,
            contactModule: $contactModule,
            durationSeconds: null,
            ringDurationSeconds: null,
            startedAt: new DateTimeImmutable(),
            answeredAt: null,
            endedAt: null,
            recordingUrl: null,
            recordingSid: null,
            recordingDurationSeconds: null,
            recordingStatus: null,
            notes: null,
            outcome: null,
            customFields: [],
            metadata: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Create a new inbound call.
     */
    public static function createInbound(
        string $fromNumber,
        string $toNumber,
        ?int $userId = null,
    ): self {
        return new self(
            id: null,
            providerId: null,
            externalCallId: null,
            direction: CallDirection::Inbound,
            status: CallStatus::Ringing,
            fromNumber: $fromNumber,
            toNumber: $toNumber,
            userId: $userId,
            contactId: null,
            contactModule: null,
            durationSeconds: null,
            ringDurationSeconds: null,
            startedAt: new DateTimeImmutable(),
            answeredAt: null,
            endedAt: null,
            recordingUrl: null,
            recordingSid: null,
            recordingDurationSeconds: null,
            recordingStatus: null,
            notes: null,
            outcome: null,
            customFields: [],
            metadata: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute a call from persistence.
     */
    public static function reconstitute(
        int $id,
        ?int $providerId,
        ?string $externalCallId,
        CallDirection $direction,
        CallStatus $status,
        string $fromNumber,
        string $toNumber,
        ?int $userId,
        ?int $contactId,
        ?string $contactModule,
        ?int $durationSeconds,
        ?int $ringDurationSeconds,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $answeredAt,
        ?DateTimeImmutable $endedAt,
        ?string $recordingUrl,
        ?string $recordingSid,
        ?int $recordingDurationSeconds,
        ?string $recordingStatus,
        ?string $notes,
        ?string $outcome,
        array $customFields,
        array $metadata,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            providerId: $providerId,
            externalCallId: $externalCallId,
            direction: $direction,
            status: $status,
            fromNumber: $fromNumber,
            toNumber: $toNumber,
            userId: $userId,
            contactId: $contactId,
            contactModule: $contactModule,
            durationSeconds: $durationSeconds,
            ringDurationSeconds: $ringDurationSeconds,
            startedAt: $startedAt,
            answeredAt: $answeredAt,
            endedAt: $endedAt,
            recordingUrl: $recordingUrl,
            recordingSid: $recordingSid,
            recordingDurationSeconds: $recordingDurationSeconds,
            recordingStatus: $recordingStatus,
            notes: $notes,
            outcome: $outcome,
            customFields: $customFields,
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Business Logic Methods
    // -------------------------------------------------------------------------

    /**
     * Check if call is inbound.
     */
    public function isInbound(): bool
    {
        return $this->direction === CallDirection::Inbound;
    }

    /**
     * Check if call is outbound.
     */
    public function isOutbound(): bool
    {
        return $this->direction === CallDirection::Outbound;
    }

    /**
     * Check if call is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    /**
     * Check if call was missed.
     */
    public function isMissed(): bool
    {
        return $this->status->isMissed();
    }

    /**
     * Check if call is currently active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if call has a recording.
     */
    public function hasRecording(): bool
    {
        return !empty($this->recordingUrl);
    }

    /**
     * Get the formatted duration string (e.g., "5:32").
     */
    public function getFormattedDuration(): string
    {
        if ($this->durationSeconds === null || $this->durationSeconds === 0) {
            return '0:00';
        }

        $minutes = intdiv($this->durationSeconds, 60);
        $seconds = $this->durationSeconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Mark call as answered.
     *
     * @return self Returns a new instance with answered state
     */
    public function markAnswered(): self
    {
        return new self(
            id: $this->id,
            providerId: $this->providerId,
            externalCallId: $this->externalCallId,
            direction: $this->direction,
            status: CallStatus::InProgress,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $this->contactId,
            contactModule: $this->contactModule,
            durationSeconds: $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: new DateTimeImmutable(),
            endedAt: $this->endedAt,
            recordingUrl: $this->recordingUrl,
            recordingSid: $this->recordingSid,
            recordingDurationSeconds: $this->recordingDurationSeconds,
            recordingStatus: $this->recordingStatus,
            notes: $this->notes,
            outcome: $this->outcome,
            customFields: $this->customFields,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Mark call as completed.
     *
     * @return self Returns a new instance with completed state
     */
    public function markCompleted(?int $durationSeconds = null): self
    {
        return new self(
            id: $this->id,
            providerId: $this->providerId,
            externalCallId: $this->externalCallId,
            direction: $this->direction,
            status: CallStatus::Completed,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $this->contactId,
            contactModule: $this->contactModule,
            durationSeconds: $durationSeconds ?? $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: $this->answeredAt,
            endedAt: new DateTimeImmutable(),
            recordingUrl: $this->recordingUrl,
            recordingSid: $this->recordingSid,
            recordingDurationSeconds: $this->recordingDurationSeconds,
            recordingStatus: $this->recordingStatus,
            notes: $this->notes,
            outcome: $this->outcome,
            customFields: $this->customFields,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Mark call as missed (no answer).
     *
     * @return self Returns a new instance with missed state
     */
    public function markMissed(CallStatus $reason = CallStatus::NoAnswer): self
    {
        return new self(
            id: $this->id,
            providerId: $this->providerId,
            externalCallId: $this->externalCallId,
            direction: $this->direction,
            status: $reason,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $this->contactId,
            contactModule: $this->contactModule,
            durationSeconds: $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: $this->answeredAt,
            endedAt: new DateTimeImmutable(),
            recordingUrl: $this->recordingUrl,
            recordingSid: $this->recordingSid,
            recordingDurationSeconds: $this->recordingDurationSeconds,
            recordingStatus: $this->recordingStatus,
            notes: $this->notes,
            outcome: $this->outcome,
            customFields: $this->customFields,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Link call to a contact.
     *
     * @return self Returns a new instance with contact linked
     */
    public function linkToContact(int $contactId, string $module = 'contacts'): self
    {
        return new self(
            id: $this->id,
            providerId: $this->providerId,
            externalCallId: $this->externalCallId,
            direction: $this->direction,
            status: $this->status,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $contactId,
            contactModule: $module,
            durationSeconds: $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: $this->answeredAt,
            endedAt: $this->endedAt,
            recordingUrl: $this->recordingUrl,
            recordingSid: $this->recordingSid,
            recordingDurationSeconds: $this->recordingDurationSeconds,
            recordingStatus: $this->recordingStatus,
            notes: $this->notes,
            outcome: $this->outcome,
            customFields: $this->customFields,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Log call outcome and notes.
     *
     * @return self Returns a new instance with outcome logged
     */
    public function logOutcome(string $outcome, ?string $notes = null): self
    {
        return new self(
            id: $this->id,
            providerId: $this->providerId,
            externalCallId: $this->externalCallId,
            direction: $this->direction,
            status: $this->status,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $this->contactId,
            contactModule: $this->contactModule,
            durationSeconds: $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: $this->answeredAt,
            endedAt: $this->endedAt,
            recordingUrl: $this->recordingUrl,
            recordingSid: $this->recordingSid,
            recordingDurationSeconds: $this->recordingDurationSeconds,
            recordingStatus: $this->recordingStatus,
            notes: $notes ?? $this->notes,
            outcome: $outcome,
            customFields: $this->customFields,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Attach recording information.
     *
     * @return self Returns a new instance with recording attached
     */
    public function attachRecording(
        string $recordingUrl,
        ?string $recordingSid = null,
        ?int $recordingDurationSeconds = null,
    ): self {
        return new self(
            id: $this->id,
            providerId: $this->providerId,
            externalCallId: $this->externalCallId,
            direction: $this->direction,
            status: $this->status,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $this->contactId,
            contactModule: $this->contactModule,
            durationSeconds: $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: $this->answeredAt,
            endedAt: $this->endedAt,
            recordingUrl: $recordingUrl,
            recordingSid: $recordingSid,
            recordingDurationSeconds: $recordingDurationSeconds,
            recordingStatus: 'completed',
            notes: $this->notes,
            outcome: $this->outcome,
            customFields: $this->customFields,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Update custom fields.
     *
     * @return self Returns a new instance with updated custom fields
     */
    public function withCustomFields(array $customFields): self
    {
        return new self(
            id: $this->id,
            providerId: $this->providerId,
            externalCallId: $this->externalCallId,
            direction: $this->direction,
            status: $this->status,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $this->contactId,
            contactModule: $this->contactModule,
            durationSeconds: $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: $this->answeredAt,
            endedAt: $this->endedAt,
            recordingUrl: $this->recordingUrl,
            recordingSid: $this->recordingSid,
            recordingDurationSeconds: $this->recordingDurationSeconds,
            recordingStatus: $this->recordingStatus,
            notes: $this->notes,
            outcome: $this->outcome,
            customFields: array_merge($this->customFields, $customFields),
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Set provider information.
     *
     * @return self Returns a new instance with provider set
     */
    public function withProvider(int $providerId, string $externalCallId): self
    {
        return new self(
            id: $this->id,
            providerId: $providerId,
            externalCallId: $externalCallId,
            direction: $this->direction,
            status: $this->status,
            fromNumber: $this->fromNumber,
            toNumber: $this->toNumber,
            userId: $this->userId,
            contactId: $this->contactId,
            contactModule: $this->contactModule,
            durationSeconds: $this->durationSeconds,
            ringDurationSeconds: $this->ringDurationSeconds,
            startedAt: $this->startedAt,
            answeredAt: $this->answeredAt,
            endedAt: $this->endedAt,
            recordingUrl: $this->recordingUrl,
            recordingSid: $this->recordingSid,
            recordingDurationSeconds: $this->recordingDurationSeconds,
            recordingStatus: $this->recordingStatus,
            notes: $this->notes,
            outcome: $this->outcome,
            customFields: $this->customFields,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderId(): ?int
    {
        return $this->providerId;
    }

    public function getExternalCallId(): ?string
    {
        return $this->externalCallId;
    }

    public function getDirection(): CallDirection
    {
        return $this->direction;
    }

    public function getStatus(): CallStatus
    {
        return $this->status;
    }

    public function getFromNumber(): string
    {
        return $this->fromNumber;
    }

    public function getToNumber(): string
    {
        return $this->toNumber;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }

    public function getContactModule(): ?string
    {
        return $this->contactModule;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function getRingDurationSeconds(): ?int
    {
        return $this->ringDurationSeconds;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getAnsweredAt(): ?DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function getEndedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function getRecordingUrl(): ?string
    {
        return $this->recordingUrl;
    }

    public function getRecordingSid(): ?string
    {
        return $this->recordingSid;
    }

    public function getRecordingDurationSeconds(): ?int
    {
        return $this->recordingDurationSeconds;
    }

    public function getRecordingStatus(): ?string
    {
        return $this->recordingStatus;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
