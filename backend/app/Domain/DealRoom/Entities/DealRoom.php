<?php

declare(strict_types=1);

namespace App\Domain\DealRoom\Entities;

use App\Domain\DealRoom\ValueObjects\DealRoomStatus;

class DealRoom
{
    private ?int $id = null;
    private string $name;
    private ?int $dealId;
    private ?int $accountId;
    private DealRoomStatus $status;
    private ?string $description;
    private ?string $accessToken;
    private bool $isPublic;
    private array $settings;
    private ?int $createdBy;
    /** @var DealRoomMember[] */
    private array $members = [];
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(string $name, ?int $dealId = null)
    {
        $this->name = $name;
        $this->dealId = $dealId;
        $this->accountId = null;
        $this->status = DealRoomStatus::DRAFT;
        $this->description = null;
        $this->accessToken = null;
        $this->isPublic = false;
        $this->settings = [];
        $this->createdBy = null;
    }

    public static function create(string $name, ?int $dealId = null, ?int $createdBy = null): self
    {
        $room = new self($name, $dealId);
        $room->createdBy = $createdBy;
        $room->accessToken = bin2hex(random_bytes(32));
        return $room;
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?int $dealId,
        ?int $accountId,
        DealRoomStatus $status,
        ?string $description,
        ?string $accessToken,
        bool $isPublic,
        array $settings,
        ?int $createdBy,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $room = new self($name, $dealId);
        $room->id = $id;
        $room->accountId = $accountId;
        $room->status = $status;
        $room->description = $description;
        $room->accessToken = $accessToken;
        $room->isPublic = $isPublic;
        $room->settings = $settings;
        $room->createdBy = $createdBy;
        $room->createdAt = $createdAt;
        $room->updatedAt = $updatedAt;
        return $room;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDealId(): ?int { return $this->dealId; }
    public function getAccountId(): ?int { return $this->accountId; }
    public function getStatus(): DealRoomStatus { return $this->status; }
    public function getDescription(): ?string { return $this->description; }
    public function getAccessToken(): ?string { return $this->accessToken; }
    public function isPublic(): bool { return $this->isPublic; }
    public function getSettings(): array { return $this->settings; }
    public function getMembers(): array { return $this->members; }

    public function setMembers(array $members): void
    {
        $this->members = $members;
    }

    public function update(string $name, ?string $description): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->status = DealRoomStatus::ACTIVE;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function closeAsWon(): void
    {
        $this->status = DealRoomStatus::CLOSED_WON;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function closeAsLost(): void
    {
        $this->status = DealRoomStatus::CLOSED_LOST;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        $this->status = DealRoomStatus::ARCHIVED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function makePublic(): void
    {
        $this->isPublic = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function makePrivate(): void
    {
        $this->isPublic = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function regenerateAccessToken(): string
    {
        $this->accessToken = bin2hex(random_bytes(32));
        $this->updatedAt = new \DateTimeImmutable();
        return $this->accessToken;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
