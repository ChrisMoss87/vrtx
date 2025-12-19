<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\MeetingType;
use App\Models\SchedulingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MeetingTypeController extends Controller
{
    /**
     * List all meeting types for a scheduling page.
     */
    public function index(SchedulingPage $schedulingPage): JsonResponse
    {
        $this->authorize('view', $schedulingPage);

        $meetingTypes = $schedulingPage->meetingTypes()
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'meeting_types' => $meetingTypes,
        ]);
    }

    /**
     * Create a new meeting type.
     */
    public function store(Request $request, SchedulingPage $schedulingPage): JsonResponse
    {
        $this->authorize('update', $schedulingPage);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
            ],
            'duration_minutes' => 'required|integer|min:5|max:480',
            'description' => 'nullable|string',
            'location_type' => 'nullable|string|in:zoom,google_meet,phone,in_person,custom',
            'location_details' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
            'questions' => 'nullable|array',
            'questions.*.id' => 'required|string',
            'questions.*.label' => 'required|string',
            'questions.*.type' => 'required|string|in:text,textarea,select,checkbox',
            'questions.*.required' => 'boolean',
            'questions.*.options' => 'nullable|array',
            'settings' => 'nullable|array',
            'settings.buffer_before' => 'nullable|integer|min:0|max:60',
            'settings.buffer_after' => 'nullable|integer|min:0|max:60',
            'settings.min_notice_hours' => 'nullable|integer|min:0|max:168',
            'settings.max_days_advance' => 'nullable|integer|min:1|max:365',
            'settings.slot_interval' => 'nullable|integer|min:5|max:60',
        ]);

        // Ensure unique slug within the page
        if (!empty($validated['slug'])) {
            $existingSlug = MeetingType::where('scheduling_page_id', $schedulingPage->id)
                ->where('slug', $validated['slug'])
                ->exists();

            if ($existingSlug) {
                return response()->json([
                    'message' => 'This URL slug is already used by another meeting type.',
                    'errors' => ['slug' => ['The slug is already in use.']],
                ], 422);
            }
        }

        // Get next display order
        $maxOrder = $schedulingPage->meetingTypes()->max('display_order') ?? -1;

        $meetingType = MeetingType::create([
            'scheduling_page_id' => $schedulingPage->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'description' => $validated['description'] ?? null,
            'location_type' => $validated['location_type'] ?? null,
            'location_details' => $validated['location_details'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'questions' => $validated['questions'] ?? [],
            'settings' => array_merge(
                MeetingType::DEFAULT_SETTINGS,
                $validated['settings'] ?? []
            ),
            'display_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'message' => 'Meeting type created successfully',
            'meeting_type' => $meetingType,
        ], 201);
    }

    /**
     * Get a specific meeting type.
     */
    public function show(SchedulingPage $schedulingPage, MeetingType $meetingType): JsonResponse
    {
        $this->authorize('view', $schedulingPage);

        if ($meetingType->scheduling_page_id !== $schedulingPage->id) {
            abort(404);
        }

        return response()->json([
            'meeting_type' => $meetingType,
        ]);
    }

    /**
     * Update a meeting type.
     */
    public function update(Request $request, SchedulingPage $schedulingPage, MeetingType $meetingType): JsonResponse
    {
        $this->authorize('update', $schedulingPage);

        if ($meetingType->scheduling_page_id !== $schedulingPage->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
            ],
            'duration_minutes' => 'sometimes|required|integer|min:5|max:480',
            'description' => 'nullable|string',
            'location_type' => 'nullable|string|in:zoom,google_meet,phone,in_person,custom',
            'location_details' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
            'questions' => 'nullable|array',
            'settings' => 'nullable|array',
            'display_order' => 'nullable|integer|min:0',
        ]);

        // Check slug uniqueness if changed
        if (!empty($validated['slug']) && $validated['slug'] !== $meetingType->slug) {
            $existingSlug = MeetingType::where('scheduling_page_id', $schedulingPage->id)
                ->where('slug', $validated['slug'])
                ->where('id', '!=', $meetingType->id)
                ->exists();

            if ($existingSlug) {
                return response()->json([
                    'message' => 'This URL slug is already used by another meeting type.',
                    'errors' => ['slug' => ['The slug is already in use.']],
                ], 422);
            }
        }

        // Merge settings with existing
        if (isset($validated['settings'])) {
            $validated['settings'] = array_merge(
                $meetingType->settings ?? MeetingType::DEFAULT_SETTINGS,
                $validated['settings']
            );
        }

        $meetingType->update($validated);

        return response()->json([
            'message' => 'Meeting type updated successfully',
            'meeting_type' => $meetingType->fresh(),
        ]);
    }

    /**
     * Delete a meeting type.
     */
    public function destroy(SchedulingPage $schedulingPage, MeetingType $meetingType): JsonResponse
    {
        $this->authorize('update', $schedulingPage);

        if ($meetingType->scheduling_page_id !== $schedulingPage->id) {
            abort(404);
        }

        // Check for scheduled meetings
        $upcomingMeetings = $meetingType->scheduledMeetings()
            ->where('status', 'scheduled')
            ->where('start_time', '>', now())
            ->count();

        if ($upcomingMeetings > 0) {
            return response()->json([
                'message' => "Cannot delete meeting type with {$upcomingMeetings} upcoming meeting(s). Please cancel them first.",
            ], 422);
        }

        $meetingType->delete();

        return response()->json([
            'message' => 'Meeting type deleted successfully',
        ]);
    }

    /**
     * Reorder meeting types.
     */
    public function reorder(Request $request, SchedulingPage $schedulingPage): JsonResponse
    {
        $this->authorize('update', $schedulingPage);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:meeting_types,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            MeetingType::where('id', $id)
                ->where('scheduling_page_id', $schedulingPage->id)
                ->update(['display_order' => $index]);
        }

        return response()->json([
            'message' => 'Meeting types reordered successfully',
            'meeting_types' => $schedulingPage->meetingTypes()->orderBy('display_order')->get(),
        ]);
    }
}
