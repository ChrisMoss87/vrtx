<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Services;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Entities\BlueprintRecordState;
use App\Domain\Workflow\Services\ConditionEvaluationService;

/**
 * Domain service for validating blueprint transitions.
 */
class TransitionValidationService
{
    public function __construct(
        private readonly ConditionEvaluationService $conditionEvaluator,
    ) {}

    /**
     * Check if a transition can be executed for a record.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public function canExecuteTransition(
        Blueprint $blueprint,
        BlueprintTransition $transition,
        ?BlueprintRecordState $recordState,
        array $recordData,
    ): array {
        $errors = [];

        // Check if transition is active
        if (!$transition->isActive()) {
            $errors[] = 'This transition is not active';
        }

        // Check if blueprint is active
        if (!$blueprint->isActive()) {
            $errors[] = 'This blueprint is not active';
        }

        // Check if record is in the correct state
        $currentStateId = $recordState?->getCurrentStateId();
        if ($transition->getFromStateId() !== $currentStateId) {
            $fromState = $blueprint->getStateById($transition->getFromStateId() ?? 0);
            $currentState = $currentStateId ? $blueprint->getStateById($currentStateId) : null;

            $errors[] = sprintf(
                'Record is in state "%s" but transition requires state "%s"',
                $currentState?->getName() ?? 'Initial',
                $fromState?->getName() ?? 'Initial'
            );
        }

        // Check conditions (before-phase)
        if ($transition->hasConditions()) {
            $context = $this->buildContext($recordData, $recordState);
            $conditionsMet = $this->conditionEvaluator->evaluate(
                $transition->getConditions(),
                $context
            );

            if (!$conditionsMet) {
                $errors[] = 'Transition conditions are not met';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get available transitions for a record.
     *
     * @return array<BlueprintTransition>
     */
    public function getAvailableTransitions(
        Blueprint $blueprint,
        ?BlueprintRecordState $recordState,
        array $recordData,
    ): array {
        $currentStateId = $recordState?->getCurrentStateId();
        $transitions = $blueprint->getTransitionsFromState($currentStateId);

        $available = [];
        foreach ($transitions as $transition) {
            $result = $this->canExecuteTransition($blueprint, $transition, $recordState, $recordData);
            if ($result['valid']) {
                $available[] = $transition;
            }
        }

        return $available;
    }

    /**
     * Build context for condition evaluation.
     */
    private function buildContext(array $recordData, ?BlueprintRecordState $recordState): array
    {
        return [
            'record' => $recordData,
            'record_id' => $recordData['id'] ?? null,
            'module_id' => $recordData['module_id'] ?? null,
            'current_state_id' => $recordState?->getCurrentStateId(),
            'state_entered_at' => $recordState?->getEnteredStateAt()?->format('c'),
            'hours_in_state' => $recordState?->getHoursInCurrentState() ?? 0,
        ];
    }
}
