<?php

declare(strict_types=1);

namespace App\Services\TimeMachine;

use App\Models\Field;
use App\Models\Module;

class DiffService
{
    public function __construct(
        protected RecordHistoryService $historyService
    ) {}

    /**
     * Calculate diff between two timestamps.
     */
    public function getDiff(
        int $moduleId,
        int $recordId,
        string $fromTimestamp,
        string $toTimestamp
    ): array {
        $fromState = $this->historyService->getRecordAtTimestamp($moduleId, $recordId, $fromTimestamp);
        $toState = $this->historyService->getRecordAtTimestamp($moduleId, $recordId, $toTimestamp);

        if (!$fromState || !$toState) {
            return [
                'error' => 'Could not retrieve state at one or both timestamps',
                'from_state' => $fromState,
                'to_state' => $toState,
            ];
        }

        $module = Module::with('fields')->find($moduleId);
        $fieldsByApiName = $module->fields->keyBy('api_name');

        $diff = $this->calculateDiff($fromState, $toState, $fieldsByApiName);

        return [
            'from_timestamp' => $fromTimestamp,
            'to_timestamp' => $toTimestamp,
            'from_state' => $fromState,
            'to_state' => $toState,
            'changes' => $diff['changes'],
            'summary' => $diff['summary'],
        ];
    }

    /**
     * Calculate the differences between two states.
     */
    protected function calculateDiff(array $fromState, array $toState, $fields): array
    {
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($fromState), array_keys($toState)));

        foreach ($allKeys as $key) {
            $fromValue = $fromState[$key] ?? null;
            $toValue = $toState[$key] ?? null;

            if ($this->hasChanged($fromValue, $toValue)) {
                $field = $fields[$key] ?? null;
                $changes[$key] = [
                    'field_api_name' => $key,
                    'field_label' => $field?->label ?? ucfirst(str_replace('_', ' ', $key)),
                    'field_type' => $field?->type ?? 'unknown',
                    'from_value' => $fromValue,
                    'to_value' => $toValue,
                    'change_type' => $this->getChangeType($fromValue, $toValue),
                    'formatted_change' => $this->formatChange($fromValue, $toValue, $field),
                ];
            }
        }

        return [
            'changes' => $changes,
            'summary' => $this->generateSummary($changes),
        ];
    }

    /**
     * Check if a value has changed.
     */
    protected function hasChanged($fromValue, $toValue): bool
    {
        if ($fromValue === null && $toValue === null) {
            return false;
        }
        if ($fromValue === null || $toValue === null) {
            return true;
        }

        if (is_array($fromValue) && is_array($toValue)) {
            return json_encode($fromValue) !== json_encode($toValue);
        }

        if (is_numeric($fromValue) && is_numeric($toValue)) {
            return (float) $fromValue !== (float) $toValue;
        }

        return $fromValue !== $toValue;
    }

    /**
     * Get the type of change.
     */
    protected function getChangeType($fromValue, $toValue): string
    {
        if ($fromValue === null && $toValue !== null) {
            return 'added';
        }
        if ($fromValue !== null && $toValue === null) {
            return 'removed';
        }
        return 'modified';
    }

    /**
     * Format the change for display.
     */
    protected function formatChange($fromValue, $toValue, ?Field $field): array
    {
        $formatted = [
            'from_display' => $this->formatValue($fromValue, $field),
            'to_display' => $this->formatValue($toValue, $field),
        ];

        // Add numeric change info if applicable
        if ($field && in_array($field->type, ['number', 'currency', 'percent', 'integer'])) {
            if (is_numeric($fromValue) && is_numeric($toValue)) {
                $diff = (float) $toValue - (float) $fromValue;
                $formatted['numeric_change'] = $diff;
                $formatted['percentage_change'] = $fromValue != 0
                    ? round(($diff / abs($fromValue)) * 100, 2)
                    : null;
            }
        }

        return $formatted;
    }

    /**
     * Format a value for display.
     */
    protected function formatValue($value, ?Field $field): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($field) {
            return match ($field->type) {
                'currency' => '$' . number_format((float) $value, 2),
                'percent' => number_format((float) $value, 1) . '%',
                'date' => $value, // Could format with Carbon if needed
                'boolean', 'switch' => $value ? 'Yes' : 'No',
                default => (string) $value,
            };
        }

        return (string) $value;
    }

    /**
     * Generate a summary of changes.
     */
    protected function generateSummary(array $changes): array
    {
        $totalChanges = count($changes);
        $changeTypes = [
            'added' => 0,
            'modified' => 0,
            'removed' => 0,
        ];

        $significantChanges = [];

        foreach ($changes as $key => $change) {
            $changeTypes[$change['change_type']]++;

            // Track significant changes (stage, amount, owner, etc.)
            if (in_array($key, ['stage', 'stage_id', 'amount', 'owner_id', 'probability', 'status'])) {
                $significantChanges[] = [
                    'field' => $change['field_label'],
                    'from' => $change['formatted_change']['from_display'],
                    'to' => $change['formatted_change']['to_display'],
                ];
            }
        }

        return [
            'total_fields_changed' => $totalChanges,
            'fields_added' => $changeTypes['added'],
            'fields_modified' => $changeTypes['modified'],
            'fields_removed' => $changeTypes['removed'],
            'significant_changes' => $significantChanges,
        ];
    }

    /**
     * Generate a side-by-side comparison view.
     */
    public function getSideBySideComparison(
        int $moduleId,
        int $recordId,
        string $fromTimestamp,
        string $toTimestamp
    ): array {
        $diff = $this->getDiff($moduleId, $recordId, $fromTimestamp, $toTimestamp);

        if (isset($diff['error'])) {
            return $diff;
        }

        $module = Module::with('fields')->find($moduleId);
        $fieldsByApiName = $module->fields->keyBy('api_name');

        $comparison = [];
        $allKeys = array_unique(array_merge(
            array_keys($diff['from_state']),
            array_keys($diff['to_state'])
        ));

        foreach ($allKeys as $key) {
            $field = $fieldsByApiName[$key] ?? null;
            $fromValue = $diff['from_state'][$key] ?? null;
            $toValue = $diff['to_state'][$key] ?? null;
            $hasChanged = isset($diff['changes'][$key]);

            $comparison[] = [
                'field_api_name' => $key,
                'field_label' => $field?->label ?? ucfirst(str_replace('_', ' ', $key)),
                'from_value' => $fromValue,
                'to_value' => $toValue,
                'from_display' => $this->formatValue($fromValue, $field),
                'to_display' => $this->formatValue($toValue, $field),
                'has_changed' => $hasChanged,
                'change_type' => $hasChanged ? $diff['changes'][$key]['change_type'] : null,
            ];
        }

        return [
            'from_timestamp' => $fromTimestamp,
            'to_timestamp' => $toTimestamp,
            'comparison' => $comparison,
            'summary' => $diff['summary'],
        ];
    }
}
