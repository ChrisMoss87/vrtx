<?php

namespace App\Http\Controllers\Api\Inbox;

use App\Application\Services\Inbox\InboxApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Inbox\InboxService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SharedInboxController extends Controller
{
    public function __construct(
        protected InboxApplicationService $inboxApplicationService,
        protected InboxService $inboxService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('shared_inboxs');

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        // Filter by user membership if not admin
        if (!$request->user()?->hasRole('admin')) {
            $query->whereHas('members', function ($q) use ($request) {
                $q->where('user_id', $request->user()?->id);
            });
        }

        $inboxes = $query->withCount(['conversations', 'members'])
            ->orderBy('name')
            ->get();

        // Add stats for each inbox
        $inboxes->each(function ($inbox) {
            $inbox->stats = $this->inboxService->getInboxStats($inbox);
        });

        return response()->json(['data' => $inboxes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:shared_inboxes,email',
            'description' => 'nullable|string',
            'type' => 'in:support,sales,general',
            'imap_host' => 'nullable|string',
            'imap_port' => 'nullable|integer',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'smtp_encryption' => 'nullable|in:ssl,tls,none',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'settings' => 'nullable|array',
            'assignment_method' => 'in:round_robin,load_balanced,manual',
        ]);

        $inbox = DB::table('shared_inboxs')->insertGetId($validated);

        // Add creator as admin member
        DB::table('shared_inbox_members')->insertGetId([
            'inbox_id' => $inbox->id,
            'user_id' => auth()->id(),
            'role' => 'admin',
            'can_reply' => true,
            'can_assign' => true,
            'can_close' => true,
        ]);

        return response()->json(['data' => $inbox], 201);
    }

    public function show(SharedInbox $sharedInbox): JsonResponse
    {
        $sharedInbox->load(['members.user:id,name,email', 'defaultAssignee:id,name']);
        $sharedInbox->loadCount(['conversations', 'cannedResponses', 'rules']);
        $sharedInbox->stats = $this->inboxService->getInboxStats($sharedInbox);

        return response()->json(['data' => $sharedInbox]);
    }

    public function update(Request $request, SharedInbox $sharedInbox): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:shared_inboxes,email,' . $sharedInbox->id,
            'description' => 'nullable|string',
            'type' => 'in:support,sales,general',
            'imap_host' => 'nullable|string',
            'imap_port' => 'nullable|integer',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'smtp_encryption' => 'nullable|in:ssl,tls,none',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'default_assignee_id' => 'nullable|exists:users,id',
            'assignment_method' => 'in:round_robin,load_balanced,manual',
        ]);

        $sharedInbox->update($validated);

        return response()->json(['data' => $sharedInbox]);
    }

    public function destroy(SharedInbox $sharedInbox): JsonResponse
    {
        $sharedInbox->delete();
        return response()->json(null, 204);
    }

    public function verify(SharedInbox $sharedInbox): JsonResponse
    {
        $results = $this->inboxService->verifyInboxConnection($sharedInbox);

        return response()->json([
            'data' => $sharedInbox->fresh(),
            'verification' => $results,
        ]);
    }

    public function sync(SharedInbox $sharedInbox): JsonResponse
    {
        $results = $this->inboxService->syncInbox($sharedInbox);

        return response()->json([
            'data' => $sharedInbox->fresh(),
            'synced_count' => $results['synced_count'],
        ]);
    }

    public function members(SharedInbox $sharedInbox): JsonResponse
    {
        $members = $sharedInbox->members()
            ->with('user:id,name,email')
            ->get();

        return response()->json(['data' => $members]);
    }

    public function addMember(Request $request, SharedInbox $sharedInbox): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'in:admin,member',
            'can_reply' => 'boolean',
            'can_assign' => 'boolean',
            'can_close' => 'boolean',
            'receives_notifications' => 'boolean',
            'active_conversation_limit' => 'nullable|integer|min:1',
        ]);

        $member = SharedInboxMember::updateOrCreate(
            [
                'inbox_id' => $sharedInbox->id,
                'user_id' => $validated['user_id'],
            ],
            array_merge($validated, ['inbox_id' => $sharedInbox->id])
        );

        return response()->json(['data' => $member->load('user:id,name,email')], 201);
    }

    public function updateMember(Request $request, SharedInbox $sharedInbox, SharedInboxMember $member): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'in:admin,member',
            'can_reply' => 'boolean',
            'can_assign' => 'boolean',
            'can_close' => 'boolean',
            'receives_notifications' => 'boolean',
            'active_conversation_limit' => 'nullable|integer|min:1',
        ]);

        $member->update($validated);

        return response()->json(['data' => $member]);
    }

    public function removeMember(SharedInbox $sharedInbox, SharedInboxMember $member): JsonResponse
    {
        $member->delete();
        return response()->json(null, 204);
    }

    public function stats(SharedInbox $sharedInbox): JsonResponse
    {
        return response()->json([
            'data' => $this->inboxService->getInboxStats($sharedInbox),
        ]);
    }
}
