<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Playbook;

use App\Domain\Playbook\Entities\Playbook as PlaybookEntity;
use App\Domain\Playbook\Repositories\PlaybookRepositoryInterface;
use App\Domain\Playbook\ValueObjects\AssigneeType;
use App\Domain\Playbook\ValueObjects\InstanceStatus;
use App\Domain\Playbook\ValueObjects\PlaybookStatus;
use App\Domain\Playbook\ValueObjects\TaskInstanceStatus;
use App\Domain\Playbook\ValueObjects\TaskType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbPlaybookRepository implements PlaybookRepositoryInterface
{
    private const TABLE = 'playbooks';
    private const TABLE_PHASES = 'playbook_phases';
    private const TABLE_TASKS = 'playbook_tasks';
    private const TABLE_INSTANCES = 'playbook_instances';
    private const TABLE_TASK_INSTANCES = 'playbook_task_instances';
    private const TABLE_ACTIVITIES = 'playbook_activities';
    private const TABLE_GOALS = 'playbook_goals';
    private const TABLE_GOAL_RESULTS = 'playbook_goal_results';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?PlaybookEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(PlaybookEntity $entity): PlaybookEntity
    {
        $data = $this->toRowData($entity);

        if ($entity->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
        } else {
            $id = DB::table(self::TABLE)
                ->insertGetId(array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id, array $relations = []): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Handle relations
        if (in_array('creator', $relations)) {
            $result['creator'] = $row->created_by
                ? DB::table('users')->select('id', 'name', 'email')->where('id', $row->created_by)->first()
                : null;
        }

        if (in_array('defaultOwner', $relations)) {
            $result['default_owner'] = $row->default_owner_id
                ? DB::table('users')->select('id', 'name', 'email')->where('id', $row->default_owner_id)->first()
                : null;
        }

        return $result;
    }

    // =========================================================================
    // PLAYBOOK QUERIES
    // =========================================================================

    public function findBySlug(string $slug, array $relations = []): ?array
    {
        $row = DB::table(self::TABLE)->where('slug', $slug)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Handle relations
        if (in_array('creator', $relations)) {
            $result['creator'] = $row->created_by
                ? DB::table('users')->select('id', 'name', 'email')->where('id', $row->created_by)->first()
                : null;
        }

        if (in_array('defaultOwner', $relations)) {
            $result['default_owner'] = $row->default_owner_id
                ? DB::table('users')->select('id', 'name', 'email')->where('id', $row->default_owner_id)->first()
                : null;
        }

        return $result;
    }

    public function listPlaybooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by trigger module
        if (!empty($filters['trigger_module'])) {
            $query->where('trigger_module', $filters['trigger_module']);
        }

        // Filter by creator
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'display_order';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        // Count total
        $total = $query->count();

        // Paginate
        $offset = ($page - 1) * $perPage;
        $rows = $query->offset($offset)->limit($perPage)->get();

        $items = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load relations
            if ($row->created_by) {
                $item['creator'] = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $row->created_by)
                    ->first();
            }

            if ($row->default_owner_id) {
                $item['default_owner'] = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $row->default_owner_id)
                    ->first();
            }

            $items[] = $item;
        }

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getActivePlaybooksForModule(string $module): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->where('trigger_module', $module)
            ->orderBy('display_order')
            ->get();

        return array_map(fn($row) => (array) $row, $rows->all());
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'trigger_module' => $data['trigger_module'],
            'trigger_condition' => $data['trigger_condition'] ?? null,
            'trigger_config' => isset($data['trigger_config']) ? json_encode($data['trigger_config']) : null,
            'estimated_days' => $data['estimated_days'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'auto_assign' => $data['auto_assign'] ?? false,
            'default_owner_id' => $data['default_owner_id'] ?? null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : json_encode([]),
            'display_order' => $data['display_order'] ?? 0,
            'created_by' => $data['created_by'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return (array) $row;
    }

    public function update(int $id, array $data): array
    {
        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'trigger_module' => $data['trigger_module'] ?? null,
            'trigger_condition' => $data['trigger_condition'] ?? null,
            'trigger_config' => isset($data['trigger_config']) ? json_encode($data['trigger_config']) : null,
            'estimated_days' => $data['estimated_days'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'auto_assign' => $data['auto_assign'] ?? null,
            'default_owner_id' => $data['default_owner_id'] ?? null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'display_order' => $data['display_order'] ?? null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();
            DB::table(self::TABLE)->where('id', $id)->update($updateData);
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return (array) $row;
    }

    public function duplicate(int $id, string $newName, int $createdBy): array
    {
        return DB::transaction(function () use ($id, $newName, $createdBy) {
            $original = DB::table(self::TABLE)->where('id', $id)->first();

            if (!$original) {
                throw new \RuntimeException("Playbook not found: {$id}");
            }

            // Create duplicate playbook
            $duplicateId = DB::table(self::TABLE)->insertGetId([
                'name' => $newName,
                'slug' => null,
                'description' => $original->description,
                'trigger_module' => $original->trigger_module,
                'trigger_condition' => $original->trigger_condition,
                'trigger_config' => $original->trigger_config,
                'estimated_days' => $original->estimated_days,
                'is_active' => $original->is_active,
                'auto_assign' => $original->auto_assign,
                'default_owner_id' => $original->default_owner_id,
                'tags' => $original->tags,
                'display_order' => $original->display_order,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Duplicate phases and tasks
            $phases = DB::table(self::TABLE_PHASES)->where('playbook_id', $id)->get();
            $phaseMap = [];

            foreach ($phases as $phase) {
                $newPhaseId = DB::table(self::TABLE_PHASES)->insertGetId([
                    'playbook_id' => $duplicateId,
                    'name' => $phase->name,
                    'description' => $phase->description,
                    'target_days' => $phase->target_days,
                    'display_order' => $phase->display_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $phaseMap[$phase->id] = $newPhaseId;

                // Duplicate tasks in this phase
                $tasks = DB::table(self::TABLE_TASKS)
                    ->where('playbook_id', $id)
                    ->where('phase_id', $phase->id)
                    ->get();

                foreach ($tasks as $task) {
                    DB::table(self::TABLE_TASKS)->insert([
                        'playbook_id' => $duplicateId,
                        'phase_id' => $newPhaseId,
                        'title' => $task->title,
                        'description' => $task->description,
                        'task_type' => $task->task_type,
                        'task_config' => $task->task_config,
                        'due_days' => $task->due_days,
                        'duration_estimate' => $task->duration_estimate,
                        'is_required' => $task->is_required,
                        'is_milestone' => $task->is_milestone,
                        'assignee_type' => $task->assignee_type,
                        'assignee_id' => $task->assignee_id,
                        'assignee_role' => $task->assignee_role,
                        'dependencies' => $task->dependencies,
                        'checklist' => $task->checklist,
                        'resources' => $task->resources,
                        'display_order' => $task->display_order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Duplicate standalone tasks (not in phases)
            $standaloneTasks = DB::table(self::TABLE_TASKS)
                ->where('playbook_id', $id)
                ->whereNull('phase_id')
                ->get();

            foreach ($standaloneTasks as $task) {
                DB::table(self::TABLE_TASKS)->insert([
                    'playbook_id' => $duplicateId,
                    'phase_id' => null,
                    'title' => $task->title,
                    'description' => $task->description,
                    'task_type' => $task->task_type,
                    'task_config' => $task->task_config,
                    'due_days' => $task->due_days,
                    'duration_estimate' => $task->duration_estimate,
                    'is_required' => $task->is_required,
                    'is_milestone' => $task->is_milestone,
                    'assignee_type' => $task->assignee_type,
                    'assignee_id' => $task->assignee_id,
                    'assignee_role' => $task->assignee_role,
                    'dependencies' => $task->dependencies,
                    'checklist' => $task->checklist,
                    'resources' => $task->resources,
                    'display_order' => $task->display_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Duplicate goals
            $goals = DB::table(self::TABLE_GOALS)->where('playbook_id', $id)->get();
            foreach ($goals as $goal) {
                DB::table(self::TABLE_GOALS)->insert([
                    'playbook_id' => $duplicateId,
                    'name' => $goal->name,
                    'metric_type' => $goal->metric_type,
                    'target_module' => $goal->target_module,
                    'target_field' => $goal->target_field,
                    'comparison_operator' => $goal->comparison_operator,
                    'target_value' => $goal->target_value,
                    'target_days' => $goal->target_days,
                    'description' => $goal->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $row = DB::table(self::TABLE)->where('id', $duplicateId)->first();
            return (array) $row;
        });
    }

    // =========================================================================
    // PHASE QUERIES
    // =========================================================================

    public function getPhases(int $playbookId): array
    {
        $rows = DB::table(self::TABLE_PHASES)
            ->where('playbook_id', $playbookId)
            ->orderBy('display_order')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load tasks
            $tasks = DB::table(self::TABLE_TASKS)
                ->where('phase_id', $row->id)
                ->orderBy('display_order')
                ->get();

            $item['tasks'] = array_map(fn($task) => (array) $task, $tasks->all());
            $result[] = $item;
        }

        return $result;
    }

    public function createPhase(int $playbookId, array $data): array
    {
        $id = DB::table(self::TABLE_PHASES)->insertGetId([
            'playbook_id' => $playbookId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_days' => $data['target_days'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_PHASES)->where('id', $id)->first();
        return (array) $row;
    }

    public function updatePhase(int $id, array $data): array
    {
        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'target_days' => $data['target_days'] ?? null,
            'display_order' => $data['display_order'] ?? null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();
            DB::table(self::TABLE_PHASES)->where('id', $id)->update($updateData);
        }

        $row = DB::table(self::TABLE_PHASES)->where('id', $id)->first();
        return (array) $row;
    }

    public function deletePhase(int $id): bool
    {
        return DB::table(self::TABLE_PHASES)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // TASK QUERIES
    // =========================================================================

    public function getTasks(int $playbookId): array
    {
        $rows = DB::table(self::TABLE_TASKS)
            ->where('playbook_id', $playbookId)
            ->orderBy('display_order')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load phase
            if ($row->phase_id) {
                $item['phase'] = DB::table(self::TABLE_PHASES)
                    ->where('id', $row->phase_id)
                    ->first();
            }

            // Load assignee
            if ($row->assignee_id) {
                $item['assignee'] = DB::table('users')
                    ->where('id', $row->assignee_id)
                    ->first();
            }

            $result[] = $item;
        }

        return $result;
    }

    public function getTask(int $taskId): ?array
    {
        $row = DB::table(self::TABLE_TASKS)->where('id', $taskId)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load playbook
        $result['playbook'] = DB::table(self::TABLE)
            ->where('id', $row->playbook_id)
            ->first();

        // Load phase
        if ($row->phase_id) {
            $result['phase'] = DB::table(self::TABLE_PHASES)
                ->where('id', $row->phase_id)
                ->first();
        }

        // Load assignee
        if ($row->assignee_id) {
            $result['assignee'] = DB::table('users')
                ->where('id', $row->assignee_id)
                ->first();
        }

        return $result;
    }

    public function createTask(int $playbookId, array $data): array
    {
        $id = DB::table(self::TABLE_TASKS)->insertGetId([
            'playbook_id' => $playbookId,
            'phase_id' => $data['phase_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'] ?? 'general',
            'task_config' => isset($data['task_config']) ? json_encode($data['task_config']) : json_encode([]),
            'due_days' => $data['due_days'] ?? 0,
            'duration_estimate' => $data['duration_estimate'] ?? null,
            'is_required' => $data['is_required'] ?? true,
            'is_milestone' => $data['is_milestone'] ?? false,
            'assignee_type' => $data['assignee_type'] ?? 'owner',
            'assignee_id' => $data['assignee_id'] ?? null,
            'assignee_role' => $data['assignee_role'] ?? null,
            'dependencies' => isset($data['dependencies']) ? json_encode($data['dependencies']) : json_encode([]),
            'checklist' => isset($data['checklist']) ? json_encode($data['checklist']) : json_encode([]),
            'resources' => isset($data['resources']) ? json_encode($data['resources']) : json_encode([]),
            'display_order' => $data['display_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_TASKS)->where('id', $id)->first();
        return (array) $row;
    }

    public function updateTask(int $id, array $data): array
    {
        $updateData = array_filter([
            'phase_id' => $data['phase_id'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'] ?? null,
            'task_config' => isset($data['task_config']) ? json_encode($data['task_config']) : null,
            'due_days' => $data['due_days'] ?? null,
            'duration_estimate' => $data['duration_estimate'] ?? null,
            'is_required' => $data['is_required'] ?? null,
            'is_milestone' => $data['is_milestone'] ?? null,
            'assignee_type' => $data['assignee_type'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'assignee_role' => $data['assignee_role'] ?? null,
            'dependencies' => isset($data['dependencies']) ? json_encode($data['dependencies']) : null,
            'checklist' => isset($data['checklist']) ? json_encode($data['checklist']) : null,
            'resources' => isset($data['resources']) ? json_encode($data['resources']) : null,
            'display_order' => $data['display_order'] ?? null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();
            DB::table(self::TABLE_TASKS)->where('id', $id)->update($updateData);
        }

        $row = DB::table(self::TABLE_TASKS)->where('id', $id)->first();
        return (array) $row;
    }

    public function deleteTask(int $id): bool
    {
        return DB::table(self::TABLE_TASKS)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // INSTANCE QUERIES
    // =========================================================================

    public function listInstances(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_INSTANCES);

        // Filter by playbook
        if (!empty($filters['playbook_id'])) {
            $query->where('playbook_id', $filters['playbook_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by owner
        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        // Filter by related record
        if (!empty($filters['related_module']) && !empty($filters['related_id'])) {
            $query->where('related_module', $filters['related_module'])
                  ->where('related_id', $filters['related_id']);
        }

        // Filter by date range
        if (!empty($filters['started_from'])) {
            $query->where('started_at', '>=', $filters['started_from']);
        }
        if (!empty($filters['started_to'])) {
            $query->where('started_at', '<=', $filters['started_to']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Count total
        $total = $query->count();

        // Paginate
        $offset = ($page - 1) * $perPage;
        $rows = $query->offset($offset)->limit($perPage)->get();

        $items = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load playbook
            $item['playbook'] = DB::table(self::TABLE)
                ->select('id', 'name', 'slug')
                ->where('id', $row->playbook_id)
                ->first();

            // Load owner
            if ($row->owner_id) {
                $item['owner'] = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $row->owner_id)
                    ->first();
            }

            $items[] = $item;
        }

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getInstance(int $id): ?array
    {
        $row = DB::table(self::TABLE_INSTANCES)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load playbook
        $result['playbook'] = DB::table(self::TABLE)
            ->where('id', $row->playbook_id)
            ->first();

        // Load owner
        if ($row->owner_id) {
            $result['owner'] = DB::table('users')
                ->where('id', $row->owner_id)
                ->first();
        }

        // Load task instances
        $taskInstances = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('instance_id', $id)
            ->get();

        $result['task_instances'] = [];
        foreach ($taskInstances as $ti) {
            $tiArray = (array) $ti;

            // Load task
            $tiArray['task'] = DB::table(self::TABLE_TASKS)
                ->where('id', $ti->task_id)
                ->first();

            // Load assignee
            if ($ti->assigned_to) {
                $tiArray['assignee'] = DB::table('users')
                    ->where('id', $ti->assigned_to)
                    ->first();
            }

            $result['task_instances'][] = $tiArray;
        }

        // Load activities
        $activities = DB::table(self::TABLE_ACTIVITIES)
            ->where('instance_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $result['activities'] = [];
        foreach ($activities as $activity) {
            $actArray = (array) $activity;

            // Load user
            if ($activity->user_id) {
                $actArray['user'] = DB::table('users')
                    ->where('id', $activity->user_id)
                    ->first();
            }

            $result['activities'][] = $actArray;
        }

        // Load goal results
        $goalResults = DB::table(self::TABLE_GOAL_RESULTS)
            ->where('instance_id', $id)
            ->get();

        $result['goal_results'] = [];
        foreach ($goalResults as $gr) {
            $grArray = (array) $gr;

            // Load goal
            $grArray['goal'] = DB::table(self::TABLE_GOALS)
                ->where('id', $gr->goal_id)
                ->first();

            $result['goal_results'][] = $grArray;
        }

        return $result;
    }

    public function getInstancesForRecord(string $module, int $recordId): array
    {
        $rows = DB::table(self::TABLE_INSTANCES)
            ->where('related_module', $module)
            ->where('related_id', $recordId)
            ->orderBy('started_at', 'desc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load playbook
            $item['playbook'] = DB::table(self::TABLE)
                ->where('id', $row->playbook_id)
                ->first();

            // Load owner
            if ($row->owner_id) {
                $item['owner'] = DB::table('users')
                    ->where('id', $row->owner_id)
                    ->first();
            }

            $result[] = $item;
        }

        return $result;
    }

    public function getActiveInstancesForUser(int $userId): array
    {
        $rows = DB::table(self::TABLE_INSTANCES)
            ->where('status', 'active')
            ->where('owner_id', $userId)
            ->orderBy('target_completion_at')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load playbook
            $item['playbook'] = DB::table(self::TABLE)
                ->where('id', $row->playbook_id)
                ->first();

            $result[] = $item;
        }

        return $result;
    }

    public function startInstance(int $playbookId, string $relatedModule, int $relatedId, int $ownerId): array
    {
        return DB::transaction(function () use ($playbookId, $relatedModule, $relatedId, $ownerId) {
            $playbook = DB::table(self::TABLE)->where('id', $playbookId)->first();

            if (!$playbook) {
                throw new \RuntimeException("Playbook not found: {$playbookId}");
            }

            // Create instance
            $targetCompletionAt = $playbook->estimated_days
                ? now()->addDays($playbook->estimated_days)
                : null;

            $instanceId = DB::table(self::TABLE_INSTANCES)->insertGetId([
                'playbook_id' => $playbook->id,
                'related_module' => $relatedModule,
                'related_id' => $relatedId,
                'status' => 'active',
                'started_at' => now(),
                'target_completion_at' => $targetCompletionAt,
                'owner_id' => $ownerId,
                'progress_percent' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create task instances
            $tasks = DB::table(self::TABLE_TASKS)
                ->where('playbook_id', $playbook->id)
                ->get();

            foreach ($tasks as $task) {
                $assignedTo = match ($task->assignee_type) {
                    'specific' => $task->assignee_id,
                    'owner' => $ownerId,
                    default => $ownerId,
                };

                $dueAt = $task->due_days > 0
                    ? now()->addDays($task->due_days)
                    : null;

                DB::table(self::TABLE_TASK_INSTANCES)->insert([
                    'instance_id' => $instanceId,
                    'task_id' => $task->id,
                    'status' => 'pending',
                    'due_at' => $dueAt,
                    'assigned_to' => $assignedTo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $instanceId,
                'activity_type' => 'started',
                'activity_data' => json_encode(['playbook_name' => $playbook->name]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $instance = DB::table(self::TABLE_INSTANCES)->where('id', $instanceId)->first();
            return (array) $instance;
        });
    }

    public function updateInstance(int $id, array $data): array
    {
        $updateData = array_filter([
            'owner_id' => $data['owner_id'] ?? null,
            'target_completion_at' => $data['target_completion_at'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();
            DB::table(self::TABLE_INSTANCES)->where('id', $id)->update($updateData);
        }

        $row = DB::table(self::TABLE_INSTANCES)->where('id', $id)->first();
        return (array) $row;
    }

    public function completeInstance(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $instance = DB::table(self::TABLE_INSTANCES)->where('id', $id)->first();

            if (!$instance) {
                throw new \RuntimeException("Instance not found: {$id}");
            }

            DB::table(self::TABLE_INSTANCES)->where('id', $id)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'progress_percent' => 100,
                'updated_at' => now(),
            ]);

            // Calculate completion time
            $startedAt = new DateTimeImmutable($instance->started_at);
            $completedAt = new DateTimeImmutable();
            $completionDays = $startedAt->diff($completedAt)->days;

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $id,
                'activity_type' => 'completed',
                'activity_data' => json_encode(['completion_time' => $completionDays]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE_INSTANCES)->where('id', $id)->first();
            return (array) $row;
        });
    }

    public function pauseInstance(int $id, ?string $reason = null): array
    {
        return DB::transaction(function () use ($id, $reason) {
            DB::table(self::TABLE_INSTANCES)->where('id', $id)->update([
                'status' => 'paused',
                'paused_at' => now(),
                'updated_at' => now(),
            ]);

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $id,
                'activity_type' => 'paused',
                'activity_data' => json_encode(['reason' => $reason]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE_INSTANCES)->where('id', $id)->first();
            return (array) $row;
        });
    }

    public function resumeInstance(int $id): array
    {
        return DB::transaction(function () use ($id) {
            DB::table(self::TABLE_INSTANCES)->where('id', $id)->update([
                'status' => 'active',
                'paused_at' => null,
                'updated_at' => now(),
            ]);

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $id,
                'activity_type' => 'resumed',
                'activity_data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE_INSTANCES)->where('id', $id)->first();
            return (array) $row;
        });
    }

    public function cancelInstance(int $id, ?string $reason = null): array
    {
        return DB::transaction(function () use ($id, $reason) {
            DB::table(self::TABLE_INSTANCES)->where('id', $id)->update([
                'status' => 'cancelled',
                'paused_at' => null,
                'updated_at' => now(),
            ]);

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $id,
                'activity_type' => 'cancelled',
                'activity_data' => json_encode(['reason' => $reason]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE_INSTANCES)->where('id', $id)->first();
            return (array) $row;
        });
    }

    // =========================================================================
    // TASK INSTANCE QUERIES
    // =========================================================================

    public function getTaskInstances(int $instanceId): array
    {
        $rows = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('instance_id', $instanceId)
            ->orderBy('due_at')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load task
            $task = DB::table(self::TABLE_TASKS)->where('id', $row->task_id)->first();
            $item['task'] = $task;

            // Load phase
            if ($task && $task->phase_id) {
                $item['task']->phase = DB::table(self::TABLE_PHASES)
                    ->where('id', $task->phase_id)
                    ->first();
            }

            // Load assignee
            if ($row->assigned_to) {
                $item['assignee'] = DB::table('users')
                    ->where('id', $row->assigned_to)
                    ->first();
            }

            $result[] = $item;
        }

        return $result;
    }

    public function getPendingTasksForUser(int $userId): array
    {
        $rows = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('status', 'pending')
            ->where('assigned_to', $userId)
            ->orderBy('due_at')
            ->get();

        return $this->loadTaskInstanceRelations($rows);
    }

    public function getOverdueTasksForUser(int $userId): array
    {
        $rows = DB::table(self::TABLE_TASK_INSTANCES)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('assigned_to', $userId)
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->get();

        return $this->loadTaskInstanceRelations($rows);
    }

    private function loadTaskInstanceRelations($rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;

            // Load task
            $task = DB::table(self::TABLE_TASKS)->where('id', $row->task_id)->first();
            $item['task'] = $task;

            // Load instance with playbook
            $instance = DB::table(self::TABLE_INSTANCES)->where('id', $row->instance_id)->first();
            if ($instance) {
                $instanceArray = (array) $instance;
                $instanceArray['playbook'] = DB::table(self::TABLE)
                    ->where('id', $instance->playbook_id)
                    ->first();
                $item['instance'] = $instanceArray;
            }

            $result[] = $item;
        }

        return $result;
    }

    public function startTaskInstance(int $taskInstanceId): array
    {
        return DB::transaction(function () use ($taskInstanceId) {
            $taskInstance = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();

            if (!$taskInstance) {
                throw new \RuntimeException("Task instance not found: {$taskInstanceId}");
            }

            DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

            // Load task
            $task = DB::table(self::TABLE_TASKS)->where('id', $taskInstance->task_id)->first();

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $taskInstance->instance_id,
                'task_instance_id' => $taskInstanceId,
                'activity_type' => 'task_started',
                'activity_data' => json_encode(['task_title' => $task->title]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();
            return (array) $row;
        });
    }

    public function completeTaskInstance(int $taskInstanceId, int $completedBy, ?string $notes = null, ?int $timeSpent = null): array
    {
        return DB::transaction(function () use ($taskInstanceId, $completedBy, $notes, $timeSpent) {
            $taskInstance = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();

            if (!$taskInstance) {
                throw new \RuntimeException("Task instance not found: {$taskInstanceId}");
            }

            $updateData = [
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $completedBy,
                'updated_at' => now(),
            ];

            if ($notes !== null) {
                $updateData['notes'] = $notes;
            }

            if ($timeSpent !== null) {
                $updateData['time_spent'] = $timeSpent;
            }

            DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->update($updateData);

            // Load task
            $task = DB::table(self::TABLE_TASKS)->where('id', $taskInstance->task_id)->first();

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $taskInstance->instance_id,
                'task_instance_id' => $taskInstanceId,
                'activity_type' => 'task_completed',
                'activity_data' => json_encode([
                    'task_title' => $task->title,
                    'time_spent' => $timeSpent,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();
            return (array) $row;
        });
    }

    public function skipTaskInstance(int $taskInstanceId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($taskInstanceId, $reason) {
            $taskInstance = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();

            if (!$taskInstance) {
                throw new \RuntimeException("Task instance not found: {$taskInstanceId}");
            }

            $updateData = [
                'status' => 'skipped',
                'completed_at' => now(),
                'updated_at' => now(),
            ];

            if ($reason !== null) {
                $updateData['notes'] = $reason;
            }

            DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->update($updateData);

            // Load task
            $task = DB::table(self::TABLE_TASKS)->where('id', $taskInstance->task_id)->first();

            // Log activity
            DB::table(self::TABLE_ACTIVITIES)->insert([
                'instance_id' => $taskInstance->instance_id,
                'task_instance_id' => $taskInstanceId,
                'activity_type' => 'task_skipped',
                'activity_data' => json_encode([
                    'task_title' => $task->title,
                    'reason' => $reason,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();
            return (array) $row;
        });
    }

    public function updateTaskChecklist(int $taskInstanceId, int $itemIndex, bool $completed): array
    {
        $taskInstance = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();

        if (!$taskInstance) {
            throw new \RuntimeException("Task instance not found: {$taskInstanceId}");
        }

        $checklistStatus = $taskInstance->checklist_status ? json_decode($taskInstance->checklist_status, true) : [];
        $checklistStatus[$itemIndex] = $completed;

        DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->update([
            'checklist_status' => json_encode($checklistStatus),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_TASK_INSTANCES)->where('id', $taskInstanceId)->first();
        return (array) $row;
    }

    // =========================================================================
    // GOAL QUERIES
    // =========================================================================

    public function createGoal(int $playbookId, array $data): array
    {
        $id = DB::table(self::TABLE_GOALS)->insertGetId([
            'playbook_id' => $playbookId,
            'name' => $data['name'],
            'metric_type' => $data['metric_type'],
            'target_module' => $data['target_module'] ?? null,
            'target_field' => $data['target_field'] ?? null,
            'comparison_operator' => $data['comparison_operator'] ?? '>=',
            'target_value' => $data['target_value'],
            'target_days' => $data['target_days'] ?? null,
            'description' => $data['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_GOALS)->where('id', $id)->first();
        return (array) $row;
    }

    public function recordGoalResult(int $instanceId, int $goalId, $actualValue): array
    {
        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();

        if (!$goal) {
            throw new \RuntimeException("Goal not found: {$goalId}");
        }

        // Simple evaluation logic
        $achieved = match ($goal->comparison_operator) {
            '>=' => $actualValue >= $goal->target_value,
            '>' => $actualValue > $goal->target_value,
            '<=' => $actualValue <= $goal->target_value,
            '<' => $actualValue < $goal->target_value,
            '=' => $actualValue == $goal->target_value,
            default => false,
        };

        $id = DB::table(self::TABLE_GOAL_RESULTS)->insertGetId([
            'instance_id' => $instanceId,
            'goal_id' => $goalId,
            'actual_value' => $actualValue,
            'achieved' => $achieved,
            'achieved_at' => $achieved ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_GOAL_RESULTS)->where('id', $id)->first();
        return (array) $row;
    }

    // =========================================================================
    // ANALYTICS QUERIES
    // =========================================================================

    public function getPlaybookAnalytics(int $playbookId): array
    {
        $playbook = DB::table(self::TABLE)->where('id', $playbookId)->first();

        if (!$playbook) {
            throw new \RuntimeException("Playbook not found: {$playbookId}");
        }

        $totalInstances = DB::table(self::TABLE_INSTANCES)
            ->where('playbook_id', $playbookId)
            ->count();

        $activeInstances = DB::table(self::TABLE_INSTANCES)
            ->where('playbook_id', $playbookId)
            ->where('status', 'active')
            ->count();

        $completedInstances = DB::table(self::TABLE_INSTANCES)
            ->where('playbook_id', $playbookId)
            ->where('status', 'completed')
            ->count();

        // Calculate average completion days
        $avgResult = DB::table(self::TABLE_INSTANCES)
            ->where('playbook_id', $playbookId)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, started_at, completed_at)) as avg_days')
            ->first();

        $avgCompletionDays = $avgResult ? round($avgResult->avg_days, 1) : 0;

        // Task completion rate
        $allTaskInstances = DB::table(self::TABLE_TASK_INSTANCES)
            ->whereIn('instance_id', function ($query) use ($playbookId) {
                $query->select('id')
                    ->from(self::TABLE_INSTANCES)
                    ->where('playbook_id', $playbookId);
            })
            ->count();

        $completedTasks = DB::table(self::TABLE_TASK_INSTANCES)
            ->whereIn('instance_id', function ($query) use ($playbookId) {
                $query->select('id')
                    ->from(self::TABLE_INSTANCES)
                    ->where('playbook_id', $playbookId);
            })
            ->where('status', 'completed')
            ->count();

        $taskCompletionRate = $allTaskInstances > 0
            ? round(($completedTasks / $allTaskInstances) * 100, 2)
            : 0;

        return [
            'playbook_id' => $playbookId,
            'playbook_name' => $playbook->name,
            'total_instances' => $totalInstances,
            'active_instances' => $activeInstances,
            'completed_instances' => $completedInstances,
            'completion_rate' => $totalInstances > 0
                ? round(($completedInstances / $totalInstances) * 100, 2)
                : 0,
            'avg_completion_days' => $avgCompletionDays,
            'task_completion_rate' => $taskCompletionRate,
        ];
    }

    public function getInstanceProgress(int $instanceId): array
    {
        $instance = DB::table(self::TABLE_INSTANCES)->where('id', $instanceId)->first();

        if (!$instance) {
            throw new \RuntimeException("Instance not found: {$instanceId}");
        }

        $totalTasks = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('instance_id', $instanceId)
            ->count();

        $completedTasks = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('instance_id', $instanceId)
            ->where('status', 'completed')
            ->count();

        $overdueTasks = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('instance_id', $instanceId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('due_at', '<', now())
            ->count();

        $tasksByStatus = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('instance_id', $instanceId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        return [
            'instance_id' => $instanceId,
            'status' => $instance->status,
            'progress_percent' => $instance->progress_percent,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'overdue_tasks' => $overdueTasks,
            'tasks_by_status' => $tasksByStatus,
            'started_at' => $instance->started_at,
            'target_completion_at' => $instance->target_completion_at,
            'completed_at' => $instance->completed_at,
        ];
    }

    public function getUserTaskSummary(int $userId): array
    {
        $pendingTasks = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('status', 'pending')
            ->where('assigned_to', $userId)
            ->count();

        $inProgressTasks = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('status', 'in_progress')
            ->where('assigned_to', $userId)
            ->count();

        $overdueTasks = DB::table(self::TABLE_TASK_INSTANCES)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('assigned_to', $userId)
            ->where('due_at', '<', now())
            ->count();

        $completedToday = DB::table(self::TABLE_TASK_INSTANCES)
            ->where('status', 'completed')
            ->where('assigned_to', $userId)
            ->whereDate('completed_at', today())
            ->count();

        return [
            'user_id' => $userId,
            'pending_tasks' => $pendingTasks,
            'in_progress_tasks' => $inProgressTasks,
            'overdue_tasks' => $overdueTasks,
            'completed_today' => $completedToday,
        ];
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): PlaybookEntity
    {
        return PlaybookEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            slug: $row->slug,
            description: $row->description,
            triggerModule: $row->trigger_module,
            triggerCondition: $row->trigger_condition,
            triggerConfig: $row->trigger_config ? json_decode($row->trigger_config, true) : null,
            estimatedDays: $row->estimated_days,
            status: PlaybookStatus::from($row->is_active ? 'active' : 'inactive'),
            autoAssign: (bool) $row->auto_assign,
            defaultOwnerId: $row->default_owner_id,
            tags: $row->tags ? json_decode($row->tags, true) : [],
            displayOrder: $row->display_order,
            createdBy: $row->created_by,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : new DateTimeImmutable(),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    private function toRowData(PlaybookEntity $playbook): array
    {
        return [
            'name' => $playbook->getName(),
            'slug' => $playbook->getSlug(),
            'description' => $playbook->getDescription(),
            'trigger_module' => $playbook->getTriggerModule(),
            'trigger_condition' => $playbook->getTriggerCondition(),
            'trigger_config' => $playbook->getTriggerConfig() ? json_encode($playbook->getTriggerConfig()) : null,
            'estimated_days' => $playbook->getEstimatedDays(),
            'is_active' => $playbook->getStatus()->value === 'active',
            'auto_assign' => $playbook->isAutoAssign(),
            'default_owner_id' => $playbook->getDefaultOwnerId(),
            'tags' => json_encode($playbook->getTags()),
            'display_order' => $playbook->getDisplayOrder(),
            'created_by' => $playbook->getCreatedBy(),
        ];
    }
}
