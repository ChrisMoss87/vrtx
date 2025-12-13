<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScanDuplicatesJob;
use App\Models\DuplicateCandidate;
use App\Models\DuplicateRule;
use App\Models\Module;
use App\Services\Duplicates\DuplicateDetectionService;
use App\Services\Duplicates\DuplicateMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DuplicateController extends Controller
{
    public function __construct(
        protected DuplicateDetectionService $detectionService,
        protected DuplicateMergeService $mergeService
    ) {}

    /**
     * Check for potential duplicates (real-time check on create/update).
     *
     * GET /api/v1/duplicates/check
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|integer|exists:modules,id',
            'data' => 'required|array',
            'exclude_record_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $module = Module::findOrFail($request->module_id);
        $duplicates = $this->detectionService->checkForDuplicates(
            $module,
            $request->data,
            $request->exclude_record_id
        );

        // Determine if we should block
        $shouldBlock = collect($duplicates)->contains(fn ($d) => $d['action'] === 'block');

        // Transform for response
        $transformedDuplicates = collect($duplicates)->map(function ($duplicate) {
            return [
                'record_id' => $duplicate['record_id'],
                'record' => [
                    'id' => $duplicate['record']->id,
                    'data' => $duplicate['record']->data,
                    'created_at' => $duplicate['record']->created_at,
                ],
                'match_score' => round($duplicate['match_score'] * 100, 1), // Convert to percentage
                'matched_rules' => $duplicate['matched_rules'],
                'action' => $duplicate['action'],
            ];
        })->take(5)->values(); // Limit to top 5

        return response()->json([
            'data' => [
                'has_duplicates' => $duplicates !== [],
                'should_block' => $shouldBlock,
                'duplicates' => $transformedDuplicates,
            ],
        ]);
    }

    /**
     * List duplicate candidates for a module.
     *
     * GET /api/v1/duplicates/candidates
     */
    public function candidates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|integer|exists:modules,id',
            'status' => 'nullable|string|in:pending,merged,dismissed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $candidates = $this->detectionService->getCandidates(
            $request->module_id,
            $request->status,
            $request->per_page ?? 20
        );

        // Transform candidates
        $transformedCandidates = $candidates->through(function ($candidate) {
            return [
                'id' => $candidate->id,
                'record_a' => [
                    'id' => $candidate->recordA->id,
                    'data' => $candidate->recordA->data,
                    'created_at' => $candidate->recordA->created_at,
                ],
                'record_b' => [
                    'id' => $candidate->recordB->id,
                    'data' => $candidate->recordB->data,
                    'created_at' => $candidate->recordB->created_at,
                ],
                'match_score' => round($candidate->match_score * 100, 1),
                'matched_rules' => $candidate->matched_rules,
                'status' => $candidate->status,
                'reviewed_by' => $candidate->reviewer ? [
                    'id' => $candidate->reviewer->id,
                    'name' => $candidate->reviewer->name,
                ] : null,
                'reviewed_at' => $candidate->reviewed_at,
                'dismiss_reason' => $candidate->dismiss_reason,
                'created_at' => $candidate->created_at,
            ];
        });

        return response()->json([
            'data' => $transformedCandidates->items(),
            'meta' => [
                'current_page' => $transformedCandidates->currentPage(),
                'last_page' => $transformedCandidates->lastPage(),
                'per_page' => $transformedCandidates->perPage(),
                'total' => $transformedCandidates->total(),
            ],
        ]);
    }

    /**
     * Merge duplicate records.
     *
     * POST /api/v1/duplicates/merge
     */
    public function merge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'surviving_record_id' => 'required|integer|exists:module_records,id',
            'merge_record_ids' => 'required|array|min:1',
            'merge_record_ids.*' => 'integer|exists:module_records,id',
            'field_selections' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $mergedRecord = $this->mergeService->mergeRecords(
                $request->surviving_record_id,
                $request->merge_record_ids,
                $request->field_selections,
                $request->user()->id
            );

            return response()->json([
                'data' => [
                    'id' => $mergedRecord->id,
                    'data' => $mergedRecord->data,
                ],
                'message' => 'Records merged successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview a merge operation.
     *
     * POST /api/v1/duplicates/preview
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'record_a_id' => 'required|integer|exists:module_records,id',
            'record_b_id' => 'required|integer|exists:module_records,id',
            'field_selections' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $preview = $this->mergeService->previewMerge(
            $request->record_a_id,
            $request->record_b_id,
            $request->field_selections ?? []
        );

        return response()->json([
            'data' => $preview,
        ]);
    }

    /**
     * Dismiss a duplicate candidate.
     *
     * POST /api/v1/duplicates/dismiss
     */
    public function dismiss(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|integer|exists:duplicate_candidates,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $candidate = DuplicateCandidate::findOrFail($request->candidate_id);
        $this->detectionService->dismissCandidate(
            $candidate,
            $request->user()->id,
            $request->reason
        );

        return response()->json([
            'message' => 'Duplicate candidate dismissed',
        ]);
    }

    /**
     * List duplicate detection rules.
     *
     * GET /api/v1/duplicates/rules
     */
    public function rules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'nullable|integer|exists:modules,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = DuplicateRule::with(['module', 'creator'])->ordered();

        if ($request->module_id) {
            $query->forModule($request->module_id);
        }

        $rules = $query->get();

        return response()->json([
            'data' => $rules->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'module' => [
                    'id' => $rule->module->id,
                    'name' => $rule->module->name,
                ],
                'is_active' => $rule->is_active,
                'action' => $rule->action,
                'conditions' => $rule->conditions,
                'priority' => $rule->priority,
                'created_by' => $rule->creator ? [
                    'id' => $rule->creator->id,
                    'name' => $rule->creator->name,
                ] : null,
                'created_at' => $rule->created_at,
            ]),
        ]);
    }

    /**
     * Create a duplicate detection rule.
     *
     * POST /api/v1/duplicates/rules
     */
    public function createRule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|integer|exists:modules,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'action' => 'nullable|string|in:warn,block',
            'conditions' => 'required|array',
            'priority' => 'nullable|integer|min:0|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rule = DuplicateRule::create([
            'module_id' => $request->module_id,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'action' => $request->action ?? 'warn',
            'conditions' => $request->conditions,
            'priority' => $request->priority ?? 0,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $rule,
            'message' => 'Duplicate rule created successfully',
        ], 201);
    }

    /**
     * Update a duplicate detection rule.
     *
     * PUT /api/v1/duplicates/rules/{id}
     */
    public function updateRule(Request $request, int $id): JsonResponse
    {
        $rule = DuplicateRule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'action' => 'nullable|string|in:warn,block',
            'conditions' => 'nullable|array',
            'priority' => 'nullable|integer|min:0|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rule->update($request->only([
            'name',
            'description',
            'is_active',
            'action',
            'conditions',
            'priority',
        ]));

        return response()->json([
            'data' => $rule->fresh(),
            'message' => 'Duplicate rule updated successfully',
        ]);
    }

    /**
     * Delete a duplicate detection rule.
     *
     * DELETE /api/v1/duplicates/rules/{id}
     */
    public function deleteRule(int $id): JsonResponse
    {
        $rule = DuplicateRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'message' => 'Duplicate rule deleted successfully',
        ]);
    }

    /**
     * Trigger a batch scan for duplicates.
     *
     * POST /api/v1/duplicates/scan
     */
    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|integer|exists:modules,id',
            'limit' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Dispatch async job for batch scanning
        ScanDuplicatesJob::dispatch(
            $request->module_id,
            $request->limit
        );

        return response()->json([
            'message' => 'Duplicate scan started. Results will be available in the candidates list.',
        ]);
    }

    /**
     * Get merge history for a module.
     *
     * GET /api/v1/duplicates/history
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|integer|exists:modules,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $history = $this->mergeService->getMergeHistory(
            $request->module_id,
            $request->per_page ?? 20
        );

        return response()->json([
            'data' => $history->through(fn ($log) => [
                'id' => $log->id,
                'surviving_record' => $log->survivingRecord ? [
                    'id' => $log->survivingRecord->id,
                    'data' => $log->survivingRecord->data,
                ] : null,
                'merged_count' => $log->merged_count,
                'merged_record_ids' => $log->merged_record_ids,
                'field_selections' => $log->field_selections,
                'merged_by' => $log->mergedByUser ? [
                    'id' => $log->mergedByUser->id,
                    'name' => $log->mergedByUser->name,
                ] : null,
                'created_at' => $log->created_at,
            ])->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    /**
     * Get duplicate statistics.
     *
     * GET /api/v1/duplicates/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'nullable|integer|exists:modules,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = DuplicateCandidate::query();
        if ($request->module_id) {
            $query->forModule($request->module_id);
        }

        $pending = (clone $query)->where('status', 'pending')->count();
        $merged = (clone $query)->where('status', 'merged')->count();
        $dismissed = (clone $query)->where('status', 'dismissed')->count();

        $ruleQuery = DuplicateRule::query();
        if ($request->module_id) {
            $ruleQuery->forModule($request->module_id);
        }
        $activeRules = $ruleQuery->active()->count();
        $totalRules = $ruleQuery->count();

        return response()->json([
            'data' => [
                'candidates' => [
                    'pending' => $pending,
                    'merged' => $merged,
                    'dismissed' => $dismissed,
                    'total' => $pending + $merged + $dismissed,
                ],
                'rules' => [
                    'active' => $activeRules,
                    'total' => $totalRules,
                ],
            ],
        ]);
    }
}
