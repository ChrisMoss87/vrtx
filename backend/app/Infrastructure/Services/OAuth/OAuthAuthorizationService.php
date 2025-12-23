<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\OAuth;

use App\Domain\Email\DTOs\EmailAccountTokensDTO;
use App\Domain\Email\DTOs\OAuthCallbackDTO;
use App\Domain\Email\Services\OAuthAuthorizationServiceInterface;
use App\Domain\Email\ValueObjects\OAuthProvider;
use App\Domain\Email\ValueObjects\OAuthState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OAuthAuthorizationService implements OAuthAuthorizationServiceInterface
{
    public function generateAuthorizationUrl(
        int $userId,
        OAuthProvider $provider,
        ?int $reconnectAccountId = null,
        ?string $redirectTo = null,
    ): string {
        $state = OAuthState::create(
            userId: $userId,
            provider: $provider,
            reconnectAccountId: $reconnectAccountId,
            redirectTo: $redirectTo,
        );

        $params = [
            'client_id' => $this->getClientId($provider),
            'redirect_uri' => $this->getRedirectUri($provider),
            'response_type' => 'code',
            'scope' => $provider->getScopeString(),
            'state' => $state->encode(),
            'access_type' => 'offline',
            'prompt' => $reconnectAccountId ? 'consent' : 'select_account',
        ];

        // Microsoft-specific parameters
        if ($provider === OAuthProvider::MICROSOFT) {
            unset($params['access_type']);
            $params['response_mode'] = 'query';
        }

        return $provider->getAuthorizationUrl() . '?' . http_build_query($params);
    }

    public function exchangeCodeForTokens(
        string $code,
        OAuthProvider $provider,
    ): EmailAccountTokensDTO {
        $response = Http::asForm()->post($provider->getTokenUrl(), [
            'client_id' => $this->getClientId($provider),
            'client_secret' => $this->getClientSecret($provider),
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri($provider),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            Log::error('OAuth token exchange failed', [
                'provider' => $provider->value,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to exchange authorization code for tokens');
        }

        $tokenData = $response->json();

        // Fetch user info to get email and name
        $userInfo = $this->fetchUserInfo($tokenData['access_token'], $provider);

        return EmailAccountTokensDTO::fromOAuthResponse($tokenData, $userInfo, $provider);
    }

    public function validateState(string $encodedState): OAuthState
    {
        $state = OAuthState::fromEncoded($encodedState);

        if ($state->isExpired()) {
            throw new \InvalidArgumentException('OAuth state has expired');
        }

        return $state;
    }

    public function refreshAccessToken(
        string $refreshToken,
        OAuthProvider $provider,
    ): EmailAccountTokensDTO {
        $params = [
            'client_id' => $this->getClientId($provider),
            'client_secret' => $this->getClientSecret($provider),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        if ($provider === OAuthProvider::MICROSOFT) {
            $params['scope'] = $provider->getScopeString();
        }

        $response = Http::asForm()->post($provider->getTokenUrl(), $params);

        if (!$response->successful()) {
            Log::error('OAuth token refresh failed', [
                'provider' => $provider->value,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to refresh access token');
        }

        $tokenData = $response->json();

        // Preserve the refresh token if not returned
        if (!isset($tokenData['refresh_token'])) {
            $tokenData['refresh_token'] = $refreshToken;
        }

        $userInfo = $this->fetchUserInfo($tokenData['access_token'], $provider);

        return EmailAccountTokensDTO::fromOAuthResponse($tokenData, $userInfo, $provider);
    }

    public function revokeTokens(
        string $accessToken,
        OAuthProvider $provider,
    ): bool {
        $revokeUrl = match ($provider) {
            OAuthProvider::GMAIL => 'https://oauth2.googleapis.com/revoke',
            OAuthProvider::MICROSOFT => 'https://graph.microsoft.com/v1.0/me/revokeSignInSessions',
        };

        try {
            if ($provider === OAuthProvider::GMAIL) {
                $response = Http::asForm()->post($revokeUrl, [
                    'token' => $accessToken,
                ]);
            } else {
                $response = Http::withToken($accessToken)->post($revokeUrl);
            }

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('OAuth token revocation failed', [
                'provider' => $provider->value,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function fetchUserInfo(
        string $accessToken,
        OAuthProvider $provider,
    ): array {
        $response = Http::withToken($accessToken)->get($provider->getUserInfoUrl());

        if (!$response->successful()) {
            Log::error('Failed to fetch user info', [
                'provider' => $provider->value,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to fetch user info from OAuth provider');
        }

        return $response->json();
    }

    private function getClientId(OAuthProvider $provider): string
    {
        $key = match ($provider) {
            OAuthProvider::GMAIL => 'services.google.client_id',
            OAuthProvider::MICROSOFT => 'services.microsoft.client_id',
        };

        $clientId = config($key);

        if (!$clientId) {
            throw new \RuntimeException("OAuth client ID not configured for {$provider->value}");
        }

        return $clientId;
    }

    private function getClientSecret(OAuthProvider $provider): string
    {
        $key = match ($provider) {
            OAuthProvider::GMAIL => 'services.google.client_secret',
            OAuthProvider::MICROSOFT => 'services.microsoft.client_secret',
        };

        $clientSecret = config($key);

        if (!$clientSecret) {
            throw new \RuntimeException("OAuth client secret not configured for {$provider->value}");
        }

        return $clientSecret;
    }

    private function getRedirectUri(OAuthProvider $provider): string
    {
        // Use a common callback URL for all providers
        return config('app.url') . '/api/v1/email/oauth/callback';
    }
}
