<?php

declare(strict_types=1);

namespace App\Domain\Email\DTOs;

use App\Domain\Email\ValueObjects\OAuthProvider;

final readonly class EmailAccountTokensDTO
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public \DateTimeImmutable $expiresAt,
        public string $email,
        public ?string $name,
        public OAuthProvider $provider,
    ) {}

    public static function fromOAuthResponse(
        array $tokenResponse,
        array $userInfo,
        OAuthProvider $provider,
    ): self {
        $expiresIn = $tokenResponse['expires_in'] ?? 3600;
        $expiresAt = new \DateTimeImmutable("+{$expiresIn} seconds");

        // Extract email and name based on provider
        $email = match ($provider) {
            OAuthProvider::GMAIL => $userInfo['email'] ?? '',
            OAuthProvider::MICROSOFT => $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? '',
        };

        $name = match ($provider) {
            OAuthProvider::GMAIL => $userInfo['name'] ?? null,
            OAuthProvider::MICROSOFT => $userInfo['displayName'] ?? null,
        };

        return new self(
            accessToken: $tokenResponse['access_token'],
            refreshToken: $tokenResponse['refresh_token'] ?? '',
            expiresAt: $expiresAt,
            email: $email,
            name: $name,
            provider: $provider,
        );
    }

    public function isExpiringSoon(int $bufferSeconds = 300): bool
    {
        $buffer = new \DateTimeImmutable("+{$bufferSeconds} seconds");
        return $buffer > $this->expiresAt;
    }
}
