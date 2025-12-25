<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Proposal;

use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicProposalController extends Controller
{
    private const STATUS_SENT = 'sent';
    private const STATUS_VIEWED = 'viewed';
    private const STATUS_ACCEPTED = 'accepted';
    private const STATUS_REJECTED = 'rejected';

    public function __construct(
        protected ProposalRepositoryInterface $repository
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $proposal = $this->repository->findByUuid($uuid);

        if (!$proposal) {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        // Record view
        $sessionId = Str::random(32);
        $this->repository->recordView($uuid, $sessionId);

        // Check if expired
        $validUntil = $proposal['valid_until'] ?? null;
        $isExpired = $validUntil && Carbon::parse($validUntil)->isPast();

        if ($isExpired) {
            return response()->json([
                'message' => 'This proposal has expired',
                'expired' => true,
                'valid_until' => $validUntil,
            ], 410);
        }

        // Check if already accepted/rejected
        if ($proposal['status'] === self::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'This proposal has already been accepted',
                'status' => 'accepted',
                'accepted_at' => $proposal['accepted_at'],
            ]);
        }

        if ($proposal['status'] === self::STATUS_REJECTED) {
            return response()->json([
                'message' => 'This proposal has been declined',
                'status' => 'rejected',
            ]);
        }

        $canAccept = in_array($proposal['status'], [self::STATUS_SENT, self::STATUS_VIEWED]) && !$isExpired;

        return response()->json([
            'proposal' => $proposal,
            'can_accept' => $canAccept,
        ]);
    }

    public function trackView(Request $request, string $uuid): JsonResponse
    {
        $proposal = $this->repository->findByUuid($uuid);

        if (!$proposal) {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        $validated = $request->validate([
            'email' => 'nullable|email',
            'name' => 'nullable|string',
        ]);

        $sessionId = Str::random(32);
        $view = $this->repository->recordView(
            $uuid,
            $sessionId,
            $validated['email'] ?? null,
            $validated['name'] ?? null
        );

        return response()->json(['session_id' => $sessionId]);
    }

    public function updateViewSession(Request $request, string $uuid): JsonResponse
    {
        $proposal = $this->repository->findByUuid($uuid);

        if (!$proposal) {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        $validated = $request->validate([
            'session_id' => 'required|string',
            'sections_viewed' => 'nullable|array',
            'ended' => 'nullable|boolean',
        ]);

        // Find view by session_id
        $view = DB::table('proposal_views')
            ->where('proposal_id', $proposal['id'])
            ->where('session_id', $validated['session_id'])
            ->first();

        if (!$view) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        // Track section views
        if (!empty($validated['sections_viewed'])) {
            foreach ($validated['sections_viewed'] as $sectionId => $seconds) {
                $this->repository->trackSectionView($view->id, (int) $sectionId, (int) $seconds);
            }
        }

        // End session if requested
        if (!empty($validated['ended'])) {
            $this->repository->endViewSession($view->id);
        }

        return response()->json(['message' => 'Session updated']);
    }

    public function toggleItem(Request $request, string $uuid, int $itemId): JsonResponse
    {
        $proposal = $this->repository->findByUuid($uuid);

        if (!$proposal) {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        // Check if item is optional
        $item = DB::table('proposal_pricing_items')
            ->where('id', $itemId)
            ->where('proposal_id', $proposal['id'])
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        if (!$item->is_optional) {
            return response()->json(['message' => 'This item cannot be toggled'], 422);
        }

        $updatedItem = $this->repository->toggleItemSelection($uuid, $itemId);

        // Get updated proposal total
        $updatedProposal = $this->repository->findByUuid($uuid);

        return response()->json([
            'is_selected' => $updatedItem['is_selected'] ?? false,
            'total_value' => $updatedProposal['total_value'] ?? 0,
        ]);
    }

    public function accept(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'accepted_by' => 'required|string|max:255',
            'signature' => 'nullable|string',
        ]);

        try {
            $proposal = $this->repository->acceptProposal(
                $uuid,
                $validated['accepted_by'],
                $validated['signature'] ?? null,
                $request->ip()
            );

            return response()->json([
                'message' => 'Proposal accepted successfully',
                'accepted_at' => $proposal['accepted_at'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'rejected_by' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $this->repository->rejectProposal(
                $uuid,
                $validated['rejected_by'],
                $validated['reason'] ?? null
            );

            return response()->json(['message' => 'Proposal declined']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function addComment(Request $request, string $uuid): JsonResponse
    {
        $proposal = $this->repository->findByUuid($uuid);

        if (!$proposal) {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        $validated = $request->validate([
            'section_id' => 'nullable|exists:proposal_sections,id',
            'comment' => 'required|string|max:2000',
            'author_email' => 'required|email',
            'author_name' => 'nullable|string|max:255',
        ]);

        $validated['author_type'] = 'client';

        $comment = $this->repository->addComment($proposal['id'], $validated);

        return response()->json($comment, 201);
    }

    public function comments(string $uuid): JsonResponse
    {
        $proposal = $this->repository->findByUuid($uuid);

        if (!$proposal) {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        $comments = $this->repository->getComments($proposal['id']);

        return response()->json($comments);
    }
}
