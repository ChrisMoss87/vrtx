<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use DateTimeImmutable;

final class CmsPageVersion
{
    private function __construct(
        private ?int $id,
        private int $pageId,
        private int $versionNumber,
        private string $title,
        private array $content,
        private ?array $seoData,
        private ?string $changeSummary,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $pageId,
        int $versionNumber,
        string $title,
        array $content,
        ?array $seoData = null,
        ?string $changeSummary = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            pageId: $pageId,
            versionNumber: $versionNumber,
            title: $title,
            content: $content,
            seoData: $seoData,
            changeSummary: $changeSummary,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $pageId,
        int $versionNumber,
        string $title,
        array $content,
        ?array $seoData,
        ?string $changeSummary,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            pageId: $pageId,
            versionNumber: $versionNumber,
            title: $title,
            content: $content,
            seoData: $seoData,
            changeSummary: $changeSummary,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getPageId(): int { return $this->pageId; }
    public function getVersionNumber(): int { return $this->versionNumber; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): array { return $this->content; }
    public function getSeoData(): ?array { return $this->seoData; }
    public function getChangeSummary(): ?string { return $this->changeSummary; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
}
