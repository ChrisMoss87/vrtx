<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Repositories;

use App\Domain\Forecasting\Entities\ForecastSnapshot;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use DateTimeImmutable;

/**
 * Repository interface for ForecastSnapshot entity.
 */
interface ForecastSnapshotRepositoryInterface
{
    /**
     * Find a snapshot by its ID.
     */
    public function findById(int $id): ?ForecastSnapshot;

    /**
     * Find snapshot by pipeline and specific date.
     */
    public function findByPipelineAndDate(
        int $pipelineId,
        ForecastPeriod $period,
        DateTimeImmutable $snapshotDate,
        ?int $userId = null
    ): ?ForecastSnapshot;

    /**
     * Find latest snapshot for a pipeline and period.
     */
    public function findLatestByPipeline(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null
    ): ?ForecastSnapshot;

    /**
     * Find snapshot history for a pipeline.
     *
     * @return array<ForecastSnapshot>
     */
    public function findHistoryByPipeline(
        int $pipelineId,
        string $periodType,
        ?int $userId = null,
        int $limit = 12
    ): array;

    /**
     * Find all snapshots for a period.
     *
     * @return array<ForecastSnapshot>
     */
    public function findByPeriod(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null
    ): array;

    /**
     * Save a snapshot (insert or update).
     */
    public function save(ForecastSnapshot $snapshot): ForecastSnapshot;

    /**
     * Delete snapshots older than a specific date.
     */
    public function deleteOlderThan(DateTimeImmutable $date): int;

    /**
     * Delete a snapshot.
     */
    public function delete(int $id): bool;
}
