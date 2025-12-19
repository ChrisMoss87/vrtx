<?php

declare(strict_types=1);

namespace App\Domain\Competitor\Entities;

class Battlecard
{
    private ?int $id = null;
    private int $competitorId;
    private string $title;
    private array $sections;
    private array $talkingPoints;
    private array $objectionHandlers;
    private bool $isPublished;
    private ?int $createdBy;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(int $competitorId, string $title)
    {
        $this->competitorId = $competitorId;
        $this->title = $title;
        $this->sections = [];
        $this->talkingPoints = [];
        $this->objectionHandlers = [];
        $this->isPublished = false;
        $this->createdBy = null;
    }

    public static function create(int $competitorId, string $title, ?int $createdBy = null): self
    {
        $battlecard = new self($competitorId, $title);
        $battlecard->createdBy = $createdBy;
        return $battlecard;
    }

    public static function reconstitute(
        int $id,
        int $competitorId,
        string $title,
        array $sections,
        array $talkingPoints,
        array $objectionHandlers,
        bool $isPublished,
        ?int $createdBy,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $battlecard = new self($competitorId, $title);
        $battlecard->id = $id;
        $battlecard->sections = $sections;
        $battlecard->talkingPoints = $talkingPoints;
        $battlecard->objectionHandlers = $objectionHandlers;
        $battlecard->isPublished = $isPublished;
        $battlecard->createdBy = $createdBy;
        $battlecard->createdAt = $createdAt;
        $battlecard->updatedAt = $updatedAt;
        return $battlecard;
    }

    public function getId(): ?int { return $this->id; }
    public function getCompetitorId(): int { return $this->competitorId; }
    public function getTitle(): string { return $this->title; }
    public function getSections(): array { return $this->sections; }
    public function getTalkingPoints(): array { return $this->talkingPoints; }
    public function getObjectionHandlers(): array { return $this->objectionHandlers; }
    public function isPublished(): bool { return $this->isPublished; }

    public function update(string $title, array $sections, array $talkingPoints, array $objectionHandlers): void
    {
        $this->title = $title;
        $this->sections = $sections;
        $this->talkingPoints = $talkingPoints;
        $this->objectionHandlers = $objectionHandlers;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function publish(): void
    {
        $this->isPublished = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
