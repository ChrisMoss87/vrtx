<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\WebForm\WebFormApplicationService;
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\WebForms\WebFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WebFormController extends Controller
{
    // Field type constants for validation
    private const FIELD_TYPES = [
        'text' => 'Text Input',
        'email' => 'Email',
        'phone' => 'Phone',
        'textarea' => 'Text Area',
        'select' => 'Select Dropdown',
        'multi_select' => 'Multi-Select',
        'checkbox' => 'Checkbox',
        'radio' => 'Radio Buttons',
        'date' => 'Date Picker',
        'datetime' => 'Date & Time',
        'number' => 'Number',
        'currency' => 'Currency',
        'file' => 'File Upload',
        'hidden' => 'Hidden Field',
        'url' => 'URL',
    ];

    public function __construct(
        protected WebFormRepositoryInterface $webFormRepository,
        protected WebFormService $webFormService,
        protected WebFormApplicationService $webFormApplicationService
    ) {}

    /**
     * List all web forms.
     *
     * GET /api/v1/web-forms
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'active' => 'nullable|boolean',
            'module_id' => 'nullable|integer|exists:modules,id',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filters = [
            'active' => $request->active,
            'module_id' => $request->module_id,
            'search' => $request->search,
        ];

        $result = $this->webFormRepository->listForms(
            $filters,
            $request->per_page ?? 25,
            $request->page ?? 1
        );

        return response()->json([
            'data' => array_map(fn ($form) => $this->transformFormArray($form), $result->items()),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    /**
     * Get a single web form.
     *
     * GET /api/v1/web-forms/{id}
     */
    public function show(int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id, ['creator', 'module', 'assignee', 'fields']);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        return response()->json([
            'data' => $this->transformFormArray($form, true),
        ]);
    }

    /**
     * Create a new web form.
     *
     * POST /api/v1/web-forms
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:web_forms,slug',
            'description' => 'nullable|string|max:1000',
            'module_id' => 'required|integer|exists:modules,id',
            'is_active' => 'nullable|boolean',
            'settings' => 'nullable|array',
            'styling' => 'nullable|array',
            'thank_you_config' => 'nullable|array',
            'spam_protection' => 'nullable|array',
            'assign_to_user_id' => 'nullable|integer|exists:users,id',
            'fields' => 'nullable|array',
            'fields.*.field_type' => 'required_with:fields|string|in:' . implode(',', array_keys(self::FIELD_TYPES)),
            'fields.*.label' => 'required_with:fields|string|max:255',
            'fields.*.name' => 'nullable|string|max:100',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.is_required' => 'nullable|boolean',
            'fields.*.module_field_id' => 'nullable|integer|exists:fields,id',
            'fields.*.options' => 'nullable|array',
            'fields.*.validation_rules' => 'nullable|array',
            'fields.*.display_order' => 'nullable|integer',
            'fields.*.settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $form = $this->webFormRepository->createForm($request->all(), $userId);

        return response()->json([
            'data' => $this->transformFormArray($form, true),
            'message' => 'Web form created successfully',
        ], 201);
    }

    /**
     * Update a web form.
     *
     * PUT /api/v1/web-forms/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:100|unique:web_forms,slug,' . $id,
            'description' => 'nullable|string|max:1000',
            'module_id' => 'nullable|integer|exists:modules,id',
            'is_active' => 'nullable|boolean',
            'settings' => 'nullable|array',
            'styling' => 'nullable|array',
            'thank_you_config' => 'nullable|array',
            'spam_protection' => 'nullable|array',
            'assign_to_user_id' => 'nullable|integer|exists:users,id',
            'fields' => 'nullable|array',
            'fields.*.field_type' => 'required_with:fields|string|in:' . implode(',', array_keys(self::FIELD_TYPES)),
            'fields.*.label' => 'required_with:fields|string|max:255',
            'fields.*.name' => 'nullable|string|max:100',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.is_required' => 'nullable|boolean',
            'fields.*.module_field_id' => 'nullable|integer|exists:fields,id',
            'fields.*.options' => 'nullable|array',
            'fields.*.validation_rules' => 'nullable|array',
            'fields.*.display_order' => 'nullable|integer',
            'fields.*.settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updatedForm = $this->webFormRepository->updateForm($id, $request->all());

        return response()->json([
            'data' => $this->transformFormArray($updatedForm, true),
            'message' => 'Web form updated successfully',
        ]);
    }

    /**
     * Delete a web form.
     *
     * DELETE /api/v1/web-forms/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        $this->webFormRepository->deleteForm($id);

        return response()->json([
            'message' => 'Web form deleted successfully',
        ]);
    }

    /**
     * Duplicate a web form.
     *
     * POST /api/v1/web-forms/{id}/duplicate
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $newForm = $this->webFormRepository->duplicateForm($id, $userId);

        return response()->json([
            'data' => $this->transformFormArray($newForm, true),
            'message' => 'Web form duplicated successfully',
        ], 201);
    }

    /**
     * Toggle form active status.
     *
     * POST /api/v1/web-forms/{id}/toggle-active
     */
    public function toggleActive(int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        $isActive = $form['is_active'] ?? false;
        $updatedForm = $this->webFormRepository->updateForm($id, ['is_active' => !$isActive]);

        return response()->json([
            'data' => $this->transformFormArray($updatedForm),
            'message' => $updatedForm['is_active'] ? 'Form activated' : 'Form deactivated',
        ]);
    }

    /**
     * Get form submissions.
     *
     * GET /api/v1/web-forms/{id}/submissions
     */
    public function submissions(Request $request, int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:processed,failed,spam,pending',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filters = [
            'status' => $request->status,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
        ];

        $result = $this->webFormRepository->listSubmissions(
            $id,
            $filters,
            $request->per_page ?? 20,
            $request->page ?? 1
        );

        return response()->json([
            'data' => array_map(fn ($sub) => [
                'id' => $sub['id'],
                'submission_data' => $sub['submission_data'],
                'record_id' => $sub['record_id'] ?? null,
                'record' => $sub['record'] ?? null,
                'status' => $sub['status'],
                'error_message' => $sub['error_message'] ?? null,
                'ip_address' => $sub['ip_address'] ?? null,
                'referrer' => $sub['referrer'] ?? null,
                'utm_params' => $sub['utm_params'] ?? [],
                'submitted_at' => $sub['submitted_at'] ?? null,
            ], $result->items()),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    /**
     * Get form analytics.
     *
     * GET /api/v1/web-forms/{id}/analytics
     */
    public function analytics(Request $request, int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fromDate = $request->from_date ?? now()->subDays(30)->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();

        $analytics = $this->webFormRepository->getFormAnalytics($id, $fromDate, $toDate);

        return response()->json([
            'data' => $analytics,
        ]);
    }

    /**
     * Get embed code for a form.
     *
     * GET /api/v1/web-forms/{id}/embed
     */
    public function embedCode(Request $request, int $id): JsonResponse
    {
        $form = $this->webFormRepository->findByIdAsArray($id);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        $publicUrl = url("/forms/{$form['slug']}");
        $iframeCode = '<iframe src="' . url("/forms/{$form['slug']}/render") . '" width="100%" height="600" frameborder="0"></iframe>';
        $jsCode = '<div id="vrtx-form-' . $form['slug'] . '"></div><script src="' . url("/forms/{$form['slug']}/embed.js") . '"></script>';

        return response()->json([
            'data' => [
                'iframe' => $iframeCode,
                'javascript' => $jsCode,
                'public_url' => $publicUrl,
            ],
        ]);
    }

    /**
     * Get available modules for form creation.
     *
     * GET /api/v1/web-forms/modules
     */
    public function modules(): JsonResponse
    {
        $modules = $this->webFormService->getAvailableModules();

        return response()->json([
            'data' => $modules->map(fn ($module) => [
                'id' => $module->id,
                'name' => $module->name,
                'singular_name' => $module->singular_name,
                'api_name' => $module->api_name,
                'fields' => $module->fields->map(fn ($field) => [
                    'id' => $field->id,
                    'label' => $field->label,
                    'api_name' => $field->api_name,
                    'field_type' => $field->type,
                    'is_required' => $field->is_required,
                ]),
            ]),
        ]);
    }

    /**
     * Get available field types.
     *
     * GET /api/v1/web-forms/field-types
     */
    public function fieldTypes(): JsonResponse
    {
        return response()->json([
            'data' => self::FIELD_TYPES,
        ]);
    }

    /**
     * Transform a form array for API response.
     */
    protected function transformFormArray(array $form, bool $includeDetails = false): array
    {
        $publicUrl = url("/forms/{$form['slug']}");

        $data = [
            'id' => $form['id'],
            'name' => $form['name'],
            'slug' => $form['slug'],
            'description' => $form['description'] ?? null,
            'module' => isset($form['module']) ? [
                'id' => $form['module']['id'],
                'name' => $form['module']['name'],
                'api_name' => $form['module']['api_name'] ?? null,
            ] : null,
            'is_active' => $form['is_active'] ?? false,
            'public_url' => $publicUrl,
            'created_by' => isset($form['creator']) ? [
                'id' => $form['creator']['id'],
                'name' => $form['creator']['name'],
            ] : null,
            'created_at' => $form['created_at'] ?? null,
            'updated_at' => $form['updated_at'] ?? null,
        ];

        if ($includeDetails) {
            $data['settings'] = $form['settings'] ?? [];
            $data['styling'] = $form['styling'] ?? [];
            $data['thank_you_config'] = $form['thank_you_config'] ?? [];
            $data['spam_protection'] = $form['spam_protection'] ?? [];
            $data['assign_to_user'] = isset($form['assignee']) ? [
                'id' => $form['assignee']['id'],
                'name' => $form['assignee']['name'],
            ] : null;
            $data['fields'] = array_map(fn ($field) => [
                'id' => $field['id'],
                'field_type' => $field['field_type'],
                'label' => $field['label'],
                'name' => $field['name'] ?? null,
                'placeholder' => $field['placeholder'] ?? null,
                'is_required' => $field['is_required'] ?? false,
                'module_field_id' => $field['module_field_id'] ?? null,
                'options' => $field['options'] ?? [],
                'validation_rules' => $field['validation_rules'] ?? [],
                'display_order' => $field['display_order'] ?? 0,
                'settings' => $field['settings'] ?? [],
            ], $form['fields'] ?? []);

            $iframeCode = '<iframe src="' . url("/forms/{$form['slug']}/render") . '" width="100%" height="600" frameborder="0"></iframe>';
            $jsCode = '<div id="vrtx-form-' . $form['slug'] . '"></div><script src="' . url("/forms/{$form['slug']}/embed.js") . '"></script>';

            $data['embed_code'] = [
                'iframe' => $iframeCode,
                'javascript' => $jsCode,
            ];
        }

        return $data;
    }

    /**
     * Transform a form for API response.
     */
    protected function transformForm(WebForm $form, bool $includeDetails = false): array
    {
        $data = [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'description' => $form->description,
            'module' => $form->module ? [
                'id' => $form->module->id,
                'name' => $form->module->name,
                'api_name' => $form->module->api_name,
            ] : null,
            'is_active' => $form->is_active,
            'public_url' => $form->public_url,
            'submission_count' => $form->submissions()->count(),
            'created_by' => $form->creator ? [
                'id' => $form->creator->id,
                'name' => $form->creator->name,
            ] : null,
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ];

        if ($includeDetails) {
            $data['settings'] = $form->settings;
            $data['styling'] = $form->styling;
            $data['thank_you_config'] = $form->thank_you_config;
            $data['spam_protection'] = $form->spam_protection;
            $data['assign_to_user'] = $form->assignee ? [
                'id' => $form->assignee->id,
                'name' => $form->assignee->name,
            ] : null;
            $data['fields'] = $form->fields->map(fn ($field) => [
                'id' => $field->id,
                'field_type' => $field->field_type,
                'label' => $field->label,
                'name' => $field->name,
                'placeholder' => $field->placeholder,
                'is_required' => $field->is_required,
                'module_field_id' => $field->module_field_id,
                'module_field' => $field->moduleField ? [
                    'id' => $field->moduleField->id,
                    'label' => $field->moduleField->label,
                    'api_name' => $field->moduleField->api_name,
                ] : null,
                'options' => $field->options,
                'validation_rules' => $field->validation_rules,
                'display_order' => $field->display_order,
                'settings' => $field->settings,
            ]);
            $data['embed_code'] = [
                'iframe' => $form->iframe_embed_code,
                'javascript' => $form->js_embed_code,
            ];
        }

        return $data;
    }
}
