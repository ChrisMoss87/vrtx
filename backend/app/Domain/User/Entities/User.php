<?php

declare(strict_types=1);

namespace App\Domain\User\Entities;

use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\UserPreferences;
use DateTimeImmutable;

final class User
{
    private function __construct(
        private ?UserId $id,
        private string $name,
        private Email $email,
        private string $passwordHash,
        private UserPreferences $preferences,
        private ?DateTimeImmutable $emailVerifiedAt,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        Email $email,
        string $passwordHash,
        ?UserPreferences $preferences = null,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            name: $name,
            email: $email,
            passwordHash: $passwordHash,
            preferences: $preferences ?? UserPreferences::empty(),
            emailVerifiedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $email,
        string $passwordHash,
        array $preferences,
        ?DateTimeImmutable $emailVerifiedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: UserId::fromInt($id),
            name: $name,
            email: Email::fromString($email),
            passwordHash: $passwordHash,
            preferences: UserPreferences::fromArray($preferences),
            emailVerifiedAt: $emailVerifiedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?UserId
    {
        return $this->id;
    }

    public function getIdValue(): ?int
    {
        return $this->id?->value();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getEmailValue(): string
    {
        return $this->email->value();
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getPreferences(): UserPreferences
    {
        return $this->preferences;
    }

    public function getPreference(string $key, mixed $default = null): mixed
    {
        return $this->preferences->get($key, $default);
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withName(string $name): self
    {
        return new self(
            id: $this->id,
            name: $name,
            email: $this->email,
            passwordHash: $this->passwordHash,
            preferences: $this->preferences,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withEmail(Email $email): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $email,
            passwordHash: $this->passwordHash,
            preferences: $this->preferences,
            emailVerifiedAt: null, // Reset verification when email changes
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withPasswordHash(string $passwordHash): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            passwordHash: $passwordHash,
            preferences: $this->preferences,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withPreference(string $key, mixed $value): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            passwordHash: $this->passwordHash,
            preferences: $this->preferences->with($key, $value),
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withPreferences(UserPreferences $preferences): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            passwordHash: $this->passwordHash,
            preferences: $preferences,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markEmailAsVerified(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            passwordHash: $this->passwordHash,
            preferences: $this->preferences,
            emailVerifiedAt: new DateTimeImmutable(),
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'name' => $this->name,
            'email' => $this->email->value(),
            'preferences' => $this->preferences->toArray(),
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
