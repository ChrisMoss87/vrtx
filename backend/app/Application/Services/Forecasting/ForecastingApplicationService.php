<?php

declare(strict_types=1);

namespace App\Application\Services\Forecasting;

use App\Domain\Forecasting\DTOs\CreateForecastScenarioDTO;
use App\Domain\Forecasting\DTOs\CreateQuotaDTO;
use App\Domain\Forecasting\DTOs\ForecastResponseDTO;
use App\Domain\Forecasting\DTOs\QuotaResponseDTO;
use App\Domain\Forecasting\DTOs\UpdateDealForecastDTO;
use App\Domain\Forecasting\Entities\ForecastAdjustment;
use App\Domain\Forecasting\Entities\ForecastScenario;
use App\Domain\Forecasting\Entities\SalesQuota;
use App\Domain\Forecasting\Events\ForecastAdjusted;
use App\Domain\Forecasting\Events\ForecastScenarioCreated;
use App\Domain\Forecasting\Events\QuotaUpdated;
use App\Domain\Forecasting\Events\SnapshotCreated;
use App\Domain\Forecasting\Repositories\ForecastAdjustmentRepositoryInterface;
use App\Domain\Forecasting\Repositories\ForecastScenarioRepositoryInterface;
use App\Domain\Forecasting\Repositories\ForecastSnapshotRepositoryInterface;
use App\Domain\Forecasting\Repositories\SalesQuotaRepositoryInterface;
use App\Domain\Forecasting\Services\ForecastCalculatorService;
use App\Domain\Forecasting\Services\SnapshotService;
use App\Domain\Forecasting\ValueObjects\AdjustmentType;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * ForecastingApplicationService.
 *
 * Application service that coordinates forecasting operations across domain services and repositories.
 */
final class ForecastingApplicationService
{
    public function __construct(
        private readonly ForecastCalculatorService $calculatorService,
        private readonly SnapshotService $snapshotService,
        private readonly ForecastScenarioRepositoryInterface $scenarioRepository,
        private readonly SalesQuotaRepositoryInterface $quotaRepository,
        private readonly ForecastSnapshotRepositoryInterface $snapshotRepository,
        private readonly ForecastAdjustmentRepositoryInterface $adjustmentRepository,
        private readonly PipelineRepositoryInterface $pipelineRepository,
        private readonly ModuleRecordRepositoryInterface $moduleRecordRepository,
        private readonly AuthContextInterface $authContext,
    ) {}

    /**
     * Get forecast summary for a pipeline and period.
     */
    public function getForecastSummary(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        ?DateTimeImmutable $periodStart = null
    ): ForecastResponseDTO {
        $pipeline = $this->pipelineRepository->findByIdWithRelations($pipelineId);
        if (!$pipeline) {
            throw new \RuntimeException("Pipeline not found: {$pipelineId}");
        }

        $period = ForecastPeriod::fromType($periodType, $periodStart);

        $valueField = $pipeline['settings']['value_field'] ?? 'amount';
        $stageFieldName = $pipeline['stage_field_api_name'];

        // Get stage data
        $stageData = [];
        foreach ($pipeline['stages'] as $stage) {
            $stageData[$stage['id']] = [
                'probability' => $stage['probability'],
                'is_won' => $stage['is_won_stage'],
                'is_lost' => $stage['is_lost_stage'],
            ];
        }

        // Get deals for the period
        $deals = $this->moduleRecordRepository->findByPeriod(
            $pipeline['module_id'],
            $period->start(),
            $period->end(),
            $userId
        );

        // Calculate forecast summary
        $summary = $this->calculatorService->calculateForecastSummary(
            $deals,
            $stageData,
            $valueField,
            $stageFieldName
        );

        // Get quota if available
        $quota = null;
        if ($userId) {
            $quotaEntity = $this->quotaRepository->findByUserAndPeriod(
                $userId,
                $period,
                $pipelineId
            );

            if ($quotaEntity) {
                $currentAmount = $summary['closed_won']['amount'] + $summary['commit']['amount'];
                $quota = [
                    'id' => $quotaEntity->getId(),
                    'amount' => $quotaEntity->quotaAmount(),
                    'attainment' => $quotaEntity->getAttainment($currentAmount),
                    'remaining' => $quotaEntity->getRemainingAmount($summary['closed_won']['amount']),
                ];
            }
        }

        $summary['quota'] = $quota;
        $summary['period'] = $period->toArray();

        return ForecastResponseDTO::fromArray($summary);
    }

    /**
     * Update a deal's forecast settings.
     */
    public function updateDealForecast(UpdateDealForecastDTO $dto): ModuleRecord
    {
        return DB::transaction(function () use ($dto) {
            $deal = ModuleRecord::findOrFail($dto->moduleRecordId);
            $userId = UserId::fromInt($dto->userId);

            // Track category change
            if ($dto->category !== null && $dto->category->value !== $deal->forecast_category) {
                $adjustment = ForecastAdjustment::create(
                    userId: $userId,
                    moduleRecordId: $dto->moduleRecordId,
                    adjustmentType: AdjustmentType::CATEGORY_CHANGE,
                    oldValue: $deal->forecast_category,
                    newValue: $dto->category->value,
                    reason: $dto->reason
                );
                $saved = $this->adjustmentRepository->save($adjustment);

                Event::dispatch(new ForecastAdjusted(
                    $saved->getId(),
                    $dto->userId,
                    $dto->moduleRecordId,
                    AdjustmentType::CATEGORY_CHANGE->value,
                    $deal->forecast_category,
                    $dto->category->value
                ));

                $deal->forecast_category = $dto->category->value;
            }

            // Track amount override change
            if ($dto->override !== null && $dto->override != $deal->forecast_override) {
                $adjustment = ForecastAdjustment::create(
                    userId: $userId,
                    moduleRecordId: $dto->moduleRecordId,
                    adjustmentType: AdjustmentType::AMOUNT_OVERRIDE,
                    oldValue: (string) $deal->forecast_override,
                    newValue: (string) $dto->override,
                    reason: $dto->reason
                );
                $saved = $this->adjustmentRepository->save($adjustment);

                Event::dispatch(new ForecastAdjusted(
                    $saved->getId(),
                    $dto->userId,
                    $dto->moduleRecordId,
                    AdjustmentType::AMOUNT_OVERRIDE->value,
                    (string) $deal->forecast_override,
                    (string) $dto->override
                ));

                $deal->forecast_override = $dto->override > 0 ? $dto->override : null;
            }

            // Track close date change
            if ($dto->expectedCloseDate !== null) {
                $oldDate = $deal->expected_close_date?->format('Y-m-d');
                $newDate = $dto->expectedCloseDate->format('Y-m-d');

                if ($oldDate !== $newDate) {
                    $adjustment = ForecastAdjustment::create(
                        userId: $userId,
                        moduleRecordId: $dto->moduleRecordId,
                        adjustmentType: AdjustmentType::CLOSE_DATE_CHANGE,
                        oldValue: $oldDate,
                        newValue: $newDate,
                        reason: $dto->reason
                    );
                    $saved = $this->adjustmentRepository->save($adjustment);

                    Event::dispatch(new ForecastAdjusted(
                        $saved->getId(),
                        $dto->userId,
                        $dto->moduleRecordId,
                        AdjustmentType::CLOSE_DATE_CHANGE->value,
                        $oldDate,
                        $newDate
                    ));

                    $deal->expected_close_date = $dto->expectedCloseDate;
                }
            }

            $deal->save();
            return $deal->fresh();
        });
    }

    /**
     * Create a forecast scenario.
     */
    public function createScenario(CreateForecastScenarioDTO $dto): ForecastScenario
    {
        $userId = UserId::fromInt($dto->userId);

        $scenario = ForecastScenario::create(
            name: $dto->name,
            userId: $userId,
            moduleId: $dto->moduleId,
            periodStart: $dto->periodStart,
            periodEnd: $dto->periodEnd,
            scenarioType: $dto->scenarioType,
            description: $dto->description,
            targetAmount: $dto->targetAmount,
            isBaseline: $dto->isBaseline,
            isShared: $dto->isShared,
            settings: $dto->settings,
        );

        $saved = $this->scenarioRepository->save($scenario);

        Event::dispatch(new ForecastScenarioCreated(
            $saved->getId(),
            $dto->userId,
            $dto->moduleId,
            $dto->scenarioType->value,
            $dto->isBaseline
        ));

        return $saved;
    }

    /**
     * Create or update a quota.
     */
    public function saveQuota(CreateQuotaDTO $dto): SalesQuota
    {
        return DB::transaction(function () use ($dto) {
            $userId = $dto->userId ? UserId::fromInt($dto->userId) : null;

            // Check for existing quota
            $existing = null;
            if ($dto->userId) {
                $existing = $this->quotaRepository->findByUserAndPeriod(
                    $dto->userId,
                    $dto->period,
                    $dto->pipelineId
                );
            } elseif ($dto->teamId) {
                $existing = $this->quotaRepository->findByTeamAndPeriod(
                    $dto->teamId,
                    $dto->period,
                    $dto->pipelineId
                );
            }

            if ($existing) {
                $oldAmount = $existing->quotaAmount();
                $existing->update($dto->quotaAmount, $dto->notes);
                $saved = $this->quotaRepository->save($existing);

                Event::dispatch(new QuotaUpdated(
                    $saved->getId(),
                    $dto->userId,
                    $dto->teamId,
                    $dto->pipelineId,
                    $oldAmount,
                    $dto->quotaAmount
                ));

                return $saved;
            }

            $quota = SalesQuota::create(
                period: $dto->period,
                quotaAmount: $dto->quotaAmount,
                quotaType: $dto->quotaType,
                userId: $userId,
                pipelineId: $dto->pipelineId,
                teamId: $dto->teamId,
                currency: $dto->currency,
                notes: $dto->notes,
            );

            return $this->quotaRepository->save($quota);
        });
    }

    /**
     * Create a forecast snapshot.
     */
    public function createSnapshot(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        ?DateTimeImmutable $periodStart = null
    ): void {
        $forecastData = $this->getForecastSummary($pipelineId, $userId, $periodType, $periodStart);
        $period = ForecastPeriod::fromType($periodType, $periodStart);
        $userIdVO = $userId ? UserId::fromInt($userId) : null;

        $snapshot = $this->snapshotService->createSnapshot(
            $pipelineId,
            $period,
            $forecastData->toArray(),
            $userIdVO
        );

        Event::dispatch(new SnapshotCreated(
            $snapshot->getId(),
            $pipelineId,
            $userId,
            $periodType,
            $snapshot->snapshotDate()->format('Y-m-d'),
            $snapshot->weightedAmount()
        ));
    }

    /**
     * Get forecast accuracy over time.
     */
    public function getForecastAccuracy(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        int $periods = 6
    ): array {
        return $this->snapshotService->calculateAccuracyFromHistory(
            $pipelineId,
            $periodType,
            $userId,
            $periods
        );
    }
}
