<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\SchedulingPage;
use App\Services\Scheduling\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SchedulingPageController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * List all scheduling pages for the current user.
     */
    public function index(): JsonResponse
    {
        $pages = SchedulingPage::where('user_id', Auth::id())
            ->with('meetingTypes')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pages' => $pages,
        ]);
    }

    /**
     * Create a new scheduling page.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('scheduling_pages', 'slug'),
            ],
            'description' => 'nullable|string',
            'timezone' => 'required|string|timezone',
            'is_active' => 'boolean',
            'branding' => 'nullable|array',
            'branding.logo_url' => 'nullable|url',
            'branding.primary_color' => 'nullable|string|max:7',
            'branding.welcome_message' => 'nullable|string',
        ]);

        $page = SchedulingPage::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'timezone' => $validated['timezone'],
            'is_active' => $validated['is_active'] ?? true,
            'branding' => $validated['branding'] ?? null,
        ]);

        // Initialize default availability if user doesn't have any
        $user = Auth::user();
        if ($user->availabilityRules()->count() === 0) {
            $this->availabilityService->initializeDefaultAvailability($user);
        }

        return response()->json([
            'message' => 'Scheduling page created successfully',
            'page' => $page->load('meetingTypes'),
        ], 201);
    }

    /**
     * Get a specific scheduling page.
     */
    public function show(SchedulingPage $schedulingPage): JsonResponse
    {
        $this->authorize('view', $schedulingPage);

        return response()->json([
            'page' => $schedulingPage->load('meetingTypes'),
        ]);
    }

    /**
     * Update a scheduling page.
     */
    public function update(Request $request, SchedulingPage $schedulingPage): JsonResponse
    {
        $this->authorize('update', $schedulingPage);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('scheduling_pages', 'slug')->ignore($schedulingPage->id),
            ],
            'description' => 'nullable|string',
            'timezone' => 'sometimes|required|string|timezone',
            'is_active' => 'boolean',
            'branding' => 'nullable|array',
        ]);

        $schedulingPage->update($validated);

        return response()->json([
            'message' => 'Scheduling page updated successfully',
            'page' => $schedulingPage->fresh()->load('meetingTypes'),
        ]);
    }

    /**
     * Delete a scheduling page.
     */
    public function destroy(SchedulingPage $schedulingPage): JsonResponse
    {
        $this->authorize('delete', $schedulingPage);

        $schedulingPage->delete();

        return response()->json([
            'message' => 'Scheduling page deleted successfully',
        ]);
    }

    /**
     * Check if a slug is available.
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|max:100|regex:/^[a-z0-9-]+$/',
            'exclude_id' => 'nullable|integer',
        ]);

        $query = SchedulingPage::where('slug', $request->slug);

        if ($request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $available = !$query->exists();

        return response()->json([
            'available' => $available,
            'slug' => $request->slug,
        ]);
    }
}
