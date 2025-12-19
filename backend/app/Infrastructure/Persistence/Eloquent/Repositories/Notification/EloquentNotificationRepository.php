<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Notification;

use App\Domain\Notification\Entities\Notification as NotificationEntity;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Models\Notification;
use DateTimeImmutable;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function findById(int $id): ?NotificationEntity
    {
        $model = Notification::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdForUser(int $id, int $userId): ?NotificationEntity
    {
        $model = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function getForUser(
        int $userId,
        ?string $category = null,
        bool $unreadOnly = false,
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = Notification::where('user_id', $userId)
            ->active()
            ->orderByDesc('created_at');

        if ($category !== null) {
            $query->forCategory($category);
        }

        if ($unreadOnly) {
            $query->unread();
        }

        $models = $query->skip($offset)->take($limit)->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function getUnreadCount(int $userId, ?string $category = null): int
    {
        $query = Notification::where('user_id', $userId)
            ->active()
            ->unread();

        if ($category !== null) {
            $query->forCategory($category);
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
            'data' => $entity->getData(),
            'read_at' => $entity->getReadAt()?->format('Y-m-d H:i:s'),
            'archived_at' => $entity->getArchivedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($entity->getId() !== null) {
            $model = Notification::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = Notification::create($data);
        }

        return $this->toDomain($model->fresh());
    }

    public function markAsRead(int $id, int $userId): bool
    {
        return Notification::where('id', $id)
            ->where('user_id', $userId)
            ->update(['read_at' => now()]) > 0;
    }

    public function markAllAsRead(int $userId, ?string $category = null): int
    {
        $query = Notification::where('user_id', $userId)
            ->whereNull('read_at');

        if ($category !== null) {
            $query->forCategory($category);
        }

        return $query->update(['read_at' => now()]);
    }

    public function archive(int $id, int $userId): bool
    {
        return Notification::where('id', $id)
            ->where('user_id', $userId)
            ->update(['archived_at' => now()]) > 0;
    }

    public function deleteOlderThan(int $days): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    private function toDomain(Notification $model): NotificationEntity
    {
        return NotificationEntity::reconstitute(
            id: $model->id,
            userId: $model->user_id,
            type: $model->type,
            category: $model->category,
            title: $model->title,
            body: $model->body,
            icon: $model->icon,
            iconColor: $model->icon_color,
            actionUrl: $model->action_url,
            actionLabel: $model->action_label,
            notifiableType: $model->notifiable_type,
            notifiableId: $model->notifiable_id,
            data: $model->data ?? [],
            readAt: $model->read_at ? new DateTimeImmutable($model->read_at->toDateTimeString()) : null,
            archivedAt: $model->archived_at ? new DateTimeImmutable($model->archived_at->toDateTimeString()) : null,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );
    }
}
