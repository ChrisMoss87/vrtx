<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Email;

use App\Application\Services\Email\EmailApplicationService;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Services\Email\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailMessageController extends Controller
{
    public function __construct(
        protected EmailApplicationService $emailApplicationService,
        protected EmailService $emailService
    ) {}

    /**
     * List emails with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'nullable|integer|exists:email_accounts,id',
            'folder' => 'nullable|string',
            'direction' => 'nullable|string|in:inbound,outbound',
            'status' => 'nullable|string|in:draft,queued,sent,failed,received',
            'is_read' => 'nullable|boolean',
            'is_starred' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'linked_record_type' => 'nullable|string',
            'linked_record_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = EmailMessage::query()
            ->whereHas('account', fn($q) => $q->where('user_id', Auth::id()))
            ->with(['account:id,name,email_address', 'template:id,name'])
            ->orderBy('received_at', 'desc')
            ->orderBy('sent_at', 'desc')
            ->orderBy('created_at', 'desc');

        if (isset($validated['account_id'])) {
            $query->where('account_id', $validated['account_id']);
        }

        if (isset($validated['folder'])) {
            $query->where('folder', $validated['folder']);
        }

        if (isset($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['is_read'])) {
            $query->where('is_read', $validated['is_read']);
        }

        if (isset($validated['is_starred'])) {
            $query->where('is_starred', $validated['is_starred']);
        }

        if (isset($validated['linked_record_type']) && isset($validated['linked_record_id'])) {
            $query->where('linked_record_type', $validated['linked_record_type'])
                ->where('linked_record_id', $validated['linked_record_id']);
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('from_email', 'like', "%{$search}%")
                    ->orWhere('from_name', 'like', "%{$search}%")
                    ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        $perPage = $validated['per_page'] ?? 25;
        $messages = $query->paginate($perPage);

        return response()->json([
            'data' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Get a single email message.
     */
    public function show(EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('view', $emailMessage);

        $emailMessage->load(['account:id,name,email_address', 'template', 'replies']);

        // Mark as read if unread
        if (!$emailMessage->is_read && $emailMessage->direction === EmailMessage::DIRECTION_INBOUND) {
            $emailMessage->markAsRead();
        }

        return response()->json([
            'data' => $emailMessage,
        ]);
    }

    /**
     * Create a draft email.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|integer|exists:email_accounts,id',
            'to' => 'required|array|min:1',
            'to.*' => 'email',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
            'subject' => 'nullable|string|max:998',
            'body_html' => 'nullable|string',
            'body_text' => 'nullable|string',
            'reply_to' => 'nullable|email',
            'thread_id' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:email_messages,id',
            'linked_record_type' => 'nullable|string',
            'linked_record_id' => 'nullable|integer',
            'template_id' => 'nullable|integer|exists:email_templates,id',
        ]);

        $account = EmailAccount::where('id', $validated['account_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $message = $this->emailService->createDraft($account, $validated);

        return response()->json([
            'data' => $message,
            'message' => 'Draft created successfully',
        ], 201);
    }

    /**
     * Update a draft email.
     */
    public function update(Request $request, EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('update', $emailMessage);

        if ($emailMessage->status !== EmailMessage::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft emails can be updated',
            ], 422);
        }

        $validated = $request->validate([
            'to' => 'sometimes|array',
            'to.*' => 'email',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
            'subject' => 'nullable|string|max:998',
            'body_html' => 'nullable|string',
            'body_text' => 'nullable|string',
            'reply_to' => 'nullable|email',
        ]);

        $emailMessage->update([
            'to_emails' => $validated['to'] ?? $emailMessage->to_emails,
            'cc_emails' => $validated['cc'] ?? $emailMessage->cc_emails,
            'bcc_emails' => $validated['bcc'] ?? $emailMessage->bcc_emails,
            'subject' => $validated['subject'] ?? $emailMessage->subject,
            'body_html' => $validated['body_html'] ?? $emailMessage->body_html,
            'body_text' => $validated['body_text'] ?? $emailMessage->body_text,
            'reply_to' => $validated['reply_to'] ?? $emailMessage->reply_to,
        ]);

        return response()->json([
            'data' => $emailMessage->fresh(),
            'message' => 'Draft updated successfully',
        ]);
    }

    /**
     * Send an email.
     */
    public function send(EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('update', $emailMessage);

        if (!in_array($emailMessage->status, [EmailMessage::STATUS_DRAFT, EmailMessage::STATUS_FAILED])) {
            return response()->json([
                'message' => 'Only draft or failed emails can be sent',
            ], 422);
        }

        if (empty($emailMessage->to_emails)) {
            return response()->json([
                'message' => 'At least one recipient is required',
            ], 422);
        }

        $success = $this->emailService->send($emailMessage);
        $this->emailService->disconnect();

        if ($success) {
            return response()->json([
                'data' => $emailMessage->fresh(),
                'message' => 'Email sent successfully',
            ]);
        }

        return response()->json([
            'message' => 'Failed to send email',
            'error' => $emailMessage->fresh()->failed_reason,
        ], 500);
    }

    /**
     * Schedule an email.
     */
    public function schedule(Request $request, EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('update', $emailMessage);

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $this->emailService->queue($emailMessage, new \DateTime($validated['scheduled_at']));

        return response()->json([
            'data' => $emailMessage->fresh(),
            'message' => 'Email scheduled successfully',
        ]);
    }

    /**
     * Reply to an email.
     */
    public function reply(Request $request, EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('view', $emailMessage);

        $validated = $request->validate([
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
        ]);

        $reply = $this->emailService->createReply($emailMessage, $validated);

        return response()->json([
            'data' => $reply,
            'message' => 'Reply draft created',
        ], 201);
    }

    /**
     * Forward an email.
     */
    public function forward(Request $request, EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('view', $emailMessage);

        $validated = $request->validate([
            'to' => 'required|array|min:1',
            'to.*' => 'email',
            'body_html' => 'nullable|string',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
        ]);

        $forward = $this->emailService->createForward($emailMessage, $validated);

        return response()->json([
            'data' => $forward,
            'message' => 'Forward draft created',
        ], 201);
    }

    /**
     * Delete an email.
     */
    public function destroy(EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('delete', $emailMessage);

        $emailMessage->delete();

        return response()->json([
            'message' => 'Email deleted successfully',
        ]);
    }

    /**
     * Mark email as read.
     */
    public function markRead(EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('update', $emailMessage);

        $emailMessage->markAsRead();

        return response()->json([
            'data' => $emailMessage->fresh(),
            'message' => 'Email marked as read',
        ]);
    }

    /**
     * Mark email as unread.
     */
    public function markUnread(EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('update', $emailMessage);

        $emailMessage->markAsUnread();

        return response()->json([
            'data' => $emailMessage->fresh(),
            'message' => 'Email marked as unread',
        ]);
    }

    /**
     * Toggle starred status.
     */
    public function toggleStar(EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('update', $emailMessage);

        $emailMessage->toggleStar();

        return response()->json([
            'data' => $emailMessage->fresh(),
            'message' => $emailMessage->is_starred ? 'Email starred' : 'Email unstarred',
        ]);
    }

    /**
     * Move email to folder.
     */
    public function moveToFolder(Request $request, EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('update', $emailMessage);

        $validated = $request->validate([
            'folder' => 'required|string|max:255',
        ]);

        $success = $this->emailService->moveToFolder($emailMessage, $validated['folder']);
        $this->emailService->disconnect();

        if ($success) {
            return response()->json([
                'data' => $emailMessage->fresh(),
                'message' => 'Email moved successfully',
            ]);
        }

        return response()->json([
            'message' => 'Failed to move email',
        ], 500);
    }

    /**
     * Get email thread.
     */
    public function thread(EmailMessage $emailMessage): JsonResponse
    {
        $this->authorize('view', $emailMessage);

        if (!$emailMessage->thread_id) {
            return response()->json([
                'data' => [$emailMessage->load('account:id,name,email_address')],
            ]);
        }

        $thread = EmailMessage::where('thread_id', $emailMessage->thread_id)
            ->with(['account:id,name,email_address'])
            ->orderBy('received_at')
            ->orderBy('sent_at')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $thread,
        ]);
    }

    /**
     * Bulk mark as read.
     */
    public function bulkMarkRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:email_messages,id',
        ]);

        EmailMessage::whereIn('id', $validated['ids'])
            ->whereHas('account', fn($q) => $q->where('user_id', Auth::id()))
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Emails marked as read',
        ]);
    }

    /**
     * Bulk delete.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:email_messages,id',
        ]);

        EmailMessage::whereIn('id', $validated['ids'])
            ->whereHas('account', fn($q) => $q->where('user_id', Auth::id()))
            ->delete();

        return response()->json([
            'message' => 'Emails deleted',
        ]);
    }
}
