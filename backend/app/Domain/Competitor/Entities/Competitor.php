<?php

declare(strict_types=1);

namespace App\Domain\Competitor\Entities;

class Competitor
{
    private ?int $id = null;
    private string $name;
    private ?string $website;
    private ?string $description;
    private ?string $logoUrl;
    private array $strengths;
    private array $weaknesses;
    private array $pricing;
    private bool $isActive;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->website = null;
        $this->description = null;
        $this->logoUrl = null;
        $this->strengths = [];
        $this->weaknesses = [];
        $this->pricing = [];
        $this->isActive = true;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $website,
        ?string $description,
        ?string $logoUrl,
        array $strengths,
        array $weaknesses,
        array $pricing,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $competitor = new self($name);
        $competitor->id = $id;
        $competitor->website = $website;
        $competitor->description = $description;
        $competitor->logoUrl = $logoUrl;
        $competitor->strengths = $strengths;
        $competitor->weaknesses = $weaknesses;
        $competitor->pricing = $pricing;
        $competitor->isActive = $isActive;
        $competitor->createdAt = $createdAt;
        $competitor->updatedAt = $updatedAt;
        return $competitor;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getWebsite(): ?string { return $this->website; }
    public function getDescription(): ?string { return $this->description; }
    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function getStrengths(): array { return $this->strengths; }
    public function getWeaknesses(): array { return $this->weaknesses; }
    public function getPricing(): array { return $this->pricing; }
    public function isActive(): bool { return $this->isActive; }

    public function update(string $name, ?string $website, ?string $description): void
    {
        $this->name = $name;
        $this->website = $website;
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setStrengthsAndWeaknesses(array $strengths, array $weaknesses): void
    {
        $this->strengths = $strengths;
        $this->weaknesses = $weaknesses;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setPricing(array $pricing): void
    {
        $this->pricing = $pricing;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
