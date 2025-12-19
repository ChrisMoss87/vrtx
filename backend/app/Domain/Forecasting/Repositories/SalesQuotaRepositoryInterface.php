<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Repositories;

use App\Domain\Forecasting\Entities\SalesQuota;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;

/**
 * Repository interface for SalesQuota entity.
 */
interface SalesQuotaRepositoryInterface
{
    /**
     * Find a quota by its ID.
     */
    public function findById(int $id): ?SalesQuota;

    /**
     * Find quotas for a user.
     *
     * @return array<SalesQuota>
     */
    public function findByUser(int $userId): array;

    /**
     * Find quota for a user and period.
     */
    public function findByUserAndPeriod(
        int $userId,
        ForecastPeriod $period,
        ?int $pipelineId = null
    ): ?SalesQuota;

    /**
     * Find quotas for a team.
     *
     * @return array<SalesQuota>
     */
    public function findByTeam(int $teamId): array;

    /**
     * Find quota for a team and period.
     */
    public function findByTeamAndPeriod(
        int $teamId,
        ForecastPeriod $period,
        ?int $pipelineId = null
    ): ?SalesQuota;

    /**
     * Find quotas for a pipeline.
     *
     * @return array<SalesQuota>
     */
    public function findByPipeline(int $pipelineId): array;

    /**
     * Find all current quotas.
     *
     * @return array<SalesQuota>
     */
    public function findCurrent(): array;

    /**
     * Save a quota (insert or update).
     */
    public function save(SalesQuota $quota): SalesQuota;

    /**
     * Delete a quota.
     */
    public function delete(int $id): bool;
}
