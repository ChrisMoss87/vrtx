<?php

declare(strict_types=1);

namespace App\Domain\Contract\Entities;

use App\Domain\Contract\ValueObjects\BillingFrequency;
use App\Domain\Contract\ValueObjects\ContractStatus;
use App\Domain\Contract\ValueObjects\RenewalStatus;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

/**
 * Contract domain entity representing a business contract.
 *
 * Contracts track agreements with customers or vendors, including
 * value, terms, renewal dates, and billing frequency.
 */
final class Contract implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $contractNumber,
        private ?string $relatedModule,
        private ?int $relatedId,
        private ?string $type,
        private ContractStatus $status,
        private ?string $value,
        private string $currency,
        private ?BillingFrequency $billingFrequency,
        private ?DateTimeImmutable $startDate,
        private ?DateTimeImmutable $endDate,
        private ?DateTimeImmutable $renewalDate,
        private int $renewalNoticeDays,
        private bool $autoRenew,
        private ?RenewalStatus $renewalStatus,
        private ?int $ownerId,
        private ?string $terms,
        private ?string $notes,
        private array $customFields,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create a new contract.
     */
    public static function create(
        string $name,
        int $ownerId,
        ?string $type = null,
        string $currency = 'USD',
    ): self {
        return new self(
            id: null,
            name: $name,
            contractNumber: null,
            relatedModule: null,
            relatedId: null,
            type: $type,
            status: ContractStatus::Draft,
            value: null,
            currency: $currency,
            billingFrequency: null,
            startDate: null,
            endDate: null,
            renewalDate: null,
            renewalNoticeDays: 30,
            autoRenew: false,
            renewalStatus: RenewalStatus::NotApplicable,
            ownerId: $ownerId,
            terms: null,
            notes: null,
            customFields: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Reconstitute a contract from persistence.
     */
    public static function reconstitute(
        int $id,
        string $name,
        ?string $contractNumber,
        ?string $relatedModule,
        ?int $relatedId,
        ?string $type,
        ContractStatus $status,
        ?string $value,
        string $currency,
        ?BillingFrequency $billingFrequency,
        ?DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate,
        ?DateTimeImmutable $renewalDate,
        int $renewalNoticeDays,
        bool $autoRenew,
        ?RenewalStatus $renewalStatus,
        ?int $ownerId,
        ?string $terms,
        ?string $notes,
        array $customFields,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            contractNumber: $contractNumber,
            relatedModule: $relatedModule,
            relatedId: $relatedId,
            type: $type,
            status: $status,
            value: $value,
            currency: $currency,
            billingFrequency: $billingFrequency,
            startDate: $startDate,
            endDate: $endDate,
            renewalDate: $renewalDate,
            renewalNoticeDays: $renewalNoticeDays,
            autoRenew: $autoRenew,
            renewalStatus: $renewalStatus,
            ownerId: $ownerId,
            terms: $terms,
            notes: $notes,
            customFields: $customFields,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Business Logic Methods
    // -------------------------------------------------------------------------

    /**
     * Get days until contract expires.
     * Returns negative number if already expired.
     */
    public function getDaysUntilExpiry(): int
    {
        if ($this->endDate === null) {
            return PHP_INT_MAX;
        }

        $now = new DateTimeImmutable();
        $diff = $now->diff($this->endDate);

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Check if contract is expiring soon (within renewal notice period).
     */
    public function isExpiring(): bool
    {
        $daysUntilExpiry = $this->getDaysUntilExpiry();
        return $daysUntilExpiry <= $this->renewalNoticeDays && $daysUntilExpiry >= 0;
    }

    /**
     * Check if contract has expired.
     */
    public function isExpired(): bool
    {
        return $this->getDaysUntilExpiry() < 0;
    }

    /**
     * Check if contract is active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if contract is editable.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if contract can be activated.
     */
    public function canBeActivated(): bool
    {
        return $this->status->canBeActivated();
    }

    /**
     * Check if contract can be terminated.
     */
    public function canBeTerminated(): bool
    {
        return $this->status->canBeTerminated();
    }

    /**
     * Check if contract can be renewed.
     */
    public function canBeRenewed(): bool
    {
        return $this->status->canBeRenewed();
    }

    /**
     * Activate the contract.
     *
     * @return self Returns a new instance with active status
     * @throws \DomainException If contract cannot be activated
     */
    public function activate(DateTimeImmutable $startDate, DateTimeImmutable $endDate): self
    {
        if (!$this->canBeActivated()) {
            throw new \DomainException("Contract cannot be activated from status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: ContractStatus::Active,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $startDate,
            endDate: $endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Terminate the contract.
     *
     * @return self Returns a new instance with terminated status
     * @throws \DomainException If contract cannot be terminated
     */
    public function terminate(?string $reason = null): self
    {
        if (!$this->canBeTerminated()) {
            throw new \DomainException("Contract cannot be terminated from status: {$this->status->value}");
        }

        $notes = $this->notes;
        if ($reason !== null) {
            $notes = $notes ? "{$notes}\n\nTermination reason: {$reason}" : "Termination reason: {$reason}";
        }

        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: ContractStatus::Terminated,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: new DateTimeImmutable(),
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: false,
            renewalStatus: RenewalStatus::NotApplicable,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Mark contract as renewed.
     *
     * @return self Returns a new instance with renewed status
     */
    public function markRenewed(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: ContractStatus::Renewed,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: RenewalStatus::Completed,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Mark contract as expired.
     *
     * @return self Returns a new instance with expired status
     */
    public function markExpired(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: ContractStatus::Expired,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: false,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update renewal settings.
     *
     * @return self Returns a new instance with updated renewal settings
     */
    public function updateRenewalSettings(
        bool $autoRenew,
        int $renewalNoticeDays,
        ?DateTimeImmutable $renewalDate = null,
    ): self {
        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: $this->status,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $renewalDate ?? $this->renewalDate,
            renewalNoticeDays: $renewalNoticeDays,
            autoRenew: $autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update value and billing.
     *
     * @return self Returns a new instance with updated value/billing
     */
    public function updateValue(
        string $value,
        ?BillingFrequency $billingFrequency = null,
        ?string $currency = null,
    ): self {
        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: $this->status,
            value: $value,
            currency: $currency ?? $this->currency,
            billingFrequency: $billingFrequency ?? $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update contract details.
     *
     * @return self Returns a new instance with updated details
     */
    public function updateDetails(
        ?string $name = null,
        ?string $terms = null,
        ?string $notes = null,
    ): self {
        return new self(
            id: $this->id,
            name: $name ?? $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: $this->status,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $terms ?? $this->terms,
            notes: $notes ?? $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Assign contract number.
     *
     * @return self Returns a new instance with contract number
     */
    public function assignContractNumber(string $contractNumber): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: $this->status,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Link to related record.
     *
     * @return self Returns a new instance linked to record
     */
    public function linkToRecord(string $module, int $recordId): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $module,
            relatedId: $recordId,
            type: $this->type,
            status: $this->status,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Transfer ownership.
     *
     * @return self Returns a new instance with new owner
     */
    public function transferOwnership(int $newOwnerId): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: $this->status,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $newOwnerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: $this->customFields,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
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
            name: $this->name,
            contractNumber: $this->contractNumber,
            relatedModule: $this->relatedModule,
            relatedId: $this->relatedId,
            type: $this->type,
            status: $this->status,
            value: $this->value,
            currency: $this->currency,
            billingFrequency: $this->billingFrequency,
            startDate: $this->startDate,
            endDate: $this->endDate,
            renewalDate: $this->renewalDate,
            renewalNoticeDays: $this->renewalNoticeDays,
            autoRenew: $this->autoRenew,
            renewalStatus: $this->renewalStatus,
            ownerId: $this->ownerId,
            terms: $this->terms,
            notes: $this->notes,
            customFields: array_merge($this->customFields, $customFields),
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function getRelatedModule(): ?string
    {
        return $this->relatedModule;
    }

    public function getRelatedId(): ?int
    {
        return $this->relatedId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getStatus(): ContractStatus
    {
        return $this->status;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getBillingFrequency(): ?BillingFrequency
    {
        return $this->billingFrequency;
    }

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getRenewalDate(): ?DateTimeImmutable
    {
        return $this->renewalDate;
    }

    public function getRenewalNoticeDays(): int
    {
        return $this->renewalNoticeDays;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function getRenewalStatus(): ?RenewalStatus
    {
        return $this->renewalStatus;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
