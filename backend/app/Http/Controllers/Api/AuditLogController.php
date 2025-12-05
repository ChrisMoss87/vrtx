<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auditable_type' => 'nullable|string',
            'auditable_id' => 'nullable|integer',
            'event' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'tags' => 'nullable|array',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if (isset($validated['auditable_type'])) {
            $query->where('auditable_type', $validated['auditable_type']);
        }

        if (isset($validated['auditable_id'])) {
            $query->where('auditable_id', $validated['auditable_id']);
        }

        if (isset($validated['event'])) {
            $query->forEvent($validated['event']);
        }

        if (isset($validated['user_id'])) {
            $query->byUser($validated['user_id']);
        }

        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            $query->betweenDates($validated['start_date'], $validated['end_date']);
        }

        if (isset($validated['tags'])) {
            $query->withTags($validated['tags']);
        }

        $perPage = $validated['per_page'] ?? 25;
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => collect($logs->items())->map(fn($log) => $this->formatLog($log)),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get a single audit log entry.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user:id,name,email');

        return response()->json([
            'data' => $this->formatLog($auditLog, true),
        ]);
    }

    /**
     * Get audit trail for a specific record.
     */
    public function forRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auditable_type' => 'required|string',
            'auditable_id' => 'required|integer',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $validated['limit'] ?? 50;

        $logs = AuditLog::forAuditable($validated['auditable_type'], $validated['auditable_id'])
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $logs->map(fn($log) => $this->formatLog($log)),
        ]);
    }

    /**
     * Get audit summary for a record.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auditable_type' => 'required|string',
            'auditable_id' => 'required|integer',
        ]);

        $logs = AuditLog::forAuditable($validated['auditable_type'], $validated['auditable_id']);

        // Get summary stats
        $totalChanges = $logs->count();
        $eventCounts = AuditLog::forAuditable($validated['auditable_type'], $validated['auditable_id'])
            ->selectRaw('event, count(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();

        $uniqueUsers = AuditLog::forAuditable($validated['auditable_type'], $validated['auditable_id'])
            ->distinct('user_id')
            ->count('user_id');

        $lastChange = AuditLog::forAuditable($validated['auditable_type'], $validated['auditable_id'])
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->first();

        $firstChange = AuditLog::forAuditable($validated['auditable_type'], $validated['auditable_id'])
            ->orderBy('created_at', 'asc')
            ->first();

        return response()->json([
            'data' => [
                'total_changes' => $totalChanges,
                'event_counts' => $eventCounts,
                'unique_users' => $uniqueUsers,
                'first_change_at' => $firstChange?->created_at,
                'last_change_at' => $lastChange?->created_at,
                'last_change_by' => $lastChange?->user,
            ],
        ]);
    }

    /**
     * Get audit logs for a user.
     */
    public function forUser(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AuditLog::byUser($userId)
            ->orderBy('created_at', 'desc');

        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            $query->betweenDates($validated['start_date'], $validated['end_date']);
        }

        $perPage = $validated['per_page'] ?? 25;
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => collect($logs->items())->map(fn($log) => $this->formatLog($log)),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Compare two audit log entries.
     */
    public function compare(AuditLog $log1, AuditLog $log2): JsonResponse
    {
        return response()->json([
            'data' => [
                'log1' => $this->formatLog($log1, true),
                'log2' => $this->formatLog($log2, true),
                'time_diff' => $log1->created_at->diffForHumans($log2->created_at, true),
            ],
        ]);
    }

    /**
     * Format log for response.
     */
    protected function formatLog(AuditLog $log, bool $detailed = false): array
    {
        $data = [
            'id' => $log->id,
            'user' => $log->user,
            'event' => $log->event,
            'event_description' => $log->event_description,
            'auditable_type' => class_basename($log->auditable_type),
            'auditable_id' => $log->auditable_id,
            'changed_fields' => $log->changed_fields,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at->toISOString(),
        ];

        if ($detailed) {
            $data['old_values'] = $log->old_values;
            $data['new_values'] = $log->new_values;
            $data['diff'] = $log->getDiff();
            $data['user_agent'] = $log->user_agent;
            $data['url'] = $log->url;
            $data['tags'] = $log->tags;
            $data['batch_id'] = $log->batch_id;
        }

        return $data;
    }
}
