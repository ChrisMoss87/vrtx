<?php

namespace App\Http\Controllers\Api\Support;

use App\Application\Services\Support\SupportApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Support\TicketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private SupportApplicationService $appService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with(['category', 'assignee', 'portalUser']);

        // Filters
        if ($request->has('status')) {
            $statuses = explode(',', $request->input('status'));
            $query->whereIn('status', $statuses);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('assigned_to')) {
            if ($request->input('assigned_to') === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($request->input('assigned_to') === 'me') {
                $query->where('assigned_to', auth()->id());
            } else {
                $query->where('assigned_to', $request->input('assigned_to'));
            }
        }

        if ($request->has('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'ilike', "%{$search}%")
                    ->orWhere('subject', 'ilike', "%{$search}%");
            });
        }

        if ($request->boolean('overdue_only')) {
            $query->overdueSla();
        }

        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $tickets = $query->paginate($request->input('per_page', 20));

        return response()->json($tickets);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = SupportTicket::with([
            'category',
            'assignee',
            'portalUser',
            'submitter',
            'team',
            'replies' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
            'replies.user',
            'replies.portalUser',
            'activities' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(50);
            },
            'activities.user',
            'escalations' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        return response()->json(['ticket' => $ticket]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'sometimes|integer|between:1,4',
            'category_id' => 'nullable|exists:ticket_categories,id',
            'assigned_to' => 'nullable|exists:users,id',
            'team_id' => 'nullable|exists:support_teams,id',
            'contact_id' => 'nullable|integer',
            'account_id' => 'nullable|integer',
            'channel' => 'sometimes|string|in:portal,email,phone,chat',
            'tags' => 'sometimes|array',
            'custom_fields' => 'sometimes|array',
        ]);

        $ticket = $this->ticketService->createTicket($validated, auth()->user());

        return response()->json([
            'ticket' => $ticket->load(['category', 'assignee']),
            'message' => 'Ticket created successfully',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:open,pending,in_progress,resolved,closed',
            'priority' => 'sometimes|integer|between:1,4',
            'category_id' => 'nullable|exists:ticket_categories,id',
            'assigned_to' => 'nullable|exists:users,id',
            'team_id' => 'nullable|exists:support_teams,id',
            'tags' => 'sometimes|array',
        ]);

        $ticket = $this->ticketService->updateTicket($ticket, $validated, auth()->user());

        return response()->json([
            'ticket' => $ticket->load(['category', 'assignee']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted']);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();

        $validated = $request->validate([
            'content' => 'required|string',
            'is_internal' => 'sometimes|boolean',
            'attachments' => 'sometimes|array',
        ]);

        $reply = $this->ticketService->addReply(
            $ticket,
            $validated['content'],
            auth()->user(),
            null,
            $validated['is_internal'] ?? false,
            $validated['attachments'] ?? []
        );

        return response()->json([
            'reply' => $reply->load(['user']),
            'message' => 'Reply added successfully',
        ], 201);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket = $this->ticketService->assignTicket(
            $ticket,
            $validated['assigned_to'],
            auth()->user()
        );

        return response()->json([
            'ticket' => $ticket->load(['assignee']),
            'message' => 'Ticket assigned successfully',
        ]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();

        $validated = $request->validate([
            'resolution_note' => 'nullable|string',
        ]);

        $ticket = $this->ticketService->resolveTicket(
            $ticket,
            auth()->user(),
            $validated['resolution_note'] ?? null
        );

        return response()->json([
            'ticket' => $ticket,
            'message' => 'Ticket resolved',
        ]);
    }

    public function close(int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();
        $ticket = $this->ticketService->closeTicket($ticket, auth()->user());

        return response()->json([
            'ticket' => $ticket,
            'message' => 'Ticket closed',
        ]);
    }

    public function reopen(int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();
        $ticket = $this->ticketService->updateTicket($ticket, ['status' => 'open'], auth()->user());

        return response()->json([
            'ticket' => $ticket,
            'message' => 'Ticket reopened',
        ]);
    }

    public function escalate(Request $request, int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first();

        $validated = $request->validate([
            'type' => 'required|string|in:response_sla,resolution_sla,manual',
            'level' => 'required|string|in:first,second,third',
            'escalated_to' => 'required|exists:users,id',
            'reason' => 'required|string',
        ]);

        $escalation = $this->ticketService->escalateTicket(
            $ticket,
            $validated['type'],
            $validated['level'],
            $validated['escalated_to'],
            $validated['reason'],
            auth()->user()
        );

        return response()->json([
            'escalation' => $escalation,
            'message' => 'Ticket escalated',
        ]);
    }

    public function merge(Request $request, int $id): JsonResponse
    {
        $primaryTicket = DB::table('support_tickets')->where('id', $id)->first();

        $validated = $request->validate([
            'secondary_ticket_id' => 'required|exists:support_tickets,id',
        ]);

        $secondaryTicket = SupportTicket::findOrFail($validated['secondary_ticket_id']);

        $ticket = $this->ticketService->mergeTickets($primaryTicket, $secondaryTicket, auth()->user());

        return response()->json([
            'ticket' => $ticket->load(['replies', 'activities']),
            'message' => 'Tickets merged successfully',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->boolean('my_stats') ? auth()->id() : null;
        $stats = $this->ticketService->getTicketStats($userId);

        return response()->json($stats);
    }

    // Meta endpoints
    public function statuses(): JsonResponse
    {
        return response()->json(SupportTicket::getStatuses());
    }

    public function priorities(): JsonResponse
    {
        return response()->json(SupportTicket::getPriorities());
    }

    public function channels(): JsonResponse
    {
        return response()->json(SupportTicket::getChannels());
    }
}
