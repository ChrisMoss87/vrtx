<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Repositories;

use App\Domain\Forecasting\Entities\ForecastScenario;
use DateTimeImmutable;

/**
 * Repository interface for ForecastScenario aggregate root.
 */
interface ForecastScenarioRepositoryInterface
{
    /**
     * Find a scenario by its ID.
     */
    public function findById(int $id): ?ForecastScenario;

    /**
     * Find all scenarios for a user.
     *
     * @return array<ForecastScenario>
     */
    public function findByUser(int $userId): array;

    /**
     * Find scenarios for a user and module.
     *
     * @return array<ForecastScenario>
     */
    public function findByUserAndModule(int $userId, int $moduleId): array;

    /**
     * Find scenarios for a period.
     *
     * @return array<ForecastScenario>
     */
    public function findByPeriod(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): array;

    /**
     * Find baseline scenario for a period.
     */
    public function findBaseline(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): ?ForecastScenario;

    /**
     * Find shared scenarios.
     *
     * @return array<ForecastScenario>
     */
    public function findShared(int $moduleId): array;

    /**
     * Save a scenario (insert or update).
     */
    public function save(ForecastScenario $scenario): ForecastScenario;

    /**
     * Delete a scenario.
     */
    public function delete(int $id): bool;
}
