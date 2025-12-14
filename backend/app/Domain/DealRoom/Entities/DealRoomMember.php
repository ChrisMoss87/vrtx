<?php

declare(strict_types=1);

namespace App\Domain\DealRoom\Entities;

use App\Domain\DealRoom\ValueObjects\MemberRole;

class DealRoomMember
{
    private ?int $id = null;
    private int $dealRoomId;
    private ?int $userId;
    private ?int $contactId;
    private string $email;
    private ?string $name;
    private MemberRole $role;
    private ?\DateTimeImmutable $lastAccessedAt;
    private ?\DateTimeImmutable $invitedAt;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(int $dealRoomId, string $email, MemberRole $role)
    {
        $this->dealRoomId = $dealRoomId;
        $this->email = $email;
        $this->role = $role;
        $this->userId = null;
        $this->contactId = null;
        $this->name = null;
        $this->lastAccessedAt = null;
        $this->invitedAt = new \DateTimeImmutable();
    }

    public static function createInternal(int $dealRoomId, int $userId, string $email, MemberRole $role): self
    {
        $member = new self($dealRoomId, $email, $role);
        $member->userId = $userId;
        return $member;
    }

    public static function createExternal(int $dealRoomId, string $email, ?string $name, ?int $contactId = null): self
    {
        $member = new self($dealRoomId, $email, MemberRole::GUEST);
        $member->name = $name;
        $member->contactId = $contactId;
        return $member;
    }

    public static function reconstitute(
        int $id,
        int $dealRoomId,
        ?int $userId,
        ?int $contactId,
        string $email,
        ?string $name,
        MemberRole $role,
        ?\DateTimeImmutable $lastAccessedAt,
        ?\DateTimeImmutable $invitedAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $member = new self($dealRoomId, $email, $role);
        $member->id = $id;
        $member->userId = $userId;
        $member->contactId = $contactId;
        $member->name = $name;
        $member->lastAccessedAt = $lastAccessedAt;
        $member->invitedAt = $invitedAt;
        $member->createdAt = $createdAt;
        $member->updatedAt = $updatedAt;
        return $member;
    }

    public function getId(): ?int { return $this->id; }
    public function getDealRoomId(): int { return $this->dealRoomId; }
    public function getUserId(): ?int { return $this->userId; }
    public function getContactId(): ?int { return $this->contactId; }
    public function getEmail(): string { return $this->email; }
    public function getName(): ?string { return $this->name; }
    public function getRole(): MemberRole { return $this->role; }
    public function getLastAccessedAt(): ?\DateTimeImmutable { return $this->lastAccessedAt; }

    public function changeRole(MemberRole $role): void
    {
        $this->role = $role;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordAccess(): void
    {
        $this->lastAccessedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isInternal(): bool
    {
        return $this->userId !== null;
    }

    public function canEdit(): bool
    {
        return $this->role->canEdit();
    }
}
