<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Entities\Workflow;
use App\Domain\Workflow\ValueObjects\FieldChangeType;
use App\Domain\Workflow\ValueObjects\TriggerType;

/**
 * Domain service for evaluating workflow trigger conditions.
 *
 * This service determines whether a workflow should be triggered
 * based on the event type and field change conditions.
 */
class WorkflowTriggerEvaluatorService
{
    /**
     * Evaluate if a workflow should trigger for the given event.
     *
     * @param Workflow $workflow The workflow to evaluate
     * @param string $eventType The event type that occurred
     * @param array<string, mixed>|null $recordData Current record data
     * @param array<string, mixed>|null $oldData Previous record data
     * @param bool $isCreate Whether this is a create operation
     */
    public function shouldTrigger(
        Workflow $workflow,
        string $eventType,
        ?array $recordData = null,
        ?array $oldData = null,
        bool $isCreate = false,
    ): bool {
        if (!$workflow->isActive()) {
            return false;
        }

        if (!$workflow->canExecuteToday()) {
            return false;
        }

        if (!$workflow->triggerTiming()->matches($isCreate)) {
            return false;
        }

        if (!$workflow->triggerType()->matchesEvent($eventType)) {
            return false;
        }

        // For field_changed triggers, also check field conditions
        if ($workflow->triggerType() === TriggerType::FIELD_CHANGED) {
            return $this->checkFieldChangedCondition($workflow, $recordData, $oldData);
        }

        return true;
    }

    /**
     * Check if the configured field has changed according to the change type.
     *
     * @param Workflow $workflow The workflow with field change configuration
     * @param array<string, mixed>|null $newData New record data
     * @param array<string, mixed>|null $oldData Old record data
     */
    public function checkFieldChangedCondition(
        Workflow $workflow,
        ?array $newData,
        ?array $oldData,
    ): bool {
        $config = $workflow->triggerConfig();
        $watchedFields = $workflow->watchedFields();

        // Fall back to config fields if watched_fields is empty
        if (empty($watchedFields)) {
            $watchedFields = $config->fields();
        }

        if (empty($watchedFields) || $newData === null || $oldData === null) {
            return false;
        }

        $changeType = $config->changeType();
        $fromValue = $config->fromValue();
        $toValue = $config->toValue();

        foreach ($watchedFields as $field) {
            $oldValue = $this->getNestedValue($oldData, $field);
            $newValue = $this->getNestedValue($newData, $field);

            // Check if value actually changed
            if ($oldValue === $newValue) {
                continue;
            }

            // Check based on change type
            $matches = match ($changeType) {
                FieldChangeType::ANY => true,
                FieldChangeType::FROM_VALUE => $this->compareValues($oldValue, $fromValue),
                FieldChangeType::TO_VALUE => $this->compareValues($newValue, $toValue),
                FieldChangeType::FROM_TO => $this->compareValues($oldValue, $fromValue)
                    && $this->compareValues($newValue, $toValue),
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the fields that changed between old and new data.
     *
     * @param array<string, mixed>|null $newData New record data
     * @param array<string, mixed>|null $oldData Old record data
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChangedFields(?array $newData, ?array $oldData): array
    {
        if ($newData === null || $oldData === null) {
            return [];
        }

        $changed = [];
        $allKeys = array_unique(array_merge(array_keys($newData), array_keys($oldData)));

        foreach ($allKeys as $key) {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }

    /**
     * Get nested value from array using dot notation.
     *
     * @param array<string, mixed> $data The data array
     * @param string $path Dot-notation path to the value
     */
    private function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Compare two values with type coercion.
     */
    private function compareValues(mixed $actual, mixed $expected): bool
    {
        // Null check
        if ($expected === null) {
            return $actual === null;
        }

        // String comparison (case-insensitive)
        if (is_string($actual) && is_string($expected)) {
            return strtolower($actual) === strtolower($expected);
        }

        // Numeric comparison
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        // Boolean comparison
        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        // Default strict comparison
        return $actual === $expected;
    }
}
