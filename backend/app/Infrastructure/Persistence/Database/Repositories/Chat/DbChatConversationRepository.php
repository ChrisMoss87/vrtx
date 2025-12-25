<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Chat;

use App\Domain\Chat\Entities\ChatConversation as ChatConversationEntity;
use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use App\Domain\Chat\ValueObjects\ConversationPriority;
use App\Domain\Chat\ValueObjects\ConversationStatus;
use App\Domain\Chat\ValueObjects\Rating;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbChatConversationRepository implements ChatConversationRepositoryInterface
{
    private const TABLE = 'chat_conversations';
    private const TABLE_VISITORS = 'chat_visitors';
    private const TABLE_WIDGETS = 'chat_widgets';
    private const TABLE_USERS = 'users';
    private const TABLE_MESSAGES = 'chat_messages';
    private const TABLE_AGENT_STATUS = 'chat_agent_statuses';

    private const STATUS_OPEN = 'open';
    private const STATUS_PENDING = 'pending';
    private const STATUS_CLOSED = 'closed';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?ChatConversationEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(ChatConversationEntity $entity): ChatConversationEntity
    {
        $data = $this->toRowData($entity);

        if ($entity->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByIdWithRelations(int $id, array $relations = []): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load relations if requested
        foreach ($relations as $relation) {
            if ($relation === 'visitor' && $row->visitor_id) {
                $result['visitor'] = DB::table(self::TABLE_VISITORS)
                    ->where('id', $row->visitor_id)
                    ->first();
            } elseif ($relation === 'assignedAgent' && $row->assigned_to) {
                $result['assigned_agent'] = DB::table(self::TABLE_USERS)
                    ->where('id', $row->assigned_to)
                    ->first();
            } elseif ($relation === 'widget' && $row->widget_id) {
                $result['widget'] = DB::table(self::TABLE_WIDGETS)
                    ->where('id', $row->widget_id)
                    ->first();
            }
        }

        return $result;
    }

    public function findAll(): array
    {
        return array_map(fn($row) => (array) $row, DB::table(self::TABLE)->get()->all());
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, ['created_at' => now(), 'updated_at' => now()])
        );

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return (array) $row;
    }

    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return (array) $row;
    }

    public function listConversations(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE . ' as c');

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }

        // Filter by multiple statuses
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('c.status', $filters['statuses']);
        }

        // Open only
        if (!empty($filters['open_only'])) {
            $query->where('c.status', self::STATUS_OPEN);
        }

        // Filter by widget
        if (!empty($filters['widget_id'])) {
            $query->where('c.widget_id', $filters['widget_id']);
        }

        // Filter by assigned agent
        if (!empty($filters['assigned_to'])) {
            $query->where('c.assigned_to', $filters['assigned_to']);
        }

        // Unassigned only
        if (!empty($filters['unassigned_only'])) {
            $query->whereNull('c.assigned_to');
        }

        // Filter by department
        if (!empty($filters['department'])) {
            $query->where('c.department', $filters['department']);
        }

        // Filter by priority
        if (!empty($filters['priority'])) {
            $query->where('c.priority', $filters['priority']);
        }

        // Filter by visitor
        if (!empty($filters['visitor_id'])) {
            $query->where('c.visitor_id', $filters['visitor_id']);
        }

        // Filter by date range
        if (!empty($filters['created_from'])) {
            $query->where('c.created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $query->where('c.created_at', '<=', $filters['created_to']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('c.subject', 'ilike', "%{$search}%")
                    ->orWhereExists(function ($sq) use ($search) {
                        $sq->select(DB::raw(1))
                            ->from(self::TABLE_VISITORS . ' as v')
                            ->whereColumn('v.id', 'c.visitor_id')
                            ->where(function ($vq) use ($search) {
                                $vq->where('v.email', 'ilike', "%{$search}%")
                                    ->orWhere('v.name', 'ilike', "%{$search}%");
                            });
                    });
            });
        }

        // Count total before pagination
        $total = $query->count();

        // Sort
        $sortField = $filters['sort_by'] ?? 'last_message_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy('c.' . $sortField, $sortDir);

        // Paginate
        $offset = ($page - 1) * $perPage;
        $items = $query->limit($perPage)->offset($offset)->get();

        // Load relations for each item
        $itemsArray = [];
        foreach ($items as $item) {
            $itemArray = (array) $item;

            // Load visitor
            if ($item->visitor_id) {
                $itemArray['visitor'] = DB::table(self::TABLE_VISITORS)
                    ->where('id', $item->visitor_id)
                    ->first();
            }

            // Load assigned agent
            if ($item->assigned_to) {
                $itemArray['assigned_agent'] = DB::table(self::TABLE_USERS)
                    ->where('id', $item->assigned_to)
                    ->first();
            }

            // Load widget
            if ($item->widget_id) {
                $itemArray['widget'] = DB::table(self::TABLE_WIDGETS)
                    ->where('id', $item->widget_id)
                    ->first();
            }

            $itemsArray[] = $itemArray;
        }

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findActiveConversationForVisitor(int $visitorId): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->whereIn('status', [self::STATUS_OPEN, self::STATUS_PENDING])
            ->orderBy('created_at', 'desc')
            ->first();

        return $row ? (array) $row : null;
    }

    public function findUnassignedConversations(int $limit = 50): array
    {
        $conversations = DB::table(self::TABLE)
            ->whereNull('assigned_to')
            ->where('status', self::STATUS_OPEN)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($conversations as $conversation) {
            $conversationArray = (array) $conversation;

            // Load visitor
            if ($conversation->visitor_id) {
                $conversationArray['visitor'] = DB::table(self::TABLE_VISITORS)
                    ->where('id', $conversation->visitor_id)
                    ->first();
            }

            // Load widget
            if ($conversation->widget_id) {
                $conversationArray['widget'] = DB::table(self::TABLE_WIDGETS)
                    ->where('id', $conversation->widget_id)
                    ->first();
            }

            $result[] = $conversationArray;
        }

        return $result;
    }

    public function findMyConversations(int $userId): array
    {
        $conversations = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->whereIn('status', [self::STATUS_OPEN, self::STATUS_PENDING])
            ->orderBy('last_message_at', 'desc')
            ->get();

        $result = [];
        foreach ($conversations as $conversation) {
            $conversationArray = (array) $conversation;

            // Load visitor
            if ($conversation->visitor_id) {
                $conversationArray['visitor'] = DB::table(self::TABLE_VISITORS)
                    ->where('id', $conversation->visitor_id)
                    ->first();
            }

            // Load widget
            if ($conversation->widget_id) {
                $conversationArray['widget'] = DB::table(self::TABLE_WIDGETS)
                    ->where('id', $conversation->widget_id)
                    ->first();
            }

            $result[] = $conversationArray;
        }

        return $result;
    }

    public function assign(int $conversationId, int $userId): array
    {
        DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->update([
                'assigned_to' => $userId,
                'updated_at' => now()
            ]);

        // Update agent status
        DB::table(self::TABLE_AGENT_STATUS)
            ->where('user_id', $userId)
            ->increment('active_conversations');

        $row = DB::table(self::TABLE)->where('id', $conversationId)->first();
        $result = (array) $row;

        // Load assigned agent
        if ($row->assigned_to) {
            $result['assigned_agent'] = DB::table(self::TABLE_USERS)
                ->where('id', $row->assigned_to)
                ->first();
        }

        return $result;
    }

    public function unassign(int $conversationId): array
    {
        $previousAgent = DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->value('assigned_to');

        DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->update([
                'assigned_to' => null,
                'updated_at' => now()
            ]);

        if ($previousAgent) {
            DB::table(self::TABLE_AGENT_STATUS)
                ->where('user_id', $previousAgent)
                ->where('active_conversations', '>', 0)
                ->decrement('active_conversations');
        }

        $row = DB::table(self::TABLE)->where('id', $conversationId)->first();
        return (array) $row;
    }

    public function close(int $conversationId): array
    {
        $previousAgent = DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->value('assigned_to');

        DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->update([
                'status' => self::STATUS_CLOSED,
                'resolved_at' => now(),
                'updated_at' => now()
            ]);

        if ($previousAgent) {
            DB::table(self::TABLE_AGENT_STATUS)
                ->where('user_id', $previousAgent)
                ->where('active_conversations', '>', 0)
                ->decrement('active_conversations');
        }

        $row = DB::table(self::TABLE)->where('id', $conversationId)->first();
        return (array) $row;
    }

    public function reopen(int $conversationId): array
    {
        $assignedTo = DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->value('assigned_to');

        DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->update([
                'status' => self::STATUS_OPEN,
                'resolved_at' => null,
                'updated_at' => now()
            ]);

        if ($assignedTo) {
            DB::table(self::TABLE_AGENT_STATUS)
                ->where('user_id', $assignedTo)
                ->increment('active_conversations');
        }

        $row = DB::table(self::TABLE)->where('id', $conversationId)->first();
        return (array) $row;
    }

    public function addTags(int $conversationId, array $tags): array
    {
        $row = DB::table(self::TABLE)->where('id', $conversationId)->first();
        $existingTags = $row->tags ? json_decode($row->tags, true) : [];

        DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->update([
                'tags' => json_encode(array_unique(array_merge($existingTags, $tags))),
                'updated_at' => now()
            ]);

        $row = DB::table(self::TABLE)->where('id', $conversationId)->first();
        return (array) $row;
    }

    public function rate(int $conversationId, float $rating, ?string $comment = null): array
    {
        DB::table(self::TABLE)
            ->where('id', $conversationId)
            ->update([
                'rating' => max(1, min(5, $rating)),
                'rating_comment' => $comment,
                'updated_at' => now()
            ]);

        $row = DB::table(self::TABLE)->where('id', $conversationId)->first();
        return (array) $row;
    }

    public function getConversationStats(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $conversations = DB::table(self::TABLE)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalConversations = $conversations->count();
        $closedConversations = $conversations->where('status', self::STATUS_CLOSED);

        // Calculate average response time
        $responseTimesSum = 0;
        $responseTimesCount = 0;
        foreach ($closedConversations as $c) {
            if ($c->first_response_at && $c->created_at) {
                $responseTimesSum += (strtotime($c->first_response_at) - strtotime($c->created_at)) / 60;
                $responseTimesCount++;
            }
        }
        $avgResponseTimeMinutes = $responseTimesCount > 0 ? $responseTimesSum / $responseTimesCount : 0;

        // Calculate average resolution time
        $resolutionTimesSum = 0;
        $resolutionTimesCount = 0;
        foreach ($closedConversations as $c) {
            if ($c->resolved_at && $c->created_at) {
                $resolutionTimesSum += (strtotime($c->resolved_at) - strtotime($c->created_at)) / 60;
                $resolutionTimesCount++;
            }
        }
        $avgResolutionTimeMinutes = $resolutionTimesCount > 0 ? $resolutionTimesSum / $resolutionTimesCount : 0;

        // Rating stats
        $ratedConversations = $closedConversations->whereNotNull('rating');
        $ratingDistribution = [];
        foreach ($ratedConversations as $c) {
            $ratingKey = (int) $c->rating;
            $ratingDistribution[$ratingKey] = ($ratingDistribution[$ratingKey] ?? 0) + 1;
        }

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_conversations' => $totalConversations,
            'by_status' => [
                self::STATUS_OPEN => $conversations->where('status', self::STATUS_OPEN)->count(),
                self::STATUS_PENDING => $conversations->where('status', self::STATUS_PENDING)->count(),
                self::STATUS_CLOSED => $closedConversations->count(),
            ],
            'avg_first_response_minutes' => round($avgResponseTimeMinutes, 1),
            'avg_resolution_minutes' => round($avgResolutionTimeMinutes, 1),
            'total_messages' => $conversations->sum('message_count'),
            'avg_messages_per_conversation' => round($conversations->avg('message_count') ?? 0, 1),
            'ratings' => [
                'count' => $ratedConversations->count(),
                'average' => round($ratedConversations->avg('rating') ?? 0, 1),
                'distribution' => $ratingDistribution,
            ],
        ];
    }

    public function getAgentPerformance(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $conversations = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $closedConversations = $conversations->where('status', self::STATUS_CLOSED);

        // Calculate average first response time
        $responseTimesSum = 0;
        $responseTimesCount = 0;
        foreach ($closedConversations as $c) {
            if ($c->first_response_at && $c->created_at) {
                $responseTimesSum += (strtotime($c->first_response_at) - strtotime($c->created_at)) / 60;
                $responseTimesCount++;
            }
        }
        $avgResponseTimeMinutes = $responseTimesCount > 0 ? $responseTimesSum / $responseTimesCount : 0;

        // Calculate average resolution time
        $resolutionTimesSum = 0;
        $resolutionTimesCount = 0;
        foreach ($closedConversations as $c) {
            if ($c->resolved_at && $c->created_at) {
                $resolutionTimesSum += (strtotime($c->resolved_at) - strtotime($c->created_at)) / 60;
                $resolutionTimesCount++;
            }
        }
        $avgResolutionTimeMinutes = $resolutionTimesCount > 0 ? $resolutionTimesSum / $resolutionTimesCount : 0;

        // Count messages sent
        $totalMessagesSent = DB::table(self::TABLE_MESSAGES . ' as m')
            ->join(self::TABLE . ' as c', 'c.id', '=', 'm.conversation_id')
            ->where('c.assigned_to', $userId)
            ->where('m.sender_type', 'agent')
            ->where('m.sender_id', $userId)
            ->whereBetween('m.created_at', [$start, $end])
            ->count();

        return [
            'user_id' => $userId,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'total_conversations' => $conversations->count(),
            'closed_conversations' => $closedConversations->count(),
            'avg_first_response_minutes' => round($avgResponseTimeMinutes, 1),
            'avg_resolution_minutes' => round($avgResolutionTimeMinutes, 1),
            'avg_rating' => round($closedConversations->whereNotNull('rating')->avg('rating') ?? 0, 1),
            'total_messages_sent' => $totalMessagesSent,
        ];
    }

    public function getHourlyChatVolume(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);

        $volume = DB::table(self::TABLE)
            ->where('created_at', '>=', $startDate)
            ->selectRaw("EXTRACT(HOUR FROM created_at) as hour, COUNT(*) as count")
            ->groupByRaw('EXTRACT(HOUR FROM created_at)')
            ->orderBy('hour')
            ->get();

        $result = array_fill(0, 24, 0);
        foreach ($volume as $row) {
            $result[(int)$row->hour] = $row->count;
        }

        return $result;
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): ChatConversationEntity
    {
        return ChatConversationEntity::reconstitute(
            id: (int) $row->id,
            widgetId: (int) $row->widget_id,
            visitorId: (int) $row->visitor_id,
            contactId: $row->contact_id ? (int) $row->contact_id : null,
            assignedTo: $row->assigned_to ? (int) $row->assigned_to : null,
            status: ConversationStatus::from($row->status),
            priority: ConversationPriority::from($row->priority),
            department: $row->department,
            subject: $row->subject,
            tags: $row->tags ? json_decode($row->tags, true) : [],
            messageCount: (int) $row->message_count,
            visitorMessageCount: (int) $row->visitor_message_count,
            agentMessageCount: (int) $row->agent_message_count,
            rating: $row->rating !== null ? Rating::fromValue((float) $row->rating, $row->rating_comment ?? null) : null,
            firstResponseAt: $row->first_response_at ? new DateTimeImmutable($row->first_response_at) : null,
            resolvedAt: $row->resolved_at ? new DateTimeImmutable($row->resolved_at) : null,
            lastMessageAt: $row->last_message_at ? new DateTimeImmutable($row->last_message_at) : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(ChatConversationEntity $entity): array
    {
        return [
            'widget_id' => $entity->getWidgetId(),
            'visitor_id' => $entity->getVisitorId(),
            'contact_id' => $entity->getContactId(),
            'assigned_to' => $entity->getAssignedTo(),
            'status' => $entity->getStatus()->value,
            'priority' => $entity->getPriority()->value,
            'department' => $entity->getDepartment(),
            'subject' => $entity->getSubject(),
            'tags' => json_encode($entity->getTags()),
            'message_count' => $entity->getMessageCount(),
            'visitor_message_count' => $entity->getVisitorMessageCount(),
            'agent_message_count' => $entity->getAgentMessageCount(),
            'rating' => $entity->getRating()?->getValue(),
            'rating_comment' => $entity->getRating()?->getComment(),
            'first_response_at' => $entity->getFirstResponseAt()?->format('Y-m-d H:i:s'),
            'resolved_at' => $entity->getResolvedAt()?->format('Y-m-d H:i:s'),
            'last_message_at' => $entity->getLastMessageAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
