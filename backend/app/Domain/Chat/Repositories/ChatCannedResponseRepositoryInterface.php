<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use Illuminate\Support\Collection;

interface ChatCannedResponseRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByShortcut(int $userId, string $shortcut): ?array;

    public function findForUser(int $userId, array $filters = []): Collection;

    public function create(array $data): int;

    public function update(int $id, array $data): array;

    public function delete(int $id): bool;

    public function incrementUsage(int $id): void;

    public function renderContent(int $id, array $variables = []): string;
}
