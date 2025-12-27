<?php

declare(strict_types=1);

namespace App\Domain\AI\Repositories;

interface AiSettingRepositoryInterface
{
    public function get(): ?array;

    public function firstOrCreate(array $defaults = []): array;

    public function update(array $data): array;

    public function isAvailable(): bool;

    public function isBudgetExceeded(): bool;

    public function getRemainingBudgetCents(): int;

    public function recordUsage(int $costCents): void;

    public function resetMonthlyUsage(): void;

    public function calculateCost(int $inputTokens, int $outputTokens): int;
}
