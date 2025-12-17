<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use DateTimeImmutable;

final class CmsFormSubmission
{
    private function __construct(
        private ?int $id,
        private int $formId,
        private array $data,
        private ?array $metadata,
        private ?int $contactId,
        private ?int $leadId,
        private ?string $sourceUrl,
        private ?string $ipAddress,
        private ?string $userAgent,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $formId,
        array $data,
        ?string $sourceUrl = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): self {
        return new self(
            id: null,
            formId: $formId,
            data: $data,
            metadata: [
                'submitted_at' => (new DateTimeImmutable())->format('c'),
                'source_url' => $sourceUrl,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ],
            contactId: null,
            leadId: null,
            sourceUrl: $sourceUrl,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $formId,
        array $data,
        ?array $metadata,
        ?int $contactId,
        ?int $leadId,
        ?string $sourceUrl,
        ?string $ipAddress,
        ?string $userAgent,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            formId: $formId,
            data: $data,
            metadata: $metadata,
            contactId: $contactId,
            leadId: $leadId,
            sourceUrl: $sourceUrl,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getFormId(): int { return $this->formId; }
    public function getData(): array { return $this->data; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function getContactId(): ?int { return $this->contactId; }
    public function getLeadId(): ?int { return $this->leadId; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    public function linkToContact(int $contactId): void
    {
        $this->contactId = $contactId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function linkToLead(int $leadId): void
    {
        $this->leadId = $leadId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getFieldValue(string $fieldName): mixed
    {
        return $this->data[$fieldName] ?? null;
    }
}
