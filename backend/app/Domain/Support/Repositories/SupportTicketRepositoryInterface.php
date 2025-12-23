<?php

declare(strict_types=1);

namespace App\Domain\Support\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Support\Entities\SupportTicket;

interface SupportTicketRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    /**
     * Find a ticket entity by ID.
     */
    public function findById(int $id): ?SupportTicket;

    /**
     * Save a ticket entity.
     */
    public function save(SupportTicket $entity): SupportTicket;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    /**
     * Find a ticket by ID with all relations (backward-compatible).
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find a ticket by ticket number.
     */
    public function findByTicketNumber(string $ticketNumber): ?array;

    /**
     * List tickets with filtering and pagination.
     */
    public function listTickets(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get tickets assigned to a specific user.
     */
    public function getAssignedTickets(int $userId, bool $openOnly = true): array;

    /**
     * Get unassigned open tickets.
     */
    public function getUnassignedTickets(): array;

    /**
     * Get tickets with overdue SLA.
     */
    public function getOverdueSlaTickets(): array;

    /**
     * Create a new ticket.
     */
    public function create(array $data): array;

    /**
     * Update a ticket.
     */
    public function update(int $id, array $data): array;

    /**
     * Delete a ticket.
     */
    public function delete(int $id): bool;

    /**
     * Generate a unique ticket number.
     */
    public function generateTicketNumber(): string;

    /**
     * Get ticket statistics.
     */
    public function getTicketStats(array $filters = []): array;

    /**
     * Get agent performance statistics.
     */
    public function getAgentPerformance(int $userId, int $days = 30): array;

    /**
     * Get daily ticket counts.
     */
    public function getDailyTicketCounts(int $days = 30): array;

    /**
     * Add a reply to a ticket.
     */
    public function addReply(int $ticketId, array $data): array;

    /**
     * Update a reply.
     */
    public function updateReply(int $replyId, array $data): array;

    /**
     * Delete a reply.
     */
    public function deleteReply(int $replyId): bool;

    /**
     * Create an escalation for a ticket.
     */
    public function createEscalation(int $ticketId, array $data): array;

    /**
     * Log ticket activity.
     */
    public function logActivity(int $ticketId, int $userId, string $action, string $description): void;

    /**
     * Calculate SLA deadlines for a ticket.
     */
    public function calculateSlaDeadlines(int $ticketId): void;

    /**
     * List ticket categories.
     */
    public function listCategories(bool $activeOnly = false): array;

    /**
     * Get a category by ID.
     */
    public function getCategoryById(int $id): ?array;

    /**
     * Create a category.
     */
    public function createCategory(array $data): array;

    /**
     * Update a category.
     */
    public function updateCategory(int $id, array $data): array;

    /**
     * Delete a category.
     */
    public function deleteCategory(int $id): bool;
}
