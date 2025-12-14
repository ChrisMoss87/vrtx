<?php

declare(strict_types=1);

namespace App\Domain\Email\Entities;

class EmailAccount
{
    private ?int $id = null;
    private int $userId;
    private string $email;
    private ?string $name;
    private string $provider;
    private array $settings;
    private bool $isActive;
    private bool $isDefault;
    private ?\DateTimeImmutable $lastSyncedAt;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(int $userId, string $email, string $provider)
    {
        $this->userId = $userId;
        $this->email = $email;
        $this->provider = $provider;
        $this->name = null;
        $this->settings = [];
        $this->isActive = true;
        $this->isDefault = false;
        $this->lastSyncedAt = null;
    }

    public static function create(int $userId, string $email, string $provider): self
    {
        return new self($userId, $email, $provider);
    }

    public static function reconstitute(
        int $id,
        int $userId,
        string $email,
        ?string $name,
        string $provider,
        array $settings,
        bool $isActive,
        bool $isDefault,
        ?\DateTimeImmutable $lastSyncedAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $account = new self($userId, $email, $provider);
        $account->id = $id;
        $account->name = $name;
        $account->settings = $settings;
        $account->isActive = $isActive;
        $account->isDefault = $isDefault;
        $account->lastSyncedAt = $lastSyncedAt;
        $account->createdAt = $createdAt;
        $account->updatedAt = $updatedAt;
        return $account;
    }

    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getEmail(): string { return $this->email; }
    public function getName(): ?string { return $this->name; }
    public function getProvider(): string { return $this->provider; }
    public function getSettings(): array { return $this->settings; }
    public function isActive(): bool { return $this->isActive; }
    public function isDefault(): bool { return $this->isDefault; }
    public function getLastSyncedAt(): ?\DateTimeImmutable { return $this->lastSyncedAt; }

    public function updateSettings(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setAsDefault(): void
    {
        $this->isDefault = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function unsetDefault(): void
    {
        $this->isDefault = false;
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

    public function markSynced(): void
    {
        $this->lastSyncedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
}
