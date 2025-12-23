<?php

declare(strict_types=1);

namespace App\Application\Services\Email;

use App\Domain\Email\DTOs\CreateEmailDTO;
use App\Domain\Email\DTOs\EmailAccountTokensDTO;
use App\Domain\Email\DTOs\EmailResponseDTO;
use App\Domain\Email\DTOs\OAuthCallbackDTO;
use App\Domain\Email\Entities\EmailAccount;
use App\Domain\Email\Entities\EmailMessage;
use App\Domain\Email\Entities\EmailTemplate;
use App\Domain\Email\Repositories\EmailAccountRepositoryInterface;
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use App\Domain\Email\Services\EmailSendingService;
use App\Domain\Email\Services\OAuthAuthorizationServiceInterface;
use App\Domain\Email\ValueObjects\EmailType;
use App\Domain\Email\ValueObjects\OAuthProvider;
use Illuminate\Support\Facades\DB;

class EmailApplicationService
{
    public function __construct(
        private readonly EmailMessageRepositoryInterface $emailRepository,
        private readonly EmailTemplateRepositoryInterface $templateRepository,
        private readonly EmailAccountRepositoryInterface $accountRepository,
        private readonly EmailSendingService $sendingService,
        private readonly ?OAuthAuthorizationServiceInterface $oauthService = null,
    ) {}

    public function sendEmail(CreateEmailDTO $dto): EmailResponseDTO
    {
        return DB::transaction(function () use ($dto) {
            $type = $dto->templateId ? EmailType::TEMPLATE : EmailType::MANUAL;

            $email = EmailMessage::create(
                accountId: $dto->accountId,
                fromEmail: $dto->fromEmail,
                toRecipients: $dto->toRecipients,
                subject: $dto->subject,
                type: $type,
            );

            if ($dto->bodyHtml) {
                $email->setBody($dto->bodyHtml, $dto->bodyText);
            }

            $email->setRecipients($dto->toRecipients, $dto->ccRecipients, $dto->bccRecipients);

            if ($dto->moduleId && $dto->recordId) {
                $email->linkToRecord($dto->moduleId, $dto->recordId);
            }

            $sentEmail = $this->sendingService->send($email);

            return EmailResponseDTO::fromEntity($sentEmail);
        });
    }

    public function getEmailsForRecord(int $moduleId, int $recordId): array
    {
        $emails = $this->emailRepository->findByRecordId($moduleId, $recordId);
        return array_map(fn($e) => EmailResponseDTO::fromEntity($e), $emails);
    }

    public function getEmailThread(string $threadId): array
    {
        $emails = $this->emailRepository->findByThreadId($threadId);
        return array_map(fn($e) => EmailResponseDTO::fromEntity($e), $emails);
    }

    public function createTemplate(string $name, ?string $subject, ?string $bodyHtml, ?int $userId = null): EmailTemplate
    {
        $template = EmailTemplate::create($name, $userId);
        $template->update($name, $subject, $bodyHtml);
        return $this->templateRepository->save($template);
    }

    public function getTemplates(?int $moduleId = null): array
    {
        if ($moduleId) {
            return $this->templateRepository->findByModuleId($moduleId);
        }
        return $this->templateRepository->findActive();
    }

    public function getUserAccounts(int $userId): array
    {
        return $this->accountRepository->findByUserId($userId);
    }

    public function recordEmailOpen(int $emailId): void
    {
        $email = $this->emailRepository->findById($emailId);
        if ($email) {
            $email->recordOpen();
            $this->emailRepository->save($email);
        }
    }

    public function recordEmailClick(int $emailId): void
    {
        $email = $this->emailRepository->findById($emailId);
        if ($email) {
            $email->recordClick();
            $this->emailRepository->save($email);
        }
    }

    // ==================== OAuth Methods ====================

    /**
     * Initiate OAuth connection for a provider.
     */
    public function initiateOAuthConnection(int $userId, string $provider, ?string $redirectTo = null): array
    {
        $this->ensureOAuthServiceAvailable();

        $oauthProvider = OAuthProvider::from($provider);
        $url = $this->oauthService->generateAuthorizationUrl(
            userId: $userId,
            provider: $oauthProvider,
            redirectTo: $redirectTo,
        );

        return ['oauth_url' => $url];
    }

    /**
     * Reconnect an existing OAuth account.
     */
    public function reconnectOAuthAccount(int $userId, int $accountId): array
    {
        $this->ensureOAuthServiceAvailable();

        $account = $this->accountRepository->findById($accountId);

        if (!$account) {
            throw new \InvalidArgumentException('Email account not found');
        }

        if ($account->getUserId() !== $userId) {
            throw new \InvalidArgumentException('Not authorized to reconnect this account');
        }

        $provider = OAuthProvider::tryFrom($account->getProvider());
        if (!$provider) {
            throw new \InvalidArgumentException('Account does not support OAuth');
        }

        $url = $this->oauthService->generateAuthorizationUrl(
            userId: $userId,
            provider: $provider,
            reconnectAccountId: $accountId,
        );

        return ['oauth_url' => $url];
    }

    /**
     * Handle OAuth callback and create/update email account.
     */
    public function handleOAuthCallback(OAuthCallbackDTO $dto): array
    {
        $this->ensureOAuthServiceAvailable();

        if ($dto->hasError()) {
            return [
                'success' => false,
                'error' => $dto->getErrorMessage(),
            ];
        }

        if (!$dto->isValid()) {
            return [
                'success' => false,
                'error' => 'Invalid OAuth callback parameters',
            ];
        }

        try {
            // Validate and decode state
            $state = $this->oauthService->validateState($dto->state);

            // Exchange code for tokens
            $tokens = $this->oauthService->exchangeCodeForTokens($dto->code, $state->provider);

            // Find or create the email account
            $account = $this->findOrCreateOAuthAccount($state, $tokens);

            return [
                'success' => true,
                'account_id' => $account->getId(),
                'email' => $tokens->email,
                'redirect_to' => $state->redirectTo,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect an OAuth account (revoke tokens).
     */
    public function disconnectOAuthAccount(int $userId, int $accountId): bool
    {
        $this->ensureOAuthServiceAvailable();

        $account = $this->accountRepository->findById($accountId);

        if (!$account || $account->getUserId() !== $userId) {
            throw new \InvalidArgumentException('Email account not found or not authorized');
        }

        $provider = OAuthProvider::tryFrom($account->getProvider());
        if (!$provider) {
            throw new \InvalidArgumentException('Account does not support OAuth');
        }

        // Revoke tokens at the provider
        $accessToken = $account->getSettings()['oauth_token'] ?? null;
        if ($accessToken) {
            $this->oauthService->revokeTokens($accessToken, $provider);
        }

        // Deactivate the account
        $account->deactivate();
        $this->accountRepository->save($account);

        return true;
    }

    /**
     * Get OAuth account connection status.
     */
    public function getOAuthAccountStatus(int $accountId): array
    {
        $account = $this->accountRepository->findById($accountId);

        if (!$account) {
            return ['status' => 'not_found'];
        }

        $settings = $account->getSettings();
        $expiresAt = $settings['oauth_expires_at'] ?? null;

        $isExpired = $expiresAt && new \DateTimeImmutable($expiresAt) < new \DateTimeImmutable();
        $connectionStatus = $settings['connection_status'] ?? 'unknown';

        return [
            'status' => $connectionStatus,
            'is_active' => $account->isActive(),
            'is_expired' => $isExpired,
            'email' => $account->getEmail(),
            'provider' => $account->getProvider(),
            'last_synced_at' => $account->getLastSyncedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Find or create an OAuth email account.
     */
    private function findOrCreateOAuthAccount(
        \App\Domain\Email\ValueObjects\OAuthState $state,
        EmailAccountTokensDTO $tokens,
    ): EmailAccount {
        return DB::transaction(function () use ($state, $tokens) {
            // If reconnecting, update the existing account
            if ($state->isReconnect()) {
                $account = $this->accountRepository->findById($state->reconnectAccountId);
                if (!$account) {
                    throw new \InvalidArgumentException('Account to reconnect not found');
                }

                $account->updateSettings([
                    'oauth_token' => $tokens->accessToken,
                    'oauth_refresh_token' => $tokens->refreshToken,
                    'oauth_expires_at' => $tokens->expiresAt->format(\DateTimeInterface::ATOM),
                    'connection_status' => 'connected',
                    'connection_error' => null,
                ]);
                $account->activate();

                return $this->accountRepository->save($account);
            }

            // Check if account already exists for this email
            $existing = $this->accountRepository->findByEmail($state->userId, $tokens->email);
            if ($existing) {
                $existing->updateSettings([
                    'oauth_token' => $tokens->accessToken,
                    'oauth_refresh_token' => $tokens->refreshToken,
                    'oauth_expires_at' => $tokens->expiresAt->format(\DateTimeInterface::ATOM),
                    'connection_status' => 'connected',
                    'connection_error' => null,
                ]);
                $existing->activate();

                return $this->accountRepository->save($existing);
            }

            // Create new account
            $account = EmailAccount::create(
                userId: $state->userId,
                email: $tokens->email,
                provider: $tokens->provider->value,
            );

            $account->updateSettings([
                'name' => $tokens->name,
                'oauth_token' => $tokens->accessToken,
                'oauth_refresh_token' => $tokens->refreshToken,
                'oauth_expires_at' => $tokens->expiresAt->format(\DateTimeInterface::ATOM),
                'connection_status' => 'connected',
                'imap_host' => $tokens->provider->getImapHost(),
                'imap_port' => $tokens->provider->getImapPort(),
                'smtp_host' => $tokens->provider->getSmtpHost(),
                'smtp_port' => $tokens->provider->getSmtpPort(),
                'encryption' => 'ssl',
            ]);

            return $this->accountRepository->save($account);
        });
    }

    /**
     * Ensure OAuth service is available.
     */
    private function ensureOAuthServiceAvailable(): void
    {
        if (!$this->oauthService) {
            throw new \RuntimeException('OAuth service is not configured');
        }
    }
}
