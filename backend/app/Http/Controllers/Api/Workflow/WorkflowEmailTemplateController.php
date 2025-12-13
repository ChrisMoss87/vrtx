<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflow;

use App\Http\Controllers\Controller;
use App\Models\WorkflowEmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API controller for workflow email templates.
 */
class WorkflowEmailTemplateController extends Controller
{
    /**
     * List all workflow email templates.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
            'include_system' => 'nullable|boolean',
        ]);

        $query = WorkflowEmailTemplate::query()
            ->orderBy('name');

        if (isset($validated['category'])) {
            $query->inCategory($validated['category']);
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if (!($validated['include_system'] ?? true)) {
            $query->userCreated();
        }

        $templates = $query->get();

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Get a single template.
     */
    public function show(WorkflowEmailTemplate $workflowEmailTemplate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'template' => $workflowEmailTemplate,
            'available_variables' => WorkflowEmailTemplate::getDefaultVariables(),
        ]);
    }

    /**
     * Create a new template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'required|string|max:998',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'available_variables' => 'nullable|array',
            'category' => 'nullable|string|max:255',
        ]);

        $template = WorkflowEmailTemplate::create([
            ...$validated,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'template' => $template,
            'message' => 'Email template created successfully',
        ], 201);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, WorkflowEmailTemplate $workflowEmailTemplate): JsonResponse
    {
        // Prevent editing system templates
        if ($workflowEmailTemplate->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System templates cannot be modified',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'sometimes|string|max:998',
            'body_html' => 'sometimes|string',
            'body_text' => 'nullable|string',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'available_variables' => 'nullable|array',
            'category' => 'nullable|string|max:255',
        ]);

        $workflowEmailTemplate->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'template' => $workflowEmailTemplate->fresh(),
            'message' => 'Email template updated successfully',
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(WorkflowEmailTemplate $workflowEmailTemplate): JsonResponse
    {
        // Prevent deleting system templates
        if ($workflowEmailTemplate->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System templates cannot be deleted',
            ], 403);
        }

        $workflowEmailTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email template deleted successfully',
        ]);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(WorkflowEmailTemplate $workflowEmailTemplate): JsonResponse
    {
        $copy = $workflowEmailTemplate->replicate();
        $copy->name = $workflowEmailTemplate->name . ' (Copy)';
        $copy->is_system = false;
        $copy->created_by = Auth::id();
        $copy->updated_by = Auth::id();
        $copy->save();

        return response()->json([
            'success' => true,
            'template' => $copy,
            'message' => 'Email template duplicated successfully',
        ], 201);
    }

    /**
     * Preview template with sample data.
     */
    public function preview(Request $request, WorkflowEmailTemplate $workflowEmailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        $sampleData = $validated['data'] ?? $this->getSampleData();

        return response()->json([
            'success' => true,
            'preview' => [
                'subject' => $workflowEmailTemplate->renderSubject($sampleData),
                'body_html' => $workflowEmailTemplate->renderBodyHtml($sampleData),
                'body_text' => $workflowEmailTemplate->renderBodyText($sampleData),
            ],
            'sample_data' => $sampleData,
        ]);
    }

    /**
     * Get available categories.
     */
    public function categories(): JsonResponse
    {
        $categories = WorkflowEmailTemplate::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Get available variables documentation.
     */
    public function variables(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'variables' => WorkflowEmailTemplate::getDefaultVariables(),
        ]);
    }

    /**
     * Generate sample data for preview.
     */
    protected function getSampleData(): array
    {
        return [
            'record' => [
                'id' => 123,
                'name' => 'Sample Record',
                'email' => 'contact@example.com',
                'phone' => '+1 (555) 123-4567',
                'company' => 'Acme Corporation',
                'status' => 'Active',
                'created_at' => now()->subDays(7)->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
            'user' => [
                'id' => 1,
                'name' => 'John Smith',
                'email' => 'john.smith@company.com',
            ],
            'current_user' => [
                'id' => Auth::id() ?? 1,
                'name' => Auth::user()?->name ?? 'Current User',
                'email' => Auth::user()?->email ?? 'user@company.com',
            ],
            'trigger' => [
                'type' => 'record_created',
                'changed_fields' => ['status', 'updated_at'],
                'old_values' => ['status' => 'Pending'],
                'new_values' => ['status' => 'Active'],
            ],
            'now' => [
                'date' => now()->format('Y-m-d'),
                'time' => now()->format('H:i:s'),
                'datetime' => now()->format('Y-m-d H:i:s'),
                'timestamp' => now()->timestamp,
            ],
        ];
    }
}
