<?php

namespace App\Services\Duplicates;

use App\Models\DuplicateCandidate;
use App\Models\MergeLog;
use App\Models\Module;
use App\Models\ModuleRecord;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateMergeService
{
    /**
     * Merge multiple records into a surviving record.
     *
     * @param int $survivingRecordId The record to keep
     * @param array $mergeRecordIds Records to merge into the surviving one
     * @param array $fieldSelections Which values to keep: ['field' => 'a'|'b'|'custom', ...]
     * @param int $userId User performing the merge
     * @return ModuleRecord
     * @throws Exception
     */
    public function mergeRecords(
        int $survivingRecordId,
        array $mergeRecordIds,
        array $fieldSelections,
        int $userId
    ): ModuleRecord {
        return DB::transaction(function () use ($survivingRecordId, $mergeRecordIds, $fieldSelections, $userId) {
            // Load the surviving record
            $survivingRecord = ModuleRecord::findOrFail($survivingRecordId);
            $module = $survivingRecord->module;

            // Load records to merge
            $mergeRecords = ModuleRecord::whereIn('id', $mergeRecordIds)->get();

            if ($mergeRecords->count() !== count($mergeRecordIds)) {
                throw new Exception('One or more records to merge were not found.');
            }

            // Verify all records belong to the same module
            foreach ($mergeRecords as $record) {
                if ($record->module_id !== $module->id) {
                    throw new Exception('All records must belong to the same module.');
                }
            }

            // Store merged records data for audit
            $mergedData = $mergeRecords->map(fn ($r) => [
                'id' => $r->id,
                'data' => $r->data,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ])->toArray();

            // Build merged data based on field selections
            $finalData = $this->buildMergedData(
                $survivingRecord,
                $mergeRecords,
                $fieldSelections
            );

            // Update surviving record with merged data
            $survivingRecord->data = $finalData;
            $survivingRecord->save();

            // Transfer relationships from merged records
            $this->transferRelationships($survivingRecord, $mergeRecords);

            // Transfer activities/timeline entries
            $this->transferActivities($survivingRecord, $mergeRecords);

            // Update any duplicate candidates involving these records
            $this->updateDuplicateCandidates($survivingRecord, $mergeRecordIds, $userId);

            // Create merge log
            MergeLog::create([
                'module_id' => $module->id,
                'surviving_record_id' => $survivingRecordId,
                'merged_record_ids' => $mergeRecordIds,
                'field_selections' => $fieldSelections,
                'merged_data' => $mergedData,
                'merged_by' => $userId,
            ]);

            // Delete merged records (soft delete if available)
            foreach ($mergeRecords as $record) {
                $record->delete();
            }

            return $survivingRecord->fresh();
        });
    }

    /**
     * Build merged data from field selections.
     */
    protected function buildMergedData(
        ModuleRecord $survivingRecord,
        $mergeRecords,
        array $fieldSelections
    ): array {
        $finalData = $survivingRecord->data ?? [];

        foreach ($fieldSelections as $field => $selection) {
            if ($selection === 'a' || $selection === 'surviving') {
                // Keep surviving record's value (already in finalData)
                continue;
            }

            if (is_array($selection) && isset($selection['custom'])) {
                // Custom value
                $finalData[$field] = $selection['custom'];
                continue;
            }

            if (is_array($selection) && isset($selection['record_id'])) {
                // Specific record's value
                $sourceRecord = $mergeRecords->firstWhere('id', $selection['record_id']);
                if ($sourceRecord && isset($sourceRecord->data[$field])) {
                    $finalData[$field] = $sourceRecord->data[$field];
                }
                continue;
            }

            // 'b' or numeric index - take from first merge record
            if ($selection === 'b' || is_numeric($selection)) {
                $index = is_numeric($selection) ? (int) $selection : 0;
                $sourceRecord = $mergeRecords->values()->get($index);
                if ($sourceRecord && isset($sourceRecord->data[$field])) {
                    $finalData[$field] = $sourceRecord->data[$field];
                }
            }
        }

        return $finalData;
    }

    /**
     * Transfer relationships from merged records to surviving record.
     */
    protected function transferRelationships(ModuleRecord $survivingRecord, $mergeRecords): void
    {
        foreach ($mergeRecords as $mergedRecord) {
            // Update any records that reference the merged record
            // This handles lookup fields and relationships

            // Update ModuleRecordRelationship entries
            DB::table('module_record_relationships')
                ->where('related_record_id', $mergedRecord->id)
                ->update(['related_record_id' => $survivingRecord->id]);

            DB::table('module_record_relationships')
                ->where('module_record_id', $mergedRecord->id)
                ->update(['module_record_id' => $survivingRecord->id]);

            // Update notes referencing this record
            DB::table('notes')
                ->where('notable_type', ModuleRecord::class)
                ->where('notable_id', $mergedRecord->id)
                ->update(['notable_id' => $survivingRecord->id]);

            // Update tasks referencing this record
            DB::table('tasks')
                ->where('taskable_type', ModuleRecord::class)
                ->where('taskable_id', $mergedRecord->id)
                ->update(['taskable_id' => $survivingRecord->id]);

            // Update emails referencing this record
            if (DB::getSchemaBuilder()->hasTable('emails')) {
                DB::table('emails')
                    ->where('emailable_type', ModuleRecord::class)
                    ->where('emailable_id', $mergedRecord->id)
                    ->update(['emailable_id' => $survivingRecord->id]);
            }

            // Update attachments referencing this record
            if (DB::getSchemaBuilder()->hasTable('attachments')) {
                DB::table('attachments')
                    ->where('attachable_type', ModuleRecord::class)
                    ->where('attachable_id', $mergedRecord->id)
                    ->update(['attachable_id' => $survivingRecord->id]);
            }

            // Update audit logs if needed
            if (DB::getSchemaBuilder()->hasTable('audit_logs')) {
                DB::table('audit_logs')
                    ->where('auditable_type', ModuleRecord::class)
                    ->where('auditable_id', $mergedRecord->id)
                    ->update(['auditable_id' => $survivingRecord->id]);
            }
        }
    }

    /**
     * Transfer activities and timeline from merged records.
     */
    protected function transferActivities(ModuleRecord $survivingRecord, $mergeRecords): void
    {
        foreach ($mergeRecords as $mergedRecord) {
            // Transfer any activity records
            if (DB::getSchemaBuilder()->hasTable('activities')) {
                DB::table('activities')
                    ->where('subject_type', ModuleRecord::class)
                    ->where('subject_id', $mergedRecord->id)
                    ->update([
                        'subject_id' => $survivingRecord->id,
                        'properties' => DB::raw("jsonb_set(COALESCE(properties, '{}'), '{merged_from}', '\"" . $mergedRecord->id . "\"')"),
                    ]);
            }
        }
    }

    /**
     * Update duplicate candidates after merge.
     */
    protected function updateDuplicateCandidates(
        ModuleRecord $survivingRecord,
        array $mergedRecordIds,
        int $userId
    ): void {
        // Mark candidates as merged
        DuplicateCandidate::where(function ($query) use ($survivingRecord, $mergedRecordIds) {
            $allIds = array_merge([$survivingRecord->id], $mergedRecordIds);
            $query->whereIn('record_id_a', $allIds)
                ->whereIn('record_id_b', $allIds);
        })->update([
            'status' => DuplicateCandidate::STATUS_MERGED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);

        // Delete candidates that reference merged (deleted) records
        DuplicateCandidate::whereIn('record_id_a', $mergedRecordIds)
            ->orWhereIn('record_id_b', $mergedRecordIds)
            ->delete();
    }

    /**
     * Preview merge result without actually merging.
     */
    public function previewMerge(
        int $recordAId,
        int $recordBId,
        array $fieldSelections
    ): array {
        $recordA = ModuleRecord::findOrFail($recordAId);
        $recordB = ModuleRecord::findOrFail($recordBId);
        $module = $recordA->module;

        // Get field definitions
        $fields = $module->fields ?? [];

        $preview = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? $field['api_name'] ?? null;
            if (!$fieldName) {
                continue;
            }

            $valueA = $recordA->data[$fieldName] ?? null;
            $valueB = $recordB->data[$fieldName] ?? null;

            $selection = $fieldSelections[$fieldName] ?? 'a';
            $selectedValue = $selection === 'b' ? $valueB : $valueA;

            if (is_array($selection) && isset($selection['custom'])) {
                $selectedValue = $selection['custom'];
            }

            $preview[$fieldName] = [
                'field' => $fieldName,
                'label' => $field['label'] ?? $fieldName,
                'value_a' => $valueA,
                'value_b' => $valueB,
                'selected_value' => $selectedValue,
                'selection' => $selection,
                'differs' => $valueA !== $valueB,
            ];
        }

        return [
            'record_a' => [
                'id' => $recordA->id,
                'data' => $recordA->data,
            ],
            'record_b' => [
                'id' => $recordB->id,
                'data' => $recordB->data,
            ],
            'preview' => $preview,
            'field_count' => count($preview),
            'differing_fields' => collect($preview)->filter(fn ($f) => $f['differs'])->count(),
        ];
    }

    /**
     * Get merge history for a module.
     */
    public function getMergeHistory(int $moduleId, int $perPage = 20)
    {
        return MergeLog::with(['survivingRecord', 'mergedByUser'])
            ->forModule($moduleId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
