<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Modules;

use App\Application\Services\RecordService;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Http\Controllers\Controller;
use App\Services\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordController extends Controller
{
    public function __construct(
        private readonly RecordService $recordService,
        private readonly RbacService $rbacService
    ) {}

    /**
     * List all records for a module with pagination and filtering.
     */
    public function index(Request $request, string $moduleApiName): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        // Check view permission
        if (!$this->rbacService->canAccessModule($request->user(), $module, 'view')) {
            return response()->json([
                'error' => 'You do not have permission to view records in this module',
            ], 403);
        }

        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'filters' => ['nullable', 'array'],
            'sort' => ['nullable', 'array'],
            'search' => ['nullable', 'string'],
            'search_fields' => ['nullable', 'array'],
        ]);

        // Ensure integers
        $page = isset($validated['page']) ? (int) $validated['page'] : 1;
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;

        try {
            if (isset($validated['search'])) {
                $result = $this->recordService->searchRecords(
                    $module->id,
                    $validated['search'],
                    $validated['search_fields'] ?? [],
                    $page,
                    $perPage
                );
            } else {
                $result = $this->recordService->getRecords(
                    $module->id,
                    $validated['filters'] ?? [],
                    $validated['sort'] ?? [],
                    $page,
                    $perPage
                );
            }

            // Filter hidden fields from records
            $hiddenFields = $this->rbacService->getHiddenFields($request->user(), $module);

            return response()->json([
                'records' => array_map(fn (ModuleRecord $record) => $this->transformRecord($record, $hiddenFields), $result['data']),
                'meta' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get a single record.
     */
    public function show(string $moduleApiName, int $recordId): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        $record = $this->recordService->getRecord($module->id, $recordId);

        if (!$record) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        return response()->json([
            'record' => $this->transformRecord($record),
        ]);
    }

    /**
     * Create a new record.
     */
    public function store(Request $request, string $moduleApiName): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        // Check create permission
        if (!$this->rbacService->canAccessModule($request->user(), $module, 'create')) {
            return response()->json([
                'error' => 'You do not have permission to create records in this module',
            ], 403);
        }

        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        try {
            $record = $this->recordService->createRecord(
                $module->id,
                $validated['data'],
                auth()->id()
            );

            return response()->json([
                'message' => 'Record created successfully',
                'record' => $this->transformRecord($record),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update an existing record.
     */
    public function update(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        // Check edit permission at module level
        if (!$this->rbacService->canAccessModule($request->user(), $module, 'edit')) {
            return response()->json([
                'error' => 'You do not have permission to edit records in this module',
            ], 403);
        }

        // Check record-level access (ownership rules)
        $existingRecord = \App\Models\ModuleRecord::find($recordId);
        if ($existingRecord && !$this->rbacService->canEditRecord($request->user(), $existingRecord)) {
            return response()->json([
                'error' => 'You do not have permission to edit this record',
            ], 403);
        }

        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        try {
            $record = $this->recordService->updateRecord(
                $module->id,
                $recordId,
                $validated['data'],
                auth()->id()
            );

            return response()->json([
                'message' => 'Record updated successfully',
                'record' => $this->transformRecord($record),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Partial update (PATCH) for inline editing - merges with existing data.
     */
    public function patch(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        // Get existing record
        $existingRecord = $this->recordService->getRecord($module->id, $recordId);

        if (!$existingRecord) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        // Accept either 'data' wrapper or direct field values
        $inputData = $request->has('data') ? $request->input('data') : $request->except(['_method', '_token']);

        // Merge new data with existing data
        $mergedData = array_merge($existingRecord->data(), $inputData);

        try {
            $record = $this->recordService->updateRecord(
                $module->id,
                $recordId,
                $mergedData,
                auth()->id()
            );

            return response()->json([
                'message' => 'Record updated successfully',
                'record' => $this->transformRecord($record),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk update multiple records with the same field values.
     */
    public function bulkUpdate(Request $request, string $moduleApiName): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
            'data' => ['required', 'array', 'min:1'],
        ]);

        try {
            $updatedCount = 0;
            $errors = [];

            foreach ($validated['record_ids'] as $recordId) {
                try {
                    // Get existing record
                    $existingRecord = $this->recordService->getRecord($module->id, $recordId);

                    if (!$existingRecord) {
                        $errors[] = "Record {$recordId} not found";
                        continue;
                    }

                    // Merge new data with existing data
                    $mergedData = array_merge($existingRecord->data(), $validated['data']);

                    $this->recordService->updateRecord(
                        $module->id,
                        $recordId,
                        $mergedData,
                        auth()->id()
                    );

                    $updatedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Record {$recordId}: " . $e->getMessage();
                }
            }

            $response = [
                'message' => "{$updatedCount} record(s) updated successfully",
                'updated_count' => $updatedCount,
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a record.
     */
    public function destroy(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        // Check delete permission at module level
        if (!$this->rbacService->canAccessModule($request->user(), $module, 'delete')) {
            return response()->json([
                'error' => 'You do not have permission to delete records in this module',
            ], 403);
        }

        // Check record-level access (ownership rules)
        $existingRecord = \App\Models\ModuleRecord::find($recordId);
        if ($existingRecord && !$this->rbacService->canDeleteRecord($request->user(), $existingRecord)) {
            return response()->json([
                'error' => 'You do not have permission to delete this record',
            ], 403);
        }

        try {
            $this->recordService->deleteRecord($module->id, $recordId);

            return response()->json([
                'message' => 'Record deleted successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Lookup search endpoint for lookup fields.
     * Returns minimal data (id + display field) for typeahead search.
     */
    public function lookup(Request $request, string $moduleApiName): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'display_field' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'selected_ids' => ['nullable', 'array'],
            'selected_ids.*' => ['integer'],
        ]);

        $searchQuery = $validated['q'] ?? '';
        $displayField = $validated['display_field'] ?? 'name';
        $limit = $validated['limit'] ?? 20;
        $selectedIds = $validated['selected_ids'] ?? [];

        try {
            // Get search fields from module - use searchable fields
            $searchFields = [];
            foreach ($module->blocks as $block) {
                foreach ($block->fields as $field) {
                    if ($field->is_searchable) {
                        $searchFields[] = $field->api_name;
                    }
                }
            }

            // If no searchable fields, default to display field
            if (empty($searchFields)) {
                $searchFields = [$displayField];
            }

            $results = [];

            // If there are selected IDs, fetch those records first
            if (!empty($selectedIds)) {
                $selectedRecords = $this->recordService->getRecordsByIds($module->id, $selectedIds);
                foreach ($selectedRecords as $record) {
                    $data = $record->data();
                    $results[] = [
                        'id' => $record->id(),
                        'label' => $data[$displayField] ?? "Record #{$record->id()}",
                        'data' => $data,
                    ];
                }
            }

            // Search for additional records
            if (!empty($searchQuery)) {
                $searchResult = $this->recordService->searchRecords(
                    $module->id,
                    $searchQuery,
                    $searchFields,
                    1,
                    $limit
                );

                foreach ($searchResult['data'] as $record) {
                    // Skip if already in selected results
                    $recordId = $record->id();
                    $alreadyIncluded = false;
                    foreach ($results as $existing) {
                        if ($existing['id'] === $recordId) {
                            $alreadyIncluded = true;
                            break;
                        }
                    }

                    if (!$alreadyIncluded) {
                        $data = $record->data();
                        $results[] = [
                            'id' => $recordId,
                            'label' => $data[$displayField] ?? "Record #{$recordId}",
                            'data' => $data,
                        ];
                    }
                }
            } elseif (empty($selectedIds)) {
                // No search query and no selected IDs - return first N records
                $defaultResult = $this->recordService->getRecords(
                    $module->id,
                    [],
                    [],
                    1,
                    $limit
                );

                foreach ($defaultResult['data'] as $record) {
                    $data = $record->data();
                    $results[] = [
                        'id' => $record->id(),
                        'label' => $data[$displayField] ?? "Record #{$record->id()}",
                        'data' => $data,
                    ];
                }
            }

            return response()->json([
                'results' => array_slice($results, 0, $limit),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk delete records.
     */
    public function bulkDestroy(Request $request, string $moduleApiName): JsonResponse
    {
        // Get module by API name
        $module = \App\Models\Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'error' => 'Module not found',
            ], 404);
        }

        $validated = $request->validate([
            'record_ids' => ['required', 'array'],
            'record_ids.*' => ['integer'],
        ]);

        try {
            $deletedCount = $this->recordService->bulkDeleteRecords(
                $module->id,
                $validated['record_ids']
            );

            return response()->json([
                'message' => "{$deletedCount} records deleted successfully",
                'deleted_count' => $deletedCount,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Transform record entity to array.
     */
    private function transformRecord(ModuleRecord $record, array $hiddenFields = []): array
    {
        $data = $record->data();

        // Remove hidden fields from data
        foreach ($hiddenFields as $field) {
            unset($data[$field]);
        }

        return [
            'id' => $record->id(),
            'module_id' => $record->moduleId(),
            'data' => $data,
            'created_by' => $record->createdBy(),
            'updated_by' => $record->updatedBy(),
            'created_at' => $record->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $record->updatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
