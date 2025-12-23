<?php

declare(strict_types=1);

namespace App\Domain\Email\Services;

use App\Domain\Email\DTOs\EmailAccountTokensDTO;
use App\Domain\Email\DTOs\OAuthCallbackDTO;
use App\Domain\Email\ValueObjects\OAuthProvider;
use App\Domain\Email\ValueObjects\OAuthState;

interface OAuthAuthorizationServiceInterface
{
    /**
     * Generate an OAuth authorization URL for the given provider.
     */
    public function generateAuthorizationUrl(
        int $userId,
        OAuthProvider $provider,
        ?int $reconnectAccountId = null,
        ?string $redirectTo = null,
    ): string;

    /**
     * Exchange an authorization code for access and refresh tokens.
     */
    public function exchangeCodeForTokens(
        string $code,
        OAuthProvider $provider,
    ): EmailAccountTokensDTO;

    /**
     * Validate and decode an OAuth state parameter.
     *
     * @throws \InvalidArgumentException If state is invalid or expired
     */
    public function validateState(string $encodedState): OAuthState;

    /**
     * Refresh an expired access token using the refresh token.
     */
    public function refreshAccessToken(
        string $refreshToken,
        OAuthProvider $provider,
    ): EmailAccountTokensDTO;

    /**
     * Revoke OAuth tokens for an account.
     */
    public function revokeTokens(
        string $accessToken,
        OAuthProvider $provider,
    ): bool;

    /**
     * Fetch user info from the OAuth provider.
     */
    public function fetchUserInfo(
        string $accessToken,
        OAuthProvider $provider,
    ): array;
}
