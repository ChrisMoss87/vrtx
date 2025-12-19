<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertHistory;
use App\Models\AnalyticsAlertSubscription;
use App\Services\Analytics\AnalyticsAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsAlertController extends Controller
{
    public function __construct(
        protected AnalyticsAlertService $alertService
    ) {}

    /**
     * List all alerts for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AnalyticsAlert::with(['module:id,api_name,label', 'report:id,name'])
            ->where('user_id', Auth::id());

        if ($request->has('type')) {
            $query->ofType($request->input('type'));
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $alerts,
        ]);
    }

    /**
     * Create a new alert.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'alert_type' => 'required|in:threshold,anomaly,trend,comparison',
            'module_id' => 'nullable|exists:modules,id',
            'report_id' => 'nullable|exists:reports,id',
            'metric_field' => 'nullable|string|max:100',
            'aggregation' => 'nullable|in:count,sum,avg,min,max,count_distinct',
            'filters' => 'nullable|array',
            'condition_config' => 'required|array',
            'notification_config' => 'required|array',
            'check_frequency' => 'required|in:hourly,daily,weekly',
            'check_time' => 'nullable|date_format:H:i',
            'cooldown_minutes' => 'nullable|integer|min:5|max:1440',
        ]);

        $validated['user_id'] = Auth::id();

        $alert = AnalyticsAlert::create($validated);

        return response()->json([
            'data' => $alert->load(['module:id,api_name,label', 'report:id,name']),
            'message' => 'Alert created successfully',
        ], 201);
    }

    /**
     * Get a single alert.
     */
    public function show(AnalyticsAlert $alert): JsonResponse
    {
        $this->authorize('view', $alert);

        return response()->json([
            'data' => $alert->load([
                'module:id,api_name,label',
                'report:id,name',
                'history' => fn($q) => $q->orderBy('created_at', 'desc')->limit(10),
            ]),
        ]);
    }

    /**
     * Update an alert.
     */
    public function update(Request $request, AnalyticsAlert $alert): JsonResponse
    {
        $this->authorize('update', $alert);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'alert_type' => 'sometimes|in:threshold,anomaly,trend,comparison',
            'module_id' => 'nullable|exists:modules,id',
            'report_id' => 'nullable|exists:reports,id',
            'metric_field' => 'nullable|string|max:100',
            'aggregation' => 'nullable|in:count,sum,avg,min,max,count_distinct',
            'filters' => 'nullable|array',
            'condition_config' => 'sometimes|array',
            'notification_config' => 'sometimes|array',
            'check_frequency' => 'sometimes|in:hourly,daily,weekly',
            'check_time' => 'nullable|date_format:H:i',
            'cooldown_minutes' => 'nullable|integer|min:5|max:1440',
            'is_active' => 'sometimes|boolean',
        ]);

        $alert->update($validated);

        return response()->json([
            'data' => $alert->fresh(['module:id,api_name,label', 'report:id,name']),
            'message' => 'Alert updated successfully',
        ]);
    }

    /**
     * Delete an alert.
     */
    public function destroy(AnalyticsAlert $alert): JsonResponse
    {
        $this->authorize('delete', $alert);

        $alert->delete();

        return response()->json([
            'message' => 'Alert deleted successfully',
        ]);
    }

    /**
     * Toggle alert active status.
     */
    public function toggle(AnalyticsAlert $alert): JsonResponse
    {
        $this->authorize('update', $alert);

        $alert->update(['is_active' => !$alert->is_active]);

        return response()->json([
            'data' => $alert,
            'message' => $alert->is_active ? 'Alert enabled' : 'Alert disabled',
        ]);
    }

    /**
     * Manually trigger an alert check.
     */
    public function check(AnalyticsAlert $alert): JsonResponse
    {
        $this->authorize('update', $alert);

        try {
            $triggered = $this->alertService->checkAlert($alert);

            return response()->json([
                'data' => [
                    'triggered' => $triggered,
                    'alert' => $alert->fresh(),
                ],
                'message' => $triggered ? 'Alert triggered!' : 'Alert conditions not met',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check alert: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get alert history.
     */
    public function history(Request $request, AnalyticsAlert $alert): JsonResponse
    {
        $this->authorize('view', $alert);

        $history = $alert->history()
            ->with('acknowledgedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($history);
    }

    /**
     * Get all unacknowledged alerts for the current user.
     */
    public function unacknowledged(): JsonResponse
    {
        $alerts = $this->alertService->getUnacknowledgedAlerts(Auth::id());

        return response()->json([
            'data' => $alerts,
        ]);
    }

    /**
     * Acknowledge an alert history entry.
     */
    public function acknowledge(Request $request, AnalyticsAlertHistory $history): JsonResponse
    {
        // Check user has access to this alert
        $alert = $history->alert;
        $this->authorize('view', $alert);

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $this->alertService->acknowledgeAlert(
            $history->id,
            Auth::id(),
            $request->input('note')
        );

        return response()->json([
            'data' => $history->fresh(),
            'message' => 'Alert acknowledged',
        ]);
    }

    /**
     * Get alert statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->alertService->getAlertStats(Auth::id());

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get available alert types and configuration options.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'types' => AnalyticsAlert::getTypes(),
                'frequencies' => AnalyticsAlert::getFrequencies(),
                'operators' => [
                    'greater_than' => 'Greater than',
                    'less_than' => 'Less than',
                    'greater_or_equal' => 'Greater than or equal',
                    'less_or_equal' => 'Less than or equal',
                    'equals' => 'Equals',
                    'not_equals' => 'Not equals',
                ],
                'aggregations' => [
                    'count' => 'Count',
                    'sum' => 'Sum',
                    'avg' => 'Average',
                    'min' => 'Minimum',
                    'max' => 'Maximum',
                    'count_distinct' => 'Count Distinct',
                ],
                'comparison_periods' => [
                    'previous_day' => 'Previous Day',
                    'previous_week' => 'Previous Week',
                    'previous_month' => 'Previous Month',
                    'previous_quarter' => 'Previous Quarter',
                    'previous_year' => 'Previous Year',
                ],
                'sensitivities' => [
                    'low' => 'Low (fewer alerts)',
                    'medium' => 'Medium',
                    'high' => 'High (more alerts)',
                ],
            ],
        ]);
    }

    /**
     * Subscribe to an alert.
     */
    public function subscribe(Request $request, AnalyticsAlert $alert): JsonResponse
    {
        $request->validate([
            'channels' => 'nullable|array',
            'channels.*' => 'in:email,in_app,slack,webhook',
        ]);

        $subscription = AnalyticsAlertSubscription::updateOrCreate(
            [
                'alert_id' => $alert->id,
                'user_id' => Auth::id(),
            ],
            [
                'channels' => $request->input('channels'),
                'is_muted' => false,
            ]
        );

        return response()->json([
            'data' => $subscription,
            'message' => 'Subscribed to alert',
        ]);
    }

    /**
     * Unsubscribe from an alert.
     */
    public function unsubscribe(AnalyticsAlert $alert): JsonResponse
    {
        AnalyticsAlertSubscription::where('alert_id', $alert->id)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json([
            'message' => 'Unsubscribed from alert',
        ]);
    }

    /**
     * Mute an alert subscription.
     */
    public function mute(Request $request, AnalyticsAlert $alert): JsonResponse
    {
        $request->validate([
            'until' => 'nullable|date|after:now',
        ]);

        $subscription = AnalyticsAlertSubscription::where('alert_id', $alert->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'Not subscribed to this alert',
            ], 404);
        }

        $until = $request->input('until') ? new \DateTime($request->input('until')) : null;
        $subscription->mute($until);

        return response()->json([
            'data' => $subscription,
            'message' => $until ? "Muted until {$until->format('Y-m-d H:i')}" : 'Muted indefinitely',
        ]);
    }
}
