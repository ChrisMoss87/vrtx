<?php

declare(strict_types=1);

namespace App\Domain\Competitor\Repositories;

use App\Domain\Competitor\Entities\Battlecard;

interface BattlecardRepositoryInterface
{
    public function findById(int $id): ?Battlecard;

    public function findByCompetitorId(int $competitorId): array;

    public function findPublished(): array;

    public function save(Battlecard $battlecard): Battlecard;

    public function delete(int $id): bool;
}
