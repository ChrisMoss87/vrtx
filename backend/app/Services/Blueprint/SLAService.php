<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintSla;
use App\Models\BlueprintSlaEscalation;
use App\Models\BlueprintSlaEscalationLog;
use App\Models\BlueprintSlaInstance;
use App\Models\BlueprintState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Manages SLA monitoring and escalations for blueprint states.
 */
class SLAService
{
    /**
     * Start SLA tracking when a record enters a state.
     */
    public function startSLA(int $recordId, BlueprintState $state): ?BlueprintSlaInstance
    {
        $sla = $state->sla;
        if (!$sla || !$sla->is_active) {
            return null;
        }

        // Complete any existing active SLA for this record in the same blueprint
        $this->completeSLA($recordId, $state->blueprint_id);

        // Calculate due date
        $enteredAt = now();
        $dueAt = $this->calculateDueAt($sla, $enteredAt);

        return BlueprintSlaInstance::create([
            'sla_id' => $sla->id,
            'record_id' => $recordId,
            'state_entered_at' => $enteredAt,
            'due_at' => $dueAt,
            'status' => BlueprintSlaInstance::STATUS_ACTIVE,
        ]);
    }

    /**
     * Complete SLA tracking when a record leaves a state.
     */
    public function completeSLA(int $recordId, int $blueprintId): void
    {
        // Find active SLA instances for this record in this blueprint
        $activeInstances = BlueprintSlaInstance::where('record_id', $recordId)
            ->where('status', BlueprintSlaInstance::STATUS_ACTIVE)
            ->whereHas('sla', function ($query) use ($blueprintId) {
                $query->where('blueprint_id', $blueprintId);
            })
            ->get();

        foreach ($activeInstances as $instance) {
            $instance->complete();
        }
    }

    /**
     * Calculate the due date based on SLA configuration.
     */
    public function calculateDueAt(BlueprintSla $sla, Carbon $enteredAt): Carbon
    {
        $dueAt = $enteredAt->copy();
        $hoursRemaining = $sla->duration_hours;

        if (!$sla->business_hours_only && !$sla->exclude_weekends) {
            // Simple calculation: just add hours
            return $dueAt->addHours($hoursRemaining);
        }

        // Complex calculation with business hours and/or weekend exclusion
        $businessStart = 9; // 9 AM
        $businessEnd = 17; // 5 PM
        $businessHoursPerDay = $businessEnd - $businessStart;

        while ($hoursRemaining > 0) {
            // Skip weekends if configured
            if ($sla->exclude_weekends && $dueAt->isWeekend()) {
                $dueAt->addDay()->startOfDay()->setHour($businessStart);
                continue;
            }

            if ($sla->business_hours_only) {
                // Only count business hours
                $currentHour = $dueAt->hour;

                if ($currentHour < $businessStart) {
                    $dueAt->setHour($businessStart);
                    continue;
                }

                if ($currentHour >= $businessEnd) {
                    $dueAt->addDay()->startOfDay()->setHour($businessStart);
                    continue;
                }

                // We're in business hours
                $hoursLeftToday = $businessEnd - $currentHour;
                $hoursToAdd = min($hoursRemaining, $hoursLeftToday);
                $dueAt->addHours($hoursToAdd);
                $hoursRemaining -= $hoursToAdd;
            } else {
                // Count all hours, just skip weekends
                $hoursLeftToday = 24 - $dueAt->hour;
                $hoursToAdd = min($hoursRemaining, $hoursLeftToday);
                $dueAt->addHours($hoursToAdd);
                $hoursRemaining -= $hoursToAdd;
            }
        }

        return $dueAt;
    }

    /**
     * Check all active SLAs and trigger escalations as needed.
     * This should be called by a scheduled job (every minute).
     */
    public function checkSLAs(): array
    {
        $results = [
            'checked' => 0,
            'escalations_triggered' => 0,
            'breaches_marked' => 0,
        ];

        $activeInstances = BlueprintSlaInstance::where('status', BlueprintSlaInstance::STATUS_ACTIVE)
            ->with(['sla.escalations'])
            ->get();

        foreach ($activeInstances as $instance) {
            $results['checked']++;

            // Check if SLA is breached
            if (now()->isAfter($instance->due_at)) {
                if ($instance->status !== BlueprintSlaInstance::STATUS_BREACHED) {
                    $instance->breach();
                    $results['breaches_marked']++;
                }
            }

            // Check escalations
            $triggered = $this->checkEscalations($instance);
            $results['escalations_triggered'] += $triggered;
        }

        return $results;
    }

    /**
     * Check and trigger escalations for an SLA instance.
     */
    protected function checkEscalations(BlueprintSlaInstance $instance): int
    {
        $sla = $instance->sla;
        $escalations = $sla->escalations;
        $triggered = 0;

        foreach ($escalations as $escalation) {
            // Skip if already triggered
            if ($instance->hasEscalationTriggered($escalation->id)) {
                continue;
            }

            $shouldTrigger = $this->shouldTriggerEscalation($instance, $escalation);
            if ($shouldTrigger) {
                $this->executeEscalation($instance, $escalation);
                $triggered++;
            }
        }

        return $triggered;
    }

    /**
     * Check if an escalation should be triggered.
     */
    protected function shouldTriggerEscalation(BlueprintSlaInstance $instance, BlueprintSlaEscalation $escalation): bool
    {
        $percentageElapsed = $instance->getPercentageElapsed();

        return match ($escalation->trigger_type) {
            BlueprintSlaEscalation::TRIGGER_APPROACHING => $percentageElapsed >= ($escalation->trigger_value ?? 80),
            BlueprintSlaEscalation::TRIGGER_BREACHED => $instance->isBreached() || $percentageElapsed >= 100,
            default => false,
        };
    }

    /**
     * Execute an escalation action.
     */
    public function executeEscalation(BlueprintSlaInstance $instance, BlueprintSlaEscalation $escalation): BlueprintSlaEscalationLog
    {
        try {
            $result = $this->runEscalationAction($instance, $escalation);

            return BlueprintSlaEscalationLog::create([
                'sla_instance_id' => $instance->id,
                'escalation_id' => $escalation->id,
                'executed_at' => now(),
                'status' => 'success',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('SLA escalation failed', [
                'instance_id' => $instance->id,
                'escalation_id' => $escalation->id,
                'error' => $e->getMessage(),
            ]);

            return BlueprintSlaEscalationLog::create([
                'sla_instance_id' => $instance->id,
                'escalation_id' => $escalation->id,
                'executed_at' => now(),
                'status' => 'failed',
                'result' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * Run the escalation action.
     */
    protected function runEscalationAction(BlueprintSlaInstance $instance, BlueprintSlaEscalation $escalation): array
    {
        $config = $escalation->config;
        $context = $this->buildContext($instance);

        return match ($escalation->action_type) {
            BlueprintSlaEscalation::ACTION_SEND_EMAIL => $this->sendEscalationEmail($config, $context),
            BlueprintSlaEscalation::ACTION_UPDATE_FIELD => $this->updateField($config, $context),
            BlueprintSlaEscalation::ACTION_CREATE_TASK => $this->createTask($config, $context),
            BlueprintSlaEscalation::ACTION_NOTIFY_USER => $this->notifyUser($config, $context),
            default => ['status' => 'skipped', 'reason' => 'Unknown action type'],
        };
    }

    /**
     * Build context for escalation actions.
     */
    protected function buildContext(BlueprintSlaInstance $instance): array
    {
        $sla = $instance->sla;
        $state = $sla->state;
        $blueprint = $sla->blueprint;

        return [
            'record_id' => $instance->record_id,
            'module_id' => $blueprint->module_id,
            'state_name' => $state->name,
            'sla_name' => $sla->name,
            'duration_hours' => $sla->duration_hours,
            'state_entered_at' => $instance->state_entered_at->toIso8601String(),
            'due_at' => $instance->due_at->toIso8601String(),
            'percentage_elapsed' => $instance->getPercentageElapsed(),
            'remaining_hours' => $instance->getRemainingHours(),
            'is_breached' => $instance->isBreached(),
        ];
    }

    /**
     * Send escalation email.
     */
    protected function sendEscalationEmail(array $config, array $context): array
    {
        // Implementation would use email service
        return ['sent' => true, 'recipients' => $config['to'] ?? []];
    }

    /**
     * Update a field value.
     */
    protected function updateField(array $config, array $context): array
    {
        $fieldName = $config['field'] ?? null;
        $value = $config['value'] ?? null;

        if (!$fieldName) {
            return ['updated' => false, 'error' => 'No field specified'];
        }

        // Get module and update record
        $moduleId = $context['module_id'];
        $recordId = $context['record_id'];

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
     * Create a task.
     */
    protected function createTask(array $config, array $context): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tasks')) {
            return ['created' => false, 'error' => 'Tasks table not found'];
        }

        $id = \Illuminate\Support\Facades\DB::table('tasks')->insertGetId([
            'subject' => $config['subject'] ?? 'SLA Escalation Task',
            'description' => $config['description'] ?? "SLA escalation for record {$context['record_id']}",
            'due_date' => $config['due_date'] ?? now()->addDay()->toDateString(),
            'assigned_to' => $config['assigned_to'] ?? null,
            'priority' => $config['priority'] ?? 'high',
            'status' => 'open',
            'related_module_id' => $context['module_id'],
            'related_record_id' => $context['record_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['created' => true, 'task_id' => $id];
    }

    /**
     * Notify a user.
     */
    protected function notifyUser(array $config, array $context): array
    {
        $userIds = $config['user_ids'] ?? [];
        if (empty($userIds)) {
            return ['sent' => false, 'error' => 'No users specified'];
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            foreach ($userIds as $userId) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\\Notifications\\SLAEscalationNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $userId,
                    'data' => json_encode([
                        'title' => $config['title'] ?? 'SLA Escalation',
                        'message' => $config['message'] ?? 'An SLA is approaching or breached',
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
     * Get SLA status for a record.
     */
    public function getSLAStatus(int $blueprintId, int $recordId): ?array
    {
        $activeInstance = BlueprintSlaInstance::where('record_id', $recordId)
            ->where('status', BlueprintSlaInstance::STATUS_ACTIVE)
            ->whereHas('sla', function ($query) use ($blueprintId) {
                $query->where('blueprint_id', $blueprintId);
            })
            ->with('sla.state')
            ->first();

        if (!$activeInstance) {
            return null;
        }

        return [
            'sla_id' => $activeInstance->sla_id,
            'sla_name' => $activeInstance->sla->name,
            'state_name' => $activeInstance->sla->state->name,
            'duration_hours' => $activeInstance->sla->duration_hours,
            'state_entered_at' => $activeInstance->state_entered_at->toIso8601String(),
            'due_at' => $activeInstance->due_at->toIso8601String(),
            'percentage_elapsed' => $activeInstance->getPercentageElapsed(),
            'remaining_hours' => $activeInstance->getRemainingHours(),
            'remaining_seconds' => $activeInstance->getRemainingSeconds(),
            'status' => $activeInstance->status,
            'is_breached' => $activeInstance->isBreached(),
            'is_approaching' => $activeInstance->isApproaching(),
        ];
    }
}
