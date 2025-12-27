<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Domain\User\Entities\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Action to create a task linked to the triggering record.
 * Supports relative due dates and dynamic assignment.
 */
class CreateTaskAction implements ActionInterface
{
    public const DUE_DATE_SPECIFIC = 'specific';
    public const DUE_DATE_RELATIVE = 'relative';
    public const DUE_DATE_FROM_FIELD = 'from_field';

    public const ASSIGN_TO_SPECIFIC = 'specific_user';
    public const ASSIGN_TO_OWNER = 'record_owner';
    public const ASSIGN_TO_TRIGGERED_BY = 'triggered_by';
    public const ASSIGN_TO_FIELD = 'field_value';

    public function execute(array $config, array $context): array
    {
        // Find tasks module
        $taskModule = DB::table('modules')->where('api_name', 'tasks')
            ->orWhere('api_name', 'task')
            ->first();

        if (!$taskModule) {
            throw new \RuntimeException('Tasks module not found');
        }

        $dueDate = $this->resolveDueDate($config, $context);
        $assignedTo = $this->resolveAssignedTo($config, $context);
        $subject = $this->interpolate($config['subject'] ?? 'New Task', $context);
        $description = $this->interpolate($config['description'] ?? '', $context);
        $priority = $config['priority'] ?? 'normal';
        $status = $config['status'] ?? 'pending';

        $taskData = [
            'subject' => $subject,
            'description' => $description,
            'due_date' => $dueDate?->format('Y-m-d H:i:s'),
            'priority' => $priority,
            'status' => $status,
            'owner_id' => $assignedTo,
            // Link to the triggering record
            'related_to_type' => $context['module']['api_name'] ?? null,
            'related_to_id' => $context['record']['id'] ?? null,
        ];

        // Add any additional custom fields
        if (!empty($config['additional_fields'])) {
            foreach ($config['additional_fields'] as $field => $value) {
                $taskData[$field] = $this->interpolate((string) $value, $context);
            }
        }

        $userId = $context['triggered_by'] ?? $assignedTo;

        $task = DB::table('module_records')->insertGetId([
            'module_id' => $taskModule->id,
            'data' => $taskData,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        Log::info('Workflow created task', [
            'task_id' => $task->id,
            'subject' => $subject,
            'assigned_to' => $assignedTo,
            'due_date' => $dueDate?->format('Y-m-d H:i:s'),
        ]);

        return [
            'created' => true,
            'task_id' => $task->id,
            'subject' => $subject,
            'assigned_to' => $assignedTo,
            'due_date' => $dueDate?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Resolve the due date based on configuration.
     */
    protected function resolveDueDate(array $config, array $context): ?Carbon
    {
        $dueDateType = $config['due_date_type'] ?? self::DUE_DATE_RELATIVE;

        return match ($dueDateType) {
            self::DUE_DATE_SPECIFIC => $this->resolveSpecificDate($config),
            self::DUE_DATE_RELATIVE => $this->resolveRelativeDate($config),
            self::DUE_DATE_FROM_FIELD => $this->resolveDateFromField($config, $context),
            default => now()->addDays(1),
        };
    }

    /**
     * Resolve specific date.
     */
    protected function resolveSpecificDate(array $config): ?Carbon
    {
        $date = $config['due_date'] ?? null;
        return $date ? Carbon::parse($date) : null;
    }

    /**
     * Resolve relative date (e.g., 3 days from now).
     */
    protected function resolveRelativeDate(array $config): Carbon
    {
        $offset = $config['due_date_offset'] ?? 1;
        $unit = $config['due_date_unit'] ?? 'days';
        $setTime = $config['due_time'] ?? null;

        $date = now();

        $date = match ($unit) {
            'hours' => $date->addHours($offset),
            'days' => $date->addDays($offset),
            'weeks' => $date->addWeeks($offset),
            'months' => $date->addMonths($offset),
            default => $date->addDays($offset),
        };

        // Optionally set specific time
        if ($setTime) {
            $timeParts = explode(':', $setTime);
            $date->setTime((int) ($timeParts[0] ?? 9), (int) ($timeParts[1] ?? 0));
        }

        return $date;
    }

    /**
     * Resolve due date from a record field.
     */
    protected function resolveDateFromField(array $config, array $context): ?Carbon
    {
        $field = $config['due_date_field'] ?? null;
        $offset = $config['due_date_offset'] ?? 0;
        $unit = $config['due_date_unit'] ?? 'days';

        if (!$field) {
            return null;
        }

        $dateValue = $context['record']['data'][$field] ?? null;
        if (!$dateValue) {
            return null;
        }

        $date = Carbon::parse($dateValue);

        // Apply offset if any
        if ($offset !== 0) {
            $date = match ($unit) {
                'hours' => $date->addHours($offset),
                'days' => $date->addDays($offset),
                'weeks' => $date->addWeeks($offset),
                'months' => $date->addMonths($offset),
                default => $date->addDays($offset),
            };
        }

        return $date;
    }

    /**
     * Resolve who the task should be assigned to.
     */
    protected function resolveAssignedTo(array $config, array $context): ?int
    {
        $assignType = $config['assign_to_type'] ?? self::ASSIGN_TO_OWNER;

        return match ($assignType) {
            self::ASSIGN_TO_SPECIFIC => $config['assign_to_user_id'] ?? null,
            self::ASSIGN_TO_OWNER => $context['record']['data']['owner_id'] ?? null,
            self::ASSIGN_TO_TRIGGERED_BY => $context['triggered_by'] ?? null,
            self::ASSIGN_TO_FIELD => $this->resolveAssignedToFromField($config, $context),
            default => $context['triggered_by'] ?? null,
        };
    }

    /**
     * Resolve assigned user from a record field.
     */
    protected function resolveAssignedToFromField(array $config, array $context): ?int
    {
        $field = $config['assign_to_field'] ?? null;
        if (!$field) {
            return null;
        }

        $value = $context['record']['data'][$field] ?? null;
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Interpolate variables in a string.
     */
    protected function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
            $path = trim($matches[1]);
            return $this->getValueByPath($context, $path) ?? $matches[0];
        }, $template);
    }

    /**
     * Get a value from context by dot-notation path.
     */
    protected function getValueByPath(array $context, string $path): ?string
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'subject',
                    'label' => 'Task Subject',
                    'type' => 'text',
                    'required' => true,
                    'supports_variables' => true,
                    'description' => 'Use {{record.field_name}} for dynamic values',
                ],
                [
                    'name' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'required' => false,
                    'supports_variables' => true,
                ],
                [
                    'name' => 'priority',
                    'label' => 'Priority',
                    'type' => 'select',
                    'required' => false,
                    'default' => 'normal',
                    'options' => [
                        ['value' => 'low', 'label' => 'Low'],
                        ['value' => 'normal', 'label' => 'Normal'],
                        ['value' => 'high', 'label' => 'High'],
                        ['value' => 'urgent', 'label' => 'Urgent'],
                    ],
                ],
                [
                    'name' => 'status',
                    'label' => 'Initial Status',
                    'type' => 'select',
                    'required' => false,
                    'default' => 'pending',
                    'options' => [
                        ['value' => 'pending', 'label' => 'Pending'],
                        ['value' => 'in_progress', 'label' => 'In Progress'],
                        ['value' => 'completed', 'label' => 'Completed'],
                    ],
                ],
                [
                    'name' => 'due_date_type',
                    'label' => 'Due Date Type',
                    'type' => 'select',
                    'required' => true,
                    'default' => self::DUE_DATE_RELATIVE,
                    'options' => [
                        ['value' => self::DUE_DATE_RELATIVE, 'label' => 'Relative (X days from now)'],
                        ['value' => self::DUE_DATE_SPECIFIC, 'label' => 'Specific Date'],
                        ['value' => self::DUE_DATE_FROM_FIELD, 'label' => 'From Record Field'],
                    ],
                ],
                [
                    'name' => 'due_date_offset',
                    'label' => 'Offset Amount',
                    'type' => 'number',
                    'required' => false,
                    'default' => 1,
                    'description' => 'Number of time units to add (can be negative)',
                    'show_when' => ['due_date_type' => [self::DUE_DATE_RELATIVE, self::DUE_DATE_FROM_FIELD]],
                ],
                [
                    'name' => 'due_date_unit',
                    'label' => 'Time Unit',
                    'type' => 'select',
                    'required' => false,
                    'default' => 'days',
                    'options' => [
                        ['value' => 'hours', 'label' => 'Hours'],
                        ['value' => 'days', 'label' => 'Days'],
                        ['value' => 'weeks', 'label' => 'Weeks'],
                        ['value' => 'months', 'label' => 'Months'],
                    ],
                    'show_when' => ['due_date_type' => [self::DUE_DATE_RELATIVE, self::DUE_DATE_FROM_FIELD]],
                ],
                [
                    'name' => 'due_time',
                    'label' => 'Due Time',
                    'type' => 'time',
                    'required' => false,
                    'description' => 'Optional: Set specific time (e.g., 09:00)',
                    'show_when' => ['due_date_type' => self::DUE_DATE_RELATIVE],
                ],
                [
                    'name' => 'due_date',
                    'label' => 'Due Date',
                    'type' => 'datetime',
                    'required' => false,
                    'show_when' => ['due_date_type' => self::DUE_DATE_SPECIFIC],
                ],
                [
                    'name' => 'due_date_field',
                    'label' => 'Date Field',
                    'type' => 'field_select',
                    'required' => false,
                    'description' => 'Select date field to base due date on',
                    'show_when' => ['due_date_type' => self::DUE_DATE_FROM_FIELD],
                ],
                [
                    'name' => 'assign_to_type',
                    'label' => 'Assign To',
                    'type' => 'select',
                    'required' => true,
                    'default' => self::ASSIGN_TO_OWNER,
                    'options' => [
                        ['value' => self::ASSIGN_TO_OWNER, 'label' => 'Record Owner'],
                        ['value' => self::ASSIGN_TO_TRIGGERED_BY, 'label' => 'User Who Triggered Workflow'],
                        ['value' => self::ASSIGN_TO_SPECIFIC, 'label' => 'Specific User'],
                        ['value' => self::ASSIGN_TO_FIELD, 'label' => 'From Record Field'],
                    ],
                ],
                [
                    'name' => 'assign_to_user_id',
                    'label' => 'User',
                    'type' => 'user_select',
                    'required' => false,
                    'show_when' => ['assign_to_type' => self::ASSIGN_TO_SPECIFIC],
                ],
                [
                    'name' => 'assign_to_field',
                    'label' => 'User Field',
                    'type' => 'field_select',
                    'required' => false,
                    'description' => 'Select field containing user ID',
                    'show_when' => ['assign_to_type' => self::ASSIGN_TO_FIELD],
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['subject'])) {
            $errors['subject'] = 'Task subject is required';
        }

        $dueDateType = $config['due_date_type'] ?? self::DUE_DATE_RELATIVE;
        if ($dueDateType === self::DUE_DATE_FROM_FIELD && empty($config['due_date_field'])) {
            $errors['due_date_field'] = 'Date field is required when using "From Record Field"';
        }

        $assignType = $config['assign_to_type'] ?? self::ASSIGN_TO_OWNER;
        if ($assignType === self::ASSIGN_TO_SPECIFIC && empty($config['assign_to_user_id'])) {
            $errors['assign_to_user_id'] = 'User is required when assigning to specific user';
        }
        if ($assignType === self::ASSIGN_TO_FIELD && empty($config['assign_to_field'])) {
            $errors['assign_to_field'] = 'User field is required';
        }

        return $errors;
    }
}
