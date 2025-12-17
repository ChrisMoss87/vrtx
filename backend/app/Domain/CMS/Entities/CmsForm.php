<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\CMS\ValueObjects\FormSubmitAction;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class CmsForm implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $description,
        private array $fields,
        private ?array $settings,
        private FormSubmitAction $submitAction,
        private ?int $targetModuleId,
        private ?array $fieldMapping,
        private string $submitButtonText,
        private ?string $successMessage,
        private ?string $redirectUrl,
        private ?array $notificationEmails,
        private ?int $notificationTemplateId,
        private int $submissionCount,
        private int $viewCount,
        private bool $isActive,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function create(
        string $name,
        string $slug,
        array $fields = [],
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            description: null,
            fields: $fields,
            settings: null,
            submitAction: FormSubmitAction::CREATE_LEAD,
            targetModuleId: null,
            fieldMapping: null,
            submitButtonText: 'Submit',
            successMessage: 'Thank you for your submission!',
            redirectUrl: null,
            notificationEmails: null,
            notificationTemplateId: null,
            submissionCount: 0,
            viewCount: 0,
            isActive: true,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        array $fields,
        ?array $settings,
        FormSubmitAction $submitAction,
        ?int $targetModuleId,
        ?array $fieldMapping,
        string $submitButtonText,
        ?string $successMessage,
        ?string $redirectUrl,
        ?array $notificationEmails,
        ?int $notificationTemplateId,
        int $submissionCount,
        int $viewCount,
        bool $isActive,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            description: $description,
            fields: $fields,
            settings: $settings,
            submitAction: $submitAction,
            targetModuleId: $targetModuleId,
            fieldMapping: $fieldMapping,
            submitButtonText: $submitButtonText,
            successMessage: $successMessage,
            redirectUrl: $redirectUrl,
            notificationEmails: $notificationEmails,
            notificationTemplateId: $notificationTemplateId,
            submissionCount: $submissionCount,
            viewCount: $viewCount,
            isActive: $isActive,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getDescription(): ?string { return $this->description; }
    public function getFields(): array { return $this->fields; }
    public function getSettings(): ?array { return $this->settings; }
    public function getSubmitAction(): FormSubmitAction { return $this->submitAction; }
    public function getTargetModuleId(): ?int { return $this->targetModuleId; }
    public function getFieldMapping(): ?array { return $this->fieldMapping; }
    public function getSubmitButtonText(): string { return $this->submitButtonText; }
    public function getSuccessMessage(): ?string { return $this->successMessage; }
    public function getRedirectUrl(): ?string { return $this->redirectUrl; }
    public function getNotificationEmails(): ?array { return $this->notificationEmails; }
    public function getNotificationTemplateId(): ?int { return $this->notificationTemplateId; }
    public function getSubmissionCount(): int { return $this->submissionCount; }
    public function getViewCount(): int { return $this->viewCount; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }

    public function update(
        string $name,
        string $slug,
        ?string $description,
        array $fields,
        ?array $settings,
    ): void {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->fields = $fields;
        $this->settings = $settings;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function configureSubmitAction(
        FormSubmitAction $action,
        ?int $targetModuleId = null,
        ?array $fieldMapping = null,
    ): void {
        $this->submitAction = $action;
        $this->targetModuleId = $targetModuleId;
        $this->fieldMapping = $fieldMapping;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function configureDisplay(
        string $submitButtonText,
        ?string $successMessage,
        ?string $redirectUrl,
    ): void {
        $this->submitButtonText = $submitButtonText;
        $this->successMessage = $successMessage;
        $this->redirectUrl = $redirectUrl;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function configureNotifications(
        ?array $emails,
        ?int $templateId,
    ): void {
        $this->notificationEmails = $emails;
        $this->notificationTemplateId = $templateId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function incrementSubmissionCount(): void
    {
        $this->submissionCount++;
    }

    public function incrementViewCount(): void
    {
        $this->viewCount++;
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function delete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function duplicate(string $newName, string $newSlug, ?int $createdBy = null): self
    {
        return new self(
            id: null,
            name: $newName,
            slug: $newSlug,
            description: $this->description,
            fields: $this->fields,
            settings: $this->settings,
            submitAction: $this->submitAction,
            targetModuleId: $this->targetModuleId,
            fieldMapping: $this->fieldMapping,
            submitButtonText: $this->submitButtonText,
            successMessage: $this->successMessage,
            redirectUrl: $this->redirectUrl,
            notificationEmails: $this->notificationEmails,
            notificationTemplateId: $this->notificationTemplateId,
            submissionCount: 0,
            viewCount: 0,
            isActive: true,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public function getConversionRate(): float
    {
        if ($this->viewCount === 0) {
            return 0.0;
        }
        return ($this->submissionCount / $this->viewCount) * 100;
    }
}
