<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Repositories;

use App\Domain\Forecasting\Entities\ForecastScenario;
use App\Domain\Shared\ValueObjects\PaginatedResult;
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
     * Find a scenario by its ID and return as array.
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find all scenarios for a user.
     *
     * @return array<ForecastScenario>
     */
    public function findByUser(int $userId): array;

    /**
     * Find all scenarios for a user as arrays.
     *
     * @return array<array>
     */
    public function findByUserAsArrays(int $userId): array;

    /**
     * Find scenarios for a user and module.
     *
     * @return array<ForecastScenario>
     */
    public function findByUserAndModule(int $userId, int $moduleId): array;

    /**
     * Find scenarios for a user and module as arrays.
     *
     * @return array<array>
     */
    public function findByUserAndModuleAsArrays(int $userId, int $moduleId): array;

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
     * Find scenarios for a period as arrays.
     *
     * @return array<array>
     */
    public function findByPeriodAsArrays(
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
     * Find baseline scenario for a period as array.
     */
    public function findBaselineAsArray(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): ?array;

    /**
     * Find shared scenarios.
     *
     * @return array<ForecastScenario>
     */
    public function findShared(int $moduleId): array;

    /**
     * Find shared scenarios as arrays.
     *
     * @return array<array>
     */
    public function findSharedAsArrays(int $moduleId): array;

    /**
     * Find scenarios with filtering and pagination.
     */
    public function findWithFilters(array $filters, int $perPage = 15): PaginatedResult;

    /**
     * Save a scenario (insert or update).
     */
    public function save(ForecastScenario $scenario): ForecastScenario;

    /**
     * Delete a scenario.
     */
    public function delete(int $id): bool;
}
