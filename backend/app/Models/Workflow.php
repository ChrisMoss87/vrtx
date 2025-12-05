<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use HasFactory, SoftDeletes;

    // Trigger types
    public const TRIGGER_RECORD_CREATED = 'record_created';
    public const TRIGGER_RECORD_UPDATED = 'record_updated';
    public const TRIGGER_RECORD_DELETED = 'record_deleted';
    public const TRIGGER_FIELD_CHANGED = 'field_changed';
    public const TRIGGER_TIME_BASED = 'time_based';
    public const TRIGGER_WEBHOOK = 'webhook';
    public const TRIGGER_MANUAL = 'manual';

    // New trigger types
    public const TRIGGER_RECORD_SAVED = 'record_saved'; // Both create and update
    public const TRIGGER_RELATED_CREATED = 'related_created'; // When a related record is created
    public const TRIGGER_RELATED_UPDATED = 'related_updated'; // When a related record is updated
    public const TRIGGER_RECORD_CONVERTED = 'record_converted'; // When a record is converted (e.g., Lead → Contact)

    // Trigger timing options
    public const TIMING_ALL = 'all';
    public const TIMING_CREATE_ONLY = 'create_only';
    public const TIMING_UPDATE_ONLY = 'update_only';

    // Field change types
    public const CHANGE_TYPE_ANY = 'any';
    public const CHANGE_TYPE_FROM_VALUE = 'from_value';
    public const CHANGE_TYPE_TO_VALUE = 'to_value';
    public const CHANGE_TYPE_FROM_TO = 'from_to';

    protected $fillable = [
        'name',
        'description',
        'module_id',
        'is_active',
        'priority',
        'trigger_type',
        'trigger_config',
        'trigger_timing',
        'watched_fields',
        'webhook_secret',
        'stop_on_first_match',
        'max_executions_per_day',
        'executions_today',
        'executions_today_date',
        'conditions',
        'run_once_per_record',
        'allow_manual_trigger',
        'delay_seconds',
        'schedule_cron',
        'last_run_at',
        'next_run_at',
        'execution_count',
        'success_count',
        'failure_count',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'trigger_config' => 'array',
        'watched_fields' => 'array',
        'stop_on_first_match' => 'boolean',
        'max_executions_per_day' => 'integer',
        'executions_today' => 'integer',
        'executions_today_date' => 'date',
        'conditions' => 'array',
        'run_once_per_record' => 'boolean',
        'allow_manual_trigger' => 'boolean',
        'delay_seconds' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'execution_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $attributes = [
        'is_active' => false,
        'priority' => 0,
        'trigger_config' => '{}',
        'trigger_timing' => 'all',
        'watched_fields' => '[]',
        'stop_on_first_match' => false,
        'executions_today' => 0,
        'conditions' => '[]',
        'run_once_per_record' => false,
        'allow_manual_trigger' => true,
        'delay_seconds' => 0,
        'execution_count' => 0,
        'success_count' => 0,
        'failure_count' => 0,
    ];

    /**
     * Get available trigger types.
     */
    public static function getTriggerTypes(): array
    {
        return [
            self::TRIGGER_RECORD_CREATED => [
                'label' => 'When a record is created',
                'description' => 'Triggers when a new record is created in the module',
                'category' => 'record',
            ],
            self::TRIGGER_RECORD_UPDATED => [
                'label' => 'When a record is updated',
                'description' => 'Triggers when an existing record is modified',
                'category' => 'record',
            ],
            self::TRIGGER_RECORD_SAVED => [
                'label' => 'When a record is saved (create or update)',
                'description' => 'Triggers on both record creation and updates',
                'category' => 'record',
            ],
            self::TRIGGER_RECORD_DELETED => [
                'label' => 'When a record is deleted',
                'description' => 'Triggers when a record is removed',
                'category' => 'record',
            ],
            self::TRIGGER_FIELD_CHANGED => [
                'label' => 'When a field value changes',
                'description' => 'Triggers when specific field(s) change value',
                'category' => 'field',
                'requires_config' => true,
            ],
            self::TRIGGER_RELATED_CREATED => [
                'label' => 'When a related record is created',
                'description' => 'Triggers when a related record is created (e.g., new task on a deal)',
                'category' => 'related',
                'requires_config' => true,
            ],
            self::TRIGGER_RELATED_UPDATED => [
                'label' => 'When a related record is updated',
                'description' => 'Triggers when a related record is modified',
                'category' => 'related',
                'requires_config' => true,
            ],
            self::TRIGGER_RECORD_CONVERTED => [
                'label' => 'When a record is converted',
                'description' => 'Triggers when a record is converted (e.g., Lead to Contact)',
                'category' => 'record',
            ],
            self::TRIGGER_TIME_BASED => [
                'label' => 'On a schedule',
                'description' => 'Triggers at scheduled times (cron or relative to field date)',
                'category' => 'scheduled',
                'requires_config' => true,
            ],
            self::TRIGGER_WEBHOOK => [
                'label' => 'When a webhook is received',
                'description' => 'Triggers when an external system sends data via webhook',
                'category' => 'external',
            ],
            self::TRIGGER_MANUAL => [
                'label' => 'Manual trigger only',
                'description' => 'Only runs when manually triggered by a user',
                'category' => 'manual',
            ],
        ];
    }

    /**
     * Get trigger timing options.
     */
    public static function getTriggerTimingOptions(): array
    {
        return [
            self::TIMING_ALL => 'On create and update',
            self::TIMING_CREATE_ONLY => 'Only on create',
            self::TIMING_UPDATE_ONLY => 'Only on update',
        ];
    }

    /**
     * Get field change type options.
     */
    public static function getFieldChangeTypes(): array
    {
        return [
            self::CHANGE_TYPE_ANY => [
                'label' => 'Any change',
                'description' => 'Triggers when the field value changes to anything',
            ],
            self::CHANGE_TYPE_FROM_VALUE => [
                'label' => 'Changes from specific value',
                'description' => 'Triggers when the field changes FROM a specific value',
            ],
            self::CHANGE_TYPE_TO_VALUE => [
                'label' => 'Changes to specific value',
                'description' => 'Triggers when the field changes TO a specific value',
            ],
            self::CHANGE_TYPE_FROM_TO => [
                'label' => 'Changes from X to Y',
                'description' => 'Triggers when the field changes from one specific value to another',
            ],
        ];
    }

    /**
     * Get the module this workflow belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the steps for this workflow.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('order');
    }

    /**
     * Get the executions for this workflow.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /**
     * Get the run history for this workflow.
     */
    public function runHistory(): HasMany
    {
        return $this->hasMany(WorkflowRunHistory::class);
    }

    /**
     * Check if this workflow has already run for a specific record.
     */
    public function hasRunForRecord(int $recordId, string $recordType, ?string $triggerType = null): bool
    {
        $query = $this->runHistory()
            ->where('record_id', $recordId)
            ->where('record_type', $recordType);

        if ($triggerType) {
            $query->where('trigger_type', $triggerType);
        }

        return $query->exists();
    }

    /**
     * Record that this workflow has run for a specific record.
     */
    public function recordRunForRecord(int $recordId, string $recordType, string $triggerType): void
    {
        $this->runHistory()->create([
            'record_id' => $recordId,
            'record_type' => $recordType,
            'trigger_type' => $triggerType,
            'executed_at' => now(),
        ]);
    }

    /**
     * Get the user who created this workflow.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this workflow.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include active workflows.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by module.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope a query to filter by trigger type.
     */
    public function scopeForTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Check if this workflow should run for the given record event.
     */
    public function shouldTriggerFor(string $eventType, ?array $recordData = null, ?array $oldData = null, bool $isCreate = false): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check rate limiting
        if (!$this->canExecuteToday()) {
            return false;
        }

        // Check trigger timing
        if (!$this->checkTriggerTiming($isCreate)) {
            return false;
        }

        // Direct match
        if ($this->trigger_type === $eventType) {
            return true;
        }

        // record_saved matches both create and update
        if ($this->trigger_type === self::TRIGGER_RECORD_SAVED) {
            if ($eventType === self::TRIGGER_RECORD_CREATED || $eventType === self::TRIGGER_RECORD_UPDATED) {
                return true;
            }
        }

        // field_changed should also match record_updated
        if ($this->trigger_type === self::TRIGGER_FIELD_CHANGED && $eventType === self::TRIGGER_RECORD_UPDATED) {
            return $this->checkFieldChangedCondition($recordData, $oldData);
        }

        return false;
    }

    /**
     * Check if the trigger timing matches the current operation.
     */
    protected function checkTriggerTiming(bool $isCreate): bool
    {
        $timing = $this->trigger_timing ?? self::TIMING_ALL;

        return match ($timing) {
            self::TIMING_CREATE_ONLY => $isCreate,
            self::TIMING_UPDATE_ONLY => !$isCreate,
            default => true, // TIMING_ALL
        };
    }

    /**
     * Check if the workflow can execute today (rate limiting).
     */
    public function canExecuteToday(): bool
    {
        if ($this->max_executions_per_day === null) {
            return true;
        }

        // Reset counter if it's a new day
        if ($this->executions_today_date === null || !$this->executions_today_date->isToday()) {
            return true;
        }

        return $this->executions_today < $this->max_executions_per_day;
    }

    /**
     * Increment today's execution counter.
     */
    public function incrementTodayExecutions(): void
    {
        $today = now()->toDateString();

        if ($this->executions_today_date === null || $this->executions_today_date->toDateString() !== $today) {
            $this->update([
                'executions_today' => 1,
                'executions_today_date' => $today,
            ]);
        } else {
            $this->increment('executions_today');
        }
    }

    /**
     * Check if the configured field has changed according to the change type.
     */
    public function checkFieldChangedCondition(?array $newData, ?array $oldData): bool
    {
        $config = $this->trigger_config ?? [];
        $watchedFields = $this->watched_fields ?? $config['fields'] ?? [];
        $changeType = $config['change_type'] ?? self::CHANGE_TYPE_ANY;
        $fromValue = $config['from_value'] ?? null;
        $toValue = $config['to_value'] ?? null;

        if (empty($watchedFields) || !$newData || !$oldData) {
            return false;
        }

        foreach ($watchedFields as $field) {
            $oldValue = $this->getNestedValue($oldData, $field);
            $newValue = $this->getNestedValue($newData, $field);

            // Check if value actually changed
            if ($oldValue === $newValue) {
                continue;
            }

            // Check based on change type
            $matches = match ($changeType) {
                self::CHANGE_TYPE_ANY => true,
                self::CHANGE_TYPE_FROM_VALUE => $this->compareValues($oldValue, $fromValue),
                self::CHANGE_TYPE_TO_VALUE => $this->compareValues($newValue, $toValue),
                self::CHANGE_TYPE_FROM_TO => $this->compareValues($oldValue, $fromValue) && $this->compareValues($newValue, $toValue),
                default => true,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get nested value from array using dot notation.
     */
    protected function getNestedValue(array $data, string $path): mixed
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
    protected function compareValues(mixed $actual, mixed $expected): bool
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

    /**
     * Get the fields that changed between old and new data.
     */
    public function getChangedFields(?array $newData, ?array $oldData): array
    {
        if (!$newData || !$oldData) {
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
     * Increment execution counters.
     */
    public function recordExecution(bool $success): void
    {
        $this->increment('execution_count');

        if ($success) {
            $this->increment('success_count');
        } else {
            $this->increment('failure_count');
        }

        $this->update(['last_run_at' => now()]);
    }
}
