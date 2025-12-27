<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Value object representing link sharing configuration for a document.
 */
final class ShareSettings
{
    private function __construct(
        private string $token,
        private DocumentPermission $permission,
        private ?string $passwordHash,
        private ?DateTimeImmutable $expiresAt,
        private bool $allowDownload,
        private bool $requireEmail,
    ) {}

    /**
     * Create new share settings with a generated token.
     */
    public static function create(
        DocumentPermission $permission,
        ?string $password = null,
        ?DateTimeImmutable $expiresAt = null,
        bool $allowDownload = true,
        bool $requireEmail = false,
    ): self {
        if ($permission === DocumentPermission::OWNER) {
            throw new InvalidArgumentException('Cannot share with owner permission');
        }

        $token = bin2hex(random_bytes(32));

        return new self(
            token: $token,
            permission: $permission,
            passwordHash: $password !== null ? password_hash($password, PASSWORD_DEFAULT) : null,
            expiresAt: $expiresAt,
            allowDownload: $allowDownload,
            requireEmail: $requireEmail,
        );
    }

    /**
     * Reconstitute from persisted data.
     */
    public static function reconstitute(
        string $token,
        DocumentPermission $permission,
        ?string $passwordHash,
        ?DateTimeImmutable $expiresAt,
        bool $allowDownload,
        bool $requireEmail,
    ): self {
        return new self(
            token: $token,
            permission: $permission,
            passwordHash: $passwordHash,
            expiresAt: $expiresAt,
            allowDownload: $allowDownload,
            requireEmail: $requireEmail,
        );
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPermission(): DocumentPermission
    {
        return $this->permission;
    }

    public function hasPassword(): bool
    {
        return $this->passwordHash !== null;
    }

    public function verifyPassword(string $password): bool
    {
        if ($this->passwordHash === null) {
            return true;
        }

        return password_verify($password, $this->passwordHash);
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    public function allowsDownload(): bool
    {
        return $this->allowDownload;
    }

    public function requiresEmail(): bool
    {
        return $this->requireEmail;
    }

    /**
     * Create new settings with updated permission.
     */
    public function withPermission(DocumentPermission $permission): self
    {
        if ($permission === DocumentPermission::OWNER) {
            throw new InvalidArgumentException('Cannot share with owner permission');
        }

        return new self(
            token: $this->token,
            permission: $permission,
            passwordHash: $this->passwordHash,
            expiresAt: $this->expiresAt,
            allowDownload: $this->allowDownload,
            requireEmail: $this->requireEmail,
        );
    }

    /**
     * Create new settings with updated expiration.
     */
    public function withExpiration(?DateTimeImmutable $expiresAt): self
    {
        return new self(
            token: $this->token,
            permission: $this->permission,
            passwordHash: $this->passwordHash,
            expiresAt: $expiresAt,
            allowDownload: $this->allowDownload,
            requireEmail: $this->requireEmail,
        );
    }

    /**
     * Create new settings with new password.
     */
    public function withPassword(?string $password): self
    {
        return new self(
            token: $this->token,
            permission: $this->permission,
            passwordHash: $password !== null ? password_hash($password, PASSWORD_DEFAULT) : null,
            expiresAt: $this->expiresAt,
            allowDownload: $this->allowDownload,
            requireEmail: $this->requireEmail,
        );
    }

    /**
     * Regenerate the share token.
     */
    public function regenerateToken(): self
    {
        return new self(
            token: bin2hex(random_bytes(32)),
            permission: $this->permission,
            passwordHash: $this->passwordHash,
            expiresAt: $this->expiresAt,
            allowDownload: $this->allowDownload,
            requireEmail: $this->requireEmail,
        );
    }

    /**
     * Convert to array for persistence.
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'permission' => $this->permission->value,
            'password_hash' => $this->passwordHash,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'allow_download' => $this->allowDownload,
            'require_email' => $this->requireEmail,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            permission: DocumentPermission::from($data['permission']),
            passwordHash: $data['password_hash'] ?? null,
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            allowDownload: $data['allow_download'] ?? true,
            requireEmail: $data['require_email'] ?? false,
        );
    }
}
