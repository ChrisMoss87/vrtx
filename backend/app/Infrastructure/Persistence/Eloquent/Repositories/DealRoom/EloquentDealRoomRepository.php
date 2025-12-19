<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\DealRoom;

use App\Domain\DealRoom\Entities\DealRoom;
use App\Domain\DealRoom\Repositories\DealRoomRepositoryInterface;
use DateTimeImmutable;

class EloquentDealRoomRepository implements DealRoomRepositoryInterface
{
    public function findById(int $id): ?DealRoom
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(DealRoom $entity): DealRoom
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
