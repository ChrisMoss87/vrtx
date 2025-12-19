<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Models\SchedulingOverride;
use App\Services\Scheduling\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AvailabilityController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Get availability rules for the current user.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $rules = AvailabilityRule::where('user_id', $user->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // If no rules exist, initialize defaults
        if ($rules->isEmpty()) {
            $this->availabilityService->initializeDefaultAvailability($user);
            $rules = AvailabilityRule::where('user_id', $user->id)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();
        }

        // Group by day
        $byDay = [];
        foreach (AvailabilityRule::DAYS as $dayNum => $dayName) {
            $dayRules = $rules->where('day_of_week', $dayNum)->values();
            $byDay[$dayNum] = [
                'day_of_week' => $dayNum,
                'day_name' => $dayName,
                'is_available' => $dayRules->where('is_available', true)->isNotEmpty(),
                'windows' => $dayRules->map(fn($r) => [
                    'id' => $r->id,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                    'is_available' => $r->is_available,
                ])->toArray(),
            ];
        }

        return response()->json([
            'availability' => array_values($byDay),
            'days' => AvailabilityRule::DAYS,
        ]);
    }

    /**
     * Update availability rules.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.day_of_week' => 'required|integer|min:0|max:6',
            'rules.*.is_available' => 'required|boolean',
            'rules.*.windows' => 'required_if:rules.*.is_available,true|array',
            'rules.*.windows.*.start_time' => 'required|date_format:H:i',
            'rules.*.windows.*.end_time' => 'required|date_format:H:i|after:rules.*.windows.*.start_time',
        ]);

        $user = Auth::user();

        // Delete existing rules
        AvailabilityRule::where('user_id', $user->id)->delete();

        // Create new rules
        foreach ($validated['rules'] as $dayRule) {
            if (!$dayRule['is_available']) {
                // Create a disabled rule for the day
                AvailabilityRule::create([
                    'user_id' => $user->id,
                    'day_of_week' => $dayRule['day_of_week'],
                    'start_time' => '00:00',
                    'end_time' => '00:00',
                    'is_available' => false,
                ]);
            } else {
                foreach ($dayRule['windows'] ?? [] as $window) {
                    AvailabilityRule::create([
                        'user_id' => $user->id,
                        'day_of_week' => $dayRule['day_of_week'],
                        'start_time' => $window['start_time'],
                        'end_time' => $window['end_time'],
                        'is_available' => true,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Availability updated successfully',
        ]);
    }

    /**
     * Get overrides for a date range.
     */
    public function getOverrides(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $overrides = SchedulingOverride::where('user_id', Auth::id())
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->orderBy('date')
            ->get();

        return response()->json([
            'overrides' => $overrides,
        ]);
    }

    /**
     * Create or update a date override.
     */
    public function storeOverride(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'is_available' => 'required|boolean',
            'start_time' => 'required_if:is_available,true|nullable|date_format:H:i',
            'end_time' => 'required_if:is_available,true|nullable|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:255',
        ]);

        $override = SchedulingOverride::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'date' => $validated['date'],
            ],
            [
                'is_available' => $validated['is_available'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'reason' => $validated['reason'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Override saved successfully',
            'override' => $override,
        ]);
    }

    /**
     * Delete a date override.
     */
    public function destroyOverride(SchedulingOverride $override): JsonResponse
    {
        if ($override->user_id !== Auth::id()) {
            abort(403);
        }

        $override->delete();

        return response()->json([
            'message' => 'Override deleted successfully',
        ]);
    }
}
