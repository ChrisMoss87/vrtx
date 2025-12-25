<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\TransitionExecution;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Domain\Blueprint\ValueObjects\ExecutionStatus;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbTransitionExecutionRepository implements TransitionExecutionRepositoryInterface
{
    private const TABLE = 'blueprint_transition_executions';

    // Status constants (previously on model)
    private const STATUS_PENDING = 'pending';
    private const STATUS_PENDING_REQUIREMENTS = 'pending_requirements';
    private const STATUS_PENDING_APPROVAL = 'pending_approval';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_REJECTED = 'rejected';

    public function findById(int $id): ?TransitionExecution
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByRecordId(int $recordId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('record_id', $recordId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByTransitionId(int $transitionId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('transition_id', $transitionId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findPendingForRecord(int $recordId): ?TransitionExecution
    {
        $row = DB::table(self::TABLE)
            ->where('record_id', $recordId)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_PENDING_REQUIREMENTS,
                self::STATUS_PENDING_APPROVAL,
            ])
            ->orderByDesc('created_at')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(TransitionExecution $execution): TransitionExecution
    {
        $data = $this->toRowData($execution);

        if ($execution->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $execution->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $execution->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    private function toDomainEntity(stdClass $row): TransitionExecution
    {
        return TransitionExecution::reconstitute(
            id: (int) $row->id,
            transitionId: (int) $row->transition_id,
            recordId: (int) $row->record_id,
            fromStateId: $row->from_state_id ? (int) $row->from_state_id : null,
            toStateId: $row->to_state_id ? (int) $row->to_state_id : null,
            status: $this->mapModelStatusToExecutionStatus($row->status),
            executedBy: $row->executed_by ? UserId::fromInt((int) $row->executed_by) : null,
            requirementData: $row->requirements_data ? (is_string($row->requirements_data) ? json_decode($row->requirements_data, true) : $row->requirements_data) : [],
            actionResults: $row->action_results ? (is_string($row->action_results) ? json_decode($row->action_results, true) : $row->action_results) : [],
            errorMessage: $row->error_message,
            startedAt: $row->started_at ? new DateTimeImmutable($row->started_at) : null,
            completedAt: $row->completed_at ? new DateTimeImmutable($row->completed_at) : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    private function toRowData(TransitionExecution $execution): array
    {
        return [
            'transition_id' => $execution->getTransitionId(),
            'record_id' => $execution->getRecordId(),
            'from_state_id' => $execution->getFromStateId(),
            'to_state_id' => $execution->getToStateId(),
            'executed_by' => $execution->getExecutedBy()?->value(),
            'status' => $this->mapExecutionStatusToModel($execution->getStatus()),
            'requirements_data' => json_encode($execution->getRequirementData()),
            'action_results' => json_encode($execution->getActionResults()),
            'error_message' => $execution->getErrorMessage(),
            'started_at' => $execution->getStartedAt()?->format('Y-m-d H:i:s'),
            'completed_at' => $execution->getCompletedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function mapModelStatusToExecutionStatus(string $status): ExecutionStatus
    {
        return match ($status) {
            self::STATUS_PENDING => ExecutionStatus::PENDING,
            self::STATUS_PENDING_REQUIREMENTS => ExecutionStatus::AWAITING_REQUIREMENTS,
            self::STATUS_PENDING_APPROVAL => ExecutionStatus::AWAITING_APPROVAL,
            self::STATUS_APPROVED => ExecutionStatus::IN_PROGRESS,
            self::STATUS_COMPLETED => ExecutionStatus::COMPLETED,
            self::STATUS_FAILED => ExecutionStatus::FAILED,
            self::STATUS_CANCELLED => ExecutionStatus::CANCELLED,
            self::STATUS_REJECTED => ExecutionStatus::CANCELLED,
            default => ExecutionStatus::PENDING,
        };
    }

    private function mapExecutionStatusToModel(ExecutionStatus $status): string
    {
        return match ($status) {
            ExecutionStatus::PENDING => self::STATUS_PENDING,
            ExecutionStatus::AWAITING_REQUIREMENTS => self::STATUS_PENDING_REQUIREMENTS,
            ExecutionStatus::AWAITING_APPROVAL => self::STATUS_PENDING_APPROVAL,
            ExecutionStatus::IN_PROGRESS => self::STATUS_APPROVED,
            ExecutionStatus::COMPLETED => self::STATUS_COMPLETED,
            ExecutionStatus::FAILED => self::STATUS_FAILED,
            ExecutionStatus::CANCELLED => self::STATUS_CANCELLED,
            ExecutionStatus::ROLLED_BACK => self::STATUS_CANCELLED,
        };
    }
}
