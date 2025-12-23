<?php

declare(strict_types=1);

namespace App\Domain\Cadence\Entities;

use App\Domain\Cadence\ValueObjects\CadenceStatus;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class Cadence implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private int $moduleId,
        private CadenceStatus $status,
        private array $entryCriteria,
        private array $exitCriteria,
        private array $settings,
        private bool $autoEnroll,
        private bool $allowReEnrollment,
        private ?int $reEnrollmentDays,
        private ?int $maxEnrollmentsPerDay,
        private int $createdBy,
        private int $ownerId,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function create(
        string $name,
        int $moduleId,
        int $createdBy,
        int $ownerId,
        ?string $description = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            moduleId: $moduleId,
            status: CadenceStatus::DRAFT,
            entryCriteria: [],
            exitCriteria: [],
            settings: [],
            autoEnroll: false,
            allowReEnrollment: false,
            reEnrollmentDays: null,
            maxEnrollmentsPerDay: null,
            createdBy: $createdBy,
            ownerId: $ownerId,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        int $moduleId,
        CadenceStatus $status,
        array $entryCriteria,
        array $exitCriteria,
        array $settings,
        bool $autoEnroll,
        bool $allowReEnrollment,
        ?int $reEnrollmentDays,
        ?int $maxEnrollmentsPerDay,
        int $createdBy,
        int $ownerId,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            moduleId: $moduleId,
            status: $status,
            entryCriteria: $entryCriteria,
            exitCriteria: $exitCriteria,
            settings: $settings,
            autoEnroll: $autoEnroll,
            allowReEnrollment: $allowReEnrollment,
            reEnrollmentDays: $reEnrollmentDays,
            maxEnrollmentsPerDay: $maxEnrollmentsPerDay,
            createdBy: $createdBy,
            ownerId: $ownerId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getModuleId(): int { return $this->moduleId; }
    public function getStatus(): CadenceStatus { return $this->status; }
    public function getEntryCriteria(): array { return $this->entryCriteria; }
    public function getExitCriteria(): array { return $this->exitCriteria; }
    public function getSettings(): array { return $this->settings; }
    public function isAutoEnroll(): bool { return $this->autoEnroll; }
    public function allowsReEnrollment(): bool { return $this->allowReEnrollment; }
    public function getReEnrollmentDays(): ?int { return $this->reEnrollmentDays; }
    public function getMaxEnrollmentsPerDay(): ?int { return $this->maxEnrollmentsPerDay; }
    public function getCreatedBy(): int { return $this->createdBy; }
    public function getOwnerId(): int { return $this->ownerId; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }

    // Business logic methods
    public function updateDetails(string $name, ?string $description = null): self
    {
        return new self(
            id: $this->id,
            name: $name,
            description: $description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function updateCriteria(array $entryCriteria, array $exitCriteria): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $entryCriteria,
            exitCriteria: $exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function updateSettings(array $settings): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function enableAutoEnroll(): self
    {
        if ($this->autoEnroll) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: true,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function disableAutoEnroll(): self
    {
        if (!$this->autoEnroll) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: false,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function configureReEnrollment(bool $allow, ?int $days = null): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $allow,
            reEnrollmentDays: $allow ? $days : null,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function setMaxEnrollmentsPerDay(?int $max): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $max,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function activate(): self
    {
        if (!$this->status->canActivate()) {
            throw new \DomainException("Cannot activate cadence in {$this->status->value} status");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: CadenceStatus::ACTIVE,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function pause(): self
    {
        if (!$this->status->canPause()) {
            throw new \DomainException("Cannot pause cadence in {$this->status->value} status");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: CadenceStatus::PAUSED,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function archive(): self
    {
        if (!$this->status->canArchive()) {
            throw new \DomainException("Cannot archive cadence in {$this->status->value} status");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: CadenceStatus::ARCHIVED,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    public function transferOwnership(int $newOwnerId): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            moduleId: $this->moduleId,
            status: $this->status,
            entryCriteria: $this->entryCriteria,
            exitCriteria: $this->exitCriteria,
            settings: $this->settings,
            autoEnroll: $this->autoEnroll,
            allowReEnrollment: $this->allowReEnrollment,
            reEnrollmentDays: $this->reEnrollmentDays,
            maxEnrollmentsPerDay: $this->maxEnrollmentsPerDay,
            createdBy: $this->createdBy,
            ownerId: $newOwnerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    // Query methods
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canEnroll(): bool
    {
        return $this->status->canEnroll() && $this->deletedAt === null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
