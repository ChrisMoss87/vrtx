<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Services;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintRecordState;
use App\Domain\Blueprint\Entities\BlueprintSla;
use App\Domain\Blueprint\Events\SlaBreached;
use App\Domain\Blueprint\Events\SlaWarning;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintRepositoryInterface;
use App\Domain\Blueprint\ValueObjects\SlaStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Domain service for monitoring SLA compliance.
 */
class SlaMonitoringService
{
    public function __construct(
        private readonly BlueprintRepositoryInterface $blueprintRepository,
        private readonly BlueprintRecordStateRepositoryInterface $recordStateRepository,
    ) {}

    /**
     * Check SLA status for all records in a blueprint.
     *
     * @return array{warnings: int, breaches: int}
     */
    public function checkBlueprintSlas(int $blueprintId): array
    {
        $blueprint = $this->blueprintRepository->findById($blueprintId);
        if (!$blueprint || !$blueprint->isActive()) {
            return ['warnings' => 0, 'breaches' => 0];
        }

        $recordStates = $this->recordStateRepository->findByBlueprintId($blueprintId);
        $warnings = 0;
        $breaches = 0;

        foreach ($recordStates as $recordState) {
            $sla = $blueprint->getSlaForState($recordState->getCurrentStateId());
            if (!$sla || !$sla->isActive()) {
                continue;
            }

            $result = $this->checkRecordSla($blueprint, $sla, $recordState);
            if ($result === SlaStatus::WARNING) {
                $warnings++;
            } elseif ($result === SlaStatus::BREACHED) {
                $breaches++;
            }
        }

        return ['warnings' => $warnings, 'breaches' => $breaches];
    }

    /**
     * Check SLA status for a specific record.
     */
    public function checkRecordSla(
        Blueprint $blueprint,
        BlueprintSla $sla,
        BlueprintRecordState $recordState,
    ): SlaStatus {
        $hoursInState = $recordState->getHoursInCurrentState();
        $status = $sla->getStatusForElapsedHours($hoursInState);

        // Fire events for status changes
        if ($status === SlaStatus::BREACHED) {
            $hoursOverdue = $hoursInState - $sla->getDurationHours();

            Event::dispatch(new SlaBreached(
                blueprintId: $blueprint->getId(),
                slaId: $sla->getId(),
                recordId: $recordState->getRecordId(),
                stateId: $recordState->getCurrentStateId(),
                hoursOverdue: $hoursOverdue,
            ));

            Log::warning('SLA breached', [
                'blueprint_id' => $blueprint->getId(),
                'sla_id' => $sla->getId(),
                'record_id' => $recordState->getRecordId(),
                'hours_overdue' => $hoursOverdue,
            ]);
        } elseif ($status === SlaStatus::WARNING) {
            $hoursRemaining = $sla->getDurationHours() - $hoursInState;

            Event::dispatch(new SlaWarning(
                blueprintId: $blueprint->getId(),
                slaId: $sla->getId(),
                recordId: $recordState->getRecordId(),
                stateId: $recordState->getCurrentStateId(),
                hoursRemaining: $hoursRemaining,
            ));

            Log::info('SLA warning', [
                'blueprint_id' => $blueprint->getId(),
                'sla_id' => $sla->getId(),
                'record_id' => $recordState->getRecordId(),
                'hours_remaining' => $hoursRemaining,
            ]);
        }

        return $status;
    }

    /**
     * Get SLA status summary for a record.
     */
    public function getRecordSlaStatus(
        Blueprint $blueprint,
        BlueprintRecordState $recordState,
    ): ?array {
        $sla = $blueprint->getSlaForState($recordState->getCurrentStateId());
        if (!$sla || !$sla->isActive()) {
            return null;
        }

        $hoursInState = $recordState->getHoursInCurrentState();
        $status = $sla->getStatusForElapsedHours($hoursInState);
        $hoursRemaining = max(0, $sla->getDurationHours() - $hoursInState);
        $percentComplete = min(100, ($hoursInState / $sla->getDurationHours()) * 100);

        return [
            'sla_id' => $sla->getId(),
            'sla_name' => $sla->getName(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'duration_hours' => $sla->getDurationHours(),
            'warning_hours' => $sla->getWarningHours(),
            'hours_elapsed' => $hoursInState,
            'hours_remaining' => $hoursRemaining,
            'percent_complete' => round($percentComplete, 1),
            'due_at' => $sla->calculateDueDate($recordState->getEnteredStateAt())->format('c'),
            'warning_at' => $sla->calculateWarningDate($recordState->getEnteredStateAt())->format('c'),
        ];
    }
}
