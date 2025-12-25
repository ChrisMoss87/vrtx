<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowTemplateController extends Controller
{
    // Categories
    private const CATEGORY_LEAD = 'lead';
    private const CATEGORY_DEAL = 'deal';
    private const CATEGORY_CUSTOMER = 'customer';
    private const CATEGORY_DATA = 'data';
    private const CATEGORY_PRODUCTIVITY = 'productivity';
    private const CATEGORY_COMMUNICATION = 'communication';

    // Difficulty levels
    private const DIFFICULTY_BEGINNER = 'beginner';
    private const DIFFICULTY_INTERMEDIATE = 'intermediate';
    private const DIFFICULTY_ADVANCED = 'advanced';

    /**
     * Get all workflow templates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DB::table('workflow_templates')->where('is_active', true);

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
            $availableModules = DB::table('modules')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->pluck('api_name')
                ->toArray();

            // Add compatibility flag to each template
            $templates = $templates->map(function ($template) use ($availableModules) {
                $data = (array) $template;

                // Decode JSON fields
                if (isset($data['workflow_data']) && is_string($data['workflow_data'])) {
                    $data['workflow_data'] = json_decode($data['workflow_data'], true);
                }
                if (isset($data['required_modules']) && is_string($data['required_modules'])) {
                    $data['required_modules'] = json_decode($data['required_modules'], true);
                }
                if (isset($data['required_fields']) && is_string($data['required_fields'])) {
                    $data['required_fields'] = json_decode($data['required_fields'], true);
                }
                if (isset($data['variable_mappings']) && is_string($data['variable_mappings'])) {
                    $data['variable_mappings'] = json_decode($data['variable_mappings'], true);
                }

                // Check compatibility
                $data['is_compatible'] = $this->canUseWithModules($data['required_modules'] ?? [], $availableModules);
                return $data;
            });

            return response()->json([
                'success' => true,
                'templates' => $templates,
                'categories' => $this->getCategories(),
                'difficulty_levels' => $this->getDifficultyLevels(),
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
            $template = DB::table('workflow_templates')
                ->where('id', $id)
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Get available modules
            $availableModules = DB::table('modules')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->pluck('api_name')
                ->toArray();

            $data = (array) $template;

            // Decode JSON fields
            if (isset($data['workflow_data']) && is_string($data['workflow_data'])) {
                $data['workflow_data'] = json_decode($data['workflow_data'], true);
            }
            if (isset($data['required_modules']) && is_string($data['required_modules'])) {
                $data['required_modules'] = json_decode($data['required_modules'], true);
            }
            if (isset($data['required_fields']) && is_string($data['required_fields'])) {
                $data['required_fields'] = json_decode($data['required_fields'], true);
            }
            if (isset($data['variable_mappings']) && is_string($data['variable_mappings'])) {
                $data['variable_mappings'] = json_decode($data['variable_mappings'], true);
            }

            $data['is_compatible'] = $this->canUseWithModules($data['required_modules'] ?? [], $availableModules);

            // Get missing modules if not compatible
            if (!$data['is_compatible']) {
                $required = $data['required_modules'] ?? [];
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
            $template = DB::table('workflow_templates')
                ->where('id', $id)
                ->first();

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
            $availableModules = DB::table('modules')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->pluck('api_name')
                ->toArray();

            // Decode required_modules
            $requiredModules = is_string($template->required_modules)
                ? json_decode($template->required_modules, true)
                : [];

            if (!$this->canUseWithModules($requiredModules, $availableModules)) {
                $missing = array_diff($requiredModules, $availableModules);

                return response()->json([
                    'success' => false,
                    'message' => 'Template requires modules that are not available',
                    'missing_modules' => array_values($missing),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Get workflow data with mappings applied
            $mappings = $validated['mappings'] ?? [];
            $workflowData = $this->getWorkflowDataWithMappings($template, $mappings);

            // Override name if provided
            if (!empty($validated['name'])) {
                $workflowData['name'] = $validated['name'];
            }

            // Increment usage count
            DB::table('workflow_templates')
                ->where('id', $id)
                ->increment('usage_count');

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
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Get popular templates.
     */
    public function popular(int $limit = 6): JsonResponse
    {
        try {
            $templates = DB::table('workflow_templates')
                ->where('is_active', true)
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
            $templates = DB::table('workflow_templates')
                ->where('is_active', true)
                ->where('category', $category)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'category' => $category,
                'category_label' => $this->getCategories()[$category] ?? $category,
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

    /**
     * Get all categories.
     */
    private function getCategories(): array
    {
        return [
            self::CATEGORY_LEAD => 'Lead Management',
            self::CATEGORY_DEAL => 'Deal & Sales',
            self::CATEGORY_CUSTOMER => 'Customer Success',
            self::CATEGORY_DATA => 'Data Quality',
            self::CATEGORY_PRODUCTIVITY => 'Team Productivity',
            self::CATEGORY_COMMUNICATION => 'Communication',
        ];
    }

    /**
     * Get all difficulty levels.
     */
    private function getDifficultyLevels(): array
    {
        return [
            self::DIFFICULTY_BEGINNER => 'Beginner',
            self::DIFFICULTY_INTERMEDIATE => 'Intermediate',
            self::DIFFICULTY_ADVANCED => 'Advanced',
        ];
    }

    /**
     * Check if template can be used with given modules.
     */
    private function canUseWithModules(array $requiredModules, array $availableModuleApiNames): bool
    {
        if (empty($requiredModules)) {
            return true;
        }

        foreach ($requiredModules as $requiredModule) {
            if (!in_array($requiredModule, $availableModuleApiNames)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the workflow data with variable mappings applied.
     */
    private function getWorkflowDataWithMappings(object $template, array $mappings): array
    {
        $data = is_string($template->workflow_data)
            ? json_decode($template->workflow_data, true)
            : (array) $template->workflow_data;

        // Replace variable placeholders with actual values
        $json = json_encode($data);
        foreach ($mappings as $key => $value) {
            $json = str_replace("{{$key}}", (string) $value, $json);
        }

        return json_decode($json, true);
    }
}
