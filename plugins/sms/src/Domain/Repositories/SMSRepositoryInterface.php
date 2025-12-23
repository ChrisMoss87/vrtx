<?php

declare(strict_types=1);

namespace Plugins\SMS\Domain\Repositories;

interface SMSRepositoryInterface
{
    // =========================================================================
    // CONNECTIONS
    // =========================================================================

    /**
     * List all SMS connections.
     */
    public function listConnections(bool $activeOnly = false): array;

    /**
     * Get a connection by ID.
     */
    public function findConnectionById(int $id): ?array;

    /**
     * Get active connection by ID.
     */
    public function findActiveConnectionById(int $id): ?array;

    /**
     * Find connection by phone number.
     */
    public function findConnectionByPhoneNumber(string $phoneNumber): ?array;

    /**
     * Create a new connection.
     */
    public function createConnection(array $data): array;

    /**
     * Update a connection.
     */
    public function updateConnection(int $id, array $data): array;

    /**
     * Delete a connection.
     */
    public function deleteConnection(int $id): bool;

    /**
     * Get connection usage statistics.
     */
    public function getConnectionUsage(int $connectionId): array;

    // =========================================================================
    // MESSAGES
    // =========================================================================

    /**
     * List messages with filters.
     */
    public function listMessages(array $filters = [], int $perPage = 20): array;

    /**
     * Get a message by ID.
     */
    public function findMessageById(int $id): ?array;

    /**
     * Find message by provider message ID.
     */
    public function findByProviderMessageId(string $providerMessageId): ?array;

    /**
     * Get conversation history for a phone number.
     */
    public function getConversation(string $phoneNumber, int $limit = 100): array;

    /**
     * Get messages for a CRM record.
     */
    public function getRecordMessages(string $moduleApiName, int $recordId, int $limit = 50): array;

    /**
     * Create a new message.
     */
    public function createMessage(array $data): array;

    /**
     * Update message status.
     */
    public function updateMessage(int $id, array $data): array;

    // =========================================================================
    // TEMPLATES
    // =========================================================================

    /**
     * List message templates.
     */
    public function listTemplates(array $filters = []): array;

    /**
     * Get a template by ID.
     */
    public function findTemplateById(int $id): ?array;

    /**
     * Create a new template.
     */
    public function createTemplate(array $data): array;

    /**
     * Update a template.
     */
    public function updateTemplate(int $id, array $data): array;

    /**
     * Delete a template.
     */
    public function deleteTemplate(int $id): bool;

    /**
     * Increment template usage count.
     */
    public function incrementTemplateUsage(int $id): void;

    // =========================================================================
    // OPT-OUT
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
    public function listOptOuts(?int $connectionId = null, int $perPage = 50): array;

    // =========================================================================
    // CAMPAIGNS
    // =========================================================================

    /**
     * List campaigns.
     */
    public function listCampaigns(array $filters = [], int $perPage = 20): array;

    /**
     * Get a campaign by ID.
     */
    public function findCampaignById(int $id): ?array;

    /**
     * Create a campaign.
     */
    public function createCampaign(array $data): array;

    /**
     * Update a campaign.
     */
    public function updateCampaign(int $id, array $data): array;

    /**
     * Get campaign statistics.
     */
    public function getCampaignStats(int $campaignId): array;

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    /**
     * Get message statistics.
     */
    public function getMessageStats(?int $connectionId = null, ?string $period = 'today'): array;
}
