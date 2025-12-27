<?php

declare(strict_types=1);

namespace App\Domain\AI\Repositories;

interface AiEmailDraftRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByUserId(int $userId, int $limit = 20): array;

    public function create(array $data): int;

    public function markAsUsed(int $id): array;
}
