<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service to handle OAuth token refresh for email accounts.
 */
class OAuthTokenService
{
    /**
     * Gmail OAuth configuration.
     */
    protected const GMAIL_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Outlook/Microsoft OAuth configuration.
     */
    protected const OUTLOOK_TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    /**
     * Refresh the OAuth token for an account.
     */
    public function refreshToken(EmailAccount $account): bool
    {
        if (!$account->oauth_refresh_token) {
            Log::warning('Cannot refresh OAuth token - no refresh token stored', [
                'account_id' => $account->id,
            ]);
            return false;
        }

        return match ($account->provider) {
            EmailAccount::PROVIDER_GMAIL => $this->refreshGmailToken($account),
            EmailAccount::PROVIDER_OUTLOOK => $this->refreshOutlookToken($account),
            default => false,
        };
    }

    /**
     * Refresh Gmail OAuth token.
     */
    protected function refreshGmailToken(EmailAccount $account): bool
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('Gmail OAuth credentials not configured');
            return false;
        }

        try {
            $response = Http::asForm()->post(self::GMAIL_TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $account->oauth_refresh_token,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->updateAccountToken($account, $data);
            }

            Log::error('Gmail token refresh failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Gmail token refresh exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Refresh Outlook/Microsoft OAuth token.
     */
    protected function refreshOutlookToken(EmailAccount $account): bool
    {
        $clientId = config('services.microsoft.client_id');
        $clientSecret = config('services.microsoft.client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('Microsoft OAuth credentials not configured');
            return false;
        }

        try {
            $response = Http::asForm()->post(self::OUTLOOK_TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $account->oauth_refresh_token,
                'grant_type' => 'refresh_token',
                'scope' => 'https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send offline_access',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->updateAccountToken($account, $data);
            }

            Log::error('Outlook token refresh failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Outlook token refresh exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update account with new token.
     */
    protected function updateAccountToken(EmailAccount $account, array $data): bool
    {
        $expiresIn = $data['expires_in'] ?? 3600;

        $account->update([
            'oauth_token' => $data['access_token'],
            'oauth_refresh_token' => $data['refresh_token'] ?? $account->oauth_refresh_token,
            'oauth_expires_at' => now()->addSeconds($expiresIn - 60), // Subtract 60s buffer
        ]);

        Log::info('OAuth token refreshed successfully', [
            'account_id' => $account->id,
            'provider' => $account->provider,
            'expires_at' => $account->oauth_expires_at,
        ]);

        return true;
    }

    /**
     * Check if token needs refresh (with buffer time).
     */
    public function needsRefresh(EmailAccount $account, int $bufferSeconds = 300): bool
    {
        if (!$account->oauth_expires_at) {
            return false;
        }

        return $account->oauth_expires_at->subSeconds($bufferSeconds)->isPast();
    }

    /**
     * Ensure token is valid before use.
     */
    public function ensureValidToken(EmailAccount $account): bool
    {
        if (!in_array($account->provider, [
            EmailAccount::PROVIDER_GMAIL,
            EmailAccount::PROVIDER_OUTLOOK,
        ])) {
            return true; // Non-OAuth accounts don't need refresh
        }

        if (!$this->needsRefresh($account)) {
            return true; // Token is still valid
        }

        return $this->refreshToken($account);
    }
}
