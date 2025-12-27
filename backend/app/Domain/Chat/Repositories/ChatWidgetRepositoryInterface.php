<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use Illuminate\Support\Collection;

interface ChatWidgetRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByKey(string $key): ?array;

    public function findAll(bool $activeOnly = false): Collection;

    public function create(array $data): int;

    public function update(int $id, array $data): array;

    public function delete(int $id): bool;

    public function getStatus(int $id): array;

    public function getEmbedCode(int $id): string;
}
