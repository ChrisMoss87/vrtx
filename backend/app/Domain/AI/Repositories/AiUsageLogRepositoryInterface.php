<?php

declare(strict_types=1);

namespace App\Domain\AI\Repositories;

interface AiUsageLogRepositoryInterface
{
    public function create(array $data): int;

    public function getSummary(?string $startDate = null, ?string $endDate = null): array;

    public function getByUser(?string $startDate = null, ?string $endDate = null): array;

    public function getTrend(int $days = 30): array;
}
