<?php

declare(strict_types=1);

namespace App\Domain\DealRoom\Repositories;

use App\Domain\DealRoom\Entities\DealRoom;

interface DealRoomRepositoryInterface
{
    public function findById(int $id): ?DealRoom;

    public function findByDealId(int $dealId): ?DealRoom;

    public function findByAccessToken(string $token): ?DealRoom;

    public function findByUserId(int $userId): array;

    public function findActive(): array;

    public function save(DealRoom $room): DealRoom;

    public function delete(int $id): bool;
}
