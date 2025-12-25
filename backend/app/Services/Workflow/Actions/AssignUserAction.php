<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Action to assign a record to a user.
 * Supports direct assignment, round-robin, and role-based assignment.
 */
class AssignUserAction implements ActionInterface
{
    public const MODE_SPECIFIC_USER = 'specific_user';
    public const MODE_ROUND_ROBIN = 'round_robin';
    public const MODE_ROLE_BASED = 'role_based';
    public const MODE_RECORD_OWNER = 'record_owner';
    public const MODE_FIELD_VALUE = 'field_value';

    public function execute(array $config, array $context): array
    {
        $recordId = $context['record']['id'] ?? null;
        $field = $config['field'] ?? 'owner_id';
        $mode = $config['mode'] ?? self::MODE_SPECIFIC_USER;

        if (!$recordId) {
            throw new \InvalidArgumentException('Record ID is required');
        }

        $record = DB::table('module_records')->where('id', $recordId)->first();
        if (!$record) {
            throw new \InvalidArgumentException("Record not found: {$recordId}");
        }

        $userId = $this->resolveUserId($config, $context, $record);

        if (!$userId) {
            throw new \RuntimeException('Could not determine user to assign');
        }

        $data = json_decode($record->data, true);
        $previousOwner = $data[$field] ?? null;
        $data[$field] = $userId;

        DB::table('module_records')
            ->where('id', $recordId)
            ->update([
                'data' => json_encode($data),
                'updated_at' => now(),
            ]);

        return [
            'assigned' => true,
            'user_id' => $userId,
            'field' => $field,
            'mode' => $mode,
            'previous_owner' => $previousOwner,
        ];
    }

    /**
     * Resolve the user ID based on assignment mode.
     */
    protected function resolveUserId(array $config, array $context, object $record): ?int
    {
        $mode = $config['mode'] ?? self::MODE_SPECIFIC_USER;

        return match ($mode) {
            self::MODE_SPECIFIC_USER => $this->resolveSpecificUser($config),
            self::MODE_ROUND_ROBIN => $this->resolveRoundRobin($config, $record->module_id),
            self::MODE_ROLE_BASED => $this->resolveRoleBased($config),
            self::MODE_RECORD_OWNER => $this->resolveRecordOwner($context),
            self::MODE_FIELD_VALUE => $this->resolveFieldValue($config, $context),
            default => throw new \InvalidArgumentException("Unknown assignment mode: {$mode}"),
        };
    }

    /**
     * Resolve specific user assignment.
     */
    protected function resolveSpecificUser(array $config): ?int
    {
        return $config['user_id'] ?? null;
    }

    /**
     * Resolve round-robin assignment among team members.
     */
    protected function resolveRoundRobin(array $config, int $moduleId): ?int
    {
        $userIds = $config['team_user_ids'] ?? [];
        $roleId = $config['team_role_id'] ?? null;

        // If role is specified, get users from that role
        if ($roleId) {
            $userIds = DB::table('users')
                ->join('role_user', 'users.id', '=', 'role_user.user_id')
                ->where('role_user.role_id', $roleId)
                ->where('users.is_active', true)
                ->pluck('users.id')
                ->toArray();
        }

        if (empty($userIds)) {
            return null;
        }

        // Use cache to track round-robin state per module
        $cacheKey = "workflow_roundrobin_{$moduleId}_" . md5(serialize($userIds));

        return Cache::lock("{$cacheKey}_lock", 10)->block(5, function () use ($cacheKey, $userIds) {
            $lastIndex = Cache::get($cacheKey, -1);
            $nextIndex = ($lastIndex + 1) % count($userIds);
            Cache::put($cacheKey, $nextIndex, now()->addDays(30));

            return $userIds[$nextIndex];
        });
    }

    /**
     * Resolve role-based assignment (least loaded user in role).
     */
    protected function resolveRoleBased(array $config): ?int
    {
        $roleId = $config['role_id'] ?? null;
        $strategy = $config['load_balancing'] ?? 'least_records';

        if (!$roleId) {
            return null;
        }

        $users = DB::table('users')
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->where('role_user.role_id', $roleId)
            ->where('users.is_active', true)
            ->select('users.id')
            ->get();

        if ($users->isEmpty()) {
            return null;
        }

        return match ($strategy) {
            'least_records' => $this->findLeastLoadedUser($users),
            'random' => $users->random()->id,
            default => $users->first()->id,
        };
    }

    /**
     * Find user with least assigned records.
     */
    protected function findLeastLoadedUser($users): ?int
    {
        $userIds = $users->pluck('id')->toArray();

        // Count records per user
        $counts = DB::table('module_records')
            ->select(DB::raw("JSON_EXTRACT(data, '$.owner_id') as owner_id"), DB::raw('COUNT(*) as count'))
            ->whereIn(DB::raw("JSON_EXTRACT(data, '$.owner_id')"), $userIds)
            ->groupBy(DB::raw("JSON_EXTRACT(data, '$.owner_id')"))
            ->pluck('count', 'owner_id')
            ->toArray();

        // Find user with minimum count (default to 0 for users with no records)
        $minCount = PHP_INT_MAX;
        $selectedUserId = null;

        foreach ($users as $user) {
            $count = $counts[$user->id] ?? 0;
            if ($count < $minCount) {
                $minCount = $count;
                $selectedUserId = $user->id;
            }
        }

        return $selectedUserId;
    }

    /**
     * Resolve from record owner (for related records).
     */
    protected function resolveRecordOwner(array $context): ?int
    {
        return $context['record']['data']['owner_id'] ?? null;
    }

    /**
     * Resolve from a specific field value.
     */
    protected function resolveFieldValue(array $config, array $context): ?int
    {
        $sourceField = $config['source_field'] ?? null;
        if (!$sourceField) {
            return null;
        }

        $value = $context['record']['data'][$sourceField] ?? null;

        // If value is a user ID, return it
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'mode',
                    'label' => 'Assignment Mode',
                    'type' => 'select',
                    'required' => true,
                    'default' => self::MODE_SPECIFIC_USER,
                    'options' => [
                        ['value' => self::MODE_SPECIFIC_USER, 'label' => 'Specific User'],
                        ['value' => self::MODE_ROUND_ROBIN, 'label' => 'Round Robin'],
                        ['value' => self::MODE_ROLE_BASED, 'label' => 'Role-Based (Least Loaded)'],
                        ['value' => self::MODE_RECORD_OWNER, 'label' => 'Current Record Owner'],
                        ['value' => self::MODE_FIELD_VALUE, 'label' => 'From Field Value'],
                    ],
                ],
                [
                    'name' => 'user_id',
                    'label' => 'Assign To',
                    'type' => 'user_select',
                    'required' => false,
                    'show_when' => ['mode' => self::MODE_SPECIFIC_USER],
                ],
                [
                    'name' => 'team_user_ids',
                    'label' => 'Team Members',
                    'type' => 'user_multiselect',
                    'required' => false,
                    'description' => 'Select users to rotate between',
                    'show_when' => ['mode' => self::MODE_ROUND_ROBIN],
                ],
                [
                    'name' => 'team_role_id',
                    'label' => 'Or Select Role',
                    'type' => 'role_select',
                    'required' => false,
                    'description' => 'Rotate among all users in this role',
                    'show_when' => ['mode' => self::MODE_ROUND_ROBIN],
                ],
                [
                    'name' => 'role_id',
                    'label' => 'Role',
                    'type' => 'role_select',
                    'required' => false,
                    'show_when' => ['mode' => self::MODE_ROLE_BASED],
                ],
                [
                    'name' => 'load_balancing',
                    'label' => 'Selection Strategy',
                    'type' => 'select',
                    'required' => false,
                    'default' => 'least_records',
                    'options' => [
                        ['value' => 'least_records', 'label' => 'Least Assigned Records'],
                        ['value' => 'random', 'label' => 'Random'],
                    ],
                    'show_when' => ['mode' => self::MODE_ROLE_BASED],
                ],
                [
                    'name' => 'source_field',
                    'label' => 'Source Field',
                    'type' => 'field_select',
                    'required' => false,
                    'description' => 'Field containing user ID',
                    'show_when' => ['mode' => self::MODE_FIELD_VALUE],
                ],
                [
                    'name' => 'field',
                    'label' => 'Target Field',
                    'type' => 'text',
                    'required' => false,
                    'default' => 'owner_id',
                    'description' => 'Field to store the assigned user ID',
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];
        $mode = $config['mode'] ?? self::MODE_SPECIFIC_USER;

        if ($mode === self::MODE_SPECIFIC_USER && empty($config['user_id'])) {
            $errors['user_id'] = 'User is required for specific user assignment';
        }

        if ($mode === self::MODE_ROUND_ROBIN) {
            if (empty($config['team_user_ids']) && empty($config['team_role_id'])) {
                $errors['team_user_ids'] = 'Select team members or a role for round robin';
            }
        }

        if ($mode === self::MODE_ROLE_BASED && empty($config['role_id'])) {
            $errors['role_id'] = 'Role is required for role-based assignment';
        }

        if ($mode === self::MODE_FIELD_VALUE && empty($config['source_field'])) {
            $errors['source_field'] = 'Source field is required';
        }

        return $errors;
    }
}
