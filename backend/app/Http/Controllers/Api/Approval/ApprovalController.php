<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Approval;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalQuickAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $service
    ) {}

    // Approval Requests
    public function index(Request $request): JsonResponse
    {
        $query = ApprovalRequest::with(['rule', 'requestedBy', 'steps.approver']);

        if ($request->has('status')) {
            $query->status($request->status);
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->boolean('pending_only')) {
            $query->pending();
        }

        $requests = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($requests);
    }

    public function pending(): JsonResponse
    {
        $requests = $this->service->getPendingApprovals(auth()->id());

        return response()->json($requests);
    }

    public function myRequests(): JsonResponse
    {
        $requests = $this->service->getMyRequests(auth()->id());

        return response()->json($requests);
    }

    public function show(ApprovalRequest $approvalRequest): JsonResponse
    {
        $approvalRequest->load([
            'rule',
            'requestedBy',
            'finalApprover',
            'steps.approver',
            'steps.delegatedTo',
            'history.user',
        ]);

        return response()->json($approvalRequest);
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'value' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
            'data' => 'nullable|array',
        ]);

        $data = array_merge($validated['data'] ?? [], [
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'value' => $validated['value'] ?? null,
            'currency' => $validated['currency'] ?? 'USD',
        ]);

        $approvalRequest = $this->service->submitForApproval(
            $validated['entity_type'],
            $validated['entity_id'],
            $data
        );

        if (!$approvalRequest) {
            return response()->json([
                'message' => 'No approval required for this item',
                'requires_approval' => false,
            ]);
        }

        return response()->json([
            'message' => 'Submitted for approval',
            'requires_approval' => true,
            'request' => $approvalRequest,
        ], 201);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        try {
            $this->service->approve($approvalRequest, auth()->id(), $validated['comments'] ?? null);

            return response()->json([
                'message' => 'Request approved',
                'status' => $approvalRequest->fresh()->status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        try {
            $this->service->reject($approvalRequest, auth()->id(), $validated['comments'] ?? null);

            return response()->json([
                'message' => 'Request rejected',
                'status' => $approvalRequest->fresh()->status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->service->cancel($approvalRequest, $validated['reason'] ?? null);

            return response()->json(['message' => 'Request cancelled']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function history(ApprovalRequest $approvalRequest): JsonResponse
    {
        $history = $this->service->getApprovalHistory($approvalRequest);

        return response()->json($history);
    }

    public function checkNeedsApproval(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'data' => 'required|array',
        ]);

        $needsApproval = $this->service->checkNeedsApproval(
            $validated['entity_type'],
            $validated['data']
        );

        $rule = $this->service->getApplicableRule(
            $validated['entity_type'],
            $validated['data']
        );

        return response()->json([
            'needs_approval' => $needsApproval,
            'rule' => $rule ? [
                'id' => $rule->id,
                'name' => $rule->name,
                'approval_type' => $rule->approval_type,
                'sla_hours' => $rule->sla_hours,
            ] : null,
        ]);
    }

    // Approval Rules
    public function rules(Request $request): JsonResponse
    {
        $query = ApprovalRule::with('createdBy');

        if ($request->has('entity_type')) {
            $query->forEntity($request->entity_type);
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $rules = $query->ordered()->get();

        return response()->json($rules);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|string|in:' . implode(',', ApprovalRule::ENTITY_TYPES),
            'module_id' => 'nullable|exists:modules,id',
            'conditions' => 'nullable|array',
            'approver_chain' => 'required|array|min:1',
            'approval_type' => 'nullable|string|in:' . implode(',', ApprovalRule::APPROVAL_TYPES),
            'allow_self_approval' => 'nullable|boolean',
            'require_comments' => 'nullable|boolean',
            'sla_hours' => 'nullable|integer|min:1',
            'escalation_rules' => 'nullable|array',
            'notification_settings' => 'nullable|array',
            'priority' => 'nullable|integer|min:0',
        ]);

        $validated['created_by'] = auth()->id();

        $rule = ApprovalRule::create($validated);

        return response()->json($rule, 201);
    }

    public function showRule(ApprovalRule $approvalRule): JsonResponse
    {
        $approvalRule->load('createdBy');

        return response()->json($approvalRule);
    }

    public function updateRule(Request $request, ApprovalRule $approvalRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'conditions' => 'nullable|array',
            'approver_chain' => 'sometimes|array|min:1',
            'approval_type' => 'nullable|string|in:' . implode(',', ApprovalRule::APPROVAL_TYPES),
            'allow_self_approval' => 'nullable|boolean',
            'require_comments' => 'nullable|boolean',
            'sla_hours' => 'nullable|integer|min:1',
            'escalation_rules' => 'nullable|array',
            'notification_settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        $approvalRule->update($validated);

        return response()->json($approvalRule);
    }

    public function destroyRule(ApprovalRule $approvalRule): JsonResponse
    {
        $approvalRule->delete();

        return response()->json(['message' => 'Rule deleted']);
    }

    // Delegations
    public function delegations(): JsonResponse
    {
        $delegations = ApprovalDelegation::forDelegator(auth()->id())
            ->with(['delegate', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($delegations);
    }

    public function delegatedToMe(): JsonResponse
    {
        $delegations = ApprovalDelegation::active()
            ->forDelegate(auth()->id())
            ->with(['delegator'])
            ->get();

        return response()->json($delegations);
    }

    public function storeDelegation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delegate_id' => 'required|exists:users,id',
            'delegation_type' => 'nullable|string|in:all,specific_rules',
            'rule_ids' => 'nullable|array',
            'rule_ids.*' => 'exists:approval_rules,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'reason' => 'nullable|string|max:500',
        ]);

        $delegation = $this->service->setupDelegation($validated);

        return response()->json($delegation, 201);
    }

    public function destroyDelegation(ApprovalDelegation $approvalDelegation): JsonResponse
    {
        $this->service->removeDelegation($approvalDelegation);

        return response()->json(['message' => 'Delegation removed']);
    }

    // Quick Actions
    public function quickActions(): JsonResponse
    {
        $actions = $this->service->getQuickActions(auth()->id());

        return response()->json($actions);
    }

    public function storeQuickAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'action_type' => 'required|string|in:' . implode(',', ApprovalQuickAction::TYPES),
            'default_comment' => 'nullable|string|max:1000',
        ]);

        $action = $this->service->createQuickAction($validated);

        return response()->json($action, 201);
    }

    public function useQuickAction(ApprovalQuickAction $approvalQuickAction, ApprovalRequest $approvalRequest): JsonResponse
    {
        try {
            $this->service->useQuickAction($approvalQuickAction, $approvalRequest);

            return response()->json([
                'message' => 'Quick action applied',
                'status' => $approvalRequest->fresh()->status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroyQuickAction(ApprovalQuickAction $approvalQuickAction): JsonResponse
    {
        $approvalQuickAction->delete();

        return response()->json(['message' => 'Quick action deleted']);
    }
}
