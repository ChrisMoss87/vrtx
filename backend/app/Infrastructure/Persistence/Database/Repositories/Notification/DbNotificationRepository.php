<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Notification;

use App\Domain\Notification\Entities\Notification as NotificationEntity;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbNotificationRepository implements NotificationRepositoryInterface
{
    private const TABLE = 'notifications';

    public function findById(int $id): ?NotificationEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->rowToArray($row) : null;
    }

    public function findByIdForUser(int $id, int $userId): ?NotificationEntity
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByIdForUserAsArray(int $id, int $userId): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        return $row ? $this->rowToArray($row) : null;
    }

    public function getForUser(
        int $userId,
        ?string $category = null,
        bool $unreadOnly = false,
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->orderByDesc('created_at');

        if ($category !== null) {
            $query->where('category', $category);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $rows = $query->offset($offset)->limit($limit)->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function getForUserAsArrays(
        int $userId,
        ?string $category = null,
        bool $unreadOnly = false,
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->orderByDesc('created_at');

        if ($category !== null) {
            $query->where('category', $category);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->offset($offset)->limit($limit)->get()->map(fn($row) => $this->rowToArray($row))->all();
    }

    public function getPaginatedForUser(
        int $userId,
        ?string $category = null,
        bool $unreadOnly = false,
        int $perPage = 25,
        int $page = 1
    ): PaginatedResult {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->orderByDesc('created_at');

        if ($category !== null) {
            $query->where('category', $category);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $total = $query->count();

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return PaginatedResult::create(
            items: $rows->map(fn($row) => $this->rowToArray($row))->all(),
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getUnreadCount(int $userId, ?string $category = null): int
    {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->whereNull('read_at');

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->count();
    }

    public function save(NotificationEntity $entity): NotificationEntity
    {
        $data = [
            'user_id' => $entity->getUserId(),
            'type' => $entity->getType(),
            'category' => $entity->getCategory(),
            'title' => $entity->getTitle(),
            'body' => $entity->getBody(),
            'icon' => $entity->getIcon(),
            'icon_color' => $entity->getIconColor(),
            'action_url' => $entity->getActionUrl(),
            'action_label' => $entity->getActionLabel(),
            'notifiable_type' => $entity->getNotifiableType(),
            'notifiable_id' => $entity->getNotifiableId(),
            'data' => json_encode($entity->getData()),
            'read_at' => $entity->getReadAt()?->format('Y-m-d H:i:s'),
            'archived_at' => $entity->getArchivedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($entity->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function create(array $data): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }

        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    public function markAsRead(int $id, int $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['read_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function markAllAsRead(int $userId, ?string $category = null): int
    {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->whereNull('read_at');

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public function archive(int $id, int $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['archived_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function deleteOlderThan(int $days): int
    {
        return DB::table(self::TABLE)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    private function toDomainEntity(stdClass $row): NotificationEntity
    {
        return NotificationEntity::reconstitute(
            id: (int) $row->id,
            userId: (int) $row->user_id,
            type: $row->type,
            category: $row->category,
            title: $row->title,
            body: $row->body,
            icon: $row->icon,
            iconColor: $row->icon_color,
            actionUrl: $row->action_url,
            actionLabel: $row->action_label,
            notifiableType: $row->notifiable_type,
            notifiableId: $row->notifiable_id ? (int) $row->notifiable_id : null,
            data: $row->data ? (is_string($row->data) ? json_decode($row->data, true) : $row->data) : [],
            readAt: $row->read_at ? new DateTimeImmutable($row->read_at) : null,
            archivedAt: $row->archived_at ? new DateTimeImmutable($row->archived_at) : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'type' => $row->type,
            'category' => $row->category,
            'title' => $row->title,
            'body' => $row->body,
            'icon' => $row->icon,
            'icon_color' => $row->icon_color,
            'action_url' => $row->action_url,
            'action_label' => $row->action_label,
            'notifiable_type' => $row->notifiable_type,
            'notifiable_id' => $row->notifiable_id,
            'data' => $row->data ? (is_string($row->data) ? json_decode($row->data, true) : $row->data) : [],
            'read_at' => $row->read_at,
            'archived_at' => $row->archived_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
