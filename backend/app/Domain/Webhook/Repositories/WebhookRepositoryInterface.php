<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;

interface WebhookRepositoryInterface
{
    // =========================================================================
    // OUTGOING WEBHOOKS - QUERY METHODS
    // =========================================================================

    /**
     * List outgoing webhooks with filtering and pagination.
     */
    public function listWebhooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get a webhook by ID with relations.
     */
    public function getWebhook(int $id): ?array;

    /**
     * Get webhook delivery history.
     */
    public function getDeliveryHistory(int $webhookId, int $perPage = 50, int $page = 1): PaginatedResult;

    /**
     * Get webhook statistics.
     */
    public function getWebhookStats(int $webhookId): array;

    /**
     * Get webhooks for an event.
     */
    public function getWebhooksForEvent(string $event, ?int $moduleId = null): array;

    /**
     * Get available webhook events.
     */
    public function getAvailableEvents(): array;

    // =========================================================================
    // OUTGOING WEBHOOKS - COMMAND METHODS
    // =========================================================================

    /**
     * Create a new outgoing webhook.
     */
    public function createWebhook(array $data): array;

    /**
     * Update a webhook.
     */
    public function updateWebhook(int $id, array $data): array;

    /**
     * Delete a webhook.
     */
    public function deleteWebhook(int $id): bool;

    /**
     * Regenerate webhook secret.
     */
    public function regenerateSecret(int $id): string;

    /**
     * Toggle webhook active status.
     */
    public function toggleActive(int $id): array;

    // =========================================================================
    // WEBHOOK DELIVERY METHODS
    // =========================================================================

    /**
     * Queue a webhook delivery.
     */
    public function queueDelivery(int $webhookId, string $event, array $payload): array;

    /**
     * Get pending deliveries.
     */
    public function getPendingDeliveries(int $limit = 100): array;

    /**
     * Get delivery by ID.
     */
    public function getDelivery(int $deliveryId): ?array;

    /**
     * Update delivery status.
     */
    public function updateDeliveryStatus(int $deliveryId, array $data): array;

    /**
     * Get webhook for delivery.
     */
    public function getWebhookForDelivery(int $deliveryId): ?array;

    // =========================================================================
    // INCOMING WEBHOOKS - QUERY METHODS
    // =========================================================================

    /**
     * List incoming webhooks with filtering and pagination.
     */
    public function listIncomingWebhooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get an incoming webhook by ID.
     */
    public function getIncomingWebhook(int $id): ?array;

    /**
     * Get incoming webhook logs.
     */
    public function getIncomingWebhookLogs(int $webhookId, int $perPage = 50, int $page = 1): PaginatedResult;

    /**
     * Find incoming webhook by token.
     */
    public function findIncomingWebhookByToken(string $token): ?array;

    // =========================================================================
    // INCOMING WEBHOOKS - COMMAND METHODS
    // =========================================================================

    /**
     * Create an incoming webhook.
     */
    public function createIncomingWebhook(array $data): array;

    /**
     * Update an incoming webhook.
     */
    public function updateIncomingWebhook(int $id, array $data): array;

    /**
     * Delete an incoming webhook.
     */
    public function deleteIncomingWebhook(int $id): bool;

    /**
     * Regenerate incoming webhook token.
     */
    public function regenerateIncomingToken(int $id): string;

    /**
     * Create incoming webhook log.
     */
    public function createIncomingWebhookLog(array $data): array;

    /**
     * Update incoming webhook log.
     */
    public function updateIncomingWebhookLog(int $logId, array $data): array;

    /**
     * Record incoming webhook received.
     */
    public function recordIncomingWebhookReceived(int $webhookId): void;

    // =========================================================================
    // MODULE RECORD METHODS
    // =========================================================================

    /**
     * Create a module record.
     */
    public function createModuleRecord(int $moduleId, array $data, int $userId): array;

    /**
     * Update a module record.
     */
    public function updateModuleRecord(int $recordId, array $data): array;

    /**
     * Find module record by field value.
     */
    public function findModuleRecordByField(int $moduleId, string $field, mixed $value): ?array;
}
