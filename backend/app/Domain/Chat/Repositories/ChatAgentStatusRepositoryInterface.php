<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use Illuminate\Support\Collection;

interface ChatAgentStatusRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByUserId(int $userId): ?array;

    public function getOrCreate(int $userId): array;

    public function setStatus(int $userId, string $status): array;

    public function recordActivity(int $userId): array;

    public function updateSettings(int $userId, array $data): array;

    public function findOnline(): Collection;

    public function findAvailable(?string $department = null): Collection;

    public function findBestAvailableAgent(?array $routingRules, ?string $department): ?array;
}
