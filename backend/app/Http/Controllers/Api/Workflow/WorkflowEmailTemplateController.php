<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflow;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $query = DB::table('workflow_email_templates')
            ->whereNull('deleted_at')
            ->orderBy('name');

        if (isset($validated['category'])) {
            $query->where('category', $validated['category']);
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
            $query->where('is_system', false);
        }

        $templates = $query->get()->map(function ($template) {
            return $this->decodeJsonFields($template);
        });

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Get a single template.
     */
    public function show(int $id): JsonResponse
    {
        $template = DB::table('workflow_email_templates')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'template' => $this->decodeJsonFields($template),
            'available_variables' => $this->getDefaultVariables(),
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

        $now = now()->toDateTimeString();

        $id = DB::table('workflow_email_templates')->insertGetId([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'body_text' => $validated['body_text'] ?? null,
            'from_name' => $validated['from_name'] ?? null,
            'from_email' => $validated['from_email'] ?? null,
            'reply_to' => $validated['reply_to'] ?? null,
            'available_variables' => isset($validated['available_variables']) ? json_encode($validated['available_variables']) : null,
            'category' => $validated['category'] ?? null,
            'is_system' => false,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $template = DB::table('workflow_email_templates')
            ->where('id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'template' => $this->decodeJsonFields($template),
            'message' => 'Email template created successfully',
        ], 201);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = DB::table('workflow_email_templates')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        // Prevent editing system templates
        if ($template->is_system) {
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

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($key === 'available_variables' && is_array($value)) {
                $updateData[$key] = json_encode($value);
            } else {
                $updateData[$key] = $value;
            }
        }
        $updateData['updated_by'] = Auth::id();
        $updateData['updated_at'] = now()->toDateTimeString();

        DB::table('workflow_email_templates')
            ->where('id', $id)
            ->update($updateData);

        $updatedTemplate = DB::table('workflow_email_templates')
            ->where('id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'template' => $this->decodeJsonFields($updatedTemplate),
            'message' => 'Email template updated successfully',
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(int $id): JsonResponse
    {
        $template = DB::table('workflow_email_templates')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        // Prevent deleting system templates
        if ($template->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System templates cannot be deleted',
            ], 403);
        }

        DB::table('workflow_email_templates')
            ->where('id', $id)
            ->update(['deleted_at' => now()->toDateTimeString()]);

        return response()->json([
            'success' => true,
            'message' => 'Email template deleted successfully',
        ]);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(int $id): JsonResponse
    {
        $template = DB::table('workflow_email_templates')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        $now = now()->toDateTimeString();

        $newId = DB::table('workflow_email_templates')->insertGetId([
            'name' => $template->name . ' (Copy)',
            'description' => $template->description,
            'subject' => $template->subject,
            'body_html' => $template->body_html,
            'body_text' => $template->body_text,
            'from_name' => $template->from_name,
            'from_email' => $template->from_email,
            'reply_to' => $template->reply_to,
            'available_variables' => $template->available_variables,
            'category' => $template->category,
            'is_system' => false,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $copy = DB::table('workflow_email_templates')
            ->where('id', $newId)
            ->first();

        return response()->json([
            'success' => true,
            'template' => $this->decodeJsonFields($copy),
            'message' => 'Email template duplicated successfully',
        ], 201);
    }

    /**
     * Preview template with sample data.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $template = DB::table('workflow_email_templates')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        $sampleData = $validated['data'] ?? $this->getSampleData();

        return response()->json([
            'success' => true,
            'preview' => [
                'subject' => $this->renderSubject($template->subject, $sampleData),
                'body_html' => $this->renderBodyHtml($template->body_html, $sampleData),
                'body_text' => $template->body_text ? $this->renderBodyText($template->body_text, $sampleData) : null,
            ],
            'sample_data' => $sampleData,
        ]);
    }

    /**
     * Get available categories.
     */
    public function categories(): JsonResponse
    {
        $categories = DB::table('workflow_email_templates')
            ->whereNull('deleted_at')
            ->whereNotNull('category')
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
            'variables' => $this->getDefaultVariables(),
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

    /**
     * Decode JSON fields from database record.
     */
    private function decodeJsonFields(object $template): array
    {
        $data = (array) $template;

        if (isset($data['available_variables']) && is_string($data['available_variables'])) {
            $data['available_variables'] = json_decode($data['available_variables'], true);
        }

        return $data;
    }

    /**
     * Render the subject with variable substitution.
     */
    private function renderSubject(string $subject, array $variables): string
    {
        return $this->substituteVariables($subject, $variables);
    }

    /**
     * Render the HTML body with variable substitution.
     */
    private function renderBodyHtml(string $bodyHtml, array $variables): string
    {
        return $this->substituteVariables($bodyHtml, $variables);
    }

    /**
     * Render the text body with variable substitution.
     */
    private function renderBodyText(string $bodyText, array $variables): string
    {
        return $this->substituteVariables($bodyText, $variables);
    }

    /**
     * Substitute variables in a template string.
     * Variables are in the format {{variable_name}} or {{record.field_name}}.
     */
    private function substituteVariables(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function ($matches) use ($variables) {
                $key = trim($matches[1]);

                // Handle nested keys (e.g., record.name, user.email)
                $value = $this->getNestedValue($variables, $key);

                if ($value === null) {
                    return $matches[0]; // Keep original if not found
                }

                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }

                return (string) $value;
            },
            $template
        );
    }

    /**
     * Get a nested value from an array using dot notation.
     */
    private function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                $value = $value->{$key};
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get default available variables for workflows.
     */
    private function getDefaultVariables(): array
    {
        return [
            'record' => [
                'description' => 'The triggering record',
                'fields' => ['id', 'name', 'created_at', 'updated_at', '...module fields'],
            ],
            'user' => [
                'description' => 'The user who triggered the workflow',
                'fields' => ['id', 'name', 'email'],
            ],
            'current_user' => [
                'description' => 'The currently logged in user',
                'fields' => ['id', 'name', 'email'],
            ],
            'trigger' => [
                'description' => 'Information about the trigger event',
                'fields' => ['type', 'changed_fields', 'old_values', 'new_values'],
            ],
            'now' => [
                'description' => 'Current date/time',
                'fields' => ['date', 'time', 'datetime', 'timestamp'],
            ],
        ];
    }
}
