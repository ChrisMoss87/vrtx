<?php

namespace App\Http\Controllers\Api\Cadence;

use App\Application\Services\Cadence\CadenceApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Cadence;
use App\Models\CadenceStep;
use App\Models\CadenceEnrollment;
use App\Services\Cadence\CadenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CadenceController extends Controller
{
    public function __construct(
        private CadenceApplicationService $cadenceApplicationService,
        private CadenceService $cadenceService
    ) {}

    /**
     * Get cadence statuses
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Cadence::STATUSES,
        ]);
    }

    /**
     * Get available channels
     */
    public function channels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CadenceStep::CHANNELS,
        ]);
    }

    /**
     * List cadences
     */
    public function index(Request $request): JsonResponse
    {
        $cadences = $this->cadenceService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $cadences->items(),
            'meta' => [
                'current_page' => $cadences->currentPage(),
                'last_page' => $cadences->lastPage(),
                'per_page' => $cadences->perPage(),
                'total' => $cadences->total(),
            ],
        ]);
    }

    /**
     * Get a single cadence
     */
    public function show(int $id): JsonResponse
    {
        $cadence = $this->cadenceService->get($id);
        $analytics = $this->cadenceService->getAnalytics($id);

        return response()->json([
            'success' => true,
            'data' => $cadence,
            'analytics' => $analytics['summary'],
        ]);
    }

    /**
     * Create a cadence
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module_id' => 'required|exists:modules,id',
            'entry_criteria' => 'nullable|array',
            'exit_criteria' => 'nullable|array',
            'settings' => 'nullable|array',
            'auto_enroll' => 'boolean',
            'allow_re_enrollment' => 'boolean',
            're_enrollment_days' => 'nullable|integer|min:1',
            'max_enrollments_per_day' => 'nullable|integer|min:1',
            'owner_id' => 'nullable|exists:users,id',
            'steps' => 'nullable|array',
            'steps.*.channel' => 'required|in:email,call,sms,linkedin,task,wait',
            'steps.*.delay_type' => 'required|in:immediate,days,hours,business_days',
            'steps.*.delay_value' => 'required|integer|min:0',
        ]);

        $cadence = $this->cadenceService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cadence created successfully',
            'data' => $cadence,
        ], 201);
    }

    /**
     * Update a cadence
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'entry_criteria' => 'nullable|array',
            'exit_criteria' => 'nullable|array',
            'settings' => 'nullable|array',
            'auto_enroll' => 'boolean',
            'allow_re_enrollment' => 'boolean',
            're_enrollment_days' => 'nullable|integer|min:1',
            'max_enrollments_per_day' => 'nullable|integer|min:1',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $cadence = $this->cadenceService->update($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Cadence updated successfully',
            'data' => $cadence,
        ]);
    }

    /**
     * Delete a cadence
     */
    public function destroy(int $id): JsonResponse
    {
        $this->cadenceService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Cadence deleted successfully',
        ]);
    }

    /**
     * Activate a cadence
     */
    public function activate(int $id): JsonResponse
    {
        $cadence = $this->cadenceService->activate($id);

        return response()->json([
            'success' => true,
            'message' => 'Cadence activated successfully',
            'data' => $cadence,
        ]);
    }

    /**
     * Pause a cadence
     */
    public function pause(int $id): JsonResponse
    {
        $cadence = $this->cadenceService->pause($id);

        return response()->json([
            'success' => true,
            'message' => 'Cadence paused successfully',
            'data' => $cadence,
        ]);
    }

    /**
     * Archive a cadence
     */
    public function archive(int $id): JsonResponse
    {
        $cadence = $this->cadenceService->archive($id);

        return response()->json([
            'success' => true,
            'message' => 'Cadence archived successfully',
            'data' => $cadence,
        ]);
    }

    /**
     * Duplicate a cadence
     */
    public function duplicate(int $id): JsonResponse
    {
        $cadence = $this->cadenceService->duplicate($id);

        return response()->json([
            'success' => true,
            'message' => 'Cadence duplicated successfully',
            'data' => $cadence,
        ]);
    }

    /**
     * Get cadence analytics
     */
    public function analytics(Request $request, int $id): JsonResponse
    {
        $analytics = $this->cadenceService->getAnalytics(
            $id,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    // Step Management

    /**
     * Add a step to a cadence
     */
    public function addStep(Request $request, int $cadenceId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'channel' => 'required|in:email,call,sms,linkedin,task,wait',
            'delay_type' => 'required|in:immediate,days,hours,business_days',
            'delay_value' => 'required|integer|min:0',
            'preferred_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'template_id' => 'nullable|exists:email_campaign_templates,id',
            'conditions' => 'nullable|array',
            'on_reply_goto_step' => 'nullable|exists:cadence_steps,id',
            'on_click_goto_step' => 'nullable|exists:cadence_steps,id',
            'on_no_response_goto_step' => 'nullable|exists:cadence_steps,id',
            'is_ab_test' => 'boolean',
            'ab_variant_of' => 'nullable|exists:cadence_steps,id',
            'ab_percentage' => 'nullable|integer|min:1|max:100',
            'linkedin_action' => 'nullable|in:connection_request,message,view_profile,engage',
            'task_type' => 'nullable|string|max:255',
            'task_assigned_to' => 'nullable|exists:users,id',
            'step_order' => 'nullable|integer|min:1',
        ]);

        $step = $this->cadenceService->createStep($cadenceId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Step added successfully',
            'data' => $step,
        ], 201);
    }

    /**
     * Update a step
     */
    public function updateStep(Request $request, int $cadenceId, int $stepId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'channel' => 'in:email,call,sms,linkedin,task,wait',
            'delay_type' => 'in:immediate,days,hours,business_days',
            'delay_value' => 'integer|min:0',
            'preferred_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'template_id' => 'nullable|exists:email_campaign_templates,id',
            'conditions' => 'nullable|array',
            'on_reply_goto_step' => 'nullable|exists:cadence_steps,id',
            'on_click_goto_step' => 'nullable|exists:cadence_steps,id',
            'on_no_response_goto_step' => 'nullable|exists:cadence_steps,id',
            'is_ab_test' => 'boolean',
            'ab_percentage' => 'nullable|integer|min:1|max:100',
            'linkedin_action' => 'nullable|in:connection_request,message,view_profile,engage',
            'task_type' => 'nullable|string|max:255',
            'task_assigned_to' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $step = $this->cadenceService->updateStep($stepId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Step updated successfully',
            'data' => $step,
        ]);
    }

    /**
     * Delete a step
     */
    public function deleteStep(int $cadenceId, int $stepId): JsonResponse
    {
        $this->cadenceService->deleteStep($stepId);

        return response()->json([
            'success' => true,
            'message' => 'Step deleted successfully',
        ]);
    }

    /**
     * Reorder steps
     */
    public function reorderSteps(Request $request, int $cadenceId): JsonResponse
    {
        $validated = $request->validate([
            'step_ids' => 'required|array',
            'step_ids.*' => 'exists:cadence_steps,id',
        ]);

        $this->cadenceService->reorderSteps($cadenceId, $validated['step_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Steps reordered successfully',
        ]);
    }

    // Enrollment Management

    /**
     * Get enrollments for a cadence
     */
    public function enrollments(Request $request, int $cadenceId): JsonResponse
    {
        $enrollments = $this->cadenceService->getEnrollments($cadenceId, $request->all());

        return response()->json([
            'success' => true,
            'data' => $enrollments->items(),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    /**
     * Enroll a record
     */
    public function enroll(Request $request, int $cadenceId): JsonResponse
    {
        $validated = $request->validate([
            'record_id' => 'required|integer',
        ]);

        $enrollment = $this->cadenceService->enroll($cadenceId, $validated['record_id']);

        return response()->json([
            'success' => true,
            'message' => 'Record enrolled successfully',
            'data' => $enrollment,
        ], 201);
    }

    /**
     * Bulk enroll records
     */
    public function bulkEnroll(Request $request, int $cadenceId): JsonResponse
    {
        $validated = $request->validate([
            'record_ids' => 'required|array',
            'record_ids.*' => 'integer',
        ]);

        $results = $this->cadenceService->bulkEnroll($cadenceId, $validated['record_ids']);

        return response()->json([
            'success' => true,
            'message' => "Enrolled {$results['success']} records, {$results['failed']} failed",
            'data' => $results,
        ]);
    }

    /**
     * Unenroll a record
     */
    public function unenroll(Request $request, int $cadenceId, int $enrollmentId): JsonResponse
    {
        $reason = $request->input('reason', 'Manually removed');
        $enrollment = $this->cadenceService->unenroll($enrollmentId, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Record unenrolled successfully',
            'data' => $enrollment,
        ]);
    }

    /**
     * Pause an enrollment
     */
    public function pauseEnrollment(int $cadenceId, int $enrollmentId): JsonResponse
    {
        $enrollment = $this->cadenceService->pauseEnrollment($enrollmentId);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment paused successfully',
            'data' => $enrollment,
        ]);
    }

    /**
     * Resume an enrollment
     */
    public function resumeEnrollment(int $cadenceId, int $enrollmentId): JsonResponse
    {
        $enrollment = $this->cadenceService->resumeEnrollment($enrollmentId);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment resumed successfully',
            'data' => $enrollment,
        ]);
    }

    // Templates

    /**
     * Get cadence templates
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = $this->cadenceService->getTemplates($request->input('category'));

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Create cadence from template
     */
    public function createFromTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:cadence_templates,id',
            'module_id' => 'required|exists:modules,id',
            'name' => 'required|string|max:255',
        ]);

        $cadence = $this->cadenceService->createFromTemplate(
            $validated['template_id'],
            $validated['module_id'],
            $validated['name']
        );

        return response()->json([
            'success' => true,
            'message' => 'Cadence created from template',
            'data' => $cadence,
        ], 201);
    }

    /**
     * Save cadence as template
     */
    public function saveAsTemplate(Request $request, int $cadenceId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);

        $template = $this->cadenceService->saveAsTemplate(
            $cadenceId,
            $validated['name'],
            $validated['category'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Template saved successfully',
            'data' => $template,
        ], 201);
    }
}
