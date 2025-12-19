<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Services;

use App\Domain\Forecasting\Entities\ForecastSnapshot;
use App\Domain\Forecasting\Repositories\ForecastSnapshotRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * SnapshotService.
 *
 * Domain service responsible for creating and managing forecast snapshots.
 */
final class SnapshotService
{
    public function __construct(
        private readonly ForecastSnapshotRepositoryInterface $snapshotRepository
    ) {}

    /**
     * Create or update a snapshot for a period.
     */
    public function createSnapshot(
        int $pipelineId,
        ForecastPeriod $period,
        array $forecastData,
        ?UserId $userId = null,
        ?DateTimeImmutable $snapshotDate = null
    ): ForecastSnapshot {
        $snapshotDate = $snapshotDate ?? new DateTimeImmutable();

        // Check if snapshot already exists for this date
        $existing = $this->snapshotRepository->findByPipelineAndDate(
            $pipelineId,
            $period,
            $snapshotDate,
            $userId?->value()
        );

        if ($existing) {
            // Update existing snapshot
            return $this->snapshotRepository->save($existing);
        }

        // Create new snapshot
        $snapshot = ForecastSnapshot::create(
            pipelineId: $pipelineId,
            period: $period,
            snapshotDate: $snapshotDate,
            commitAmount: $forecastData['commit']['amount'] ?? 0.0,
            bestCaseAmount: $forecastData['best_case']['amount'] ?? 0.0,
            pipelineAmount: $forecastData['pipeline']['amount'] ?? 0.0,
            weightedAmount: $forecastData['weighted']['amount'] ?? 0.0,
            closedWonAmount: $forecastData['closed_won']['amount'] ?? 0.0,
            dealCount: $forecastData['weighted']['count'] ?? 0,
            userId: $userId,
            metadata: $forecastData['metadata'] ?? []
        );

        return $this->snapshotRepository->save($snapshot);
    }

    /**
     * Get snapshot history for trend analysis.
     *
     * @return array<ForecastSnapshot>
     */
    public function getSnapshotHistory(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null,
        int $limit = 12
    ): array {
        return $this->snapshotRepository->findHistoryByPipeline(
            $pipelineId,
            $period->type(),
            $userId,
            $limit
        );
    }

    /**
     * Get latest snapshot for a period.
     */
    public function getLatestSnapshot(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null
    ): ?ForecastSnapshot {
        return $this->snapshotRepository->findLatestByPipeline(
            $pipelineId,
            $period,
            $userId
        );
    }

    /**
     * Calculate forecast accuracy from snapshots.
     *
     * @return array<array{
     *     period: string,
     *     period_start: string,
     *     period_end: string,
     *     forecasted: float,
     *     actual: float,
     *     accuracy: float|null,
     *     variance: float
     * }>
     */
    public function calculateAccuracyFromHistory(
        int $pipelineId,
        string $periodType,
        ?int $userId = null,
        int $periods = 6
    ): array {
        $results = [];
        $now = new DateTimeImmutable();

        for ($i = 1; $i <= $periods; $i++) {
            $period = $this->getPeriodForOffset($periodType, $i, $now);

            $snapshot = $this->snapshotRepository->findLatestByPipeline(
                $pipelineId,
                $period,
                $userId
            );

            if ($snapshot) {
                $accuracy = $snapshot->getAccuracy();
                $variance = $snapshot->getVariance();

                $results[] = [
                    'period' => $period->label(),
                    'period_start' => $period->start()->format('Y-m-d'),
                    'period_end' => $period->end()->format('Y-m-d'),
                    'forecasted' => $snapshot->weightedAmount(),
                    'actual' => $snapshot->closedWonAmount(),
                    'accuracy' => $accuracy,
                    'variance' => $variance,
                ];
            }
        }

        return array_reverse($results);
    }

    /**
     * Get period for offset.
     */
    private function getPeriodForOffset(
        string $periodType,
        int $offset,
        DateTimeImmutable $now
    ): ForecastPeriod {
        $reference = match ($periodType) {
            'week' => $now->modify("-{$offset} weeks"),
            'quarter' => $now->modify("-{$offset} quarters"),
            'year' => $now->modify("-{$offset} years"),
            default => $now->modify("-{$offset} months"),
        };

        return ForecastPeriod::fromType($periodType, $reference);
    }

    /**
     * Delete old snapshots.
     */
    public function pruneOldSnapshots(int $daysToKeep = 365): int
    {
        $cutoffDate = (new DateTimeImmutable())->modify("-{$daysToKeep} days");
        return $this->snapshotRepository->deleteOlderThan($cutoffDate);
    }
}
