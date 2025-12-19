<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsForm;
use App\Models\CmsFormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CmsFormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CmsForm::query()
            ->with(['creator:id,name', 'targetModule:id,name,api_name'])
            ->withCount('submissions');

        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        $query->orderByDesc('created_at');

        $perPage = $validated['per_page'] ?? 25;
        $forms = $query->paginate($perPage);

        // Add conversion rate to each form
        $items = collect($forms->items())->map(function ($form) {
            $form->conversion_rate = $form->getConversionRate();
            return $form;
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $forms->currentPage(),
                'last_page' => $forms->lastPage(),
                'per_page' => $forms->perPage(),
                'total' => $forms->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_forms,slug',
            'description' => 'nullable|string',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:text,email,phone,textarea,select,checkbox,radio,date,number,file',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.options' => 'nullable|array',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.validation' => 'nullable|array',
            'settings' => 'nullable|array',
            'submit_action' => 'nullable|string|in:create_lead,create_contact,update_contact,webhook,email,custom',
            'target_module_id' => 'nullable|integer|exists:modules,id',
            'field_mapping' => 'nullable|array',
            'submit_button_text' => 'nullable|string|max:255',
            'success_message' => 'nullable|string',
            'redirect_url' => 'nullable|url|max:255',
            'notification_emails' => 'nullable|array',
            'notification_emails.*' => 'email',
            'notification_template_id' => 'nullable|integer|exists:cms_templates,id',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while (CmsForm::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $form = CmsForm::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'fields' => $validated['fields'],
            'settings' => $validated['settings'] ?? null,
            'submit_action' => $validated['submit_action'] ?? CmsForm::ACTION_CREATE_LEAD,
            'target_module_id' => $validated['target_module_id'] ?? null,
            'field_mapping' => $validated['field_mapping'] ?? null,
            'submit_button_text' => $validated['submit_button_text'] ?? 'Submit',
            'success_message' => $validated['success_message'] ?? 'Thank you for your submission!',
            'redirect_url' => $validated['redirect_url'] ?? null,
            'notification_emails' => $validated['notification_emails'] ?? null,
            'notification_template_id' => $validated['notification_template_id'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $form,
            'message' => 'Form created successfully',
        ], 201);
    }

    public function show(CmsForm $cmsForm): JsonResponse
    {
        $cmsForm->load(['creator:id,name', 'targetModule:id,name,api_name', 'notificationTemplate:id,name']);
        $cmsForm->loadCount('submissions');
        $cmsForm->conversion_rate = $cmsForm->getConversionRate();

        return response()->json([
            'data' => $cmsForm,
        ]);
    }

    public function update(Request $request, CmsForm $cmsForm): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_forms,slug,' . $cmsForm->id,
            'description' => 'nullable|string',
            'fields' => 'sometimes|array|min:1',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:text,email,phone,textarea,select,checkbox,radio,date,number,file',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.options' => 'nullable|array',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.validation' => 'nullable|array',
            'settings' => 'nullable|array',
            'submit_action' => 'nullable|string|in:create_lead,create_contact,update_contact,webhook,email,custom',
            'target_module_id' => 'nullable|integer|exists:modules,id',
            'field_mapping' => 'nullable|array',
            'submit_button_text' => 'nullable|string|max:255',
            'success_message' => 'nullable|string',
            'redirect_url' => 'nullable|url|max:255',
            'notification_emails' => 'nullable|array',
            'notification_emails.*' => 'email',
            'notification_template_id' => 'nullable|integer|exists:cms_templates,id',
            'is_active' => 'nullable|boolean',
        ]);

        $cmsForm->update($validated);

        return response()->json([
            'data' => $cmsForm->fresh(),
            'message' => 'Form updated successfully',
        ]);
    }

    public function destroy(CmsForm $cmsForm): JsonResponse
    {
        $cmsForm->delete();

        return response()->json([
            'message' => 'Form deleted successfully',
        ]);
    }

    public function duplicate(CmsForm $cmsForm): JsonResponse
    {
        $copy = $cmsForm->duplicate(Auth::id());

        return response()->json([
            'data' => $copy,
            'message' => 'Form duplicated successfully',
        ], 201);
    }

    public function submissions(Request $request, CmsForm $cmsForm): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $submissions = $cmsForm->submissions()
            ->recent()
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $submissions->items(),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    public function submission(CmsForm $cmsForm, CmsFormSubmission $submission): JsonResponse
    {
        if ($submission->form_id !== $cmsForm->id) {
            return response()->json([
                'message' => 'Submission not found',
            ], 404);
        }

        return response()->json([
            'data' => $submission,
        ]);
    }

    public function deleteSubmission(CmsForm $cmsForm, CmsFormSubmission $submission): JsonResponse
    {
        if ($submission->form_id !== $cmsForm->id) {
            return response()->json([
                'message' => 'Submission not found',
            ], 404);
        }

        $submission->delete();

        return response()->json([
            'message' => 'Submission deleted successfully',
        ]);
    }

    public function embedCode(CmsForm $cmsForm): JsonResponse
    {
        return response()->json([
            'data' => [
                'embed_code' => $cmsForm->getEmbedCode(),
                'api_endpoint' => config('app.url') . "/api/v1/cms/forms/{$cmsForm->slug}/submit",
            ],
        ]);
    }

    public function analytics(CmsForm $cmsForm): JsonResponse
    {
        $last30Days = now()->subDays(30);

        $dailySubmissions = CmsFormSubmission::where('form_id', $cmsForm->id)
            ->where('created_at', '>=', $last30Days)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'total_submissions' => $cmsForm->submission_count,
                'total_views' => $cmsForm->view_count,
                'conversion_rate' => $cmsForm->getConversionRate(),
                'daily_submissions' => $dailySubmissions,
            ],
        ]);
    }
}
