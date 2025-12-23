<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Domain\Repositories;

interface WhatsAppRepositoryInterface
{
    // =========================================================================
    // CONNECTIONS
    // =========================================================================

    /**
     * List all WhatsApp connections.
     */
    public function listConnections(array $filters = []): array;

    /**
     * Get a connection by ID.
     */
    public function findConnectionById(int $id): ?array;

    /**
     * Get the active connection.
     */
    public function getActiveConnection(): ?array;

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

    // =========================================================================
    // CONVERSATIONS
    // =========================================================================

    /**
     * List conversations with filters.
     */
    public function listConversations(array $filters = [], int $perPage = 20): array;

    /**
     * Get a conversation by ID.
     */
    public function findConversationById(int $id): ?array;

    /**
     * Get conversation by phone number.
     */
    public function findConversationByPhone(string $phoneNumber): ?array;

    /**
     * Get or create a conversation.
     */
    public function getOrCreateConversation(string $phoneNumber, ?string $contactName = null): array;

    /**
     * Update a conversation.
     */
    public function updateConversation(int $id, array $data): array;

    /**
     * Get conversations for a CRM record.
     */
    public function getConversationsForRecord(string $moduleApiName, int $recordId): array;

    // =========================================================================
    // MESSAGES
    // =========================================================================

    /**
     * List messages in a conversation.
     */
    public function listMessages(int $conversationId, int $perPage = 50): array;

    /**
     * Get a message by ID.
     */
    public function findMessageById(int $id): ?array;

    /**
     * Get a message by WhatsApp message ID.
     */
    public function findMessageByWhatsAppId(string $whatsappMessageId): ?array;

    /**
     * Create a new message.
     */
    public function createMessage(array $data): array;

    /**
     * Update message status.
     */
    public function updateMessageStatus(int $id, string $status): array;

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
     * Get a template by slug.
     */
    public function findTemplateBySlug(string $slug): ?array;

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

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    /**
     * Get message statistics.
     */
    public function getMessageStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array;

    /**
     * Get conversation statistics.
     */
    public function getConversationStats(): array;
}
