<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Proposal;

use App\Application\Services\Proposal\ProposalApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalPricingItem;
use App\Services\Proposal\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicProposalController extends Controller
{
    public function __construct(
        protected ProposalApplicationService $proposalApplicationService,
        protected ProposalService $service
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)
            ->with([
                'sections' => fn ($q) => $q->visible()->orderBy('display_order'),
                'pricingItems' => fn ($q) => $q->orderBy('display_order'),
                'assignedTo:id,name,email',
            ])
            ->firstOrFail();

        // Record view
        $this->service->recordView($proposal);

        // Check if expired
        if ($proposal->isExpired()) {
            return response()->json([
                'message' => 'This proposal has expired',
                'expired' => true,
                'valid_until' => $proposal->valid_until,
            ], 410);
        }

        // Check if already accepted/rejected
        if ($proposal->status === Proposal::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'This proposal has already been accepted',
                'status' => 'accepted',
                'accepted_at' => $proposal->accepted_at,
            ]);
        }

        if ($proposal->status === Proposal::STATUS_REJECTED) {
            return response()->json([
                'message' => 'This proposal has been declined',
                'status' => 'rejected',
            ]);
        }

        return response()->json([
            'proposal' => $proposal,
            'can_accept' => $proposal->canBeSent() && !$proposal->isExpired(),
        ]);
    }

    public function trackView(Request $request, string $uuid): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'email' => 'nullable|email',
            'name' => 'nullable|string',
        ]);

        $view = $this->service->recordView(
            $proposal,
            $validated['email'] ?? null,
            $validated['name'] ?? null
        );

        return response()->json(['session_id' => $view->session_id]);
    }

    public function updateViewSession(Request $request, string $uuid): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'session_id' => 'required|string',
            'sections_viewed' => 'nullable|array',
            'ended' => 'nullable|boolean',
        ]);

        $view = $proposal->views()->where('session_id', $validated['session_id'])->first();

        if (!$view) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $this->service->updateViewSession($view, $validated);

        return response()->json(['message' => 'Session updated']);
    }

    public function toggleItem(Request $request, string $uuid, int $itemId): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        $item = $proposal->pricingItems()->findOrFail($itemId);

        if (!$item->is_optional) {
            return response()->json(['message' => 'This item cannot be toggled'], 422);
        }

        $this->service->toggleOptionalItem($item);

        return response()->json([
            'is_selected' => $item->fresh()->is_selected,
            'total_value' => $proposal->fresh()->total_value,
        ]);
    }

    public function accept(Request $request, string $uuid): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'accepted_by' => 'required|string|max:255',
            'signature' => 'nullable|string',
        ]);

        try {
            $this->service->accept(
                $proposal,
                $validated['accepted_by'],
                $validated['signature'] ?? null
            );

            return response()->json([
                'message' => 'Proposal accepted successfully',
                'accepted_at' => $proposal->fresh()->accepted_at,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'rejected_by' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        $this->service->reject(
            $proposal,
            $validated['rejected_by'],
            $validated['reason'] ?? null
        );

        return response()->json(['message' => 'Proposal declined']);
    }

    public function addComment(Request $request, string $uuid): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'section_id' => 'nullable|exists:proposal_sections,id',
            'comment' => 'required|string|max:2000',
            'author_email' => 'required|email',
            'author_name' => 'nullable|string|max:255',
        ]);

        $validated['author_type'] = 'client';

        $comment = $this->service->addComment($proposal, $validated);

        return response()->json($comment, 201);
    }

    public function comments(string $uuid): JsonResponse
    {
        $proposal = Proposal::where('uuid', $uuid)->firstOrFail();

        $comments = $proposal->comments()
            ->with(['section', 'replies'])
            ->topLevel()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($comments);
    }
}
