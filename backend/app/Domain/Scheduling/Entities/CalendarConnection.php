<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Entities;

use App\Domain\Scheduling\ValueObjects\CalendarProvider;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * CalendarConnection entity.
 *
 * Represents a connection to an external calendar service.
 */
final class CalendarConnection implements Entity
{
    private function __construct(
        private ?int $id,
        private UserId $userId,
        private CalendarProvider $provider,
        private string $accessToken,
        private ?string $refreshToken,
        private ?DateTimeImmutable $tokenExpiresAt,
        private string $calendarId,
        private string $calendarName,
        private bool $isPrimary,
        private bool $syncEnabled,
        private ?Timestamp $lastSyncedAt,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new calendar connection.
     */
    public static function create(
        UserId $userId,
        CalendarProvider $provider,
        string $accessToken,
        string $calendarId,
        string $calendarName,
        ?string $refreshToken = null,
        ?DateTimeImmutable $tokenExpiresAt = null,
        bool $isPrimary = false,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            provider: $provider,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            tokenExpiresAt: $tokenExpiresAt,
            calendarId: $calendarId,
            calendarName: $calendarName,
            isPrimary: $isPrimary,
            syncEnabled: true,
            lastSyncedAt: null,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        UserId $userId,
        CalendarProvider $provider,
        string $accessToken,
        ?string $refreshToken,
        ?DateTimeImmutable $tokenExpiresAt,
        string $calendarId,
        string $calendarName,
        bool $isPrimary,
        bool $syncEnabled,
        ?Timestamp $lastSyncedAt,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            provider: $provider,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            tokenExpiresAt: $tokenExpiresAt,
            calendarId: $calendarId,
            calendarName: $calendarName,
            isPrimary: $isPrimary,
            syncEnabled: $syncEnabled,
            lastSyncedAt: $lastSyncedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update the access token.
     */
    public function updateToken(
        string $accessToken,
        ?string $refreshToken = null,
        ?DateTimeImmutable $expiresAt = null
    ): void {
        $this->accessToken = $accessToken;

        if ($refreshToken !== null) {
            $this->refreshToken = $refreshToken;
        }

        if ($expiresAt !== null) {
            $this->tokenExpiresAt = $expiresAt;
        }

        $this->updatedAt = Timestamp::now();
    }

    /**
     * Mark as synced.
     */
    public function markSynced(): void
    {
        $this->lastSyncedAt = Timestamp::now();
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Enable sync.
     */
    public function enableSync(): void
    {
        $this->syncEnabled = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Disable sync.
     */
    public function disableSync(): void
    {
        $this->syncEnabled = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Set as primary calendar.
     */
    public function setAsPrimary(): void
    {
        $this->isPrimary = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Remove primary status.
     */
    public function removePrimary(): void
    {
        $this->isPrimary = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Check if the token is expired.
     */
    public function isTokenExpired(): bool
    {
        if ($this->tokenExpiresAt === null) {
            return false;
        }

        return $this->tokenExpiresAt < new DateTimeImmutable();
    }

    /**
     * Check if token needs refresh (expires within 5 minutes).
     */
    public function needsTokenRefresh(): bool
    {
        if ($this->tokenExpiresAt === null) {
            return false;
        }

        $threshold = new DateTimeImmutable('+5 minutes');
        return $this->tokenExpiresAt < $threshold;
    }

    // ========== Entity Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function provider(): CalendarProvider
    {
        return $this->provider;
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function refreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function tokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function calendarId(): string
    {
        return $this->calendarId;
    }

    public function calendarName(): string
    {
        return $this->calendarName;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function syncEnabled(): bool
    {
        return $this->syncEnabled;
    }

    public function lastSyncedAt(): ?Timestamp
    {
        return $this->lastSyncedAt;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }
}
