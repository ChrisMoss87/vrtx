<?php

namespace App\Services\Support;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function createTicket(array $data, ?User $submitter = null, ?PortalUser $portalUser = null): SupportTicket
    {
        return DB::transaction(function () use ($data, $submitter, $portalUser) {
            $category = isset($data['category_id'])
                ? TicketCategory::find($data['category_id'])
                : null;

            $ticket = DB::table('support_tickets')->insertGetId([
                'ticket_number' => SupportTicket::generateTicketNumber(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => 'open',
                'priority' => $data['priority'] ?? $category?->default_priority ?? 2,
                'category_id' => $data['category_id'] ?? null,
                'submitter_id' => $submitter?->id,
                'portal_user_id' => $portalUser?->id,
                'contact_id' => $data['contact_id'] ?? null,
                'account_id' => $data['account_id'] ?? $portalUser?->account_id,
                'assigned_to' => $data['assigned_to'] ?? $category?->default_assignee_id,
                'team_id' => $data['team_id'] ?? null,
                'channel' => $data['channel'] ?? 'portal',
                'tags' => $data['tags'] ?? [],
                'custom_fields' => $data['custom_fields'] ?? [],
            ]);

            // Calculate SLA due dates
            $this->calculateSlaDueDates($ticket, $category);

            // Log creation activity
            $this->logActivity($ticket, 'created', null, $submitter, $portalUser);

            // If assigned, log assignment
            if ($ticket->assigned_to) {
                $this->logActivity($ticket, 'assigned', [
                    'assigned_to' => $ticket->assigned_to,
                ], $submitter, $portalUser);
            }

            return $ticket;
        });
    }

    public function updateTicket(SupportTicket $ticket, array $data, User $user): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $data, $user) {
            $changes = [];

            // Track status change
            if (isset($data['status']) && $data['status'] !== $ticket->status) {
                $changes['status'] = ['old' => $ticket->status, 'new' => $data['status']];

                if ($data['status'] === 'resolved') {
                    $data['resolved_at'] = now();
                } elseif ($data['status'] === 'closed') {
                    $data['closed_at'] = now();
                }
            }

            // Track priority change
            if (isset($data['priority']) && $data['priority'] !== $ticket->priority) {
                $changes['priority'] = ['old' => $ticket->priority, 'new' => $data['priority']];
            }

            // Track assignment change
            if (isset($data['assigned_to']) && $data['assigned_to'] !== $ticket->assigned_to) {
                $changes['assigned_to'] = ['old' => $ticket->assigned_to, 'new' => $data['assigned_to']];
            }

            // Track category change
            if (isset($data['category_id']) && $data['category_id'] !== $ticket->category_id) {
                $changes['category_id'] = ['old' => $ticket->category_id, 'new' => $data['category_id']];
            }

            $ticket->update($data);

            // Log activities for significant changes
            if (isset($changes['status'])) {
                $action = match ($changes['status']['new']) {
                    'resolved' => 'resolved',
                    'closed' => 'closed',
                    'open' => $changes['status']['old'] === 'closed' || $changes['status']['old'] === 'resolved' ? 'reopened' : 'status_changed',
                    default => 'status_changed',
                };
                $this->logActivity($ticket, $action, $changes, $user);
            }

            if (isset($changes['priority'])) {
                $this->logActivity($ticket, 'priority_changed', $changes, $user);
            }

            if (isset($changes['assigned_to'])) {
                $action = $changes['assigned_to']['old'] ? 'reassigned' : 'assigned';
                $this->logActivity($ticket, $action, $changes, $user);
            }

            if (isset($changes['category_id'])) {
                $this->logActivity($ticket, 'category_changed', $changes, $user);
            }

            return $ticket->fresh();
        });
    }

    public function addReply(
        SupportTicket $ticket,
        string $content,
        ?User $user = null,
        ?PortalUser $portalUser = null,
        bool $isInternal = false,
        array $attachments = []
    ): TicketReply {
        return DB::transaction(function () use ($ticket, $content, $user, $portalUser, $isInternal, $attachments) {
            $reply = DB::table('ticket_replies')->insertGetId([
                'ticket_id' => $ticket->id,
                'content' => $content,
                'user_id' => $user?->id,
                'portal_user_id' => $portalUser?->id,
                'is_internal' => $isInternal,
                'attachments' => $attachments,
            ]);

            // Track first response time
            if ($user && !$ticket->first_response_at) {
                $ticket->update(['first_response_at' => now()]);
            }

            // Update ticket status if customer replied
            if ($portalUser && $ticket->status === 'resolved') {
                $ticket->update(['status' => 'open']);
                $this->logActivity($ticket, 'reopened', null, null, $portalUser, 'Customer replied');
            }

            // Log activity
            $action = $isInternal ? 'internal_note' : 'replied';
            $this->logActivity($ticket, $action, null, $user, $portalUser);

            return $reply;
        });
    }

    public function assignTicket(SupportTicket $ticket, int $assigneeId, User $assignedBy): SupportTicket
    {
        $oldAssignee = $ticket->assigned_to;

        $ticket->update(['assigned_to' => $assigneeId]);

        $action = $oldAssignee ? 'reassigned' : 'assigned';
        $this->logActivity($ticket, $action, [
            'old_assignee' => $oldAssignee,
            'new_assignee' => $assigneeId,
        ], $assignedBy);

        return $ticket;
    }

    public function escalateTicket(
        SupportTicket $ticket,
        string $type,
        string $level,
        int $escalateTo,
        string $reason,
        User $escalatedBy
    ): TicketEscalation {
        $escalation = DB::table('ticket_escalations')->insertGetId([
            'ticket_id' => $ticket->id,
            'type' => $type,
            'level' => $level,
            'escalated_to' => $escalateTo,
            'reason' => $reason,
            'escalated_by' => $escalatedBy->id,
        ]);

        $this->logActivity($ticket, 'escalated', [
            'type' => $type,
            'level' => $level,
            'escalated_to' => $escalateTo,
        ], $escalatedBy);

        return $escalation;
    }

    public function resolveTicket(SupportTicket $ticket, User $user, ?string $resolutionNote = null): SupportTicket
    {
        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->logActivity($ticket, 'resolved', null, $user, null, $resolutionNote);

        return $ticket;
    }

    public function closeTicket(SupportTicket $ticket, User $user): SupportTicket
    {
        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->logActivity($ticket, 'closed', null, $user);

        return $ticket;
    }

    public function mergeTickets(SupportTicket $primaryTicket, SupportTicket $secondaryTicket, User $user): SupportTicket
    {
        return DB::transaction(function () use ($primaryTicket, $secondaryTicket, $user) {
            // Move all replies to primary ticket
            DB::table('ticket_replies')->where('ticket_id', $secondaryTicket->id)
                ->update(['ticket_id' => $primaryTicket->id]);

            // Add merge note
            $this->addReply(
                $primaryTicket,
                "Merged ticket #{$secondaryTicket->ticket_number}: {$secondaryTicket->subject}",
                $user,
                null,
                true
            );

            // Close secondary ticket
            $secondaryTicket->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $this->logActivity($primaryTicket, 'merged', [
                'merged_ticket' => $secondaryTicket->ticket_number,
            ], $user);

            $this->logActivity($secondaryTicket, 'merged', [
                'merged_into' => $primaryTicket->ticket_number,
            ], $user);

            return $primaryTicket;
        });
    }

    public function recordSatisfaction(SupportTicket $ticket, int $rating, ?string $feedback = null): SupportTicket
    {
        $ticket->update([
            'satisfaction_rating' => $rating,
            'satisfaction_feedback' => $feedback,
        ]);

        return $ticket;
    }

    protected function calculateSlaDueDates(SupportTicket $ticket, ?TicketCategory $category): void
    {
        $responseHours = $category?->sla_response_hours;
        $resolutionHours = $category?->sla_resolution_hours;

        // Default SLAs based on priority if category doesn't specify
        if (!$responseHours) {
            $responseHours = match ($ticket->priority) {
                4 => 1,   // Urgent: 1 hour
                3 => 4,   // High: 4 hours
                2 => 8,   // Medium: 8 hours
                default => 24, // Low: 24 hours
            };
        }

        if (!$resolutionHours) {
            $resolutionHours = match ($ticket->priority) {
                4 => 4,    // Urgent: 4 hours
                3 => 24,   // High: 24 hours
                2 => 48,   // Medium: 48 hours
                default => 72, // Low: 72 hours
            };
        }

        $ticket->update([
            'sla_response_due_at' => now()->addHours($responseHours),
            'sla_resolution_due_at' => now()->addHours($resolutionHours),
        ]);
    }

    protected function logActivity(
        SupportTicket $ticket,
        string $action,
        ?array $changes = null,
        ?User $user = null,
        ?PortalUser $portalUser = null,
        ?string $note = null
    ): TicketActivity {
        return DB::table('ticket_activities')->insertGetId([
            'ticket_id' => $ticket->id,
            'action' => $action,
            'changes' => $changes,
            'user_id' => $user?->id,
            'portal_user_id' => $portalUser?->id,
            'note' => $note,
        ]);
    }

    public function getTicketStats(?int $userId = null): array
    {
        $query = DB::table('support_tickets');

        if ($userId) {
            $query->where('assigned_to', $userId);
        }

        return [
            'open' => (clone $query)->open()->count(),
            'unassigned' => (clone $query)->unassigned()->open()->count(),
            'overdue_sla' => (clone $query)->open()->overdueSla()->count(),
            'resolved_today' => (clone $query)->resolved()->whereDate('resolved_at', today())->count(),
            'avg_response_time' => $this->getAverageResponseTime($userId),
            'avg_resolution_time' => $this->getAverageResolutionTime($userId),
        ];
    }

    protected function getAverageResponseTime(?int $userId = null): ?float
    {
        $query = SupportTicket::whereNotNull('first_response_at');

        if ($userId) {
            $query->where('assigned_to', $userId);
        }

        $avg = $query->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        return $avg ? round($avg, 1) : null;
    }

    protected function getAverageResolutionTime(?int $userId = null): ?float
    {
        $query = SupportTicket::whereNotNull('resolved_at');

        if ($userId) {
            $query->where('assigned_to', $userId);
        }

        $avg = $query->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        return $avg ? round($avg, 1) : null;
    }
}
