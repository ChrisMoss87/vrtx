<?php

declare(strict_types=1);

namespace App\Application\Services\Support;

use App\Domain\Support\Repositories\SupportTicketRepositoryInterface;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\TicketCategory;
use App\Models\TicketActivity;
use App\Models\TicketEscalation;
use App\Models\TicketCannedResponse;
use App\Models\SupportTeam;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportApplicationService
{
    public function __construct(
        private SupportTicketRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - TICKETS
    // =========================================================================

    /**
     * List tickets with filtering and pagination.
     */
    public function listTickets(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with(['submitter:id,name,email', 'assignee:id,name,email', 'category', 'team']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by priority
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Filter by category
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filter by assigned user
        if (!empty($filters['assigned_to'])) {
            $query->assignedTo($filters['assigned_to']);
        }

        // Filter by team
        if (!empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        // Filter unassigned only
        if (!empty($filters['unassigned'])) {
            $query->unassigned();
        }

        // Filter open tickets
        if (!empty($filters['open'])) {
            $query->open();
        }

        // Filter resolved tickets
        if (!empty($filters['resolved'])) {
            $query->resolved();
        }

        // Filter closed tickets
        if (!empty($filters['closed'])) {
            $query->closed();
        }

        // Filter overdue SLA
        if (!empty($filters['overdue_sla'])) {
            $query->overdueSla();
        }

        // Filter by channel
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        // Filter by submitter
        if (!empty($filters['submitter_id'])) {
            $query->where('submitter_id', $filters['submitter_id']);
        }

        // Filter by portal user
        if (!empty($filters['portal_user_id'])) {
            $query->where('portal_user_id', $filters['portal_user_id']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single ticket by ID.
     */
    public function getTicket(int $id): ?SupportTicket
    {
        return SupportTicket::with([
            'submitter:id,name,email',
            'portalUser',
            'assignee:id,name,email',
            'category',
            'team',
            'replies.user:id,name,email',
            'replies.portalUser',
            'activities.user:id,name',
            'escalations'
        ])->find($id);
    }

    /**
     * Get ticket by ticket number.
     */
    public function getTicketByNumber(string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::with([
            'submitter:id,name,email',
            'assignee:id,name,email',
            'category',
            'replies.user:id,name,email'
        ])->where('ticket_number', $ticketNumber)->first();
    }

    /**
     * Get open tickets assigned to a user.
     */
    public function getAssignedTickets(int $userId, bool $openOnly = true): Collection
    {
        $query = SupportTicket::assignedTo($userId)
            ->with(['category', 'portalUser']);

        if ($openOnly) {
            $query->open();
        }

        return $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get unassigned open tickets.
     */
    public function getUnassignedTickets(): Collection
    {
        return SupportTicket::unassigned()
            ->open()
            ->with(['category', 'portalUser', 'team'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get overdue SLA tickets.
     */
    public function getOverdueSlaTickets(): Collection
    {
        return SupportTicket::overdueSla()
            ->open()
            ->with(['assignee:id,name,email', 'category', 'team'])
            ->orderBy('sla_response_due_at')
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - TICKETS
    // =========================================================================

    /**
     * Create a new support ticket.
     */
    public function createTicket(array $data): SupportTicket
    {
        return DB::transaction(function () use ($data) {
            $ticketNumber = SupportTicket::generateTicketNumber();

            $ticket = SupportTicket::create([
                'ticket_number' => $ticketNumber,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => $data['status'] ?? 'open',
                'priority' => $data['priority'] ?? 2, // Medium
                'category_id' => $data['category_id'] ?? null,
                'submitter_id' => $data['submitter_id'] ?? Auth::id(),
                'portal_user_id' => $data['portal_user_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'account_id' => $data['account_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'channel' => $data['channel'] ?? 'portal',
                'tags' => $data['tags'] ?? [],
                'custom_fields' => $data['custom_fields'] ?? [],
            ]);

            // Calculate SLA deadlines if category has SLA settings
            if ($ticket->category) {
                $this->calculateSlaDeadlines($ticket);
            }

            // Log ticket creation activity
            $this->logActivity($ticket, 'created', 'Ticket created');

            // Auto-assign based on category default assignee
            if (!$ticket->assigned_to && $ticket->category && $ticket->category->default_assignee_id) {
                $this->assignTicket($ticket->id, $ticket->category->default_assignee_id);
            }

            return $ticket->fresh(['submitter', 'assignee', 'category']);
        });
    }

    /**
     * Update a ticket.
     */
    public function updateTicket(int $id, array $data): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($id);

        return DB::transaction(function () use ($ticket, $data) {
            $changes = [];

            // Track status changes
            if (isset($data['status']) && $data['status'] !== $ticket->status) {
                $changes['status'] = ['from' => $ticket->status, 'to' => $data['status']];
            }

            // Track priority changes
            if (isset($data['priority']) && $data['priority'] !== $ticket->priority) {
                $changes['priority'] = ['from' => $ticket->priority, 'to' => $data['priority']];
            }

            // Update ticket
            $ticket->update([
                'subject' => $data['subject'] ?? $ticket->subject,
                'description' => $data['description'] ?? $ticket->description,
                'status' => $data['status'] ?? $ticket->status,
                'priority' => $data['priority'] ?? $ticket->priority,
                'category_id' => $data['category_id'] ?? $ticket->category_id,
                'assigned_to' => $data['assigned_to'] ?? $ticket->assigned_to,
                'team_id' => $data['team_id'] ?? $ticket->team_id,
                'tags' => $data['tags'] ?? $ticket->tags,
                'custom_fields' => array_merge($ticket->custom_fields ?? [], $data['custom_fields'] ?? []),
            ]);

            // Log changes
            foreach ($changes as $field => $change) {
                $this->logActivity(
                    $ticket,
                    'updated',
                    "{$field} changed from {$change['from']} to {$change['to']}"
                );
            }

            return $ticket->fresh(['submitter', 'assignee', 'category']);
        });
    }

    /**
     * Assign ticket to a user.
     */
    public function assignTicket(int $ticketId, int $userId): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        return DB::transaction(function () use ($ticket, $userId) {
            $oldAssignee = $ticket->assigned_to;

            $ticket->update(['assigned_to' => $userId]);

            if ($oldAssignee !== $userId) {
                $this->logActivity(
                    $ticket,
                    'assigned',
                    "Ticket assigned to user ID: {$userId}"
                );
            }

            return $ticket->fresh(['assignee']);
        });
    }

    /**
     * Assign ticket to a team.
     */
    public function assignToTeam(int $ticketId, int $teamId): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        return DB::transaction(function () use ($ticket, $teamId) {
            $ticket->update(['team_id' => $teamId]);

            $this->logActivity(
                $ticket,
                'assigned',
                "Ticket assigned to team ID: {$teamId}"
            );

            return $ticket->fresh(['team']);
        });
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(int $ticketId, string $status): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        return DB::transaction(function () use ($ticket, $status) {
            $oldStatus = $ticket->status;

            $updates = ['status' => $status];

            // Set resolved_at timestamp
            if ($status === 'resolved' && !$ticket->resolved_at) {
                $updates['resolved_at'] = now();
            }

            // Set closed_at timestamp
            if ($status === 'closed' && !$ticket->closed_at) {
                $updates['closed_at'] = now();
            }

            $ticket->update($updates);

            $this->logActivity(
                $ticket,
                'status_changed',
                "Status changed from {$oldStatus} to {$status}"
            );

            return $ticket->fresh();
        });
    }

    /**
     * Delete a ticket.
     */
    public function deleteTicket(int $id): bool
    {
        $ticket = SupportTicket::findOrFail($id);
        return $ticket->delete();
    }

    // =========================================================================
    // COMMAND USE CASES - REPLIES
    // =========================================================================

    /**
     * Add a reply to a ticket.
     */
    public function addReply(int $ticketId, array $data): TicketReply
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        return DB::transaction(function () use ($ticket, $data) {
            $reply = TicketReply::create([
                'ticket_id' => $ticketId,
                'content' => $data['content'],
                'user_id' => $data['user_id'] ?? Auth::id(),
                'portal_user_id' => $data['portal_user_id'] ?? null,
                'is_internal' => $data['is_internal'] ?? false,
                'is_system' => $data['is_system'] ?? false,
                'attachments' => $data['attachments'] ?? [],
            ]);

            // Set first response time if this is the first agent reply
            if ($reply->user_id && !$ticket->first_response_at) {
                $ticket->update(['first_response_at' => now()]);
            }

            // Log activity
            $visibility = $reply->is_internal ? 'internal' : 'public';
            $this->logActivity($ticket, 'replied', "Added {$visibility} reply");

            return $reply->fresh(['user', 'portalUser']);
        });
    }

    /**
     * Update a reply.
     */
    public function updateReply(int $replyId, array $data): TicketReply
    {
        $reply = TicketReply::findOrFail($replyId);

        $reply->update([
            'content' => $data['content'] ?? $reply->content,
            'is_internal' => $data['is_internal'] ?? $reply->is_internal,
            'attachments' => $data['attachments'] ?? $reply->attachments,
        ]);

        return $reply->fresh();
    }

    /**
     * Delete a reply.
     */
    public function deleteReply(int $replyId): bool
    {
        $reply = TicketReply::findOrFail($replyId);
        return $reply->delete();
    }

    // =========================================================================
    // QUERY USE CASES - CATEGORIES
    // =========================================================================

    /**
     * List ticket categories.
     */
    public function listCategories(bool $activeOnly = false): Collection
    {
        $query = TicketCategory::query()
            ->with('defaultAssignee:id,name');

        if ($activeOnly) {
            $query->active();
        }

        return $query->ordered()->get();
    }

    /**
     * Get a category by ID.
     */
    public function getCategory(int $id): ?TicketCategory
    {
        return TicketCategory::with(['defaultAssignee', 'tickets'])->find($id);
    }

    // =========================================================================
    // COMMAND USE CASES - CATEGORIES
    // =========================================================================

    /**
     * Create a category.
     */
    public function createCategory(array $data): TicketCategory
    {
        return TicketCategory::create([
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
        ]);
    }

    /**
     * Update a category.
     */
    public function updateCategory(int $id, array $data): TicketCategory
    {
        $category = TicketCategory::findOrFail($id);

        $category->update([
            'name' => $data['name'] ?? $category->name,
            'description' => $data['description'] ?? $category->description,
            'color' => $data['color'] ?? $category->color,
            'default_assignee_id' => $data['default_assignee_id'] ?? $category->default_assignee_id,
            'default_priority' => $data['default_priority'] ?? $category->default_priority,
            'sla_response_hours' => $data['sla_response_hours'] ?? $category->sla_response_hours,
            'sla_resolution_hours' => $data['sla_resolution_hours'] ?? $category->sla_resolution_hours,
            'is_active' => $data['is_active'] ?? $category->is_active,
            'display_order' => $data['display_order'] ?? $category->display_order,
        ]);

        return $category->fresh();
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(int $id): bool
    {
        $category = TicketCategory::findOrFail($id);
        return $category->delete();
    }

    // =========================================================================
    // ESCALATION USE CASES
    // =========================================================================

    /**
     * Escalate a ticket.
     */
    public function escalateTicket(int $ticketId, array $data): TicketEscalation
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        return DB::transaction(function () use ($ticket, $data) {
            $escalation = TicketEscalation::create([
                'ticket_id' => $ticketId,
                'escalated_to' => $data['escalated_to'],
                'escalated_by' => Auth::id(),
                'reason' => $data['reason'] ?? null,
                'priority_before' => $ticket->priority,
                'priority_after' => $data['new_priority'] ?? ($ticket->priority + 1),
            ]);

            // Update ticket priority if specified
            if (isset($data['new_priority'])) {
                $ticket->update(['priority' => $data['new_priority']]);
            }

            // Reassign if specified
            if (isset($data['reassign_to'])) {
                $this->assignTicket($ticketId, $data['reassign_to']);
            }

            $this->logActivity($ticket, 'escalated', 'Ticket escalated: ' . ($data['reason'] ?? 'No reason provided'));

            return $escalation->fresh();
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
        $query = SupportTicket::query();

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Apply user filter
        if (!empty($filters['assigned_to'])) {
            $query->assignedTo($filters['assigned_to']);
        }

        // Apply team filter
        if (!empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        $total = $query->count();
        $open = (clone $query)->open()->count();
        $resolved = (clone $query)->resolved()->count();
        $closed = (clone $query)->closed()->count();
        $overdueSla = (clone $query)->overdueSla()->count();

        // Average response time (hours)
        $avgResponseTime = (clone $query)
            ->whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) as avg_hours')
            ->value('avg_hours');

        // Average resolution time (hours)
        $avgResolutionTime = (clone $query)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        // By priority
        $byPriority = (clone $query)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // By status
        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // By channel
        $byChannel = (clone $query)
            ->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        return [
            'total' => $total,
            'open' => $open,
            'resolved' => $resolved,
            'closed' => $closed,
            'overdue_sla' => $overdueSla,
            'avg_response_time_hours' => $avgResponseTime ? round($avgResponseTime, 2) : 0,
            'avg_resolution_time_hours' => $avgResolutionTime ? round($avgResolutionTime, 2) : 0,
            'resolution_rate' => $total > 0 ? round((($resolved + $closed) / $total) * 100, 2) : 0,
            'by_priority' => $byPriority,
            'by_status' => $byStatus,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * Get agent performance stats.
     */
    public function getAgentPerformance(int $userId, int $days = 30): array
    {
        $dateFrom = now()->subDays($days);

        $ticketsAssigned = SupportTicket::assignedTo($userId)
            ->where('created_at', '>=', $dateFrom)
            ->count();

        $ticketsResolved = SupportTicket::assignedTo($userId)
            ->where('resolved_at', '>=', $dateFrom)
            ->count();

        $ticketsOpen = SupportTicket::assignedTo($userId)
            ->open()
            ->count();

        $avgResponseTime = SupportTicket::assignedTo($userId)
            ->whereNotNull('first_response_at')
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) as avg_hours')
            ->value('avg_hours');

        $avgResolutionTime = SupportTicket::assignedTo($userId)
            ->whereNotNull('resolved_at')
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        $avgSatisfaction = SupportTicket::assignedTo($userId)
            ->whereNotNull('satisfaction_rating')
            ->where('created_at', '>=', $dateFrom)
            ->avg('satisfaction_rating');

        return [
            'tickets_assigned' => $ticketsAssigned,
            'tickets_resolved' => $ticketsResolved,
            'tickets_open' => $ticketsOpen,
            'resolution_rate' => $ticketsAssigned > 0 ? round(($ticketsResolved / $ticketsAssigned) * 100, 2) : 0,
            'avg_response_time_hours' => $avgResponseTime ? round($avgResponseTime, 2) : 0,
            'avg_resolution_time_hours' => $avgResolutionTime ? round($avgResolutionTime, 2) : 0,
            'avg_satisfaction_rating' => $avgSatisfaction ? round($avgSatisfaction, 2) : null,
        ];
    }

    /**
     * Get daily ticket counts.
     */
    public function getDailyTicketCounts(int $days = 30): Collection
    {
        return SupportTicket::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate SLA deadlines for a ticket.
     */
    private function calculateSlaDeadlines(SupportTicket $ticket): void
    {
        if (!$ticket->category) {
            return;
        }

        $updates = [];

        if ($ticket->category->sla_response_hours) {
            $updates['sla_response_due_at'] = now()->addHours($ticket->category->sla_response_hours);
        }

        if ($ticket->category->sla_resolution_hours) {
            $updates['sla_resolution_due_at'] = now()->addHours($ticket->category->sla_resolution_hours);
        }

        if (!empty($updates)) {
            $ticket->update($updates);
        }
    }

    /**
     * Log ticket activity.
     */
    private function logActivity(SupportTicket $ticket, string $action, string $description): void
    {
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
        ]);
    }
}
