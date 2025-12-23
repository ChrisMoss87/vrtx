<?php

declare(strict_types=1);

namespace App\Domain\Sms\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Sms\Entities\SmsMessage;

interface SmsMessageRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    /**
     * Find a message entity by ID.
     */
    public function findById(int $id): ?SmsMessage;

    /**
     * Save a message entity.
     */
    public function save(SmsMessage $entity): SmsMessage;

    // =========================================================================
    // QUERY METHODS - MESSAGES
    // =========================================================================

    /**
     * Find a message by ID with optional relations (backward-compatible).
     */
    public function findByIdAsArray(int $id, array $relations = []): ?array;

    /**
     * List messages with filtering and pagination.
     */
    public function listMessages(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get conversation history for a phone number.
     */
    public function getConversation(string $phoneNumber, int $limit = 100): array;

    /**
     * Get messages for a module record.
     */
    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): array;

    /**
     * Get SMS statistics.
     */
    public function getStats(?int $connectionId = null, ?string $period = 'today'): array;

    /**
     * Find message by provider message ID.
     */
    public function findByProviderMessageId(string $providerMessageId): ?array;

    // =========================================================================
    // COMMAND METHODS - MESSAGES
    // =========================================================================

    /**
     * Create an SMS message.
     */
    public function create(array $data): array;

    /**
     * Update a message.
     */
    public function update(int $id, array $data): ?array;

    /**
     * Delete a message.
     */
    public function delete(int $id): bool;

    // =========================================================================
    // QUERY METHODS - TEMPLATES
    // =========================================================================

    /**
     * List SMS templates.
     */
    public function listTemplates(array $filters = []): array;

    /**
     * Find a template by ID.
     */
    public function findTemplateById(int $id): ?array;

    /**
     * Create an SMS template.
     */
    public function createTemplate(array $data): array;

    /**
     * Update an SMS template.
     */
    public function updateTemplate(int $id, array $data): ?array;

    /**
     * Delete an SMS template.
     */
    public function deleteTemplate(int $id): bool;

    /**
     * Increment template usage count.
     */
    public function incrementTemplateUsage(int $id): void;

    // =========================================================================
    // QUERY METHODS - CONNECTIONS
    // =========================================================================

    /**
     * List SMS connections.
     */
    public function listConnections(bool $activeOnly = false): array;

    /**
     * Find a connection by ID.
     */
    public function findConnectionById(int $id): ?array;

    /**
     * Find active connection by ID.
     */
    public function findActiveConnectionById(int $id): ?array;

    /**
     * Find connection by phone number.
     */
    public function findConnectionByPhoneNumber(string $phoneNumber): ?array;

    /**
     * Get connection usage stats.
     */
    public function getConnectionUsage(int $connectionId): array;

    /**
     * Create an SMS connection.
     */
    public function createConnection(array $data): array;

    /**
     * Update an SMS connection.
     */
    public function updateConnection(int $id, array $data): ?array;

    /**
     * Delete an SMS connection.
     */
    public function deleteConnection(int $id): bool;

    /**
     * Check if connection has messages.
     */
    public function connectionHasMessages(int $connectionId): bool;

    // =========================================================================
    // OPT-OUT METHODS
    // =========================================================================

    /**
     * Check if a phone number has opted out.
     */
    public function isOptedOut(string $phoneNumber, ?int $connectionId = null): bool;

    /**
     * Record an opt-out.
     */
    public function recordOptOut(string $phoneNumber, ?int $connectionId = null, ?string $reason = null): array;

    /**
     * Remove an opt-out.
     */
    public function removeOptOut(string $phoneNumber, ?int $connectionId = null): bool;

    /**
     * List opt-outs.
     */
    public function listOptOuts(?int $connectionId = null, int $perPage = 50, int $page = 1): PaginatedResult;

    // =========================================================================
    // CAMPAIGN METHODS
    // =========================================================================

    /**
     * List SMS campaigns.
     */
    public function listCampaigns(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Find a campaign by ID.
     */
    public function findCampaignById(int $id): ?array;

    /**
     * Create an SMS campaign.
     */
    public function createCampaign(array $data): array;

    /**
     * Update a campaign.
     */
    public function updateCampaign(int $id, array $data): ?array;

    /**
     * Get campaign statistics.
     */
    public function getCampaignStats(int $campaignId): array;
}
