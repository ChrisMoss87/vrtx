<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Integration;

use App\Domain\Integration\Services\IntegrationOAuthServiceInterface;
use App\Domain\Integration\ValueObjects\AuthType;
use App\Domain\Integration\ValueObjects\IntegrationOAuthState;
use App\Domain\Integration\ValueObjects\IntegrationProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegrationOAuthService implements IntegrationOAuthServiceInterface
{
    public function generateAuthorizationUrl(
        int $userId,
        IntegrationProvider $provider,
        ?int $reconnectConnectionId = null,
        ?string $redirectTo = null,
    ): string {
        if ($provider->authType() !== AuthType::OAUTH2) {
            throw new \InvalidArgumentException("Provider {$provider->value} does not support OAuth 2.0");
        }

        $state = IntegrationOAuthState::create(
            userId: $userId,
            provider: $provider,
            reconnectConnectionId: $reconnectConnectionId,
            redirectTo: $redirectTo,
        );

        $params = [
            'client_id' => $this->getClientId($provider),
            'redirect_uri' => $this->getRedirectUri($provider),
            'response_type' => 'code',
            'scope' => $provider->getScopeString(),
            'state' => $state->encode(),
        ];

        // Provider-specific parameters
        $params = $this->addProviderSpecificParams($params, $provider, $reconnectConnectionId);

        return $provider->getAuthorizationUrl() . '?' . http_build_query($params);
    }

    public function exchangeCodeForTokens(
        string $code,
        IntegrationProvider $provider,
    ): array {
        $params = [
            'client_id' => $this->getClientId($provider),
            'client_secret' => $this->getClientSecret($provider),
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri($provider),
            'grant_type' => 'authorization_code',
        ];

        $response = Http::asForm()->post($provider->getTokenUrl(), $params);

        if (!$response->successful()) {
            Log::error('Integration OAuth token exchange failed', [
                'provider' => $provider->value,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to exchange authorization code for tokens');
        }

        $tokenData = $response->json();

        return $this->normalizeTokenResponse($tokenData, $provider);
    }

    public function refreshAccessToken(
        string $refreshToken,
        IntegrationProvider $provider,
    ): array {
        $params = [
            'client_id' => $this->getClientId($provider),
            'client_secret' => $this->getClientSecret($provider),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        // Some providers require scope on refresh
        if (in_array($provider, [IntegrationProvider::MICROSOFT_CALENDAR, IntegrationProvider::ONEDRIVE])) {
            $params['scope'] = $provider->getScopeString();
        }

        $response = Http::asForm()->post($provider->getTokenUrl(), $params);

        if (!$response->successful()) {
            Log::error('Integration OAuth token refresh failed', [
                'provider' => $provider->value,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to refresh access token');
        }

        $tokenData = $response->json();

        // Preserve refresh token if not returned (some providers don't return it on refresh)
        if (!isset($tokenData['refresh_token'])) {
            $tokenData['refresh_token'] = $refreshToken;
        }

        return $this->normalizeTokenResponse($tokenData, $provider);
    }

    public function revokeTokens(
        string $accessToken,
        IntegrationProvider $provider,
    ): bool {
        $revokeUrl = $this->getRevokeUrl($provider);

        if ($revokeUrl === null) {
            // Provider doesn't support token revocation
            return true;
        }

        try {
            $response = match ($provider) {
                IntegrationProvider::GOOGLE_CALENDAR, IntegrationProvider::GOOGLE_DRIVE =>
                    Http::asForm()->post($revokeUrl, ['token' => $accessToken]),
                IntegrationProvider::SLACK =>
                    Http::asForm()->post($revokeUrl, [
                        'token' => $accessToken,
                        'client_id' => $this->getClientId($provider),
                        'client_secret' => $this->getClientSecret($provider),
                    ]),
                default => Http::withToken($accessToken)->post($revokeUrl),
            };

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Integration OAuth token revocation failed', [
                'provider' => $provider->value,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function validateState(string $encodedState): IntegrationOAuthState
    {
        $state = IntegrationOAuthState::fromEncoded($encodedState);

        if ($state->isExpired()) {
            throw new \InvalidArgumentException('OAuth state has expired');
        }

        return $state;
    }

    public function getProviderInfo(
        string $accessToken,
        IntegrationProvider $provider,
    ): array {
        $userInfoUrl = $this->getUserInfoUrl($provider);

        if ($userInfoUrl === null) {
            return [];
        }

        $response = Http::withToken($accessToken)->get($userInfoUrl);

        if (!$response->successful()) {
            Log::error('Failed to fetch provider info', [
                'provider' => $provider->value,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to fetch user info from provider');
        }

        return $this->normalizeUserInfo($response->json(), $provider);
    }

    private function addProviderSpecificParams(array $params, IntegrationProvider $provider, ?int $reconnectId): array
    {
        switch ($provider) {
            case IntegrationProvider::GOOGLE_CALENDAR:
            case IntegrationProvider::GOOGLE_DRIVE:
                $params['access_type'] = 'offline';
                $params['prompt'] = $reconnectId ? 'consent' : 'select_account';
                break;

            case IntegrationProvider::MICROSOFT_CALENDAR:
            case IntegrationProvider::ONEDRIVE:
                $params['response_mode'] = 'query';
                break;

            case IntegrationProvider::QUICKBOOKS:
                // QuickBooks uses slightly different flow
                break;

            case IntegrationProvider::XERO:
                // Xero requires offline_access in scopes (already included)
                break;

            case IntegrationProvider::SLACK:
                // Slack v2 OAuth specific
                unset($params['scope']);
                $params['user_scope'] = '';
                break;

            case IntegrationProvider::ZOOM:
                // Zoom specific
                break;

            case IntegrationProvider::DOCUSIGN:
                $params['prompt'] = 'login';
                break;
        }

        return $params;
    }

    private function normalizeTokenResponse(array $tokenData, IntegrationProvider $provider): array
    {
        $expiresIn = $tokenData['expires_in'] ?? $provider->getTokenExpirySeconds();
        $expiresAt = now()->addSeconds($expiresIn);

        return [
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
            'expires_in' => $expiresIn,
            'token_type' => $tokenData['token_type'] ?? 'Bearer',
            'scope' => $tokenData['scope'] ?? null,
            'raw' => $tokenData,
        ];
    }

    private function normalizeUserInfo(array $info, IntegrationProvider $provider): array
    {
        return match ($provider) {
            IntegrationProvider::GOOGLE_CALENDAR, IntegrationProvider::GOOGLE_DRIVE => [
                'id' => $info['id'] ?? null,
                'email' => $info['email'] ?? null,
                'name' => $info['name'] ?? null,
                'picture' => $info['picture'] ?? null,
            ],
            IntegrationProvider::MICROSOFT_CALENDAR, IntegrationProvider::ONEDRIVE => [
                'id' => $info['id'] ?? null,
                'email' => $info['mail'] ?? $info['userPrincipalName'] ?? null,
                'name' => $info['displayName'] ?? null,
            ],
            IntegrationProvider::QUICKBOOKS => [
                'id' => $info['sub'] ?? null,
                'email' => $info['email'] ?? null,
                'name' => $info['givenName'] ?? null,
                'company_id' => $info['realmId'] ?? null,
            ],
            IntegrationProvider::XERO => [
                'id' => $info['xero_userid'] ?? null,
                'email' => $info['email'] ?? null,
                'name' => $info['name'] ?? null,
            ],
            IntegrationProvider::SLACK => [
                'team_id' => $info['team']['id'] ?? null,
                'team_name' => $info['team']['name'] ?? null,
                'bot_user_id' => $info['bot_user_id'] ?? null,
                'incoming_webhook' => $info['incoming_webhook'] ?? null,
            ],
            IntegrationProvider::ZOOM => [
                'id' => $info['id'] ?? null,
                'email' => $info['email'] ?? null,
                'name' => ($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? ''),
                'account_id' => $info['account_id'] ?? null,
            ],
            default => $info,
        };
    }

    private function getUserInfoUrl(IntegrationProvider $provider): ?string
    {
        return match ($provider) {
            IntegrationProvider::GOOGLE_CALENDAR, IntegrationProvider::GOOGLE_DRIVE =>
                'https://www.googleapis.com/oauth2/v2/userinfo',
            IntegrationProvider::MICROSOFT_CALENDAR, IntegrationProvider::ONEDRIVE =>
                'https://graph.microsoft.com/v1.0/me',
            IntegrationProvider::QUICKBOOKS =>
                'https://accounts.platform.intuit.com/v1/openid_connect/userinfo',
            IntegrationProvider::XERO =>
                'https://api.xero.com/connections',
            IntegrationProvider::ZOOM =>
                'https://api.zoom.us/v2/users/me',
            IntegrationProvider::DOCUSIGN =>
                'https://account.docusign.com/oauth/userinfo',
            default => null,
        };
    }

    private function getRevokeUrl(IntegrationProvider $provider): ?string
    {
        return match ($provider) {
            IntegrationProvider::GOOGLE_CALENDAR, IntegrationProvider::GOOGLE_DRIVE =>
                'https://oauth2.googleapis.com/revoke',
            IntegrationProvider::SLACK =>
                'https://slack.com/api/auth.revoke',
            IntegrationProvider::ZOOM =>
                'https://zoom.us/oauth/revoke',
            default => null,
        };
    }

    private function getClientId(IntegrationProvider $provider): string
    {
        $key = $provider->getConfigKey() . '.client_id';
        $clientId = config($key);

        if (!$clientId) {
            throw new \RuntimeException("OAuth client ID not configured for {$provider->value}");
        }

        return $clientId;
    }

    private function getClientSecret(IntegrationProvider $provider): string
    {
        $key = $provider->getConfigKey() . '.client_secret';
        $clientSecret = config($key);

        if (!$clientSecret) {
            throw new \RuntimeException("OAuth client secret not configured for {$provider->value}");
        }

        return $clientSecret;
    }

    private function getRedirectUri(IntegrationProvider $provider): string
    {
        return config('app.url') . '/api/v1/integrations/oauth/callback';
    }
}
