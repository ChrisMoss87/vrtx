<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Repositories;

use App\Domain\Forecasting\Entities\ForecastAdjustment;
use App\Domain\Forecasting\ValueObjects\AdjustmentType;

/**
 * Repository interface for ForecastAdjustment entity.
 */
interface ForecastAdjustmentRepositoryInterface
{
    /**
     * Find an adjustment by its ID.
     */
    public function findById(int $id): ?ForecastAdjustment;

    /**
     * Find adjustments for a record.
     *
     * @return array<ForecastAdjustment>
     */
    public function findByRecord(int $moduleRecordId): array;

    /**
     * Find adjustments by type for a record.
     *
     * @return array<ForecastAdjustment>
     */
    public function findByRecordAndType(
        int $moduleRecordId,
        AdjustmentType $type
    ): array;

    /**
     * Find adjustments made by a user.
     *
     * @return array<ForecastAdjustment>
     */
    public function findByUser(int $userId): array;

    /**
     * Save an adjustment (insert or update).
     */
    public function save(ForecastAdjustment $adjustment): ForecastAdjustment;

    /**
     * Delete an adjustment.
     */
    public function delete(int $id): bool;
}
