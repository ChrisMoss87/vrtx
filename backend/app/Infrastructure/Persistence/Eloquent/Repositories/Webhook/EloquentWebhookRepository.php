<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Webhook;

use App\Domain\Webhook\Entities\Webhook;
use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use DateTimeImmutable;

class EloquentWebhookRepository implements WebhookRepositoryInterface
{
    public function findById(int $id): ?Webhook
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Webhook $entity): Webhook
    {
        // TODO: Implement with Eloquent model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Eloquent model
        return false;
    }
}
