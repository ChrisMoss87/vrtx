<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Email;

use App\Application\Services\Email\EmailApplicationService;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Services\Email\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailAccountController extends Controller
{
    public function __construct(
        protected EmailApplicationService $emailApplicationService,
        protected EmailService $emailService
    ) {}

    /**
     * List user's email accounts.
     */
    public function index(): JsonResponse
    {
        $accounts = EmailAccount::where('user_id', Auth::id())
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $accounts->map(fn($account) => $this->formatAccount($account)),
        ]);
    }

    /**
     * Create a new email account.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email_address' => 'required|email|max:255',
            'provider' => 'required|string|in:imap,gmail,outlook,smtp_only',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|string|in:ssl,tls,none',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|string|in:ssl,tls,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'signature' => 'nullable|string',
            'sync_folders' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            EmailAccount::where('user_id', Auth::id())
                ->update(['is_default' => false]);
        }

        $account = EmailAccount::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return response()->json([
            'data' => $this->formatAccount($account),
            'message' => 'Email account created successfully',
        ], 201);
    }

    /**
     * Get a single email account.
     */
    public function show(EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('view', $emailAccount);

        return response()->json([
            'data' => $this->formatAccount($emailAccount),
        ]);
    }

    /**
     * Update an email account.
     */
    public function update(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('update', $emailAccount);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email_address' => 'sometimes|email|max:255',
            'provider' => 'sometimes|string|in:imap,gmail,outlook,smtp_only',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|string|in:ssl,tls,none',
            'smtp_host' => 'sometimes|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|string|in:ssl,tls,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'signature' => 'nullable|string',
            'sync_folders' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sync_enabled' => 'nullable|boolean',
        ]);

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            EmailAccount::where('user_id', Auth::id())
                ->where('id', '!=', $emailAccount->id)
                ->update(['is_default' => false]);
        }

        $emailAccount->update($validated);

        return response()->json([
            'data' => $this->formatAccount($emailAccount->fresh()),
            'message' => 'Email account updated successfully',
        ]);
    }

    /**
     * Delete an email account.
     */
    public function destroy(EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('delete', $emailAccount);

        $emailAccount->delete();

        return response()->json([
            'message' => 'Email account deleted successfully',
        ]);
    }

    /**
     * Test email account connection.
     */
    public function testConnection(EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('view', $emailAccount);

        $connected = $this->emailService->connect($emailAccount);
        $this->emailService->disconnect();

        return response()->json([
            'success' => $connected,
            'message' => $connected
                ? 'Connection successful'
                : 'Connection failed. Please check your settings.',
        ]);
    }

    /**
     * Sync emails for an account.
     */
    public function sync(EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('update', $emailAccount);

        $messages = $this->emailService->fetchNewEmails($emailAccount);
        $this->emailService->disconnect();

        return response()->json([
            'success' => true,
            'message' => sprintf('Synced %d new emails', $messages->count()),
            'count' => $messages->count(),
        ]);
    }

    /**
     * Get folders for an account.
     */
    public function folders(EmailAccount $emailAccount): JsonResponse
    {
        $this->authorize('view', $emailAccount);

        $folders = $this->emailService->getFolders($emailAccount);
        $this->emailService->disconnect();

        return response()->json([
            'data' => $folders,
        ]);
    }

    /**
     * Format account for response.
     */
    protected function formatAccount(EmailAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'email_address' => $account->email_address,
            'provider' => $account->provider,
            'imap_host' => $account->imap_host,
            'imap_port' => $account->imap_port,
            'imap_encryption' => $account->imap_encryption,
            'smtp_host' => $account->smtp_host,
            'smtp_port' => $account->smtp_port,
            'smtp_encryption' => $account->smtp_encryption,
            'username' => $account->username,
            'is_active' => $account->is_active,
            'is_default' => $account->is_default,
            'sync_enabled' => $account->sync_enabled,
            'sync_folders' => $account->sync_folders,
            'signature' => $account->signature,
            'last_sync_at' => $account->last_sync_at?->toISOString(),
            'created_at' => $account->created_at->toISOString(),
            'updated_at' => $account->updated_at->toISOString(),
        ];
    }
}
