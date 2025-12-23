<?php

declare(strict_types=1);

namespace App\Application\Services\Integration;

use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domain\Integration\Services\IntegrationOAuthServiceInterface;
use App\Domain\Integration\ValueObjects\AuthType;
use App\Domain\Integration\ValueObjects\ConnectionStatus;
use App\Domain\Integration\ValueObjects\IntegrationCategory;
use App\Domain\Integration\ValueObjects\IntegrationProvider;
use App\Domain\Integration\ValueObjects\SyncDirection;
use App\Domain\Integration\ValueObjects\SyncStatus;
use Illuminate\Support\Facades\Log;

class IntegrationApplicationService
{
    public function __construct(
        private readonly IntegrationConnectionRepositoryInterface $repository,
        private readonly IntegrationOAuthServiceInterface $oauthService,
    ) {}

    // ========================================
    // Query Use Cases
    // ========================================

    public function listAvailableIntegrations(): array
    {
        $integrations = [];

        foreach (IntegrationCategory::cases() as $category) {
            $providers = IntegrationProvider::getByCategory($category);
            $categoryIntegrations = [];

            foreach ($providers as $provider) {
                $connection = $this->repository->findBySlug($provider->value);

                $categoryIntegrations[] = [
                    'slug' => $provider->value,
                    'name' => $provider->label(),
                    'icon' => $provider->icon(),
                    'category' => $category->value,
                    'auth_type' => $provider->authType()->value,
                    'is_connected' => $connection !== null && $connection['status'] === ConnectionStatus::ACTIVE->value,
                    'status' => $connection['status'] ?? null,
                    'last_sync_at' => $connection['last_sync_at'] ?? null,
                ];
            }

            if (!empty($categoryIntegrations)) {
                $integrations[] = [
                    'category' => $category->value,
                    'label' => $category->label(),
                    'icon' => $category->icon(),
                    'description' => $category->description(),
                    'integrations' => $categoryIntegrations,
                ];
            }
        }

        // Sort by category order
        usort($integrations, fn($a, $b) =>
            IntegrationCategory::from($a['category'])->sortOrder() <=>
            IntegrationCategory::from($b['category'])->sortOrder()
        );

        return $integrations;
    }

    public function getConnection(string $slug): ?array
    {
        $provider = IntegrationProvider::tryFrom($slug);
        if (!$provider) {
            return null;
        }

        $connection = $this->repository->findBySlug($slug);

        if (!$connection) {
            return [
                'slug' => $slug,
                'name' => $provider->label(),
                'icon' => $provider->icon(),
                'category' => $provider->category()->value,
                'auth_type' => $provider->authType()->value,
                'is_connected' => false,
                'status' => null,
            ];
        }

        return array_merge($connection, [
            'name' => $provider->label(),
            'icon' => $provider->icon(),
            'category' => $provider->category()->value,
            'auth_type' => $provider->authType()->value,
            'is_connected' => $connection['status'] === ConnectionStatus::ACTIVE->value,
        ]);
    }

    public function getActiveConnections(): array
    {
        return $this->repository->getActive();
    }

    public function getConnectionsByCategory(IntegrationCategory $category): array
    {
        return $this->repository->getByCategory($category);
    }

    public function getSyncLogs(string $slug, int $limit = 50): array
    {
        $connection = $this->repository->findBySlug($slug);
        if (!$connection) {
            return [];
        }

        return $this->repository->getSyncLogs($connection['id'], $limit);
    }

    public function getFieldMappings(string $slug, ?string $entity = null): array
    {
        $connection = $this->repository->findBySlug($slug);
        if (!$connection) {
            return [];
        }

        return $this->repository->getFieldMappings($connection['id'], $entity);
    }

    public function getConnectionStats(): array
    {
        return $this->repository->getConnectionStats();
    }

    // ========================================
    // OAuth Flow Use Cases
    // ========================================

    public function initiateOAuthFlow(int $userId, string $slug, ?string $redirectTo = null): string
    {
        $provider = IntegrationProvider::tryFrom($slug);
        if (!$provider) {
            throw new \InvalidArgumentException("Unknown integration provider: {$slug}");
        }

        if ($provider->authType() !== AuthType::OAUTH2) {
            throw new \InvalidArgumentException("Provider {$slug} does not support OAuth");
        }

        // Check if already connected (for reconnection)
        $existing = $this->repository->findBySlug($slug);
        $reconnectId = $existing ? $existing['id'] : null;

        return $this->oauthService->generateAuthorizationUrl(
            userId: $userId,
            provider: $provider,
            reconnectConnectionId: $reconnectId,
            redirectTo: $redirectTo,
        );
    }

    public function handleOAuthCallback(string $code, string $state, int $userId): array
    {
        // Validate state
        $oauthState = $this->oauthService->validateState($state);

        if ($oauthState->userId !== $userId) {
            throw new \InvalidArgumentException('OAuth state user mismatch');
        }

        $provider = $oauthState->provider;

        // Exchange code for tokens
        $tokens = $this->oauthService->exchangeCodeForTokens($code, $provider);

        // Get provider info
        $providerInfo = [];
        try {
            $providerInfo = $this->oauthService->getProviderInfo($tokens['access_token'], $provider);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch provider info', [
                'provider' => $provider->value,
                'error' => $e->getMessage(),
            ]);
        }

        // Create or update connection
        $connectionData = [
            'integration_slug' => $provider->value,
            'name' => $providerInfo['name'] ?? $providerInfo['email'] ?? $provider->label(),
            'status' => ConnectionStatus::ACTIVE,
            'credentials' => [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ],
            'metadata' => $providerInfo,
            'token_expires_at' => $tokens['expires_at'],
            'connected_by' => $userId,
            'error_message' => null,
        ];

        if ($oauthState->isReconnect()) {
            $connection = $this->repository->update($oauthState->reconnectConnectionId, $connectionData);
        } else {
            $existing = $this->repository->findBySlug($provider->value);
            if ($existing) {
                $connection = $this->repository->update($existing['id'], $connectionData);
            } else {
                $connection = $this->repository->create($connectionData);
            }
        }

        return [
            'connection' => $connection,
            'redirect_to' => $oauthState->redirectTo,
        ];
    }

    // ========================================
    // API Key Connection Use Cases
    // ========================================

    public function connectWithApiKey(int $userId, string $slug, array $credentials): array
    {
        $provider = IntegrationProvider::tryFrom($slug);
        if (!$provider) {
            throw new \InvalidArgumentException("Unknown integration provider: {$slug}");
        }

        if ($provider->authType() !== AuthType::API_KEY) {
            throw new \InvalidArgumentException("Provider {$slug} does not support API key authentication");
        }

        $existing = $this->repository->findBySlug($slug);

        $connectionData = [
            'integration_slug' => $slug,
            'name' => $provider->label(),
            'status' => ConnectionStatus::ACTIVE,
            'credentials' => $credentials,
            'connected_by' => $userId,
            'error_message' => null,
        ];

        if ($existing) {
            return $this->repository->update($existing['id'], $connectionData);
        }

        return $this->repository->create($connectionData);
    }

    // ========================================
    // Connection Management Use Cases
    // ========================================

    public function disconnect(string $slug): bool
    {
        $connection = $this->repository->findBySlug($slug);
        if (!$connection) {
            return false;
        }

        $provider = IntegrationProvider::tryFrom($slug);

        // Try to revoke tokens if OAuth
        if ($provider?->authType() === AuthType::OAUTH2 && isset($connection['credentials']['access_token'])) {
            try {
                $credentials = $this->repository->getCredentials($connection['id']);
                if ($credentials && isset($credentials['access_token'])) {
                    $this->oauthService->revokeTokens($credentials['access_token'], $provider);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to revoke OAuth tokens', [
                    'provider' => $slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update connection status
        return $this->repository->updateStatus($connection['id'], ConnectionStatus::INACTIVE);
    }

    public function refreshToken(string $slug): bool
    {
        $connection = $this->repository->findBySlug($slug);
        if (!$connection) {
            return false;
        }

        $provider = IntegrationProvider::tryFrom($slug);
        if (!$provider || $provider->authType() !== AuthType::OAUTH2) {
            return false;
        }

        $credentials = $this->repository->getCredentials($connection['id']);
        if (!$credentials || !isset($credentials['refresh_token'])) {
            $this->repository->markAsExpired($connection['id']);
            return false;
        }

        try {
            $tokens = $this->oauthService->refreshAccessToken($credentials['refresh_token'], $provider);

            return $this->repository->updateTokens(
                $connection['id'],
                $tokens['access_token'],
                $tokens['refresh_token'],
                $tokens['expires_at'],
            );
        } catch (\Exception $e) {
            Log::error('Failed to refresh token', [
                'provider' => $slug,
                'error' => $e->getMessage(),
            ]);

            $this->repository->markAsError($connection['id'], 'Token refresh failed: ' . $e->getMessage());
            return false;
        }
    }

    public function updateSettings(string $slug, array $settings): bool
    {
        $connection = $this->repository->findBySlug($slug);
        if (!$connection) {
            return false;
        }

        return $this->repository->updateSettings($connection['id'], $settings);
    }

    // ========================================
    // Field Mapping Use Cases
    // ========================================

    public function saveFieldMappings(string $slug, string $crmEntity, array $mappings): bool
    {
        $connection = $this->repository->findBySlug($slug);
        if (!$connection) {
            return false;
        }

        return $this->repository->setFieldMappings($connection['id'], $crmEntity, $mappings);
    }

    // ========================================
    // Sync Use Cases
    // ========================================

    public function startSync(string $slug, string $entityType, SyncDirection $direction = SyncDirection::BOTH): ?array
    {
        $connection = $this->repository->findBySlug($slug);
        if (!$connection || $connection['status'] !== ConnectionStatus::ACTIVE->value) {
            return null;
        }

        // Check if already syncing
        if ($connection['sync_status'] === SyncStatus::SYNCING->value) {
            throw new \RuntimeException('Sync already in progress');
        }

        // Update sync status
        $this->repository->updateSyncStatus($connection['id'], SyncStatus::SYNCING);

        // Create sync log
        return $this->repository->createSyncLog($connection['id'], [
            'entity_type' => $entityType,
            'direction' => $direction,
        ]);
    }

    public function completeSync(int $logId, array $summary = []): bool
    {
        $success = $this->repository->completeSyncLog($logId, $summary);

        // Also update connection sync status
        // Note: Would need to get connection_id from log
        return $success;
    }

    // ========================================
    // Token Refresh Job Support
    // ========================================

    public function getConnectionsNeedingRefresh(): array
    {
        return $this->repository->getConnectionsNeedingTokenRefresh(5);
    }

    public function refreshAllExpiringTokens(): array
    {
        $connections = $this->getConnectionsNeedingRefresh();
        $results = [];

        foreach ($connections as $connection) {
            $results[$connection['integration_slug']] = $this->refreshToken($connection['integration_slug']);
        }

        return $results;
    }
}
