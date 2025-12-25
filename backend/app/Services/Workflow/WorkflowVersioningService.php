<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing workflow versions and history.
 */
class WorkflowVersioningService
{
    /**
     * Create a version snapshot when a workflow is created.
     */
    public function createInitialVersion(Workflow $workflow, ?int $userId = null): WorkflowVersion
    {
        return WorkflowVersion::createFromWorkflow(
            $workflow,
            WorkflowVersion::CHANGE_CREATE,
            'Initial creation',
            $userId
        );
    }

    /**
     * Create a version snapshot when a workflow is updated.
     */
    public function createVersionOnUpdate(
        Workflow $workflow,
        ?string $changeSummary = null,
        ?int $userId = null
    ): WorkflowVersion {
        return WorkflowVersion::createFromWorkflow(
            $workflow,
            WorkflowVersion::CHANGE_UPDATE,
            $changeSummary ?? 'Workflow updated',
            $userId
        );
    }

    /**
     * Get version history for a workflow.
     */
    public function getVersionHistory(int $workflowId, int $limit = 50): Collection
    {
        return WorkflowVersion::forWorkflow($workflowId)
            ->with('creator')
            ->orderByDesc('version_number')
            ->limit($limit)
            ->get()
            ->map(function ($version) {
                $diff = $version->getDiffFromPrevious();
                return [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'name' => $version->name,
                    'description' => $version->description,
                    'change_type' => $version->change_type,
                    'change_summary' => $version->change_summary,
                    'changes' => $diff['changes'],
                    'is_active' => $version->is_active_version,
                    'trigger_type' => $version->trigger_type,
                    'step_count' => count($version->steps_data),
                    'created_by' => $version->creator ? [
                        'id' => $version->creator->id,
                        'name' => $version->creator->name,
                    ] : null,
                    'created_at' => $version->created_at->toISOString(),
                ];
            });
    }

    /**
     * Get a specific version.
     */
    public function getVersion(int $versionId): ?WorkflowVersion
    {
        return WorkflowVersion::with('creator', 'workflow')->find($versionId);
    }

    /**
     * Get version details including full workflow and steps data.
     */
    public function getVersionDetails(int $versionId): ?array
    {
        $version = $this->getVersion($versionId);

        if (!$version) {
            return null;
        }

        return [
            'id' => $version->id,
            'workflow_id' => $version->workflow_id,
            'version_number' => $version->version_number,
            'name' => $version->name,
            'description' => $version->description,
            'workflow_data' => $version->workflow_data,
            'steps' => $version->steps_data,
            'trigger_type' => $version->trigger_type,
            'trigger_config' => $version->trigger_config,
            'conditions' => $version->conditions,
            'change_type' => $version->change_type,
            'change_summary' => $version->change_summary,
            'is_active' => $version->is_active_version,
            'diff' => $version->getDiffFromPrevious(),
            'created_by' => $version->creator ? [
                'id' => $version->creator->id,
                'name' => $version->creator->name,
            ] : null,
            'created_at' => $version->created_at->toISOString(),
        ];
    }

    /**
     * Rollback a workflow to a specific version.
     */
    public function rollbackToVersion(int $versionId, ?int $userId = null): Workflow
    {
        $version = DB::table('workflow_versions')->where('id', $versionId)->first();

        return DB::transaction(function () use ($version, $userId) {
            Log::info('Rolling back workflow to version', [
                'workflow_id' => $version->workflow_id,
                'target_version' => $version->version_number,
                'user_id' => $userId,
            ]);

            return $version->restore($userId);
        });
    }

    /**
     * Compare two versions.
     */
    public function compareVersions(int $versionId1, int $versionId2): array
    {
        $version1 = DB::table('workflow_versions')->where('id', $versionId1)->first();
        $version2 = DB::table('workflow_versions')->where('id', $versionId2)->first();

        // Ensure they're from the same workflow
        if ($version1->workflow_id !== $version2->workflow_id) {
            throw new \InvalidArgumentException('Cannot compare versions from different workflows');
        }

        // Order by version number (older first)
        if ($version1->version_number > $version2->version_number) {
            [$version1, $version2] = [$version2, $version1];
        }

        $changes = [];

        // Compare workflow properties
        $props = ['name', 'description', 'trigger_type', 'is_active', 'priority'];
        foreach ($props as $prop) {
            $val1 = $version1->workflow_data[$prop] ?? null;
            $val2 = $version2->workflow_data[$prop] ?? null;
            if ($val1 !== $val2) {
                $changes['workflow'][$prop] = [
                    'old' => $val1,
                    'new' => $val2,
                ];
            }
        }

        // Compare steps
        $steps1 = collect($version1->steps_data)->keyBy(fn($s) => $s['action_type'] . '_' . ($s['order'] ?? 0));
        $steps2 = collect($version2->steps_data)->keyBy(fn($s) => $s['action_type'] . '_' . ($s['order'] ?? 0));

        $addedSteps = [];
        $removedSteps = [];
        $modifiedSteps = [];

        // Find added and modified steps
        foreach ($version2->steps_data as $step) {
            $key = $step['action_type'] . '_' . ($step['order'] ?? 0);
            $oldStep = $steps1->get($key);

            if (!$oldStep) {
                $addedSteps[] = $step;
            } elseif (json_encode($step['action_config'] ?? []) !== json_encode($oldStep['action_config'] ?? [])) {
                $modifiedSteps[] = [
                    'old' => $oldStep,
                    'new' => $step,
                ];
            }
        }

        // Find removed steps
        foreach ($version1->steps_data as $step) {
            $key = $step['action_type'] . '_' . ($step['order'] ?? 0);
            if (!$steps2->has($key)) {
                $removedSteps[] = $step;
            }
        }

        $changes['steps'] = [
            'added' => $addedSteps,
            'removed' => $removedSteps,
            'modified' => $modifiedSteps,
            'count_change' => count($version2->steps_data) - count($version1->steps_data),
        ];

        return [
            'version1' => [
                'id' => $version1->id,
                'version_number' => $version1->version_number,
                'created_at' => $version1->created_at->toISOString(),
            ],
            'version2' => [
                'id' => $version2->id,
                'version_number' => $version2->version_number,
                'created_at' => $version2->created_at->toISOString(),
            ],
            'changes' => $changes,
            'summary' => $this->generateComparisonSummary($changes),
        ];
    }

    /**
     * Generate a human-readable summary of changes.
     */
    protected function generateComparisonSummary(array $changes): array
    {
        $summary = [];

        if (!empty($changes['workflow'])) {
            foreach ($changes['workflow'] as $prop => $values) {
                $summary[] = ucfirst(str_replace('_', ' ', $prop)) . " changed";
            }
        }

        if (!empty($changes['steps']['added'])) {
            $count = count($changes['steps']['added']);
            $summary[] = "{$count} step(s) added";
        }

        if (!empty($changes['steps']['removed'])) {
            $count = count($changes['steps']['removed']);
            $summary[] = "{$count} step(s) removed";
        }

        if (!empty($changes['steps']['modified'])) {
            $count = count($changes['steps']['modified']);
            $summary[] = "{$count} step(s) modified";
        }

        return $summary ?: ['No changes detected'];
    }

    /**
     * Get the latest version for a workflow.
     */
    public function getLatestVersion(int $workflowId): ?WorkflowVersion
    {
        return WorkflowVersion::forWorkflow($workflowId)
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * Get the active version for a workflow.
     */
    public function getActiveVersion(int $workflowId): ?WorkflowVersion
    {
        return WorkflowVersion::forWorkflow($workflowId)
            ->active()
            ->first();
    }

    /**
     * Delete old versions, keeping the most recent N versions.
     */
    public function pruneOldVersions(int $workflowId, int $keepCount = 50): int
    {
        $versions = WorkflowVersion::forWorkflow($workflowId)
            ->orderByDesc('version_number')
            ->get();

        if ($versions->count() <= $keepCount) {
            return 0;
        }

        $toDelete = $versions->slice($keepCount);

        // Never delete the active version
        $toDelete = $toDelete->filter(fn($v) => !$v->is_active_version);

        $deletedCount = WorkflowVersion::whereIn('id', $toDelete->pluck('id'))->delete();

        Log::info('Pruned old workflow versions', [
            'workflow_id' => $workflowId,
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }
}
