<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Domain\Workflow\Services\ConditionEvaluationService;
use App\Domain\Blueprint\Entities\BlueprintTransition;
use Illuminate\Support\Collection;

/**
 * Evaluates before-phase conditions for blueprint transitions.
 * Reuses the workflow ConditionEvaluationService for actual evaluation.
 */
class ConditionService
{
    public function __construct(
        protected ConditionEvaluationService $evaluator
    ) {}

    /**
     * Evaluate all conditions for a transition.
     */
    public function evaluate(BlueprintTransition $transition, array $recordData): bool
    {
        $conditions = $transition->getConditions();

        if ($conditions->isEmpty()) {
            return true;
        }

        // Group conditions by logical_group
        $groups = $conditions->groupBy(fn($c) => $c->getLogicalGroup());

        // Evaluate each group
        $groupResults = [];
        foreach ($groups as $groupName => $groupConditions) {
            $groupResult = $this->evaluateGroup($groupConditions, $recordData);
            $groupResults[$groupName] = $groupResult;
        }

        // Combine group results (default is AND between groups)
        return !in_array(false, $groupResults, true);
    }

    /**
     * Evaluate a group of conditions.
     */
    protected function evaluateGroup(Collection $conditions, array $recordData): bool
    {
        $results = [];

        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($condition, $recordData);
        }

        // All conditions in a group must be true (AND logic)
        return !in_array(false, $results, true);
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition($condition, array $recordData): bool
    {
        $field = $condition->getField();
        if (!$field) {
            return true; // No field specified, condition passes
        }

        $fieldName = $field->getApiName();
        $actualValue = $recordData[$fieldName] ?? null;
        $operator = $condition->getOperator();
        $expectedValue = $this->parseValue($condition->getValue(), $field->getType());

        // Build context for the evaluator
        $context = ['record' => $recordData];

        // Use the workflow condition evaluator
        $conditionArray = [
            'field' => $fieldName,
            'operator' => $operator,
            'value' => $expectedValue,
        ];

        return $this->evaluator->evaluate([$conditionArray], $context);
    }

    /**
     * Parse a value based on field type.
     */
    protected function parseValue(?string $value, string $fieldType): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($fieldType) {
            'integer', 'number' => is_numeric($value) ? (int) $value : $value,
            'decimal', 'currency', 'percent' => is_numeric($value) ? (float) $value : $value,
            'boolean', 'switch' => in_array(strtolower($value), ['true', '1', 'yes']),
            'multiselect', 'tags' => json_decode($value, true) ?? explode(',', $value),
            default => $value,
        };
    }

    /**
     * Get failed conditions (for error messages).
     */
    public function getFailedConditions(BlueprintTransition $transition, array $recordData): array
    {
        $conditions = $transition->conditions;
        $failed = [];

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $recordData)) {
                $field = $condition->field;
                $fieldName = $field ? $field->label : 'Unknown field';
                $operator = BlueprintTransitionCondition::getOperators()[$condition->operator] ?? $condition->operator;
                $failed[] = "{$fieldName} {$operator} {$condition->value}";
            }
        }

        return $failed;
    }

    /**
     * Get available operators for the UI.
     */
    public function getOperators(): array
    {
        return BlueprintTransitionCondition::getOperators();
    }

    /**
     * Get operators with metadata (for UI).
     */
    public function getOperatorsWithMetadata(): array
    {
        return ConditionEvaluationService::getOperatorsWithMetadata();
    }

    /**
     * Get operators for a specific field type.
     */
    public function getOperatorsForFieldType(string $fieldType): array
    {
        return ConditionEvaluationService::getOperatorsForFieldType($fieldType);
    }
}
