<?php

declare(strict_types=1);

namespace App\Domain\Cadence\Entities;

use App\Domain\Cadence\ValueObjects\DelayType;
use App\Domain\Cadence\ValueObjects\LinkedInAction;
use App\Domain\Cadence\ValueObjects\StepChannel;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CadenceStep implements Entity
{
    private function __construct(
        private ?int $id,
        private int $cadenceId,
        private int $stepOrder,
        private string $name,
        private StepChannel $channel,
        private DelayType $delayType,
        private int $delayValue,
        private ?string $preferredTime,
        private ?string $timezone,
        private ?string $subject,
        private ?string $content,
        private ?int $templateId,
        private array $conditions,
        private ?int $onReplyGotoStep,
        private ?int $onClickGotoStep,
        private ?int $onNoResponseGotoStep,
        private bool $isAbTest,
        private ?int $abVariantOf,
        private ?int $abPercentage,
        private ?LinkedInAction $linkedinAction,
        private ?string $taskType,
        private ?int $taskAssignedTo,
        private bool $isActive,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $cadenceId,
        int $stepOrder,
        string $name,
        StepChannel $channel,
    ): self {
        return new self(
            id: null,
            cadenceId: $cadenceId,
            stepOrder: $stepOrder,
            name: $name,
            channel: $channel,
            delayType: DelayType::IMMEDIATE,
            delayValue: 0,
            preferredTime: null,
            timezone: null,
            subject: null,
            content: null,
            templateId: null,
            conditions: [],
            onReplyGotoStep: null,
            onClickGotoStep: null,
            onNoResponseGotoStep: null,
            isAbTest: false,
            abVariantOf: null,
            abPercentage: null,
            linkedinAction: null,
            taskType: null,
            taskAssignedTo: null,
            isActive: true,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $cadenceId,
        int $stepOrder,
        string $name,
        StepChannel $channel,
        DelayType $delayType,
        int $delayValue,
        ?string $preferredTime,
        ?string $timezone,
        ?string $subject,
        ?string $content,
        ?int $templateId,
        array $conditions,
        ?int $onReplyGotoStep,
        ?int $onClickGotoStep,
        ?int $onNoResponseGotoStep,
        bool $isAbTest,
        ?int $abVariantOf,
        ?int $abPercentage,
        ?LinkedInAction $linkedinAction,
        ?string $taskType,
        ?int $taskAssignedTo,
        bool $isActive,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            cadenceId: $cadenceId,
            stepOrder: $stepOrder,
            name: $name,
            channel: $channel,
            delayType: $delayType,
            delayValue: $delayValue,
            preferredTime: $preferredTime,
            timezone: $timezone,
            subject: $subject,
            content: $content,
            templateId: $templateId,
            conditions: $conditions,
            onReplyGotoStep: $onReplyGotoStep,
            onClickGotoStep: $onClickGotoStep,
            onNoResponseGotoStep: $onNoResponseGotoStep,
            isAbTest: $isAbTest,
            abVariantOf: $abVariantOf,
            abPercentage: $abPercentage,
            linkedinAction: $linkedinAction,
            taskType: $taskType,
            taskAssignedTo: $taskAssignedTo,
            isActive: $isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getCadenceId(): int { return $this->cadenceId; }
    public function getStepOrder(): int { return $this->stepOrder; }
    public function getName(): string { return $this->name; }
    public function getChannel(): StepChannel { return $this->channel; }
    public function getDelayType(): DelayType { return $this->delayType; }
    public function getDelayValue(): int { return $this->delayValue; }
    public function getPreferredTime(): ?string { return $this->preferredTime; }
    public function getTimezone(): ?string { return $this->timezone; }
    public function getSubject(): ?string { return $this->subject; }
    public function getContent(): ?string { return $this->content; }
    public function getTemplateId(): ?int { return $this->templateId; }
    public function getConditions(): array { return $this->conditions; }
    public function getOnReplyGotoStep(): ?int { return $this->onReplyGotoStep; }
    public function getOnClickGotoStep(): ?int { return $this->onClickGotoStep; }
    public function getOnNoResponseGotoStep(): ?int { return $this->onNoResponseGotoStep; }
    public function isAbTest(): bool { return $this->isAbTest; }
    public function getAbVariantOf(): ?int { return $this->abVariantOf; }
    public function getAbPercentage(): ?int { return $this->abPercentage; }
    public function getLinkedinAction(): ?LinkedInAction { return $this->linkedinAction; }
    public function getTaskType(): ?string { return $this->taskType; }
    public function getTaskAssignedTo(): ?int { return $this->taskAssignedTo; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    // Business logic methods
    public function updateDetails(string $name, ?string $subject = null, ?string $content = null): self
    {
        if ($subject !== null && !$this->channel->requiresContent()) {
            throw new \DomainException("Channel {$this->channel->value} does not support subject");
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $subject,
            content: $content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function setDelay(DelayType $delayType, int $delayValue): self
    {
        if ($delayValue < 0) {
            throw new \DomainException('Delay value must be non-negative');
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $delayType,
            delayValue: $delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function setPreferredTime(?string $time, ?string $timezone = null): self
    {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $time,
            timezone: $timezone ?? $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function setTemplate(int $templateId): self
    {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function updateConditions(array $conditions): self
    {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function setConditionalBranching(
        ?int $onReplyGotoStep = null,
        ?int $onClickGotoStep = null,
        ?int $onNoResponseGotoStep = null,
    ): self {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $onReplyGotoStep,
            onClickGotoStep: $onClickGotoStep,
            onNoResponseGotoStep: $onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function configureAbTest(int $variantOfStepId, int $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new \DomainException('AB test percentage must be between 0 and 100');
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: true,
            abVariantOf: $variantOfStepId,
            abPercentage: $percentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function removeAbTest(): self
    {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: false,
            abVariantOf: null,
            abPercentage: null,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function setLinkedInAction(LinkedInAction $action): self
    {
        if ($this->channel !== StepChannel::LINKEDIN) {
            throw new \DomainException('LinkedIn action can only be set for LinkedIn channel steps');
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $action,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function setTask(string $taskType, ?int $assignedToUserId = null): self
    {
        if ($this->channel !== StepChannel::TASK) {
            throw new \DomainException('Task details can only be set for Task channel steps');
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $taskType,
            taskAssignedTo: $assignedToUserId,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function reorder(int $newOrder): self
    {
        if ($newOrder < 1) {
            throw new \DomainException('Step order must be at least 1');
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $newOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function activate(): self
    {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: true,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function deactivate(): self
    {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            stepOrder: $this->stepOrder,
            name: $this->name,
            channel: $this->channel,
            delayType: $this->delayType,
            delayValue: $this->delayValue,
            preferredTime: $this->preferredTime,
            timezone: $this->timezone,
            subject: $this->subject,
            content: $this->content,
            templateId: $this->templateId,
            conditions: $this->conditions,
            onReplyGotoStep: $this->onReplyGotoStep,
            onClickGotoStep: $this->onClickGotoStep,
            onNoResponseGotoStep: $this->onNoResponseGotoStep,
            isAbTest: $this->isAbTest,
            abVariantOf: $this->abVariantOf,
            abPercentage: $this->abPercentage,
            linkedinAction: $this->linkedinAction,
            taskType: $this->taskType,
            taskAssignedTo: $this->taskAssignedTo,
            isActive: false,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    // Query methods
    public function getDelayInSeconds(): int
    {
        return $this->delayType->calculateSeconds($this->delayValue);
    }

    public function requiresManualAction(): bool
    {
        return $this->channel->requiresManualAction();
    }

    public function isAutomatable(): bool
    {
        return $this->channel->isAutomatable();
    }

    public function getDisplayName(): string
    {
        return $this->name ?: "Step {$this->stepOrder}: {$this->channel->label()}";
    }

    public function hasConditionalBranching(): bool
    {
        return $this->onReplyGotoStep !== null
            || $this->onClickGotoStep !== null
            || $this->onNoResponseGotoStep !== null;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->id === null || $other->getId() === null) {
            return false;
        }

        return $this->id === $other->getId();
    }
}
