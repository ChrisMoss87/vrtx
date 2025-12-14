<?php

declare(strict_types=1);

namespace App\Application\Services\Approval;

use App\Domain\Approval\Entities\ApprovalRequest;
use App\Domain\Approval\Entities\ApprovalStep;
use App\Domain\Approval\Events\ApprovalRequested;
use App\Domain\Approval\Events\ApprovalResolved;
use App\Domain\Approval\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Approval\Repositories\ApprovalRuleRepositoryInterface;
use App\Domain\Approval\Services\ApprovalEvaluationService;
use App\Domain\Approval\ValueObjects\ApprovalStatus;
use App\Domain\Approval\ValueObjects\ApprovalType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class ApprovalApplicationService
{
    public function __construct(
        private readonly ApprovalRequestRepositoryInterface $requestRepository,
        private readonly ApprovalRuleRepositoryInterface $ruleRepository,
        private readonly ApprovalEvaluationService $evaluationService,
    ) {}

    public function submitForApproval(
        int $moduleId,
        int $recordId,
        array $recordData,
        ?int $requestedBy = null,
        ?string $reason = null,
    ): ?ApprovalRequest {
        // Find matching rule
        $rule = $this->evaluationService->findMatchingRule($moduleId, $recordData);
        if (!$rule) {
            return null;
        }

        $approverIds = $rule->getApproverIds($recordData);
        if (empty($approverIds)) {
            return null;
        }

        return DB::transaction(function () use ($moduleId, $recordId, $rule, $approverIds, $requestedBy, $reason) {
            $request = ApprovalRequest::create(
                moduleId: $moduleId,
                recordId: $recordId,
                type: $rule->getType(),
                approverIds: $approverIds,
                requestedBy: $requestedBy,
                reason: $reason,
            );

            $savedRequest = $this->requestRepository->save($request);

            Event::dispatch(new ApprovalRequested(
                requestId: $savedRequest->getId(),
                moduleId: $moduleId,
                recordId: $recordId,
                approverIds: $approverIds,
                requestedBy: $requestedBy,
            ));

            return $savedRequest;
        });
    }

    public function approve(int $requestId, int $approverId, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($requestId, $approverId, $comment) {
            $request = $this->requestRepository->findById($requestId);
            if (!$request) {
                throw new \InvalidArgumentException("Approval request not found: {$requestId}");
            }

            $request->approve($approverId, $comment);
            $savedRequest = $this->requestRepository->save($request);

            if ($savedRequest->isApproved()) {
                Event::dispatch(new ApprovalResolved(
                    requestId: $savedRequest->getId(),
                    moduleId: $savedRequest->getModuleId(),
                    recordId: $savedRequest->getRecordId(),
                    status: ApprovalStatus::APPROVED,
                    resolvedBy: $approverId,
                ));
            }

            return $savedRequest;
        });
    }

    public function reject(int $requestId, int $approverId, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($requestId, $approverId, $comment) {
            $request = $this->requestRepository->findById($requestId);
            if (!$request) {
                throw new \InvalidArgumentException("Approval request not found: {$requestId}");
            }

            $request->reject($approverId, $comment);
            $savedRequest = $this->requestRepository->save($request);

            Event::dispatch(new ApprovalResolved(
                requestId: $savedRequest->getId(),
                moduleId: $savedRequest->getModuleId(),
                recordId: $savedRequest->getRecordId(),
                status: ApprovalStatus::REJECTED,
                resolvedBy: $approverId,
            ));

            return $savedRequest;
        });
    }

    public function getPendingForApprover(int $approverId): array
    {
        return $this->requestRepository->findPendingForApprover($approverId);
    }

    public function getPendingForRecord(int $moduleId, int $recordId): ?ApprovalRequest
    {
        return $this->requestRepository->findPendingForRecord($moduleId, $recordId);
    }

    public function requiresApproval(int $moduleId, array $recordData): bool
    {
        return $this->evaluationService->requiresApproval($moduleId, $recordData);
    }
}
