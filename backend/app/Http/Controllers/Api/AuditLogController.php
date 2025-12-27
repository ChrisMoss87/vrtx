<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\AuditLog\Repositories\AuditLogRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditLogRepositoryInterface $auditLogRepository
    ) {}

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

        $filters = $validated;
        if (isset($filters['auditable_id'])) {
            $filters['auditable_id'] = (int) $filters['auditable_id'];
        }
        if (isset($filters['user_id'])) {
            $filters['user_id'] = (int) $filters['user_id'];
        }

        $perPage = (int) ($validated['per_page'] ?? 25);
        $result = $this->auditLogRepository->findWithFilters($filters, $perPage);

        return response()->json([
            'data' => collect($result->items())->map(fn($log) => $this->formatLog($log)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    /**
     * Get a single audit log entry.
     */
    public function show(int $id): JsonResponse
    {
        $auditLog = $this->auditLogRepository->findByIdWithUser($id);

        if (!$auditLog) {
            return response()->json([
                'message' => 'Audit log not found',
            ], 404);
        }

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

        $limit = (int) ($validated['limit'] ?? 50);
        $logs = $this->auditLogRepository->findForAuditable(
            $validated['auditable_type'],
            (int) $validated['auditable_id'],
            $limit
        );

        return response()->json([
            'data' => collect($logs)->map(fn($log) => $this->formatLog($log)),
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

        $summary = $this->auditLogRepository->getSummary(
            $validated['auditable_type'],
            (int) $validated['auditable_id']
        );

        return response()->json([
            'data' => $summary,
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

        $perPage = (int) ($validated['per_page'] ?? 25);
        $result = $this->auditLogRepository->findByUser(
            $userId,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $perPage
        );

        return response()->json([
            'data' => collect($result->items())->map(fn($log) => $this->formatLog($log)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    /**
     * Compare two audit log entries.
     */
    public function compare(int $log1Id, int $log2Id): JsonResponse
    {
        $log1 = $this->auditLogRepository->findByIdWithUser($log1Id);
        $log2 = $this->auditLogRepository->findByIdWithUser($log2Id);

        if (!$log1 || !$log2) {
            return response()->json([
                'message' => 'One or both audit logs not found',
            ], 404);
        }

        $time1 = new \DateTimeImmutable($log1['created_at']);
        $time2 = new \DateTimeImmutable($log2['created_at']);
        $diff = $time1->diff($time2);

        return response()->json([
            'data' => [
                'log1' => $this->formatLog($log1, true),
                'log2' => $this->formatLog($log2, true),
                'time_diff' => $this->formatDiff($diff),
            ],
        ]);
    }

    /**
     * Format log for response.
     */
    protected function formatLog(array $log, bool $detailed = false): array
    {
        $data = [
            'id' => $log['id'],
            'user' => $log['user'] ?? null,
            'event' => $log['event'],
            'event_description' => $log['event_description'] ?? null,
            'auditable_type' => class_basename($log['auditable_type'] ?? ''),
            'auditable_id' => $log['auditable_id'],
            'changed_fields' => $log['changed_fields'] ?? [],
            'ip_address' => $log['ip_address'] ?? null,
            'created_at' => $log['created_at'],
        ];

        if ($detailed) {
            $data['old_values'] = $log['old_values'] ?? null;
            $data['new_values'] = $log['new_values'] ?? null;
            $data['diff'] = $this->getDiff($log);
            $data['user_agent'] = $log['user_agent'] ?? null;
            $data['url'] = $log['url'] ?? null;
            $data['tags'] = $log['tags'] ?? null;
            $data['batch_id'] = $log['batch_id'] ?? null;
        }

        return $data;
    }

    /**
     * Get diff between old and new values.
     */
    protected function getDiff(array $log): array
    {
        $diff = [];
        $oldValues = $log['old_values'] ?? [];
        $newValues = $log['new_values'] ?? [];
        $changedFields = $log['changed_fields'] ?? [];

        foreach ($changedFields as $field) {
            $diff[$field] = [
                'old' => $oldValues[$field] ?? null,
                'new' => $newValues[$field] ?? null,
            ];
        }

        return $diff;
    }

    /**
     * Format DateInterval for human readability.
     */
    protected function formatDiff(\DateInterval $diff): string
    {
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        }
        if ($diff->d > 0) {
            $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            $parts[] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }

        if (empty($parts)) {
            return 'less than a minute';
        }

        return implode(', ', $parts);
    }
}
