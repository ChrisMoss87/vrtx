<?php

namespace App\Http\Controllers\Api\Inbox;

use App\Http\Controllers\Controller;
use App\Models\SharedInbox;
use App\Models\InboxRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InboxRuleController extends Controller
{
    public function index(Request $request, SharedInbox $sharedInbox): JsonResponse
    {
        $query = $sharedInbox->rules()->with('creator:id,name');

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $rules = $query->ordered()->get();

        return response()->json(['data' => $rules]);
    }

    public function store(Request $request, SharedInbox $sharedInbox): JsonResponse
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

        $validated['inbox_id'] = $sharedInbox->id;
        $validated['created_by'] = auth()->id();

        $rule = InboxRule::create($validated);

        return response()->json(['data' => $rule], 201);
    }

    public function show(SharedInbox $sharedInbox, InboxRule $rule): JsonResponse
    {
        $rule->load('creator:id,name');
        return response()->json(['data' => $rule]);
    }

    public function update(Request $request, SharedInbox $sharedInbox, InboxRule $rule): JsonResponse
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

        $rule->update($validated);

        return response()->json(['data' => $rule]);
    }

    public function destroy(SharedInbox $sharedInbox, InboxRule $rule): JsonResponse
    {
        $rule->delete();
        return response()->json(null, 204);
    }

    public function reorder(Request $request, SharedInbox $sharedInbox): JsonResponse
    {
        $validated = $request->validate([
            'rule_ids' => 'required|array',
            'rule_ids.*' => 'exists:inbox_rules,id',
        ]);

        foreach ($validated['rule_ids'] as $priority => $ruleId) {
            InboxRule::where('id', $ruleId)
                ->where('inbox_id', $sharedInbox->id)
                ->update(['priority' => $priority]);
        }

        return response()->json(['success' => true]);
    }

    public function toggle(SharedInbox $sharedInbox, InboxRule $rule): JsonResponse
    {
        $rule->update(['is_active' => !$rule->is_active]);
        return response()->json(['data' => $rule]);
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
