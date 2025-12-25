<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Support;

use App\Domain\Support\Entities\SupportTicket as SupportTicketEntity;
use App\Domain\Support\Repositories\SupportTicketRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class DbSupportTicketRepository implements SupportTicketRepositoryInterface
{
    private const TABLE = 'support_tickets';
    private const TABLE_REPLIES = 'ticket_replies';
    private const TABLE_CATEGORIES = 'ticket_categories';
    private const TABLE_ACTIVITIES = 'ticket_activities';
    private const TABLE_ESCALATIONS = 'ticket_escalations';
    private const TABLE_USERS = 'users';
    private const TABLE_PORTAL_USERS = 'portal_users';
    private const TABLE_TEAMS = 'teams';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?SupportTicketEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(SupportTicketEntity $entity): SupportTicketEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId() !== null) {
            DB::table(self::TABLE)->where('id', $entity->getId())->update($data);
            $row = DB::table(self::TABLE)->where('id', $entity->getId())->first();
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table(self::TABLE)->insertGetId($data);
            $row = DB::table(self::TABLE)->where('id', $id)->first();
        }

        return $this->toDomainEntity($row);
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $ticket = $this->ticketToArray($row);
        $ticket['submitter'] = $this->getUserById($row->submitter_id);
        $ticket['portal_user'] = $this->getPortalUserById($row->portal_user_id);
        $ticket['assignee'] = $this->getUserById($row->assigned_to);
        $ticket['category'] = $this->getCategoryById($row->category_id);
        $ticket['team'] = $this->getTeamById($row->team_id);
        $ticket['replies'] = $this->getTicketReplies($id);
        $ticket['activities'] = $this->getTicketActivities($id);
        $ticket['escalations'] = $this->getTicketEscalations($id);

        return $ticket;
    }

    public function findByTicketNumber(string $ticketNumber): ?array
    {
        $row = DB::table(self::TABLE)->where('ticket_number', $ticketNumber)->first();

        if (!$row) {
            return null;
        }

        $ticket = $this->ticketToArray($row);
        $ticket['submitter'] = $this->getUserById($row->submitter_id);
        $ticket['assignee'] = $this->getUserById($row->assigned_to);
        $ticket['category'] = $this->getCategoryById($row->category_id);
        $ticket['replies'] = $this->getTicketReplies($row->id);

        return $ticket;
    }

    public function listTickets(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

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
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Filter by team
        if (!empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        // Filter unassigned only
        if (!empty($filters['unassigned'])) {
            $query->whereNull('assigned_to');
        }

        // Filter open tickets
        if (!empty($filters['open'])) {
            $query->whereIn('status', ['open', 'pending', 'in_progress']);
        }

        // Filter resolved tickets
        if (!empty($filters['resolved'])) {
            $query->where('status', 'resolved');
        }

        // Filter closed tickets
        if (!empty($filters['closed'])) {
            $query->where('status', 'closed');
        }

        // Filter overdue SLA
        if (!empty($filters['overdue_sla'])) {
            $query->where(function ($q) {
                $q->where('sla_response_due_at', '<', now())
                    ->orWhere('sla_resolution_due_at', '<', now());
            })->whereNotIn('status', ['resolved', 'closed']);
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

        // Get total count
        $total = (clone $query)->count();

        // Get paginated items
        $items = $query->forPage($page, $perPage)->get();

        $mappedItems = $items->map(function ($row) {
            $ticket = $this->ticketToArray($row);
            $ticket['submitter'] = $this->getUserById($row->submitter_id);
            $ticket['assignee'] = $this->getUserById($row->assigned_to);
            $ticket['category'] = $this->getCategoryById($row->category_id);
            $ticket['team'] = $this->getTeamById($row->team_id);
            return $ticket;
        })->toArray();

        return PaginatedResult::create(
            items: $mappedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getAssignedTickets(int $userId, bool $openOnly = true): array
    {
        $query = DB::table(self::TABLE)->where('assigned_to', $userId);

        if ($openOnly) {
            $query->whereIn('status', ['open', 'pending', 'in_progress']);
        }

        return $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($row) {
                $ticket = $this->ticketToArray($row);
                $ticket['category'] = $this->getCategoryById($row->category_id);
                $ticket['portal_user'] = $this->getPortalUserById($row->portal_user_id);
                return $ticket;
            })->toArray();
    }

    public function getUnassignedTickets(): array
    {
        return DB::table(self::TABLE)
            ->whereNull('assigned_to')
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($row) {
                $ticket = $this->ticketToArray($row);
                $ticket['category'] = $this->getCategoryById($row->category_id);
                $ticket['portal_user'] = $this->getPortalUserById($row->portal_user_id);
                $ticket['team'] = $this->getTeamById($row->team_id);
                return $ticket;
            })->toArray();
    }

    public function getOverdueSlaTickets(): array
    {
        return DB::table(self::TABLE)
            ->where(function ($q) {
                $q->where('sla_response_due_at', '<', now())
                    ->orWhere('sla_resolution_due_at', '<', now());
            })
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->orderBy('sla_response_due_at')
            ->get()
            ->map(function ($row) {
                $ticket = $this->ticketToArray($row);
                $ticket['assignee'] = $this->getUserById($row->assigned_to);
                $ticket['category'] = $this->getCategoryById($row->category_id);
                $ticket['team'] = $this->getTeamById($row->team_id);
                return $ticket;
            })->toArray();
    }

    public function create(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE)->insertGetId($data);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        $ticket = $this->ticketToArray($row);
        $ticket['submitter'] = $this->getUserById($row->submitter_id);
        $ticket['assignee'] = $this->getUserById($row->assigned_to);
        $ticket['category'] = $this->getCategoryById($row->category_id);

        return $ticket;
    }

    public function update(int $id, array $data): array
    {
        $data['updated_at'] = now();

        DB::table(self::TABLE)->where('id', $id)->update($data);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        $ticket = $this->ticketToArray($row);
        $ticket['submitter'] = $this->getUserById($row->submitter_id);
        $ticket['assignee'] = $this->getUserById($row->assigned_to);
        $ticket['category'] = $this->getCategoryById($row->category_id);

        return $ticket;
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function generateTicketNumber(): string
    {
        $prefix = 'TKT';
        $number = strtoupper(Str::random(8));
        return "{$prefix}-{$number}";
    }

    public function getTicketStats(array $filters = []): array
    {
        $query = DB::table(self::TABLE);

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Apply user filter
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Apply team filter
        if (!empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        $total = (clone $query)->count();
        $open = (clone $query)->whereIn('status', ['open', 'pending', 'in_progress'])->count();
        $resolved = (clone $query)->where('status', 'resolved')->count();
        $closed = (clone $query)->where('status', 'closed')->count();
        $overdueSla = (clone $query)->where(function ($q) {
            $q->where('sla_response_due_at', '<', now())
                ->orWhere('sla_resolution_due_at', '<', now());
        })->whereNotIn('status', ['resolved', 'closed'])->count();

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

    public function getAgentPerformance(int $userId, int $days = 30): array
    {
        $dateFrom = now()->subDays($days);

        $ticketsAssigned = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->where('created_at', '>=', $dateFrom)
            ->count();

        $ticketsResolved = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->where('resolved_at', '>=', $dateFrom)
            ->count();

        $ticketsOpen = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->count();

        $avgResponseTime = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->whereNotNull('first_response_at')
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) as avg_hours')
            ->value('avg_hours');

        $avgResolutionTime = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->whereNotNull('resolved_at')
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        $avgSatisfaction = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
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

    public function getDailyTicketCounts(int $days = 30): array
    {
        return DB::table(self::TABLE)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => ['date' => $row->date, 'count' => $row->count])
            ->toArray();
    }

    public function addReply(int $ticketId, array $data): array
    {
        $insertData = array_merge($data, [
            'ticket_id' => $ticketId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table(self::TABLE_REPLIES)->insertGetId($insertData);

        // Set first response time if this is the first agent reply
        if (!empty($data['user_id'])) {
            $ticket = DB::table(self::TABLE)->where('id', $ticketId)->first();
            if ($ticket && !$ticket->first_response_at) {
                DB::table(self::TABLE)->where('id', $ticketId)->update(['first_response_at' => now()]);
            }
        }

        $row = DB::table(self::TABLE_REPLIES)->where('id', $id)->first();
        $reply = $this->replyToArray($row);
        $reply['user'] = $this->getUserById($row->user_id);
        $reply['portal_user'] = $this->getPortalUserById($row->portal_user_id);

        return $reply;
    }

    public function updateReply(int $replyId, array $data): array
    {
        $data['updated_at'] = now();
        DB::table(self::TABLE_REPLIES)->where('id', $replyId)->update($data);
        $row = DB::table(self::TABLE_REPLIES)->where('id', $replyId)->first();
        return $this->replyToArray($row);
    }

    public function deleteReply(int $replyId): bool
    {
        return DB::table(self::TABLE_REPLIES)->where('id', $replyId)->delete() > 0;
    }

    public function createEscalation(int $ticketId, array $data): array
    {
        $insertData = array_merge($data, [
            'ticket_id' => $ticketId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table(self::TABLE_ESCALATIONS)->insertGetId($insertData);
        $row = DB::table(self::TABLE_ESCALATIONS)->where('id', $id)->first();

        return $this->escalationToArray($row);
    }

    public function logActivity(int $ticketId, int $userId, string $action, string $description): void
    {
        DB::table(self::TABLE_ACTIVITIES)->insert([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function calculateSlaDeadlines(int $ticketId): void
    {
        $ticket = DB::table(self::TABLE)->where('id', $ticketId)->first();

        if (!$ticket || !$ticket->category_id) {
            return;
        }

        $category = DB::table(self::TABLE_CATEGORIES)->where('id', $ticket->category_id)->first();

        if (!$category) {
            return;
        }

        $updates = [];

        if (!empty($category->sla_response_hours)) {
            $updates['sla_response_due_at'] = now()->addHours($category->sla_response_hours);
        }

        if (!empty($category->sla_resolution_hours)) {
            $updates['sla_resolution_due_at'] = now()->addHours($category->sla_resolution_hours);
        }

        if (!empty($updates)) {
            $updates['updated_at'] = now();
            DB::table(self::TABLE)->where('id', $ticketId)->update($updates);
        }
    }

    public function listCategories(bool $activeOnly = false): array
    {
        $query = DB::table(self::TABLE_CATEGORIES);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($row) {
                $category = $this->categoryToArray($row);
                $category['default_assignee'] = $this->getUserById($row->default_assignee_id);
                return $category;
            })->toArray();
    }

    public function getCategoryById(int $id): ?array
    {
        $row = DB::table(self::TABLE_CATEGORIES)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $category = $this->categoryToArray($row);
        $category['default_assignee'] = $this->getUserById($row->default_assignee_id);

        return $category;
    }

    public function createCategory(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_CATEGORIES)->insertGetId($data);
        $row = DB::table(self::TABLE_CATEGORIES)->where('id', $id)->first();

        return $this->categoryToArray($row);
    }

    public function updateCategory(int $id, array $data): array
    {
        $data['updated_at'] = now();
        DB::table(self::TABLE_CATEGORIES)->where('id', $id)->update($data);
        $row = DB::table(self::TABLE_CATEGORIES)->where('id', $id)->first();

        return $this->categoryToArray($row);
    }

    public function deleteCategory(int $id): bool
    {
        return DB::table(self::TABLE_CATEGORIES)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): SupportTicketEntity
    {
        return SupportTicketEntity::reconstitute(
            id: $row->id,
            createdAt: isset($row->created_at) ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: isset($row->updated_at) ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(SupportTicketEntity $entity): array
    {
        return [
            'updated_at' => now(),
        ];
    }

    private function ticketToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'ticket_number' => $row->ticket_number ?? null,
            'subject' => $row->subject ?? null,
            'description' => $row->description ?? null,
            'status' => $row->status ?? null,
            'priority' => $row->priority ?? null,
            'channel' => $row->channel ?? null,
            'submitter_id' => $row->submitter_id ?? null,
            'portal_user_id' => $row->portal_user_id ?? null,
            'assigned_to' => $row->assigned_to ?? null,
            'category_id' => $row->category_id ?? null,
            'team_id' => $row->team_id ?? null,
            'first_response_at' => $row->first_response_at ?? null,
            'resolved_at' => $row->resolved_at ?? null,
            'closed_at' => $row->closed_at ?? null,
            'sla_response_due_at' => $row->sla_response_due_at ?? null,
            'sla_resolution_due_at' => $row->sla_resolution_due_at ?? null,
            'satisfaction_rating' => $row->satisfaction_rating ?? null,
            'satisfaction_comment' => $row->satisfaction_comment ?? null,
            'metadata' => isset($row->metadata) ? (is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata) : [],
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function replyToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'ticket_id' => $row->ticket_id ?? null,
            'user_id' => $row->user_id ?? null,
            'portal_user_id' => $row->portal_user_id ?? null,
            'content' => $row->content ?? null,
            'is_internal' => $row->is_internal ?? false,
            'attachments' => isset($row->attachments) ? (is_string($row->attachments) ? json_decode($row->attachments, true) : $row->attachments) : [],
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function categoryToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'description' => $row->description ?? null,
            'default_assignee_id' => $row->default_assignee_id ?? null,
            'sla_response_hours' => $row->sla_response_hours ?? null,
            'sla_resolution_hours' => $row->sla_resolution_hours ?? null,
            'is_active' => $row->is_active ?? true,
            'sort_order' => $row->sort_order ?? 0,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function escalationToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'ticket_id' => $row->ticket_id ?? null,
            'escalated_to' => $row->escalated_to ?? null,
            'escalated_by' => $row->escalated_by ?? null,
            'reason' => $row->reason ?? null,
            'level' => $row->level ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getUserById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::table(self::TABLE_USERS)->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'email' => $row->email ?? null,
        ];
    }

    private function getPortalUserById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::table(self::TABLE_PORTAL_USERS)->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'email' => $row->email ?? null,
        ];
    }

    private function getTeamById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::table(self::TABLE_TEAMS)->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
        ];
    }

    private function getTicketReplies(int $ticketId): array
    {
        return DB::table(self::TABLE_REPLIES)
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($row) {
                $reply = $this->replyToArray($row);
                $reply['user'] = $this->getUserById($row->user_id);
                $reply['portal_user'] = $this->getPortalUserById($row->portal_user_id);
                return $reply;
            })->toArray();
    }

    private function getTicketActivities(int $ticketId): array
    {
        return DB::table(self::TABLE_ACTIVITIES)
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'ticket_id' => $row->ticket_id,
                    'user_id' => $row->user_id,
                    'action' => $row->action,
                    'description' => $row->description,
                    'created_at' => $row->created_at,
                    'user' => $this->getUserById($row->user_id),
                ];
            })->toArray();
    }

    private function getTicketEscalations(int $ticketId): array
    {
        return DB::table(self::TABLE_ESCALATIONS)
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($row) => $this->escalationToArray($row))
            ->toArray();
    }
}
