<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmailTemplateController extends Controller
{
    /**
     * List email templates.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:user,system,workflow',
            'module_id' => 'nullable|integer|exists:modules,id',
            'category' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('email_templates')
            ->with('module:id,name,api_name')
            ->orderBy('is_default', 'desc')
            ->orderBy('usage_count', 'desc')
            ->orderBy('name');

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['module_id'])) {
            $query->forModule($validated['module_id']);
        }

        if (isset($validated['category'])) {
            $query->inCategory($validated['category']);
        }

        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $perPage = $validated['per_page'] ?? 25;
        $templates = $query->paginate($perPage);

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
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
            'type' => 'nullable|string|in:user,system,workflow',
            'module_id' => 'nullable|integer|exists:modules,id',
            'subject' => 'required|string|max:998',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'variables' => 'nullable|array',
            'attachments' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'category' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ]);

        // If this is set as default for a module, unset other defaults
        if ($validated['is_default'] ?? false) {
            DB::table('email_templates')->where('module_id', $validated['module_id'] ?? null)
                ->update(['is_default' => false]);
        }

        $template = DB::table('email_templates')->insertGetId([
            ...$validated,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $template,
            'message' => 'Template created successfully',
        ], 201);
    }

    /**
     * Get a single template.
     */
    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        $emailTemplate->load('module:id,name,api_name');

        return response()->json([
            'data' => $emailTemplate,
            'variables' => $emailTemplate->getAvailableVariables(),
        ]);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:user,system,workflow',
            'module_id' => 'nullable|integer|exists:modules,id',
            'subject' => 'sometimes|string|max:998',
            'body_html' => 'sometimes|string',
            'body_text' => 'nullable|string',
            'variables' => 'nullable|array',
            'attachments' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'category' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ]);

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            DB::table('email_templates')->where('module_id', $validated['module_id'] ?? $emailTemplate->module_id)
                ->where('id', '!=', $emailTemplate->id)
                ->update(['is_default' => false]);
        }

        $emailTemplate->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $emailTemplate->fresh(),
            'message' => 'Template updated successfully',
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        $emailTemplate->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(EmailTemplate $emailTemplate): JsonResponse
    {
        $copy = $emailTemplate->duplicate(Auth::id());

        return response()->json([
            'data' => $copy,
            'message' => 'Template duplicated successfully',
        ], 201);
    }

    /**
     * Preview template with sample data.
     */
    public function preview(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        $data = $validated['data'] ?? $this->getSampleData($emailTemplate);
        $rendered = $emailTemplate->render($data);

        return response()->json([
            'data' => $rendered,
            'variables_used' => $data,
        ]);
    }

    /**
     * Get sample data for preview.
     */
    protected function getSampleData(EmailTemplate $template): array
    {
        $data = [
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'date' => [
                'today' => now()->format('F j, Y'),
                'now' => now()->format('F j, Y g:i A'),
            ],
            'company' => [
                'name' => 'Acme Corp',
            ],
        ];

        if ($template->module) {
            $data['record'] = [];
            foreach ($template->module->fields as $field) {
                $data['record'][$field->api_name] = '[' . $field->label . ']';
            }
        }

        return $data;
    }

    /**
     * Get available categories.
     */
    public function categories(): JsonResponse
    {
        $categories = EmailTemplate::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json([
            'data' => $categories,
        ]);
    }
}
