<?php

declare(strict_types=1);

namespace App\Domain\Competitor\Repositories;

use App\Domain\Competitor\Entities\Competitor;

interface CompetitorRepositoryInterface
{
    public function findById(int $id): ?Competitor;

    public function findAll(): array;

    public function findActive(): array;

    public function search(string $query): array;

    public function save(Competitor $competitor): Competitor;

    public function delete(int $id): bool;
}
