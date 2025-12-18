<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkflowTemplateController extends Controller
{
    /**
     * Get all workflow templates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = WorkflowTemplate::query()->active();

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->input('category'));
            }

            // Filter by difficulty
            if ($request->has('difficulty')) {
                $query->where('difficulty', $request->input('difficulty'));
            }

            // Filter by search term
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Order by usage count for popular templates
            if ($request->boolean('popular')) {
                $query->orderByDesc('usage_count');
            } else {
                $query->orderBy('category')->orderBy('name');
            }

            $templates = $query->get();

            // Get available modules to check compatibility
            $availableModules = Module::where('is_active', true)
                ->pluck('api_name')
                ->toArray();

            // Add compatibility flag to each template
            $templates = $templates->map(function ($template) use ($availableModules) {
                $data = $template->toArray();
                $data['is_compatible'] = $template->canUseWithModules($availableModules);
                return $data;
            });

            return response()->json([
                'success' => true,
                'templates' => $templates,
                'categories' => WorkflowTemplate::getCategories(),
                'difficulty_levels' => WorkflowTemplate::getDifficultyLevels(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflow templates',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single workflow template.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $template = WorkflowTemplate::find($id);

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Get available modules
            $availableModules = Module::where('is_active', true)
                ->pluck('api_name')
                ->toArray();

            $data = $template->toArray();
            $data['is_compatible'] = $template->canUseWithModules($availableModules);

            // Get missing modules if not compatible
            if (!$data['is_compatible']) {
                $required = $template->required_modules ?? [];
                $data['missing_modules'] = array_diff($required, $availableModules);
            }

            return response()->json([
                'success' => true,
                'template' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch template',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Use a template to create a workflow.
     */
    public function use(Request $request, int $id): JsonResponse
    {
        try {
            $template = WorkflowTemplate::find($id);

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Validate variable mappings
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'mappings' => 'nullable|array',
            ]);

            // Check compatibility
            $availableModules = Module::where('is_active', true)
                ->pluck('api_name')
                ->toArray();

            if (!$template->canUseWithModules($availableModules)) {
                $required = $template->required_modules ?? [];
                $missing = array_diff($required, $availableModules);

                return response()->json([
                    'success' => false,
                    'message' => 'Template requires modules that are not available',
                    'missing_modules' => array_values($missing),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Get workflow data with mappings applied
            $mappings = $validated['mappings'] ?? [];
            $workflowData = $template->getWorkflowDataWithMappings($mappings);

            // Override name if provided
            if (!empty($validated['name'])) {
                $workflowData['name'] = $validated['name'];
            }

            // Increment usage count
            $template->incrementUsage();

            return response()->json([
                'success' => true,
                'message' => 'Template applied successfully',
                'workflow_data' => $workflowData,
                'template_id' => $template->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to use template',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get template categories.
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'categories' => WorkflowTemplate::getCategories(),
        ]);
    }

    /**
     * Get popular templates.
     */
    public function popular(int $limit = 6): JsonResponse
    {
        try {
            $templates = WorkflowTemplate::query()
                ->active()
                ->orderByDesc('usage_count')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'templates' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular templates',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get templates by category.
     */
    public function byCategory(string $category): JsonResponse
    {
        try {
            $templates = WorkflowTemplate::query()
                ->active()
                ->category($category)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'category' => $category,
                'category_label' => WorkflowTemplate::getCategories()[$category] ?? $category,
                'templates' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch templates',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
