<?php

declare(strict_types=1);

namespace App\Application\Services\Support;

use App\Domain\Support\Repositories\SupportTicketRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;

class SupportApplicationService
{
    public function __construct(
        private SupportTicketRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - TICKETS
    // =========================================================================

    /**
     * List tickets with filtering and pagination.
     */
    public function listTickets(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listTickets($filters, $perPage, $page);
    }

    /**
     * Get a single ticket by ID.
     */
    public function getTicket(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Get ticket by ticket number.
     */
    public function getTicketByNumber(string $ticketNumber): ?array
    {
        return $this->repository->findByTicketNumber($ticketNumber);
    }

    /**
     * Get open tickets assigned to a user.
     */
    public function getAssignedTickets(int $userId, bool $openOnly = true): array
    {
        return $this->repository->getAssignedTickets($userId, $openOnly);
    }

    /**
     * Get unassigned open tickets.
     */
    public function getUnassignedTickets(): array
    {
        return $this->repository->getUnassignedTickets();
    }

    /**
     * Get overdue SLA tickets.
     */
    public function getOverdueSlaTickets(): array
    {
        return $this->repository->getOverdueSlaTickets();
    }

    // =========================================================================
    // COMMAND USE CASES - TICKETS
    // =========================================================================

    /**
     * Create a new support ticket.
     */
    public function createTicket(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $ticketNumber = $this->repository->generateTicketNumber();

            $ticketData = [
                'ticket_number' => $ticketNumber,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => $data['status'] ?? 'open',
                'priority' => $data['priority'] ?? 2, // Medium
                'category_id' => $data['category_id'] ?? null,
                'submitter_id' => $data['submitter_id'] ?? $this->authContext->userId(),
                'portal_user_id' => $data['portal_user_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'account_id' => $data['account_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'channel' => $data['channel'] ?? 'portal',
                'tags' => $data['tags'] ?? [],
                'custom_fields' => $data['custom_fields'] ?? [],
            ];

            $ticket = $this->repository->create($ticketData);

            // Calculate SLA deadlines if category has SLA settings
            if (!empty($ticket['category_id'])) {
                $this->repository->calculateSlaDeadlines($ticket['id']);
            }

            // Log ticket creation activity
            $userId = $this->authContext->userId();
            if ($userId) {
                $this->repository->logActivity($ticket['id'], $userId, 'created', 'Ticket created');
            }

            // Auto-assign based on category default assignee
            if (empty($ticket['assigned_to']) && !empty($ticket['category']['default_assignee_id'])) {
                $ticket = $this->assignTicket($ticket['id'], $ticket['category']['default_assignee_id']);
            }

            return $this->repository->findById($ticket['id']);
        });
    }

    /**
     * Update a ticket.
     */
    public function updateTicket(int $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            $ticket = $this->repository->findById($id);
            if (!$ticket) {
                throw new \RuntimeException("Ticket not found: {$id}");
            }

            $changes = [];

            // Track status changes
            if (isset($data['status']) && $data['status'] !== $ticket['status']) {
                $changes['status'] = ['from' => $ticket['status'], 'to' => $data['status']];
            }

            // Track priority changes
            if (isset($data['priority']) && $data['priority'] !== $ticket['priority']) {
                $changes['priority'] = ['from' => $ticket['priority'], 'to' => $data['priority']];
            }

            // Update ticket
            $updateData = [
                'subject' => $data['subject'] ?? $ticket['subject'],
                'description' => $data['description'] ?? $ticket['description'],
                'status' => $data['status'] ?? $ticket['status'],
                'priority' => $data['priority'] ?? $ticket['priority'],
                'category_id' => $data['category_id'] ?? $ticket['category_id'],
                'assigned_to' => $data['assigned_to'] ?? $ticket['assigned_to'],
                'team_id' => $data['team_id'] ?? $ticket['team_id'],
                'tags' => $data['tags'] ?? $ticket['tags'],
                'custom_fields' => array_merge($ticket['custom_fields'] ?? [], $data['custom_fields'] ?? []),
            ];

            $ticket = $this->repository->update($id, $updateData);

            // Log changes
            $userId = $this->authContext->userId();
            if ($userId) {
                foreach ($changes as $field => $change) {
                    $this->repository->logActivity(
                        $id,
                        $userId,
                        'updated',
                        "{$field} changed from {$change['from']} to {$change['to']}"
                    );
                }
            }

            return $ticket;
        });
    }

    /**
     * Assign ticket to a user.
     */
    public function assignTicket(int $ticketId, int $userId): array
    {
        return DB::transaction(function () use ($ticketId, $userId) {
            $ticket = $this->repository->findById($ticketId);
            if (!$ticket) {
                throw new \RuntimeException("Ticket not found: {$ticketId}");
            }

            $oldAssignee = $ticket['assigned_to'] ?? null;

            $ticket = $this->repository->update($ticketId, ['assigned_to' => $userId]);

            if ($oldAssignee !== $userId) {
                $currentUserId = $this->authContext->userId();
                if ($currentUserId) {
                    $this->repository->logActivity(
                        $ticketId,
                        $currentUserId,
                        'assigned',
                        "Ticket assigned to user ID: {$userId}"
                    );
                }
            }

            return $ticket;
        });
    }

    /**
     * Assign ticket to a team.
     */
    public function assignToTeam(int $ticketId, int $teamId): array
    {
        return DB::transaction(function () use ($ticketId, $teamId) {
            $ticket = $this->repository->update($ticketId, ['team_id' => $teamId]);

            $userId = $this->authContext->userId();
            if ($userId) {
                $this->repository->logActivity(
                    $ticketId,
                    $userId,
                    'assigned',
                    "Ticket assigned to team ID: {$teamId}"
                );
            }

            return $ticket;
        });
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(int $ticketId, string $status): array
    {
        return DB::transaction(function () use ($ticketId, $status) {
            $ticket = $this->repository->findById($ticketId);
            if (!$ticket) {
                throw new \RuntimeException("Ticket not found: {$ticketId}");
            }

            $oldStatus = $ticket['status'];

            $updates = ['status' => $status];

            // Set resolved_at timestamp
            if ($status === 'resolved' && empty($ticket['resolved_at'])) {
                $updates['resolved_at'] = now();
            }

            // Set closed_at timestamp
            if ($status === 'closed' && empty($ticket['closed_at'])) {
                $updates['closed_at'] = now();
            }

            $ticket = $this->repository->update($ticketId, $updates);

            $userId = $this->authContext->userId();
            if ($userId) {
                $this->repository->logActivity(
                    $ticketId,
                    $userId,
                    'status_changed',
                    "Status changed from {$oldStatus} to {$status}"
                );
            }

            return $ticket;
        });
    }

    /**
     * Delete a ticket.
     */
    public function deleteTicket(int $id): bool
    {
        return $this->repository->delete($id);
    }

    // =========================================================================
    // COMMAND USE CASES - REPLIES
    // =========================================================================

    /**
     * Add a reply to a ticket.
     */
    public function addReply(int $ticketId, array $data): array
    {
        return DB::transaction(function () use ($ticketId, $data) {
            $replyData = [
                'content' => $data['content'],
                'user_id' => $data['user_id'] ?? $this->authContext->userId(),
                'portal_user_id' => $data['portal_user_id'] ?? null,
                'is_internal' => $data['is_internal'] ?? false,
                'is_system' => $data['is_system'] ?? false,
                'attachments' => $data['attachments'] ?? [],
            ];

            $reply = $this->repository->addReply($ticketId, $replyData);

            // Log activity
            $userId = $this->authContext->userId();
            if ($userId) {
                $visibility = $reply['is_internal'] ? 'internal' : 'public';
                $this->repository->logActivity($ticketId, $userId, 'replied', "Added {$visibility} reply");
            }

            return $reply;
        });
    }

    /**
     * Update a reply.
     */
    public function updateReply(int $replyId, array $data): array
    {
        $updateData = [
            'content' => $data['content'] ?? null,
            'is_internal' => $data['is_internal'] ?? null,
            'attachments' => $data['attachments'] ?? null,
        ];

        // Remove null values
        $updateData = array_filter($updateData, fn($value) => $value !== null);

        return $this->repository->updateReply($replyId, $updateData);
    }

    /**
     * Delete a reply.
     */
    public function deleteReply(int $replyId): bool
    {
        return $this->repository->deleteReply($replyId);
    }

    // =========================================================================
    // QUERY USE CASES - CATEGORIES
    // =========================================================================

    /**
     * List ticket categories.
     */
    public function listCategories(bool $activeOnly = false): array
    {
        return $this->repository->listCategories($activeOnly);
    }

    /**
     * Get a category by ID.
     */
    public function getCategory(int $id): ?array
    {
        return $this->repository->getCategoryById($id);
    }

    // =========================================================================
    // COMMAND USE CASES - CATEGORIES
    // =========================================================================

    /**
     * Create a category.
     */
    public function createCategory(array $data): array
    {
        $categoryData = [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#3B82F6',
            'default_assignee_id' => $data['default_assignee_id'] ?? null,
            'default_priority' => $data['default_priority'] ?? 2,
            'sla_response_hours' => $data['sla_response_hours'] ?? null,
            'sla_resolution_hours' => $data['sla_resolution_hours'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'display_order' => $data['display_order'] ?? 0,
        ];

        return $this->repository->createCategory($categoryData);
    }

    /**
     * Update a category.
     */
    public function updateCategory(int $id, array $data): array
    {
        $category = $this->repository->getCategoryById($id);
        if (!$category) {
            throw new \RuntimeException("Category not found: {$id}");
        }

        $updateData = [
            'name' => $data['name'] ?? $category['name'],
            'description' => $data['description'] ?? $category['description'],
            'color' => $data['color'] ?? $category['color'],
            'default_assignee_id' => $data['default_assignee_id'] ?? $category['default_assignee_id'],
            'default_priority' => $data['default_priority'] ?? $category['default_priority'],
            'sla_response_hours' => $data['sla_response_hours'] ?? $category['sla_response_hours'],
            'sla_resolution_hours' => $data['sla_resolution_hours'] ?? $category['sla_resolution_hours'],
            'is_active' => $data['is_active'] ?? $category['is_active'],
            'display_order' => $data['display_order'] ?? $category['display_order'],
        ];

        return $this->repository->updateCategory($id, $updateData);
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(int $id): bool
    {
        return $this->repository->deleteCategory($id);
    }

    // =========================================================================
    // ESCALATION USE CASES
    // =========================================================================

    /**
     * Escalate a ticket.
     */
    public function escalateTicket(int $ticketId, array $data): array
    {
        return DB::transaction(function () use ($ticketId, $data) {
            $ticket = $this->repository->findById($ticketId);
            if (!$ticket) {
                throw new \RuntimeException("Ticket not found: {$ticketId}");
            }

            $escalationData = [
                'escalated_to' => $data['escalated_to'],
                'escalated_by' => $this->authContext->userId(),
                'reason' => $data['reason'] ?? null,
                'priority_before' => $ticket['priority'],
                'priority_after' => $data['new_priority'] ?? ($ticket['priority'] + 1),
            ];

            $escalation = $this->repository->createEscalation($ticketId, $escalationData);

            // Update ticket priority if specified
            if (isset($data['new_priority'])) {
                $this->repository->update($ticketId, ['priority' => $data['new_priority']]);
            }

            // Reassign if specified
            if (isset($data['reassign_to'])) {
                $this->assignTicket($ticketId, $data['reassign_to']);
            }

            $userId = $this->authContext->userId();
            if ($userId) {
                $this->repository->logActivity(
                    $ticketId,
                    $userId,
                    'escalated',
                    'Ticket escalated: ' . ($data['reason'] ?? 'No reason provided')
                );
            }

            return $escalation;
        });
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get ticket statistics.
     */
    public function getTicketStats(array $filters = []): array
    {
        return $this->repository->getTicketStats($filters);
    }

    /**
     * Get agent performance stats.
     */
    public function getAgentPerformance(int $userId, int $days = 30): array
    {
        return $this->repository->getAgentPerformance($userId, $days);
    }

    /**
     * Get daily ticket counts.
     */
    public function getDailyTicketCounts(int $days = 30): array
    {
        return $this->repository->getDailyTicketCounts($days);
    }
}
