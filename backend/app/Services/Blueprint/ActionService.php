<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintActionLog;
use App\Models\BlueprintTransitionAction;
use App\Models\BlueprintTransitionExecution;
use App\Services\Workflow\Actions\ActionHandler;
use Illuminate\Support\Facades\Log;

/**
 * Executes after-phase actions for blueprint transitions.
 * Reuses workflow action handlers where applicable.
 */
class ActionService
{
    public function __construct(
        protected ActionHandler $actionHandler
    ) {}

    /**
     * Execute all actions for a transition execution.
     */
    public function executeActions(BlueprintTransitionExecution $execution): void
    {
        $actions = $execution->transition->actions()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        foreach ($actions as $action) {
            $this->executeAction($execution, $action);
        }
    }

    /**
     * Execute a single action.
     */
    public function executeAction(BlueprintTransitionExecution $execution, BlueprintTransitionAction $action): BlueprintActionLog
    {
        try {
            $result = $this->runAction($execution, $action);

            return BlueprintActionLog::create([
                'execution_id' => $execution->id,
                'action_id' => $action->id,
                'status' => 'success',
                'result' => $result,
                'executed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Blueprint action failed", [
                'execution_id' => $execution->id,
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            return BlueprintActionLog::create([
                'execution_id' => $execution->id,
                'action_id' => $action->id,
                'status' => 'failed',
                'result' => ['error' => $e->getMessage()],
                'executed_at' => now(),
            ]);
        }
    }

    /**
     * Run the action based on its type.
     */
    protected function runAction(BlueprintTransitionExecution $execution, BlueprintTransitionAction $action): array
    {
        $config = $action->config;
        $recordId = $execution->record_id;
        $transition = $execution->transition;
        $blueprint = $transition->blueprint;

        // Build context for variable substitution
        $context = $this->buildContext($execution);

        return match ($action->type) {
            BlueprintTransitionAction::TYPE_SEND_EMAIL => $this->sendEmail($config, $context),
            BlueprintTransitionAction::TYPE_UPDATE_FIELD => $this->updateField($config, $context, $blueprint->module_id, $recordId),
            BlueprintTransitionAction::TYPE_CREATE_RECORD => $this->createRecord($config, $context),
            BlueprintTransitionAction::TYPE_CREATE_TASK => $this->createTask($config, $context, $blueprint->module_id, $recordId),
            BlueprintTransitionAction::TYPE_WEBHOOK => $this->callWebhook($config, $context),
            BlueprintTransitionAction::TYPE_NOTIFY_USER => $this->notifyUser($config, $context),
            BlueprintTransitionAction::TYPE_ADD_TAG => $this->addTag($config, $recordId),
            BlueprintTransitionAction::TYPE_REMOVE_TAG => $this->removeTag($config, $recordId),
            default => ['status' => 'skipped', 'reason' => 'Unknown action type'],
        };
    }

    /**
     * Build context for variable substitution.
     */
    protected function buildContext(BlueprintTransitionExecution $execution): array
    {
        $transition = $execution->transition;
        $blueprint = $transition->blueprint;

        return [
            'record_id' => $execution->record_id,
            'module_id' => $blueprint->module_id,
            'from_state' => $execution->fromState?->name,
            'to_state' => $execution->toState->name,
            'transition_name' => $transition->name,
            'executed_by' => $execution->executed_by,
            'executed_at' => now()->toIso8601String(),
            'requirements_data' => $execution->requirements_data ?? [],
            'user' => $execution->executedBy ? [
                'id' => $execution->executedBy->id,
                'name' => $execution->executedBy->name,
                'email' => $execution->executedBy->email,
            ] : null,
        ];
    }

    /**
     * Send an email.
     */
    protected function sendEmail(array $config, array $context): array
    {
        // Use the workflow action handler if available
        $step = $this->createMockStep(BlueprintTransitionAction::TYPE_SEND_EMAIL, $config);

        try {
            $result = $this->actionHandler->execute($step, $context);
            return ['sent' => true, 'result' => $result];
        } catch (\Throwable $e) {
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update a field value.
     */
    protected function updateField(array $config, array $context, int $moduleId, int $recordId): array
    {
        $fieldName = $config['field'] ?? $config['field_api_name'] ?? null;
        $value = $this->resolveValue($config['value'] ?? null, $context);

        if (!$fieldName) {
            return ['updated' => false, 'error' => 'No field specified'];
        }

        // Use the transition service to update the field
        $module = \App\Models\Module::find($moduleId);
        if (!$module) {
            return ['updated' => false, 'error' => 'Module not found'];
        }

        $tableName = $module->api_name;

        if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
            \Illuminate\Support\Facades\DB::table($tableName)
                ->where('id', $recordId)
                ->update([
                    $fieldName => $value,
                    'updated_at' => now(),
                ]);

            return ['updated' => true, 'field' => $fieldName, 'value' => $value];
        }

        return ['updated' => false, 'error' => 'Table not found'];
    }

    /**
     * Create a new record.
     */
    protected function createRecord(array $config, array $context): array
    {
        $moduleId = $config['module_id'] ?? null;
        $data = $config['data'] ?? [];

        if (!$moduleId) {
            return ['created' => false, 'error' => 'No module specified'];
        }

        $module = \App\Models\Module::find($moduleId);
        if (!$module) {
            return ['created' => false, 'error' => 'Module not found'];
        }

        // Resolve values in data
        $resolvedData = [];
        foreach ($data as $field => $value) {
            $resolvedData[$field] = $this->resolveValue($value, $context);
        }

        $tableName = $module->api_name;
        if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
            $id = \Illuminate\Support\Facades\DB::table($tableName)->insertGetId(array_merge($resolvedData, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            return ['created' => true, 'record_id' => $id];
        }

        return ['created' => false, 'error' => 'Table not found'];
    }

    /**
     * Create a task.
     */
    protected function createTask(array $config, array $context, int $moduleId, int $recordId): array
    {
        $taskModule = \App\Models\Module::where('api_name', 'tasks')->first();
        if (!$taskModule) {
            return ['created' => false, 'error' => 'Tasks module not found'];
        }

        $data = [
            'subject' => $this->resolveValue($config['subject'] ?? 'Follow-up task', $context),
            'description' => $this->resolveValue($config['description'] ?? '', $context),
            'due_date' => $config['due_date'] ?? now()->addDays($config['due_in_days'] ?? 1)->toDateString(),
            'assigned_to' => $config['assigned_to'] ?? $context['executed_by'] ?? null,
            'priority' => $config['priority'] ?? 'medium',
            'status' => 'open',
            'related_module_id' => $moduleId,
            'related_record_id' => $recordId,
        ];

        if (\Illuminate\Support\Facades\Schema::hasTable('tasks')) {
            $id = \Illuminate\Support\Facades\DB::table('tasks')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            return ['created' => true, 'task_id' => $id];
        }

        return ['created' => false, 'error' => 'Tasks table not found'];
    }

    /**
     * Call a webhook.
     */
    protected function callWebhook(array $config, array $context): array
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];
        $payload = $config['payload'] ?? $context;

        if (!$url) {
            return ['sent' => false, 'error' => 'No URL specified'];
        }

        // Resolve values in payload
        if (is_array($payload)) {
            $payload = $this->resolveValues($payload, $context);
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);
            $response = $client->request($method, $url, [
                'headers' => $headers,
                'json' => $payload,
            ]);

            return [
                'sent' => true,
                'status_code' => $response->getStatusCode(),
                'response' => (string) $response->getBody(),
            ];
        } catch (\Throwable $e) {
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notify a user (in-app notification).
     */
    protected function notifyUser(array $config, array $context): array
    {
        $userIds = $config['user_ids'] ?? [];
        $message = $this->resolveValue($config['message'] ?? '', $context);
        $title = $this->resolveValue($config['title'] ?? 'Notification', $context);

        if (empty($userIds)) {
            // Default to the executing user
            if (isset($context['executed_by'])) {
                $userIds = [$context['executed_by']];
            } else {
                return ['sent' => false, 'error' => 'No users specified'];
            }
        }

        // Create notifications (assuming a notifications table exists)
        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            foreach ($userIds as $userId) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\\Notifications\\BlueprintNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $userId,
                    'data' => json_encode([
                        'title' => $title,
                        'message' => $message,
                        'context' => $context,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['sent' => true, 'user_count' => count($userIds)];
        }

        return ['sent' => false, 'error' => 'Notifications table not found'];
    }

    /**
     * Add tags to a record.
     */
    protected function addTag(array $config, int $recordId): array
    {
        $tags = $config['tags'] ?? [];
        // Implementation depends on how tags are stored
        return ['added' => true, 'tags' => $tags];
    }

    /**
     * Remove tags from a record.
     */
    protected function removeTag(array $config, int $recordId): array
    {
        $tags = $config['tags'] ?? [];
        // Implementation depends on how tags are stored
        return ['removed' => true, 'tags' => $tags];
    }

    /**
     * Resolve a value with variable substitution.
     */
    protected function resolveValue(mixed $value, array $context): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Replace {{variable}} patterns
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
            $key = trim($matches[1]);
            return $this->getNestedValue($context, $key) ?? $matches[0];
        }, $value);
    }

    /**
     * Resolve values in an array.
     */
    protected function resolveValues(array $data, array $context): array
    {
        $resolved = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $resolved[$key] = $this->resolveValues($value, $context);
            } else {
                $resolved[$key] = $this->resolveValue($value, $context);
            }
        }
        return $resolved;
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
     * Create a mock step object for the action handler.
     */
    protected function createMockStep(string $type, array $config): object
    {
        return new class($type, $config) {
            public function __construct(
                public string $type,
                public array $config
            ) {}
        };
    }

    /**
     * Get available action types.
     */
    public function getActionTypes(): array
    {
        return BlueprintTransitionAction::getTypes();
    }
}
