<?php

declare(strict_types=1);

namespace App\Domain\Integration\Services;

use App\Domain\Integration\ValueObjects\IntegrationOAuthState;
use App\Domain\Integration\ValueObjects\IntegrationProvider;

interface IntegrationOAuthServiceInterface
{
    public function generateAuthorizationUrl(
        int $userId,
        IntegrationProvider $provider,
        ?int $reconnectConnectionId = null,
        ?string $redirectTo = null,
    ): string;

    public function exchangeCodeForTokens(
        string $code,
        IntegrationProvider $provider,
    ): array;

    public function refreshAccessToken(
        string $refreshToken,
        IntegrationProvider $provider,
    ): array;

    public function revokeTokens(
        string $accessToken,
        IntegrationProvider $provider,
    ): bool;

    public function validateState(string $encodedState): IntegrationOAuthState;

    public function getProviderInfo(
        string $accessToken,
        IntegrationProvider $provider,
    ): array;
}
