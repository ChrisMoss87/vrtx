<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataManagement;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    /**
     * List exports for a module.
     */
    public function index(Request $request, string $moduleApiName): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $query = DB::table('exports')->where('module_id', $module->id)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $exports = $query->paginate($request->get('per_page', 20));

        return response()->json($exports);
    }

    /**
     * Get a single export.
     */
    public function show(string $moduleApiName, int $exportId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $export = DB::table('exports')->where('module_id', $module->id)
            ->with('user:id,name,email')
            ->findOrFail($exportId);

        return response()->json([
            'export' => $export,
            'is_downloadable' => $export->isDownloadable(),
            'download_url' => $export->isDownloadable()
                ? route('exports.download', ['moduleApiName' => $moduleApiName, 'exportId' => $export->id])
                : null,
        ]);
    }

    /**
     * Create and start an export.
     */
    public function store(Request $request, string $moduleApiName): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'file_type' => 'required|in:csv,xlsx',
            'selected_fields' => 'required|array|min:1',
            'selected_fields.*' => 'string',
            'filters' => 'nullable|array',
            'sorting' => 'nullable|array',
            'export_options' => 'nullable|array',
            'export_options.include_headers' => 'nullable|boolean',
            'export_options.date_format' => 'nullable|string',
        ]);

        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        // Validate selected fields exist
        $moduleFields = $module->fields()->pluck('api_name')->toArray();
        $invalidFields = array_diff($request->selected_fields, $moduleFields);

        if (!empty($invalidFields)) {
            return response()->json([
                'message' => 'Invalid fields: ' . implode(', ', $invalidFields),
            ], 422);
        }

        // Count records that will be exported
        $recordCount = $module->records()
            ->when($request->filters, fn ($q) => $this->applyFilters($q, $request->filters))
            ->count();

        $export = DB::table('exports')->insertGetId([
            'module_id' => $module->id,
            'user_id' => $request->user()->id,
            'name' => $request->name ?? $module->name . ' Export - ' . now()->format('Y-m-d H:i'),
            'file_type' => $request->file_type,
            'selected_fields' => $request->selected_fields,
            'filters' => $request->filters,
            'sorting' => $request->sorting,
            'export_options' => array_merge([
                'include_headers' => true,
                'date_format' => 'Y-m-d',
            ], $request->export_options ?? []),
            'total_records' => $recordCount,
        ]);

        // Dispatch export job
        ProcessExportJob::dispatch($export);

        return response()->json([
            'export' => $export,
            'message' => 'Export started',
        ], 201);
    }

    /**
     * Download an export file.
     */
    public function download(string $moduleApiName, int $exportId): StreamedResponse|JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $export = DB::table('exports')->where('module_id', $module->id)->findOrFail($exportId);

        if (!$export->isDownloadable()) {
            return response()->json(['message' => 'Export is not available for download'], 404);
        }

        $export->incrementDownloadCount();

        return Storage::disk('exports')->download(
            $export->file_path,
            $export->file_name,
            [
                'Content-Type' => match ($export->file_type) {
                    'csv' => 'text/csv',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    default => 'application/octet-stream',
                },
            ]
        );
    }

    /**
     * Delete an export.
     */
    public function destroy(string $moduleApiName, int $exportId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $export = DB::table('exports')->where('module_id', $module->id)->findOrFail($exportId);

        // Delete file if exists
        if ($export->file_path && Storage::disk('exports')->exists($export->file_path)) {
            Storage::disk('exports')->delete($export->file_path);
        }

        $export->delete();

        return response()->json(['message' => 'Export deleted']);
    }

    /**
     * List export templates for a module.
     */
    public function templates(Request $request, string $moduleApiName): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $templates = DB::table('export_templates')->where('module_id', $module->id)
            ->accessibleBy($request->user()->id)
            ->with('user:id,name')
            ->orderBy('name')
            ->get();

        return response()->json($templates);
    }

    /**
     * Create an export template.
     */
    public function storeTemplate(Request $request, string $moduleApiName): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'selected_fields' => 'required|array|min:1',
            'filters' => 'nullable|array',
            'sorting' => 'nullable|array',
            'export_options' => 'nullable|array',
            'default_file_type' => 'nullable|in:csv,xlsx',
            'is_shared' => 'nullable|boolean',
        ]);

        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $template = DB::table('export_templates')->insertGetId([
            'module_id' => $module->id,
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
            'selected_fields' => $request->selected_fields,
            'filters' => $request->filters,
            'sorting' => $request->sorting,
            'export_options' => $request->export_options,
            'default_file_type' => $request->default_file_type ?? 'csv',
            'is_shared' => $request->is_shared ?? false,
        ]);

        return response()->json($template, 201);
    }

    /**
     * Update an export template.
     */
    public function updateTemplate(Request $request, string $moduleApiName, int $templateId): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'selected_fields' => 'sometimes|required|array|min:1',
            'filters' => 'nullable|array',
            'sorting' => 'nullable|array',
            'export_options' => 'nullable|array',
            'default_file_type' => 'nullable|in:csv,xlsx',
            'is_shared' => 'nullable|boolean',
        ]);

        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $template = DB::table('export_templates')->where('module_id', $module->id)
            ->where('user_id', $request->user()->id)
            ->findOrFail($templateId);

        $template->update($request->only([
            'name',
            'description',
            'selected_fields',
            'filters',
            'sorting',
            'export_options',
            'default_file_type',
            'is_shared',
        ]));

        return response()->json($template->fresh());
    }

    /**
     * Delete an export template.
     */
    public function destroyTemplate(Request $request, string $moduleApiName, int $templateId): JsonResponse
    {
        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $template = DB::table('export_templates')->where('module_id', $module->id)
            ->where('user_id', $request->user()->id)
            ->findOrFail($templateId);

        $template->delete();

        return response()->json(['message' => 'Template deleted']);
    }

    /**
     * Export using a template.
     */
    public function exportFromTemplate(Request $request, string $moduleApiName, int $templateId): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'file_type' => 'nullable|in:csv,xlsx',
        ]);

        $module = Module::findByApiName($moduleApiName);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $template = DB::table('export_templates')->where('module_id', $module->id)
            ->accessibleBy($request->user()->id)
            ->findOrFail($templateId);

        $export = $template->createExport(
            $request->user()->id,
            $request->name,
            $request->file_type
        );

        // Count records
        $export->total_records = $module->records()
            ->when($export->filters, fn ($q) => $this->applyFilters($q, $export->filters))
            ->count();
        $export->save();

        // Dispatch export job
        ProcessExportJob::dispatch($export);

        return response()->json([
            'export' => $export,
            'message' => 'Export started',
        ], 201);
    }

    /**
     * Apply filters to query.
     */
    protected function applyFilters($query, ?array $filters)
    {
        if (empty($filters)) {
            return $query;
        }

        // Basic filter implementation - can be enhanced
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;

            if (!$field) {
                continue;
            }

            $query->where("data->{$field}", $operator, $value);
        }

        return $query;
    }
}
