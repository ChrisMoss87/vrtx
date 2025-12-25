<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Domain\CMS\Repositories\CmsFormRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsFormController extends Controller
{
    private const TABLE_FORMS = 'cms_forms';
    private const TABLE_SUBMISSIONS = 'cms_form_submissions';
    private const TABLE_USERS = 'users';
    private const TABLE_MODULES = 'modules';
    private const TABLE_TEMPLATES = 'cms_templates';

    public const ACTION_CREATE_LEAD = 'create_lead';

    public function __construct(
        private readonly CmsFormRepositoryInterface $formRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = [];
        if (isset($validated['is_active'])) {
            $filters['is_active'] = $validated['is_active'];
        }
        if (isset($validated['search'])) {
            $filters['search'] = $validated['search'];
        }
        $filters['sort_by'] = 'created_at';
        $filters['sort_dir'] = 'desc';

        $perPage = $validated['per_page'] ?? 25;
        $page = $request->integer('page', 1);

        $result = $this->formRepository->paginate($filters, $perPage, $page);

        // Enrich with relations
        $items = [];
        foreach ($result->items as $item) {
            // Load creator
            if ($item['created_by']) {
                $creator = DB::table(self::TABLE_USERS)
                    ->where('id', $item['created_by'])
                    ->select(['id', 'name'])
                    ->first();
                $item['creator'] = $creator ? (array) $creator : null;
            }

            // Load target module
            if ($item['target_module_id'] ?? null) {
                $module = DB::table(self::TABLE_MODULES)
                    ->where('id', $item['target_module_id'])
                    ->select(['id', 'name', 'api_name'])
                    ->first();
                $item['target_module'] = $module ? (array) $module : null;
            }

            // Count submissions
            $submissionsCount = DB::table(self::TABLE_SUBMISSIONS)
                ->where('form_id', $item['id'])
                ->count();
            $item['submissions_count'] = $submissionsCount;

            $items[] = $item;
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $result->currentPage,
                'last_page' => $result->lastPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
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
        while ($this->formRepository->findBySlug($slug) !== null) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $formId = DB::table(self::TABLE_FORMS)->insertGetId([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'fields' => json_encode($validated['fields']),
            'settings' => isset($validated['settings']) ? json_encode($validated['settings']) : null,
            'submit_action' => $validated['submit_action'] ?? self::ACTION_CREATE_LEAD,
            'target_module_id' => $validated['target_module_id'] ?? null,
            'field_mapping' => isset($validated['field_mapping']) ? json_encode($validated['field_mapping']) : null,
            'submit_button_text' => $validated['submit_button_text'] ?? 'Submit',
            'success_message' => $validated['success_message'] ?? 'Thank you for your submission!',
            'redirect_url' => $validated['redirect_url'] ?? null,
            'notification_emails' => isset($validated['notification_emails']) ? json_encode($validated['notification_emails']) : null,
            'notification_template_id' => $validated['notification_template_id'] ?? null,
            'is_active' => true,
            'view_count' => 0,
            'submission_count' => 0,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $formArray = $this->formRepository->findByIdAsArray($formId);

        return response()->json([
            'data' => $formArray,
            'message' => 'Form created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $formArray = $this->formRepository->findByIdAsArray($id);

        if (!$formArray) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        // Load creator
        if ($formArray['created_by']) {
            $creator = DB::table(self::TABLE_USERS)
                ->where('id', $formArray['created_by'])
                ->select(['id', 'name'])
                ->first();
            $formArray['creator'] = $creator ? (array) $creator : null;
        }

        // Load target module
        if ($formArray['target_module_id'] ?? null) {
            $module = DB::table(self::TABLE_MODULES)
                ->where('id', $formArray['target_module_id'])
                ->select(['id', 'name', 'api_name'])
                ->first();
            $formArray['target_module'] = $module ? (array) $module : null;
        }

        // Load notification template
        if ($formArray['notification_template_id'] ?? null) {
            $template = DB::table(self::TABLE_TEMPLATES)
                ->where('id', $formArray['notification_template_id'])
                ->select(['id', 'name'])
                ->first();
            $formArray['notification_template'] = $template ? (array) $template : null;
        }

        // Count submissions
        $submissionsCount = DB::table(self::TABLE_SUBMISSIONS)
            ->where('form_id', $id)
            ->count();
        $formArray['submissions_count'] = $submissionsCount;

        return response()->json([
            'data' => $formArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $form = $this->formRepository->findById($id);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_forms,slug,' . $id,
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

        $updateData = [];
        foreach ($validated as $key => $value) {
            if (in_array($key, ['fields', 'settings', 'field_mapping', 'notification_emails'])) {
                $updateData[$key] = is_array($value) ? json_encode($value) : $value;
            } else {
                $updateData[$key] = $value;
            }
        }
        $updateData['updated_at'] = now();

        DB::table(self::TABLE_FORMS)->where('id', $id)->update($updateData);

        $formArray = $this->formRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $formArray,
            'message' => 'Form updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $form = $this->formRepository->findById($id);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $this->formRepository->delete($id);

        return response()->json([
            'message' => 'Form deleted successfully',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $form = $this->formRepository->findById($id);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $slug = $form->getSlug() . '-copy';
        $originalSlug = $slug;
        $counter = 1;
        while ($this->formRepository->findBySlug($slug) !== null) {
            $slug = $originalSlug . '-' . $counter++;
        }

        // Use DB to duplicate with all fields
        $formRecord = DB::table(self::TABLE_FORMS)->where('id', $id)->first();
        $formData = (array) $formRecord;
        unset($formData['id'], $formData['created_at'], $formData['updated_at'], $formData['deleted_at']);
        $formData['name'] = $form->getName() . ' (Copy)';
        $formData['slug'] = $slug;
        $formData['view_count'] = 0;
        $formData['submission_count'] = 0;
        $formData['created_by'] = Auth::id();
        $formData['created_at'] = now();
        $formData['updated_at'] = now();

        $newFormId = DB::table(self::TABLE_FORMS)->insertGetId($formData);
        $formArray = $this->formRepository->findByIdAsArray($newFormId);

        return response()->json([
            'data' => $formArray,
            'message' => 'Form duplicated successfully',
        ], 201);
    }

    public function submissions(Request $request, int $id): JsonResponse
    {
        $form = $this->formRepository->findById($id);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table(self::TABLE_SUBMISSIONS)
            ->where('form_id', $id)
            ->orderByDesc('created_at');

        // Get total count
        $total = $query->count();

        // Get paginated items
        $perPage = $validated['per_page'] ?? 25;
        $page = $request->integer('page', 1);
        $offset = ($page - 1) * $perPage;
        $submissions = $query->skip($offset)->take($perPage)->get();

        // Decode JSON data
        $items = [];
        foreach ($submissions as $submission) {
            $submissionArray = (array) $submission;
            if ($submission->data) {
                $submissionArray['data'] = json_decode($submission->data, true);
            }
            if ($submission->metadata) {
                $submissionArray['metadata'] = json_decode($submission->metadata, true);
            }
            $items[] = $submissionArray;
        }

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function submission(int $formId, int $submissionId): JsonResponse
    {
        $form = $this->formRepository->findById($formId);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $submission = DB::table(self::TABLE_SUBMISSIONS)
            ->where('id', $submissionId)
            ->where('form_id', $formId)
            ->first();

        if (!$submission) {
            return response()->json([
                'message' => 'Submission not found',
            ], 404);
        }

        $submissionArray = (array) $submission;
        if ($submission->data) {
            $submissionArray['data'] = json_decode($submission->data, true);
        }
        if ($submission->metadata) {
            $submissionArray['metadata'] = json_decode($submission->metadata, true);
        }

        return response()->json([
            'data' => $submissionArray,
        ]);
    }

    public function deleteSubmission(int $formId, int $submissionId): JsonResponse
    {
        $form = $this->formRepository->findById($formId);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $submission = DB::table(self::TABLE_SUBMISSIONS)
            ->where('id', $submissionId)
            ->where('form_id', $formId)
            ->first();

        if (!$submission) {
            return response()->json([
                'message' => 'Submission not found',
            ], 404);
        }

        DB::table(self::TABLE_SUBMISSIONS)->where('id', $submissionId)->delete();

        return response()->json([
            'message' => 'Submission deleted successfully',
        ]);
    }

    public function embedCode(int $id): JsonResponse
    {
        $form = $this->formRepository->findById($id);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $embedCode = '<script src="' . config('app.url') . '/js/form-embed.js"></script>' . "\n" .
            '<div data-vrtx-form="' . $form->getSlug() . '"></div>';

        return response()->json([
            'data' => [
                'embed_code' => $embedCode,
                'api_endpoint' => config('app.url') . "/api/v1/cms/forms/{$form->getSlug()}/submit",
            ],
        ]);
    }

    public function analytics(int $id): JsonResponse
    {
        $form = $this->formRepository->findById($id);

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $last30Days = now()->subDays(30);

        $dailySubmissions = DB::table(self::TABLE_SUBMISSIONS)
            ->where('form_id', $id)
            ->where('created_at', '>=', $last30Days)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $submissionCount = $form->getSubmissionCount();
        $viewCount = $form->getViewCount();
        $conversionRate = $viewCount > 0 ? round(($submissionCount / $viewCount) * 100, 2) : 0;

        return response()->json([
            'data' => [
                'total_submissions' => $submissionCount,
                'total_views' => $viewCount,
                'conversion_rate' => $conversionRate,
                'daily_submissions' => array_map(fn($item) => (array) $item, $dailySubmissions->all()),
            ],
        ]);
    }
}
