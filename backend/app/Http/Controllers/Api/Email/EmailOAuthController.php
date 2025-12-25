<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Email;

use App\Application\Services\Email\EmailApplicationService;
use App\Domain\Email\DTOs\OAuthCallbackDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmailOAuthController extends Controller
{
    public function __construct(
        protected EmailApplicationService $emailApplicationService,
    ) {}

    /**
     * Get available OAuth providers.
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'id' => 'gmail',
                    'name' => 'Gmail',
                    'icon' => 'gmail',
                    'description' => 'Connect your Gmail account',
                    'configured' => !empty(config('services.google.client_id')),
                ],
                [
                    'id' => 'microsoft',
                    'name' => 'Microsoft Outlook',
                    'icon' => 'microsoft',
                    'description' => 'Connect your Outlook or Office 365 account',
                    'configured' => !empty(config('services.microsoft.client_id')),
                ],
            ],
        ]);
    }

    /**
     * Get authorization URL for OAuth provider.
     */
    public function getAuthorizationUrl(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'redirect_to' => 'nullable|string|url',
        ]);

        if (!in_array($provider, ['gmail', 'microsoft'])) {
            return response()->json([
                'error' => 'Invalid provider',
            ], 400);
        }

        try {
            $result = $this->emailApplicationService->initiateOAuthConnection(
                userId: Auth::id(),
                provider: $provider,
                redirectTo: $validated['redirect_to'] ?? null,
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('OAuth authorization failed', [
                'provider' => $provider,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to initiate OAuth: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle OAuth callback from provider.
     * This is a public endpoint (no auth required).
     */
    public function callback(Request $request): RedirectResponse
    {
        $dto = OAuthCallbackDTO::fromArray($request->all());

        $result = $this->emailApplicationService->handleOAuthCallback($dto);

        // Build redirect URL with result
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $redirectPath = $result['redirect_to'] ?? '/settings/email-accounts';

        if ($result['success']) {
            $redirectUrl = $frontendUrl . $redirectPath . '?' . http_build_query([
                'oauth' => 'success',
                'account_id' => $result['account_id'],
                'email' => $result['email'],
            ]);
        } else {
            $redirectUrl = $frontendUrl . $redirectPath . '?' . http_build_query([
                'oauth' => 'error',
                'error' => $result['error'],
            ]);
        }

        return redirect($redirectUrl);
    }

    /**
     * Reconnect an existing OAuth account.
     */
    public function reconnect(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('update', $emailAccount);

        try {
            $result = $this->emailApplicationService->reconnectOAuthAccount(
                userId: Auth::id(),
                accountId: $emailAccount->id,
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('OAuth reconnection failed', [
                'account_id' => $emailAccount->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to reconnect: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect an OAuth account.
     */
    public function disconnect(EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('update', $emailAccount);

        try {
            $this->emailApplicationService->disconnectOAuthAccount(
                userId: Auth::id(),
                accountId: $emailAccount->id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Email account disconnected successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth disconnect failed', [
                'account_id' => $emailAccount->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to disconnect: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get connection status for an OAuth account.
     */
    public function status(EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('view', $emailAccount);

        $status = $this->emailApplicationService->getOAuthAccountStatus($emailAccount->id);

        return response()->json([
            'data' => $status,
        ]);
    }
}
