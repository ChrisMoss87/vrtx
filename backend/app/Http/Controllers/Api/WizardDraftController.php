<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WizardDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WizardDraftController extends Controller
{
    /**
     * List all drafts for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WizardDraft::forUser(Auth::id())
            ->notExpired()
            ->orderBy('updated_at', 'desc');

        // Filter by wizard type if provided
        if ($request->has('wizard_type')) {
            $query->ofType($request->input('wizard_type'));
        }

        // Filter by reference if provided
        if ($request->has('reference_id')) {
            $query->forReference($request->input('reference_id'));
        }

        $drafts = $query->get()->map(function ($draft) {
            return [
                'id' => $draft->id,
                'wizard_type' => $draft->wizard_type,
                'reference_id' => $draft->reference_id,
                'name' => $draft->display_name,
                'current_step_index' => $draft->current_step_index,
                'completion_percentage' => $draft->completion_percentage,
                'expires_at' => $draft->expires_at?->toISOString(),
                'created_at' => $draft->created_at->toISOString(),
                'updated_at' => $draft->updated_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $drafts,
        ]);
    }

    /**
     * Get a specific draft.
     */
    public function show(int $id): JsonResponse
    {
        $draft = WizardDraft::forUser(Auth::id())
            ->notExpired()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $draft->id,
                'wizard_type' => $draft->wizard_type,
                'reference_id' => $draft->reference_id,
                'name' => $draft->name,
                'display_name' => $draft->display_name,
                'form_data' => $draft->form_data,
                'steps_state' => $draft->steps_state,
                'current_step_index' => $draft->current_step_index,
                'completion_percentage' => $draft->completion_percentage,
                'expires_at' => $draft->expires_at?->toISOString(),
                'created_at' => $draft->created_at->toISOString(),
                'updated_at' => $draft->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Create or update a draft.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wizard_type' => 'required|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'form_data' => 'required|array',
            'steps_state' => 'required|array',
            'current_step_index' => 'required|integer|min:0',
            'draft_id' => 'nullable|integer|exists:wizard_drafts,id',
        ]);

        $userId = Auth::id();

        // If draft_id is provided, update existing draft
        if (!empty($validated['draft_id'])) {
            $draft = WizardDraft::forUser($userId)->findOrFail($validated['draft_id']);

            $draft->updateDraft(
                $validated['form_data'],
                $validated['steps_state'],
                $validated['current_step_index']
            );

            if (isset($validated['name'])) {
                $draft->update(['name' => $validated['name']]);
            }
        } else {
            // Create new draft
            $draft = WizardDraft::create([
                'user_id' => $userId,
                'wizard_type' => $validated['wizard_type'],
                'reference_id' => $validated['reference_id'] ?? null,
                'name' => $validated['name'] ?? null,
                'form_data' => $validated['form_data'],
                'steps_state' => $validated['steps_state'],
                'current_step_index' => $validated['current_step_index'],
                'expires_at' => now()->addDays(30), // Default 30 day expiration
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $draft->id,
                'wizard_type' => $draft->wizard_type,
                'reference_id' => $draft->reference_id,
                'name' => $draft->name,
                'display_name' => $draft->display_name,
                'form_data' => $draft->form_data,
                'steps_state' => $draft->steps_state,
                'current_step_index' => $draft->current_step_index,
                'completion_percentage' => $draft->completion_percentage,
                'expires_at' => $draft->expires_at?->toISOString(),
                'created_at' => $draft->created_at->toISOString(),
                'updated_at' => $draft->updated_at->toISOString(),
            ],
        ], $validated['draft_id'] ?? false ? 200 : 201);
    }

    /**
     * Auto-save draft (lightweight update for auto-save functionality).
     */
    public function autoSave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => 'required|integer|exists:wizard_drafts,id',
            'form_data' => 'required|array',
            'steps_state' => 'required|array',
            'current_step_index' => 'required|integer|min:0',
        ]);

        $draft = WizardDraft::forUser(Auth::id())->findOrFail($validated['draft_id']);

        $draft->updateDraft(
            $validated['form_data'],
            $validated['steps_state'],
            $validated['current_step_index']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $draft->id,
                'updated_at' => $draft->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Rename a draft.
     */
    public function rename(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $draft = WizardDraft::forUser(Auth::id())->findOrFail($id);
        $draft->update(['name' => $validated['name']]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $draft->id,
                'name' => $draft->name,
                'display_name' => $draft->display_name,
            ],
        ]);
    }

    /**
     * Delete a draft.
     */
    public function destroy(int $id): JsonResponse
    {
        $draft = WizardDraft::forUser(Auth::id())->findOrFail($id);
        $draft->delete();

        return response()->json([
            'success' => true,
            'message' => 'Draft deleted successfully',
        ]);
    }

    /**
     * Delete multiple drafts.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:wizard_drafts,id',
        ]);

        $deleted = WizardDraft::forUser(Auth::id())
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} draft(s) deleted successfully",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Make a draft permanent (remove expiration).
     */
    public function makePermanent(int $id): JsonResponse
    {
        $draft = WizardDraft::forUser(Auth::id())->findOrFail($id);
        $draft->makePermanent();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $draft->id,
                'expires_at' => null,
            ],
        ]);
    }

    /**
     * Extend draft expiration.
     */
    public function extendExpiration(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $draft = WizardDraft::forUser(Auth::id())->findOrFail($id);
        $draft->expiresIn($validated['days']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $draft->id,
                'expires_at' => $draft->expires_at->toISOString(),
            ],
        ]);
    }
}
