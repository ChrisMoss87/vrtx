<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Models\AiEmailDraft;
use App\Services\AI\EmailCompositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmailCompositionController extends Controller
{
    public function __construct(
        protected EmailCompositionService $emailService
    ) {}

    /**
     * Compose a new email
     */
    public function compose(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'purpose' => 'required|string|max:1000',
            'recipient_name' => 'sometimes|nullable|string|max:255',
            'recipient_company' => 'sometimes|nullable|string|max:255',
            'context' => 'sometimes|nullable|array',
            'tone' => 'sometimes|in:professional,friendly,formal,casual,urgent',
            'record_module' => 'sometimes|nullable|string',
            'record_id' => 'sometimes|nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $draft = $this->emailService->compose(
                $request->purpose,
                $request->recipient_name,
                $request->recipient_company,
                $request->context ?? [],
                $request->tone ?? 'professional',
                $request->record_module,
                $request->record_id
            );

            return response()->json([
                'draft' => $this->formatDraft($draft),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to compose email',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a reply to an email
     */
    public function reply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_id' => 'required|exists:email_messages,id',
            'intent' => 'required|string|max:1000',
            'additional_context' => 'sometimes|nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $draft = $this->emailService->generateReply(
                $request->email_id,
                $request->intent,
                $request->additional_context
            );

            return response()->json([
                'draft' => $this->formatDraft($draft),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate reply',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Improve an existing email
     */
    public function improve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:500',
            'body' => 'required|string|max:10000',
            'improvement' => 'required|string|max:1000',
            'record_module' => 'sometimes|nullable|string',
            'record_id' => 'sometimes|nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $draft = $this->emailService->improve(
                $request->subject,
                $request->body,
                $request->improvement,
                $request->record_module,
                $request->record_id
            );

            return response()->json([
                'draft' => $this->formatDraft($draft),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to improve email',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate subject line suggestions
     */
    public function suggestSubjects(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:10000',
            'count' => 'sometimes|integer|min:1|max:10',
            'record_module' => 'sometimes|nullable|string',
            'record_id' => 'sometimes|nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $suggestions = $this->emailService->suggestSubjects(
                $request->body,
                $request->count ?? 5,
                $request->record_module,
                $request->record_id
            );

            return response()->json([
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate suggestions',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze email tone
     */
    public function analyzeTone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $analysis = $this->emailService->analyzeTone($request->body);

            return response()->json([
                'analysis' => $analysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to analyze tone',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's draft history
     */
    public function drafts(Request $request): JsonResponse
    {
        $drafts = AiEmailDraft::where('user_id', Auth::id())
            ->when($request->record_module, fn ($q) => $q->where('record_module', $request->record_module))
            ->when($request->record_id, fn ($q) => $q->where('record_id', $request->record_id))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($draft) => $this->formatDraft($draft));

        return response()->json([
            'drafts' => $drafts,
        ]);
    }

    /**
     * Get a specific draft
     */
    public function getDraft(int $id): JsonResponse
    {
        $draft = AiEmailDraft::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'draft' => $this->formatDraft($draft),
        ]);
    }

    /**
     * Delete a draft
     */
    public function deleteDraft(int $id): JsonResponse
    {
        $draft = AiEmailDraft::where('user_id', Auth::id())
            ->findOrFail($id);

        $draft->delete();

        return response()->json([
            'message' => 'Draft deleted successfully',
        ]);
    }

    /**
     * Mark draft as used
     */
    public function markUsed(int $id): JsonResponse
    {
        $draft = AiEmailDraft::where('user_id', Auth::id())
            ->findOrFail($id);

        $draft->markAsUsed();

        return response()->json([
            'message' => 'Draft marked as used',
        ]);
    }

    /**
     * Format draft for response
     */
    protected function formatDraft(AiEmailDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'purpose' => $draft->purpose,
            'tone' => $draft->tone,
            'subject' => $draft->subject,
            'body' => $draft->body,
            'context' => $draft->context,
            'record_module' => $draft->record_module,
            'record_id' => $draft->record_id,
            'model_used' => $draft->model_used,
            'tokens_used' => $draft->tokens_used,
            'is_used' => $draft->is_used,
            'used_at' => $draft->used_at?->toIso8601String(),
            'created_at' => $draft->created_at->toIso8601String(),
        ];
    }
}
