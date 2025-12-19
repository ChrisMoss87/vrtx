<?php

declare(strict_types=1);

namespace App\Domain\Approval\Services;

use App\Domain\Approval\Entities\ApprovalRule;
use App\Domain\Approval\Repositories\ApprovalRuleRepositoryInterface;
use App\Domain\Workflow\Services\ConditionEvaluationService;

class ApprovalEvaluationService
{
    public function __construct(
        private readonly ApprovalRuleRepositoryInterface $ruleRepository,
        private readonly ConditionEvaluationService $conditionEvaluator,
    ) {}

    public function findMatchingRule(int $moduleId, array $recordData): ?ApprovalRule
    {
        $rules = $this->ruleRepository->findActiveByModuleId($moduleId);

        // Sort by priority (higher first)
        usort($rules, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($rules as $rule) {
            if ($this->evaluateConditions($rule, $recordData)) {
                return $rule;
            }
        }

        return null;
    }

    public function evaluateConditions(ApprovalRule $rule, array $recordData): bool
    {
        $conditions = $rule->getConditions();

        if (empty($conditions)) {
            return true;
        }

        $context = ['record' => $recordData];
        return $this->conditionEvaluator->evaluate($conditions, $context);
    }

    public function requiresApproval(int $moduleId, array $recordData): bool
    {
        return $this->findMatchingRule($moduleId, $recordData) !== null;
    }
}
