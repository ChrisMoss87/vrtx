<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ViewsController extends Controller
{
    /**
     * Get all views for a module accessible by the current user.
     */
    public function index(Request $request, string $moduleApiName): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $views = DB::table('module_views')
            ->where('module_id', $module->id)
            ->where(function ($query) use ($userId) {
                $query->where('is_system', true)
                    ->orWhere('created_by', $userId)
                    ->orWhere('is_shared', true);
            })
            ->orderBy('is_system', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'views' => $views,
        ]);
    }

    /**
     * Get a specific view.
     */
    public function show(Request $request, string $moduleApiName, int $viewId): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $view = DB::table('module_views')
            ->where('module_id', $module->id)
            ->where('id', $viewId)
            ->where(function ($query) use ($userId) {
                $query->where('is_system', true)
                    ->orWhere('created_by', $userId)
                    ->orWhere('is_shared', true);
            })
            ->first();

        if (!$view) {
            return response()->json([
                'success' => false,
                'message' => 'View not found or access denied',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'view' => $view,
        ]);
    }

    /**
     * Create a new view.
     */
    public function store(Request $request, string $moduleApiName): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'view_type' => 'nullable|string|in:table,kanban',
            'kanban_config' => 'nullable|array',
            'kanban_config.group_by_field' => 'required_if:view_type,kanban|string',
            'kanban_config.value_field' => 'nullable|string',
            'kanban_config.title_field' => 'nullable|string',
            'kanban_config.subtitle_field' => 'nullable|string',
            'kanban_config.card_fields' => 'nullable|array',
            'filters' => 'nullable|array',
            'sorting' => 'nullable|array',
            'column_visibility' => 'nullable|array',
            'column_order' => 'nullable|array',
            'column_widths' => 'nullable|array',
            'page_size' => 'nullable|integer|min:10|max:200',
            'is_default' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
        ]);

        $userId = Auth::id();

        // If setting as default, unset other default views for this user
        if ($validated['is_default'] ?? false) {
            DB::table('module_views')
                ->where('module_id', $module->id)
                ->where('created_by', $userId)
                ->update(['is_default' => false]);
        }

        // Build insert data with proper JSON encoding
        $insertData = [
            'module_id' => $module->id,
            'created_by' => $userId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'view_type' => $validated['view_type'] ?? 'table',
            'kanban_config' => isset($validated['kanban_config']) ? json_encode($validated['kanban_config']) : null,
            'filters' => isset($validated['filters']) ? json_encode($validated['filters']) : json_encode([]),
            'sorting' => isset($validated['sorting']) ? json_encode($validated['sorting']) : json_encode([]),
            'column_visibility' => isset($validated['column_visibility']) ? json_encode($validated['column_visibility']) : null,
            'column_order' => isset($validated['column_order']) ? json_encode($validated['column_order']) : null,
            'column_widths' => isset($validated['column_widths']) ? json_encode($validated['column_widths']) : null,
            'page_size' => $validated['page_size'] ?? 50,
            'is_default' => $validated['is_default'] ?? false,
            'is_shared' => $validated['is_shared'] ?? false,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $viewId = DB::table('module_views')->insertGetId($insertData);
        $view = DB::table('module_views')->where('id', $viewId)->first();

        return response()->json([
            'success' => true,
            'message' => 'View created successfully',
            'view' => $view,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing view.
     */
    public function update(Request $request, string $moduleApiName, int $viewId): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $view = DB::table('module_views')->where('module_id', $module->id)
            ->where('id', $viewId)
            ->where('user_id', $userId)
            ->first();

        if (!$view) {
            return response()->json([
                'success' => false,
                'message' => 'View not found or you do not have permission to edit it',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'view_type' => 'nullable|string|in:table,kanban',
            'kanban_config' => 'nullable|array',
            'kanban_config.group_by_field' => 'nullable|string',
            'kanban_config.value_field' => 'nullable|string',
            'kanban_config.title_field' => 'nullable|string',
            'kanban_config.subtitle_field' => 'nullable|string',
            'kanban_config.card_fields' => 'nullable|array',
            'filters' => 'nullable|array',
            'sorting' => 'nullable|array',
            'column_visibility' => 'nullable|array',
            'column_order' => 'nullable|array',
            'column_widths' => 'nullable|array',
            'page_size' => 'nullable|integer|min:10|max:200',
            'is_default' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
        ]);

        // If setting as default, unset other default views for this user
        if (($validated['is_default'] ?? false) && !$view->is_default) {
            DB::table('module_views')
                ->where('module_id', $module->id)
                ->where('user_id', $userId)
                ->where('id', '!=', $viewId)
                ->update(['is_default' => false]);
        }

        // Add updated_at timestamp
        $validated['updated_at'] = now();

        DB::table('module_views')
            ->where('id', $viewId)
            ->update($validated);

        // Fetch updated view
        $updatedView = DB::table('module_views')->where('id', $viewId)->first();

        return response()->json([
            'success' => true,
            'message' => 'View updated successfully',
            'view' => $updatedView,
        ]);
    }

    /**
     * Delete a view.
     */
    public function destroy(Request $request, string $moduleApiName, int $viewId): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $deleted = DB::table('module_views')
            ->where('module_id', $module->id)
            ->where('id', $viewId)
            ->where('created_by', $userId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'View not found or you do not have permission to delete it',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'View deleted successfully',
        ]);
    }

    /**
     * Get the default view for a module (system default or user default).
     */
    public function getDefaultView(Request $request, string $moduleApiName): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        // First, try to find user's default view
        $view = DB::table('module_views')->where('module_id', $module->id)
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->first();

        // If no user default, return module's default settings
        if (!$view) {
            return response()->json([
                'success' => true,
                'view' => null,
                'module_defaults' => [
                    'filters' => $module->default_filters ?? [],
                    'sorting' => $module->default_sorting ?? [],
                    'column_visibility' => $module->default_column_visibility ?? [],
                    'page_size' => $module->default_page_size ?? 50,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'view' => $view,
            'module_defaults' => null,
        ]);
    }

    /**
     * Get fields that can be used for kanban grouping (fields with options).
     */
    public function getKanbanFields(Request $request, string $moduleApiName): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Get fields with options (select, multiselect, radio, picklist)
        $fields = DB::table('fields')
            ->where('module_id', $module->id)
            ->whereIn('type', ['picklist', 'select', 'radio'])
            ->orderBy('display_order')
            ->get()
            ->map(function ($field) {
                // Fetch options for this field
                $options = DB::table('field_options')
                    ->where('field_id', $field->id)
                    ->orderBy('display_order')
                    ->get();

                return [
                    'api_name' => $field->api_name,
                    'label' => $field->label,
                    'type' => $field->type,
                    'options' => $options->map(function ($opt) {
                        $metadata = is_string($opt->metadata) ? json_decode($opt->metadata, true) : ($opt->metadata ?? []);
                        return [
                            'value' => $opt->value,
                            'label' => $opt->label,
                            'color' => $opt->color ?? $metadata['color'] ?? null,
                            'display_order' => $opt->display_order,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'fields' => $fields,
        ]);
    }

    /**
     * Get kanban data for a view (records grouped by field options).
     *
     * Supports two modes:
     * 1. Saved view mode: viewId > 0, uses view's kanban_config
     * 2. Dynamic mode: viewId = 0, uses group_by_field query parameter
     */
    public function getKanbanData(Request $request, string $moduleApiName, int $viewId): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();
        $view = null;
        $config = [];

        // Dynamic mode: viewId = 0, use query parameter for group_by_field
        if ($viewId === 0) {
            $groupByField = $request->query('group_by_field');
            if (!$groupByField) {
                return response()->json([
                    'success' => false,
                    'message' => 'group_by_field query parameter is required for dynamic kanban',
                ], Response::HTTP_BAD_REQUEST);
            }
            $config = [
                'group_by_field' => $groupByField,
                'title_field' => 'name',
            ];
        } else {
            // Saved view mode
            $view = DB::table('module_views')
                ->where('module_id', $module->id)
                ->where('id', $viewId)
                ->where(function ($query) use ($userId) {
                    $query->where('is_system', true)
                        ->orWhere('created_by', $userId)
                        ->orWhere('is_shared', true);
                })
                ->first();

            if (!$view || ($view->type ?? null) !== 'kanban') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kanban view not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Parse kanban_config if it's a JSON string
            $kanbanConfig = $view->kanban_config;
            if (is_string($kanbanConfig)) {
                $kanbanConfig = json_decode($kanbanConfig, true) ?? [];
            }
            $config = $kanbanConfig ?? [];
            $groupByField = $config['group_by_field'] ?? null;

            if (!$groupByField) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kanban view missing group_by_field configuration',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Get the field
        $field = DB::table('fields')
            ->where('module_id', $module->id)
            ->where('api_name', $groupByField)
            ->first();

        if (!$field) {
            return response()->json([
                'success' => false,
                'message' => 'Group by field not found',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get field options
        $fieldOptions = DB::table('field_options')
            ->where('field_id', $field->id)
            ->orderBy('display_order')
            ->get();

        $valueField = $config['value_field'] ?? null;
        $titleField = $config['title_field'] ?? 'name';

        // Build columns from field options
        $columns = [];
        foreach ($fieldOptions as $option) {
            // Color can be stored directly or in metadata
            $metadata = is_string($option->metadata) ? json_decode($option->metadata, true) : ($option->metadata ?? []);
            $optionColor = $option->color ?? $metadata['color'] ?? '#6b7280';
            $columns[$option->value] = [
                'id' => $option->value,
                'name' => $option->label,
                'color' => $optionColor,
                'display_order' => $option->display_order,
                'records' => [],
                'count' => 0,
                'total' => 0,
            ];
        }

        // Add uncategorized column for records without a value
        $columns['_uncategorized'] = [
            'id' => '_uncategorized',
            'name' => 'Uncategorized',
            'color' => '#9ca3af',
            'display_order' => -1,
            'records' => [],
            'count' => 0,
            'total' => 0,
        ];

        // Fetch records with view filters applied
        $query = DB::table('module_records')->where('module_id', $module->id);

        // Apply view filters (only if view exists)
        if ($view && !empty($view->filters)) {
            foreach ($view->filters as $filter) {
                $filterField = $filter['field'] ?? null;
                $filterOperator = $filter['operator'] ?? 'equals';
                $filterValue = $filter['value'] ?? null;

                if (!$filterField) {
                    continue;
                }

                $this->applyFilter($query, $filterField, $filterOperator, $filterValue);
            }
        }

        // Apply search if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereRaw("data::text ILIKE ?", ["%{$search}%"]);
            });
        }

        $records = $query->get();

        // Group records by field value
        foreach ($records as $record) {
            // Decode JSON data if it's a string
            $data = $record->data;
            if (is_string($data)) {
                $data = json_decode($data, true) ?? [];
            }
            $data = $data ?? [];

            $columnValue = $data[$groupByField] ?? '_uncategorized';

            // Handle case where value doesn't match any option
            if (!isset($columns[$columnValue])) {
                $columnValue = '_uncategorized';
            }

            // Build a smart title using available data
            $title = $this->buildCardTitle($data, $titleField, $record->id);

            $cardData = [
                'id' => $record->id,
                'title' => $title,
                'data' => $data,
            ];

            // Add value if configured
            if ($valueField && isset($data[$valueField])) {
                $cardData['value'] = (float) $data[$valueField];
                $columns[$columnValue]['total'] += $cardData['value'];
            }

            $columns[$columnValue]['records'][] = $cardData;
            $columns[$columnValue]['count']++;
        }

        // Sort columns by display_order and remove uncategorized if empty
        $sortedColumns = collect($columns)
            ->when($columns['_uncategorized']['count'] === 0, fn ($c) => $c->forget('_uncategorized'))
            ->sortBy('display_order')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'columns' => $sortedColumns,
            'field' => [
                'api_name' => $field->api_name,
                'label' => $field->label,
            ],
            'config' => $config,
        ]);
    }

    /**
     * Move a record to a different column (update field value).
     *
     * Supports two modes:
     * 1. Saved view mode: viewId > 0, uses view's kanban_config
     * 2. Dynamic mode: viewId = 0, requires group_by_field in request body
     */
    public function moveKanbanRecord(Request $request, string $moduleApiName, int $viewId): JsonResponse
    {
        $module = DB::table('modules')->where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();
        $groupByField = null;

        // Dynamic mode: viewId = 0
        if ($viewId === 0) {
            $validated = $request->validate([
                'record_id' => 'required|integer',
                'new_value' => 'required|string',
                'group_by_field' => 'required|string',
            ]);
            $groupByField = $validated['group_by_field'];
        } else {
            // Saved view mode
            $view = DB::table('module_views')
                ->where('module_id', $module->id)
                ->where('id', $viewId)
                ->where(function ($query) use ($userId) {
                    $query->where('is_system', true)
                        ->orWhere('created_by', $userId)
                        ->orWhere('is_shared', true);
                })
                ->first();

            if (!$view || ($view->type ?? null) !== 'kanban') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kanban view not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'record_id' => 'required|integer',
                'new_value' => 'required|string',
            ]);

            // Parse kanban_config if it's a JSON string
            $kanbanConfig = $view->kanban_config;
            if (is_string($kanbanConfig)) {
                $kanbanConfig = json_decode($kanbanConfig, true) ?? [];
            }
            $groupByField = $kanbanConfig['group_by_field'] ?? null;
        }

        $record = DB::table('module_records')
            ->where('module_id', $module->id)
            ->where('id', $validated['record_id'])
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Update the field value
        $data = is_string($record->data) ? json_decode($record->data, true) : ($record->data ?? []);
        $oldValue = $data[$groupByField] ?? null;
        $data[$groupByField] = $validated['new_value'];

        DB::table('module_records')
            ->where('id', $record->id)
            ->update([
                'data' => json_encode($data),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Record moved successfully',
            'record' => [
                'id' => $record->id,
                'old_value' => $oldValue,
                'new_value' => $validated['new_value'],
            ],
        ]);
    }

    /**
     * Apply a filter to the query.
     */
    private function applyFilter($query, string $field, string $operator, $value): void
    {
        $jsonPath = "data->'{$field}'";

        switch ($operator) {
            case 'equals':
                $query->whereRaw("data->>? = ?", [$field, $value]);
                break;
            case 'not_equals':
                $query->whereRaw("data->>? != ?", [$field, $value]);
                break;
            case 'contains':
                $query->whereRaw("data->>? ILIKE ?", [$field, "%{$value}%"]);
                break;
            case 'starts_with':
                $query->whereRaw("data->>? ILIKE ?", [$field, "{$value}%"]);
                break;
            case 'ends_with':
                $query->whereRaw("data->>? ILIKE ?", [$field, "%{$value}"]);
                break;
            case 'is_empty':
                $query->where(function ($q) use ($field) {
                    $q->whereRaw("data->>? IS NULL", [$field])
                      ->orWhereRaw("data->>? = ''", [$field]);
                });
                break;
            case 'is_not_empty':
                $query->whereRaw("data->>? IS NOT NULL", [$field])
                      ->whereRaw("data->>? != ''", [$field]);
                break;
            case 'greater_than':
                $query->whereRaw("(data->>?)::numeric > ?", [$field, $value]);
                break;
            case 'less_than':
                $query->whereRaw("(data->>?)::numeric < ?", [$field, $value]);
                break;
            case 'in':
                if (is_array($value)) {
                    $query->whereRaw("data->>? = ANY(?)", [$field, '{' . implode(',', $value) . '}']);
                }
                break;
        }
    }

    /**
     * Build a smart title for a kanban card.
     *
     * Tries the configured title_field first, then falls back to common field patterns.
     */
    private function buildCardTitle(array $data, ?string $titleField, int $recordId): string
    {
        // Try configured title_field first
        if ($titleField && !empty($data[$titleField])) {
            return (string) $data[$titleField];
        }

        // Try common name patterns
        if (!empty($data['name'])) {
            return (string) $data['name'];
        }

        // Try first_name + last_name combination
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        if ($firstName || $lastName) {
            return trim("{$firstName} {$lastName}");
        }

        // Try title field
        if (!empty($data['title'])) {
            return (string) $data['title'];
        }

        // Try subject field (for tasks, tickets, etc.)
        if (!empty($data['subject'])) {
            return (string) $data['subject'];
        }

        // Try deal_name for deals
        if (!empty($data['deal_name'])) {
            return (string) $data['deal_name'];
        }

        // Try company for leads/contacts
        if (!empty($data['company'])) {
            return (string) $data['company'];
        }

        // Try email as last resort
        if (!empty($data['email'])) {
            return (string) $data['email'];
        }

        // Fallback to record ID
        return "Record #{$recordId}";
    }
}
