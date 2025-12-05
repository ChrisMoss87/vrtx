<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataManagement;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Jobs\ValidateImportJob;
use App\Models\Import;
use App\Models\Module;
use App\Services\Import\FileParser;
use App\Services\Import\ImportEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function __construct(
        protected FileParser $fileParser,
        protected ImportEngine $importEngine
    ) {}

    /**
     * List imports for a module.
     */
    public function index(Request $request, string $moduleApiName): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $query = Import::where('module_id', $module->id)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $imports = $query->paginate($request->get('per_page', 20));

        return response()->json($imports);
    }

    /**
     * Get a single import.
     */
    public function show(string $moduleApiName, int $importId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $import = Import::where('module_id', $module->id)
            ->with(['user:id,name,email', 'rows' => function ($q) {
                $q->where('status', '!=', 'success')
                    ->orderBy('row_number')
                    ->limit(100);
            }])
            ->findOrFail($importId);

        return response()->json([
            'import' => $import,
            'summary' => [
                'total_rows' => $import->total_rows,
                'processed_rows' => $import->processed_rows,
                'successful_rows' => $import->successful_rows,
                'failed_rows' => $import->failed_rows,
                'skipped_rows' => $import->skipped_rows,
                'progress_percentage' => $import->getProgressPercentage(),
            ],
        ]);
    }

    /**
     * Upload a file and create import record.
     */
    public function upload(Request $request, string $moduleApiName): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:51200', // 50MB max
            'name' => 'nullable|string|max:255',
        ]);

        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $fileType = strtolower($file->getClientOriginalExtension());
        $storagePath = 'imports/' . date('Y/m') . '/' . Str::uuid() . '.' . $fileType;

        // Store file
        Storage::disk('imports')->put($storagePath, file_get_contents($file->getRealPath()));

        // Parse preview
        $preview = $this->fileParser->parsePreview($storagePath, $fileType);

        // Auto-map columns
        $suggestedMapping = $this->importEngine->autoMapColumns($preview['headers'], $module);

        // Create import record
        $import = Import::create([
            'module_id' => $module->id,
            'user_id' => $request->user()->id,
            'name' => $request->name ?? pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'file_path' => $storagePath,
            'file_type' => $fileType,
            'file_size' => $file->getSize(),
            'total_rows' => $preview['total_rows'],
        ]);

        // Get module fields for mapping UI
        $moduleFields = $module->fields()
            ->select('id', 'api_name', 'label', 'type', 'is_required')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'import' => $import,
            'preview' => $preview,
            'suggested_mapping' => $suggestedMapping,
            'module_fields' => $moduleFields,
        ], 201);
    }

    /**
     * Configure import mapping and options.
     */
    public function configure(Request $request, string $moduleApiName, int $importId): JsonResponse
    {
        $request->validate([
            'column_mapping' => 'required|array',
            'import_options' => 'nullable|array',
            'import_options.duplicate_handling' => 'nullable|in:skip,update,create',
            'import_options.duplicate_check_field' => 'nullable|string',
            'import_options.skip_empty_rows' => 'nullable|boolean',
        ]);

        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $import = Import::where('module_id', $module->id)
            ->where('status', Import::STATUS_PENDING)
            ->findOrFail($importId);

        $import->update([
            'column_mapping' => $request->column_mapping,
            'import_options' => $request->import_options ?? [],
        ]);

        return response()->json([
            'import' => $import->fresh(),
            'message' => 'Import configured successfully',
        ]);
    }

    /**
     * Validate import data.
     */
    public function validate(string $moduleApiName, int $importId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $import = Import::where('module_id', $module->id)
            ->whereIn('status', [Import::STATUS_PENDING])
            ->findOrFail($importId);

        // Dispatch validation job
        ValidateImportJob::dispatch($import);

        return response()->json([
            'message' => 'Validation started',
            'import' => $import->fresh(),
        ]);
    }

    /**
     * Execute the import.
     */
    public function execute(string $moduleApiName, int $importId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $import = Import::where('module_id', $module->id)
            ->where('status', Import::STATUS_VALIDATED)
            ->findOrFail($importId);

        // Dispatch import job
        ProcessImportJob::dispatch($import);

        return response()->json([
            'message' => 'Import started',
            'import' => $import->fresh(),
        ]);
    }

    /**
     * Cancel an import.
     */
    public function cancel(string $moduleApiName, int $importId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $import = Import::where('module_id', $module->id)->findOrFail($importId);

        if (!$import->canBeCancelled()) {
            return response()->json(['message' => 'Import cannot be cancelled'], 422);
        }

        $import->markAsCancelled();

        return response()->json([
            'message' => 'Import cancelled',
            'import' => $import->fresh(),
        ]);
    }

    /**
     * Get import errors/failed rows.
     */
    public function errors(Request $request, string $moduleApiName, int $importId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $import = Import::where('module_id', $module->id)->findOrFail($importId);

        $failedRows = $import->rows()
            ->where('status', 'failed')
            ->orderBy('row_number')
            ->paginate($request->get('per_page', 50));

        return response()->json($failedRows);
    }

    /**
     * Download import template.
     */
    public function template(string $moduleApiName): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $fields = $module->fields()
            ->where('is_system', false)
            ->orderBy('display_order')
            ->get()
            ->map(fn ($field) => [
                'api_name' => $field->api_name,
                'label' => $field->label,
                'type' => $field->type,
                'is_required' => $field->is_required,
                'description' => $this->getFieldTemplateDescription($field),
            ]);

        return response()->json([
            'module' => [
                'name' => $module->name,
                'api_name' => $module->api_name,
            ],
            'fields' => $fields,
        ]);
    }

    /**
     * Get field description for template.
     */
    protected function getFieldTemplateDescription($field): string
    {
        $desc = match ($field->type) {
            'email' => 'Email address',
            'url' => 'Valid URL',
            'number' => 'Numeric value',
            'integer' => 'Whole number',
            'currency' => 'Currency value (e.g., 1000.00)',
            'percent' => 'Percentage (e.g., 50)',
            'date' => 'Date (YYYY-MM-DD)',
            'datetime' => 'Date and time (YYYY-MM-DD HH:MM:SS)',
            'boolean', 'switch' => 'true/false, yes/no, 1/0',
            'select', 'radio' => 'One of: ' . $field->options()->pluck('label')->implode(', '),
            'multiselect' => 'Comma-separated values from: ' . $field->options()->pluck('label')->implode(', '),
            default => 'Text value',
        };

        if ($field->is_required) {
            $desc .= ' (Required)';
        }

        return $desc;
    }

    /**
     * Delete an import and its data.
     */
    public function destroy(string $moduleApiName, int $importId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $import = Import::where('module_id', $module->id)->findOrFail($importId);

        if (!$import->isTerminal()) {
            return response()->json(['message' => 'Cannot delete import in progress'], 422);
        }

        // Delete file
        if ($import->file_path && Storage::disk('imports')->exists($import->file_path)) {
            Storage::disk('imports')->delete($import->file_path);
        }

        $import->delete();

        return response()->json(['message' => 'Import deleted']);
    }
}
