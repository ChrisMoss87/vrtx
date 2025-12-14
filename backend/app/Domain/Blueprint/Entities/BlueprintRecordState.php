<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

use App\Domain\Blueprint\ValueObjects\SlaStatus;

/**
 * Represents the current state of a record within a blueprint.
 */
class BlueprintRecordState
{
    private ?int $id = null;
    private int $blueprintId;
    private int $recordId;
    private int $currentStateId;
    private ?\DateTimeImmutable $enteredStateAt;
    private ?int $slaInstanceId;
    private array $metadata;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(
        int $blueprintId,
        int $recordId,
        int $currentStateId,
        ?\DateTimeImmutable $enteredStateAt = null,
        ?int $slaInstanceId = null,
        array $metadata = [],
    ) {
        $this->blueprintId = $blueprintId;
        $this->recordId = $recordId;
        $this->currentStateId = $currentStateId;
        $this->enteredStateAt = $enteredStateAt ?? new \DateTimeImmutable();
        $this->slaInstanceId = $slaInstanceId;
        $this->metadata = $metadata;
    }

    public static function create(
        int $blueprintId,
        int $recordId,
        int $currentStateId,
    ): self {
        return new self(
            blueprintId: $blueprintId,
            recordId: $recordId,
            currentStateId: $currentStateId,
        );
    }

    public static function reconstitute(
        int $id,
        int $blueprintId,
        int $recordId,
        int $currentStateId,
        \DateTimeImmutable $enteredStateAt,
        ?int $slaInstanceId,
        array $metadata,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $recordState = new self(
            blueprintId: $blueprintId,
            recordId: $recordId,
            currentStateId: $currentStateId,
            enteredStateAt: $enteredStateAt,
            slaInstanceId: $slaInstanceId,
            metadata: $metadata,
        );
        $recordState->id = $id;
        $recordState->createdAt = $createdAt;
        $recordState->updatedAt = $updatedAt;

        return $recordState;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlueprintId(): int
    {
        return $this->blueprintId;
    }

    public function getRecordId(): int
    {
        return $this->recordId;
    }

    public function getCurrentStateId(): int
    {
        return $this->currentStateId;
    }

    public function getEnteredStateAt(): \DateTimeImmutable
    {
        return $this->enteredStateAt;
    }

    public function getSlaInstanceId(): ?int
    {
        return $this->slaInstanceId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Domain methods
    public function transitionTo(int $newStateId, ?int $slaInstanceId = null): void
    {
        $this->currentStateId = $newStateId;
        $this->enteredStateAt = new \DateTimeImmutable();
        $this->slaInstanceId = $slaInstanceId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setSlaInstance(?int $slaInstanceId): void
    {
        $this->slaInstanceId = $slaInstanceId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Get hours elapsed in current state.
     */
    public function getHoursInCurrentState(): int
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->enteredStateAt);
        return (int) (($diff->days * 24) + $diff->h);
    }
}
