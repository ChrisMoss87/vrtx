<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Modules;

use App\Application\Services\RecordService;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecordController extends Controller
{
    public function __construct(
        private readonly RecordService $recordService
    ) {}

    /**
     * List all records for a module with pagination and filtering.
     */
    public function index(Request $request, string $moduleApiName): JsonResponse
    {
        // TODO: Get module ID from API name
        $moduleId = 1; // Placeholder

        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'filters' => ['nullable', 'array'],
            'sort' => ['nullable', 'array'],
            'search' => ['nullable', 'string'],
            'search_fields' => ['nullable', 'array'],
        ]);

        try {
            if (isset($validated['search'])) {
                $result = $this->recordService->searchRecords(
                    $moduleId,
                    $validated['search'],
                    $validated['search_fields'] ?? [],
                    $validated['page'] ?? 1,
                    $validated['per_page'] ?? 15
                );
            } else {
                $result = $this->recordService->getRecords(
                    $moduleId,
                    $validated['filters'] ?? [],
                    $validated['sort'] ?? [],
                    $validated['page'] ?? 1,
                    $validated['per_page'] ?? 15
                );
            }

            return response()->json([
                'records' => array_map(fn (ModuleRecord $record) => $this->transformRecord($record), $result['data']),
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
        // TODO: Get module ID from API name
        $moduleId = 1; // Placeholder

        $record = $this->recordService->getRecord($moduleId, $recordId);

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
        // TODO: Get module ID from API name and validate based on module fields
        $moduleId = 1; // Placeholder

        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        try {
            $record = $this->recordService->createRecord(
                $moduleId,
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
        // TODO: Get module ID from API name
        $moduleId = 1; // Placeholder

        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        try {
            $record = $this->recordService->updateRecord(
                $moduleId,
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
     * Delete a record.
     */
    public function destroy(string $moduleApiName, int $recordId): JsonResponse
    {
        // TODO: Get module ID from API name
        $moduleId = 1; // Placeholder

        try {
            $this->recordService->deleteRecord($moduleId, $recordId);

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
     * Bulk delete records.
     */
    public function bulkDestroy(Request $request, string $moduleApiName): JsonResponse
    {
        // TODO: Get module ID from API name
        $moduleId = 1; // Placeholder

        $validated = $request->validate([
            'record_ids' => ['required', 'array'],
            'record_ids.*' => ['integer'],
        ]);

        try {
            $deletedCount = $this->recordService->bulkDeleteRecords(
                $moduleId,
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
    private function transformRecord(ModuleRecord $record): array
    {
        return [
            'id' => $record->id(),
            'module_id' => $record->moduleId(),
            'data' => $record->data(),
            'created_by' => $record->createdBy(),
            'updated_by' => $record->updatedBy(),
            'created_at' => $record->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $record->updatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
