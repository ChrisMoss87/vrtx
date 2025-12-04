<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ViewsController extends Controller
{
    /**
     * Get all views for a module accessible by the current user.
     */
    public function index(Request $request, string $moduleApiName): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $views = ModuleView::where('module_id', $module->id)
            ->accessibleBy($userId)
            ->ordered()
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
        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $view = ModuleView::where('module_id', $module->id)
            ->where('id', $viewId)
            ->accessibleBy($userId)
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
        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
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
            ModuleView::where('module_id', $module->id)
                ->where('user_id', $userId)
                ->update(['is_default' => false]);
        }

        $view = ModuleView::create([
            'module_id' => $module->id,
            'user_id' => $userId,
            ...$validated,
        ]);

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
        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $view = ModuleView::where('module_id', $module->id)
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
            ModuleView::where('module_id', $module->id)
                ->where('user_id', $userId)
                ->where('id', '!=', $viewId)
                ->update(['is_default' => false]);
        }

        $view->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'View updated successfully',
            'view' => $view,
        ]);
    }

    /**
     * Delete a view.
     */
    public function destroy(Request $request, string $moduleApiName, int $viewId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        $view = ModuleView::where('module_id', $module->id)
            ->where('id', $viewId)
            ->where('user_id', $userId)
            ->first();

        if (!$view) {
            return response()->json([
                'success' => false,
                'message' => 'View not found or you do not have permission to delete it',
            ], Response::HTTP_NOT_FOUND);
        }

        $view->delete();

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
        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $userId = Auth::id();

        // First, try to find user's default view
        $view = ModuleView::where('module_id', $module->id)
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
}
