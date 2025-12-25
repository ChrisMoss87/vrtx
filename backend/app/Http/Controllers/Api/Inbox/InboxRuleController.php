<?php

namespace App\Http\Controllers\Api\Inbox;

use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InboxRuleController extends Controller
{
    public function __construct(
        protected InboxConversationRepositoryInterface $conversationRepository
    ) {}

    public function index(Request $request, int $inboxId): JsonResponse
    {
        // Verify inbox exists
        $inbox = DB::table('shared_inboxes')->where('id', $inboxId)->first();
        if (!$inbox) {
            return response()->json(['message' => 'Inbox not found'], 404);
        }

        $rules = $this->conversationRepository->listRules($inboxId);

        return response()->json(['data' => $rules]);
    }

    public function store(Request $request, int $inboxId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'integer|min:0',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'nullable',
            'condition_match' => 'in:all,any',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.value' => 'nullable',
            'is_active' => 'boolean',
            'stop_processing' => 'boolean',
        ]);

        // Verify inbox exists
        $inbox = DB::table('shared_inboxes')->where('id', $inboxId)->first();
        if (!$inbox) {
            return response()->json(['message' => 'Inbox not found'], 404);
        }

        $rule = $this->conversationRepository->createRule($inboxId, $validated, auth()->id());

        return response()->json(['data' => $rule], 201);
    }

    public function show(int $inboxId, int $ruleId): JsonResponse
    {
        // Verify inbox exists
        $inbox = DB::table('shared_inboxes')->where('id', $inboxId)->first();
        if (!$inbox) {
            return response()->json(['message' => 'Inbox not found'], 404);
        }

        $rule = DB::table('inbox_rules')->where('id', $ruleId)->where('inbox_id', $inboxId)->first();
        if (!$rule) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $ruleArray = (array) $rule;

        // Decode JSON fields
        if (isset($ruleArray['conditions']) && is_string($ruleArray['conditions'])) {
            $ruleArray['conditions'] = json_decode($ruleArray['conditions'], true);
        }
        if (isset($ruleArray['actions']) && is_string($ruleArray['actions'])) {
            $ruleArray['actions'] = json_decode($ruleArray['actions'], true);
        }

        // Load creator
        if (isset($ruleArray['created_by'])) {
            $creator = DB::table('users')->where('id', $ruleArray['created_by'])->first(['id', 'name']);
            $ruleArray['creator'] = $creator ? (array) $creator : null;
        }

        return response()->json(['data' => $ruleArray]);
    }

    public function update(Request $request, int $inboxId, int $ruleId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'integer|min:0',
            'conditions' => 'sometimes|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'nullable',
            'condition_match' => 'in:all,any',
            'actions' => 'sometimes|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.value' => 'nullable',
            'is_active' => 'boolean',
            'stop_processing' => 'boolean',
        ]);

        // Verify rule belongs to inbox
        $rule = DB::table('inbox_rules')->where('id', $ruleId)->where('inbox_id', $inboxId)->first();
        if (!$rule) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $updated = $this->conversationRepository->updateRule($ruleId, $validated);

        return response()->json(['data' => $updated]);
    }

    public function destroy(int $inboxId, int $ruleId): JsonResponse
    {
        // Verify rule belongs to inbox
        $rule = DB::table('inbox_rules')->where('id', $ruleId)->where('inbox_id', $inboxId)->first();
        if (!$rule) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $this->conversationRepository->deleteRule($ruleId);
        return response()->json(null, 204);
    }

    public function reorder(Request $request, int $inboxId): JsonResponse
    {
        $validated = $request->validate([
            'rule_ids' => 'required|array',
            'rule_ids.*' => 'integer|exists:inbox_rules,id',
        ]);

        // Verify inbox exists
        $inbox = DB::table('shared_inboxes')->where('id', $inboxId)->first();
        if (!$inbox) {
            return response()->json(['message' => 'Inbox not found'], 404);
        }

        $this->conversationRepository->reorderRules($inboxId, $validated['rule_ids']);

        return response()->json(['success' => true]);
    }

    public function toggle(int $inboxId, int $ruleId): JsonResponse
    {
        // Verify rule belongs to inbox
        $rule = DB::table('inbox_rules')->where('id', $ruleId)->where('inbox_id', $inboxId)->first();
        if (!$rule) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $updated = $this->conversationRepository->updateRule($ruleId, [
            'is_active' => !$rule->is_active,
        ]);

        return response()->json(['data' => $updated]);
    }

    public function availableFields(): JsonResponse
    {
        $fields = [
            ['value' => 'subject', 'label' => 'Subject'],
            ['value' => 'from_email', 'label' => 'From Email'],
            ['value' => 'from_name', 'label' => 'From Name'],
            ['value' => 'body', 'label' => 'Message Body'],
            ['value' => 'channel', 'label' => 'Channel'],
            ['value' => 'contact_email', 'label' => 'Contact Email'],
            ['value' => 'contact_name', 'label' => 'Contact Name'],
            ['value' => 'has_attachments', 'label' => 'Has Attachments'],
        ];

        return response()->json(['data' => $fields]);
    }

    public function availableOperators(): JsonResponse
    {
        $operators = [
            ['value' => 'equals', 'label' => 'Equals'],
            ['value' => 'not_equals', 'label' => 'Does not equal'],
            ['value' => 'contains', 'label' => 'Contains'],
            ['value' => 'not_contains', 'label' => 'Does not contain'],
            ['value' => 'starts_with', 'label' => 'Starts with'],
            ['value' => 'ends_with', 'label' => 'Ends with'],
            ['value' => 'is_empty', 'label' => 'Is empty'],
            ['value' => 'is_not_empty', 'label' => 'Is not empty'],
            ['value' => 'matches_regex', 'label' => 'Matches regex'],
        ];

        return response()->json(['data' => $operators]);
    }

    public function availableActions(): JsonResponse
    {
        $actions = [
            ['value' => 'assign', 'label' => 'Assign to user', 'requires_value' => true],
            ['value' => 'set_priority', 'label' => 'Set priority', 'requires_value' => true],
            ['value' => 'set_status', 'label' => 'Set status', 'requires_value' => true],
            ['value' => 'add_tag', 'label' => 'Add tag', 'requires_value' => true],
            ['value' => 'mark_spam', 'label' => 'Mark as spam', 'requires_value' => false],
            ['value' => 'star', 'label' => 'Star conversation', 'requires_value' => false],
        ];

        return response()->json(['data' => $actions]);
    }
}
