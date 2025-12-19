<?php

declare(strict_types=1);

namespace App\Application\Services\Inbox;

use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Models\InboxCannedResponse;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\InboxRule;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InboxApplicationService
{
    public function __construct(
        private InboxConversationRepositoryInterface $repository,
    ) {}

    // ==========================================
    // SHARED INBOX QUERY USE CASES
    // ==========================================

    /**
     * List shared inboxes the user has access to.
     */
    public function listInboxes(array $filters = []): Collection
    {
        $query = SharedInbox::query()
            ->with(['defaultAssignee']);

        if (!empty($filters['active_only'])) {
            $query->active();
        }

        if (!empty($filters['connected_only'])) {
            $query->connected();
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        // Filter by user membership
        if (!empty($filters['user_id'])) {
            $query->whereHas('members', function ($q) use ($filters) {
                $q->where('user_id', $filters['user_id']);
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single shared inbox.
     */
    public function getInbox(int $id): ?SharedInbox
    {
        return SharedInbox::with(['defaultAssignee', 'members.user'])->find($id);
    }

    /**
     * Get inbox with statistics.
     */
    public function getInboxWithStats(int $id): array
    {
        $inbox = SharedInbox::findOrFail($id);

        $conversations = $inbox->conversations();

        return [
            'inbox' => $inbox,
            'stats' => [
                'total_conversations' => $conversations->count(),
                'open_conversations' => (clone $conversations)->open()->count(),
                'pending_conversations' => (clone $conversations)->pending()->count(),
                'unassigned_conversations' => (clone $conversations)->unassigned()->count(),
                'resolved_today' => (clone $conversations)->resolved()->whereDate('resolved_at', today())->count(),
                'avg_response_time' => (clone $conversations)->whereNotNull('response_time_seconds')
                    ->avg('response_time_seconds'),
                'member_count' => $inbox->members()->count(),
            ],
        ];
    }

    /**
     * Get inbox members.
     */
    public function getInboxMembers(int $inboxId): Collection
    {
        return SharedInboxMember::where('inbox_id', $inboxId)
            ->with('user')
            ->get();
    }

    // ==========================================
    // SHARED INBOX COMMAND USE CASES
    // ==========================================

    /**
     * Create a shared inbox.
     */
    public function createInbox(array $data): SharedInbox
    {
        return DB::transaction(function () use ($data) {
            $inbox = SharedInbox::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'email',
                'imap_host' => $data['imap_host'] ?? null,
                'imap_port' => $data['imap_port'] ?? 993,
                'imap_encryption' => $data['imap_encryption'] ?? 'ssl',
                'smtp_host' => $data['smtp_host'] ?? null,
                'smtp_port' => $data['smtp_port'] ?? 587,
                'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
                'username' => $data['username'] ?? null,
                'password' => $data['password'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_connected' => false,
                'settings' => $data['settings'] ?? [],
                'default_assignee_id' => $data['default_assignee_id'] ?? null,
                'assignment_method' => $data['assignment_method'] ?? 'manual',
            ]);

            // Add creator as admin member
            $inbox->members()->create([
                'user_id' => Auth::id(),
                'role' => 'admin',
                'can_reply' => true,
                'can_assign' => true,
                'can_close' => true,
                'receives_notifications' => true,
            ]);

            return $inbox;
        });
    }

    /**
     * Update a shared inbox.
     */
    public function updateInbox(int $id, array $data): SharedInbox
    {
        $inbox = SharedInbox::findOrFail($id);

        $inbox->update([
            'name' => $data['name'] ?? $inbox->name,
            'email' => $data['email'] ?? $inbox->email,
            'description' => $data['description'] ?? $inbox->description,
            'imap_host' => $data['imap_host'] ?? $inbox->imap_host,
            'imap_port' => $data['imap_port'] ?? $inbox->imap_port,
            'imap_encryption' => $data['imap_encryption'] ?? $inbox->imap_encryption,
            'smtp_host' => $data['smtp_host'] ?? $inbox->smtp_host,
            'smtp_port' => $data['smtp_port'] ?? $inbox->smtp_port,
            'smtp_encryption' => $data['smtp_encryption'] ?? $inbox->smtp_encryption,
            'username' => $data['username'] ?? $inbox->username,
            'password' => isset($data['password']) ? $data['password'] : $inbox->password,
            'is_active' => $data['is_active'] ?? $inbox->is_active,
            'settings' => $data['settings'] ?? $inbox->settings,
            'default_assignee_id' => $data['default_assignee_id'] ?? $inbox->default_assignee_id,
            'assignment_method' => $data['assignment_method'] ?? $inbox->assignment_method,
        ]);

        return $inbox->fresh();
    }

    /**
     * Delete a shared inbox.
     */
    public function deleteInbox(int $id): void
    {
        $inbox = SharedInbox::findOrFail($id);

        DB::transaction(function () use ($inbox) {
            // Delete all related data
            $inbox->rules()->delete();
            $inbox->cannedResponses()->delete();

            // Delete messages and conversations
            $conversationIds = $inbox->conversations()->pluck('id');
            InboxMessage::whereIn('conversation_id', $conversationIds)->delete();
            $inbox->conversations()->delete();

            $inbox->members()->delete();
            $inbox->delete();
        });
    }

    /**
     * Add member to inbox.
     */
    public function addInboxMember(int $inboxId, array $data): SharedInboxMember
    {
        $inbox = SharedInbox::findOrFail($inboxId);

        // Check if member already exists
        $existing = $inbox->members()->where('user_id', $data['user_id'])->first();
        if ($existing) {
            throw new \InvalidArgumentException('User is already a member of this inbox');
        }

        return $inbox->members()->create([
            'user_id' => $data['user_id'],
            'role' => $data['role'] ?? 'member',
            'can_reply' => $data['can_reply'] ?? true,
            'can_assign' => $data['can_assign'] ?? false,
            'can_close' => $data['can_close'] ?? false,
            'receives_notifications' => $data['receives_notifications'] ?? true,
            'active_conversation_limit' => $data['active_conversation_limit'] ?? null,
        ]);
    }

    /**
     * Update inbox member.
     */
    public function updateInboxMember(int $memberId, array $data): SharedInboxMember
    {
        $member = SharedInboxMember::findOrFail($memberId);

        $member->update([
            'role' => $data['role'] ?? $member->role,
            'can_reply' => $data['can_reply'] ?? $member->can_reply,
            'can_assign' => $data['can_assign'] ?? $member->can_assign,
            'can_close' => $data['can_close'] ?? $member->can_close,
            'receives_notifications' => $data['receives_notifications'] ?? $member->receives_notifications,
            'active_conversation_limit' => $data['active_conversation_limit'] ?? $member->active_conversation_limit,
        ]);

        return $member->fresh();
    }

    /**
     * Remove member from inbox.
     */
    public function removeInboxMember(int $memberId): void
    {
        $member = SharedInboxMember::findOrFail($memberId);

        // Unassign their conversations
        InboxConversation::where('inbox_id', $member->inbox_id)
            ->where('assigned_to', $member->user_id)
            ->update(['assigned_to' => null]);

        $member->delete();
    }

    /**
     * Test inbox connection.
     */
    public function testInboxConnection(int $id): array
    {
        $inbox = SharedInbox::findOrFail($id);

        // In production, this would test IMAP/SMTP connections
        // For now, return mock result
        $success = !empty($inbox->imap_host) && !empty($inbox->smtp_host);

        $inbox->update(['is_connected' => $success]);

        return [
            'success' => $success,
            'imap_status' => $success ? 'connected' : 'failed',
            'smtp_status' => $success ? 'connected' : 'failed',
            'message' => $success ? 'Connection successful' : 'Connection failed',
        ];
    }

    /**
     * Sync inbox emails.
     */
    public function syncInbox(int $id): array
    {
        $inbox = SharedInbox::findOrFail($id);

        // In production, this would sync emails via IMAP
        // For now, update last synced timestamp
        $inbox->update(['last_synced_at' => now()]);

        return [
            'success' => true,
            'new_messages' => 0,
            'synced_at' => now()->toIso8601String(),
        ];
    }

    // ==========================================
    // CONVERSATION QUERY USE CASES
    // ==========================================

    /**
     * List conversations with filtering and pagination.
     */
    public function listConversations(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = InboxConversation::query()
            ->with(['inbox', 'assignee']);

        if (!empty($filters['inbox_id'])) {
            $query->where('inbox_id', $filters['inbox_id']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['assigned_to'])) {
            $query->assignedTo($filters['assigned_to']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->unassigned();
        }

        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        if (!empty($filters['channel'])) {
            $query->byChannel($filters['channel']);
        }

        if (isset($filters['starred']) && $filters['starred']) {
            $query->starred();
        }

        if (isset($filters['not_spam']) && $filters['not_spam']) {
            $query->notSpam();
        }

        if (!empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('subject', 'like', "%{$filters['search']}%")
                    ->orWhere('contact_name', 'like', "%{$filters['search']}%")
                    ->orWhere('contact_email', 'like', "%{$filters['search']}%")
                    ->orWhere('snippet', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sortField = $filters['sort_by'] ?? 'last_message_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single conversation with messages.
     */
    public function getConversation(int $id): ?InboxConversation
    {
        return InboxConversation::with(['inbox', 'assignee', 'contact', 'messages.sender'])
            ->find($id);
    }

    /**
     * Get conversation messages.
     */
    public function getConversationMessages(int $conversationId, int $perPage = 50): LengthAwarePaginator
    {
        return InboxMessage::where('conversation_id', $conversationId)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get conversation count by status.
     */
    public function getConversationCounts(int $inboxId): array
    {
        $base = InboxConversation::where('inbox_id', $inboxId);

        return [
            'all' => (clone $base)->count(),
            'open' => (clone $base)->open()->count(),
            'pending' => (clone $base)->pending()->count(),
            'resolved' => (clone $base)->resolved()->count(),
            'closed' => (clone $base)->closed()->count(),
            'unassigned' => (clone $base)->unassigned()->count(),
            'starred' => (clone $base)->starred()->count(),
            'spam' => (clone $base)->where('is_spam', true)->count(),
        ];
    }

    /**
     * Get my assigned conversations.
     */
    public function getMyConversations(int $userId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $filters['assigned_to'] = $userId;
        return $this->listConversations($filters, $perPage);
    }

    // ==========================================
    // CONVERSATION COMMAND USE CASES
    // ==========================================

    /**
     * Create a new conversation.
     */
    public function createConversation(int $inboxId, array $data): InboxConversation
    {
        return DB::transaction(function () use ($inboxId, $data) {
            $inbox = SharedInbox::findOrFail($inboxId);

            $conversation = InboxConversation::create([
                'inbox_id' => $inboxId,
                'subject' => $data['subject'],
                'status' => $data['status'] ?? 'open',
                'priority' => $data['priority'] ?? 'normal',
                'channel' => $data['channel'] ?? 'email',
                'contact_email' => $data['contact_email'],
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'tags' => $data['tags'] ?? [],
                'custom_fields' => $data['custom_fields'] ?? [],
            ]);

            // Auto-assign based on inbox settings
            if ($inbox->assignment_method !== 'manual') {
                $assignee = $inbox->getNextAssignee();
                if ($assignee) {
                    $conversation->assignTo($assignee->id);
                }
            }

            // Apply inbox rules
            $this->applyRules($conversation);

            // Send auto-reply if enabled
            if ($inbox->getAutoReplyEnabled()) {
                $this->sendAutoReply($conversation, $inbox);
            }

            return $conversation->fresh(['inbox', 'assignee']);
        });
    }

    /**
     * Update conversation.
     */
    public function updateConversation(int $id, array $data): InboxConversation
    {
        $conversation = InboxConversation::findOrFail($id);

        $conversation->update([
            'subject' => $data['subject'] ?? $conversation->subject,
            'priority' => $data['priority'] ?? $conversation->priority,
            'tags' => $data['tags'] ?? $conversation->tags,
            'custom_fields' => $data['custom_fields'] ?? $conversation->custom_fields,
        ]);

        return $conversation->fresh();
    }

    /**
     * Assign conversation.
     */
    public function assignConversation(int $id, ?int $userId): InboxConversation
    {
        $conversation = InboxConversation::findOrFail($id);
        $conversation->assignTo($userId);
        return $conversation->fresh(['assignee']);
    }

    /**
     * Change conversation status.
     */
    public function changeStatus(int $id, string $status): InboxConversation
    {
        $conversation = InboxConversation::findOrFail($id);

        if ($status === 'resolved') {
            $conversation->resolve();
        } elseif ($status === 'open' && $conversation->isResolved()) {
            $conversation->reopen();
        } else {
            $conversation->update(['status' => $status]);
        }

        return $conversation->fresh();
    }

    /**
     * Toggle star on conversation.
     */
    public function toggleStar(int $id): InboxConversation
    {
        $conversation = InboxConversation::findOrFail($id);
        $conversation->toggleStar();
        return $conversation->fresh();
    }

    /**
     * Mark conversation as spam.
     */
    public function markAsSpam(int $id): InboxConversation
    {
        $conversation = InboxConversation::findOrFail($id);
        $conversation->markAsSpam();
        return $conversation->fresh();
    }

    /**
     * Add tag to conversation.
     */
    public function addTag(int $id, string $tag): InboxConversation
    {
        $conversation = InboxConversation::findOrFail($id);
        $conversation->addTag($tag);
        return $conversation->fresh();
    }

    /**
     * Remove tag from conversation.
     */
    public function removeTag(int $id, string $tag): InboxConversation
    {
        $conversation = InboxConversation::findOrFail($id);
        $conversation->removeTag($tag);
        return $conversation->fresh();
    }

    /**
     * Merge conversations.
     */
    public function mergeConversations(int $targetId, array $sourceIds): InboxConversation
    {
        return DB::transaction(function () use ($targetId, $sourceIds) {
            $target = InboxConversation::findOrFail($targetId);

            foreach ($sourceIds as $sourceId) {
                if ($sourceId === $targetId) {
                    continue;
                }

                $source = InboxConversation::find($sourceId);
                if (!$source) {
                    continue;
                }

                // Move messages to target
                InboxMessage::where('conversation_id', $sourceId)
                    ->update(['conversation_id' => $targetId]);

                // Merge tags
                $mergedTags = array_unique(array_merge($target->tags ?? [], $source->tags ?? []));
                $target->update(['tags' => $mergedTags]);

                // Update message count
                $target->update(['message_count' => $target->messages()->count()]);

                // Delete source conversation
                $source->delete();
            }

            return $target->fresh();
        });
    }

    // ==========================================
    // MESSAGE USE CASES
    // ==========================================

    /**
     * Send a reply message.
     */
    public function sendReply(int $conversationId, array $data): InboxMessage
    {
        return DB::transaction(function () use ($conversationId, $data) {
            $conversation = InboxConversation::findOrFail($conversationId);
            $inbox = $conversation->inbox;

            $message = InboxMessage::create([
                'conversation_id' => $conversationId,
                'direction' => 'outbound',
                'type' => 'reply',
                'from_email' => $inbox->email,
                'from_name' => Auth::user()?->name ?? $inbox->name,
                'to_emails' => [$conversation->contact_email],
                'cc_emails' => $data['cc_emails'] ?? [],
                'bcc_emails' => $data['bcc_emails'] ?? [],
                'subject' => $data['subject'] ?? "Re: {$conversation->subject}",
                'body_text' => strip_tags($data['body']),
                'body_html' => $data['body'],
                'attachments' => $data['attachments'] ?? [],
                'status' => 'sending',
                'sent_by' => Auth::id(),
            ]);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'message_count' => $conversation->message_count + 1,
                'snippet' => $message->getSnippet(),
            ]);

            // Set first response time if this is the first reply
            if (!$conversation->first_response_at) {
                $conversation->update(['first_response_at' => now()]);
                $conversation->calculateResponseTime();
            }

            // Mark message as sent (in production, this would be done after actual sending)
            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $message->fresh(['sender']);
        });
    }

    /**
     * Add an internal note.
     */
    public function addNote(int $conversationId, array $data): InboxMessage
    {
        $conversation = InboxConversation::findOrFail($conversationId);

        $message = InboxMessage::create([
            'conversation_id' => $conversationId,
            'direction' => 'outbound',
            'type' => 'note',
            'from_name' => Auth::user()?->name,
            'body_text' => strip_tags($data['body']),
            'body_html' => $data['body'],
            'attachments' => $data['attachments'] ?? [],
            'status' => 'delivered',
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        return $message->fresh(['sender']);
    }

    /**
     * Mark messages as read.
     */
    public function markMessagesAsRead(int $conversationId): void
    {
        InboxMessage::where('conversation_id', $conversationId)
            ->whereNull('read_at')
            ->where('direction', 'inbound')
            ->update(['read_at' => now()]);
    }

    /**
     * Process incoming message.
     */
    public function processIncomingMessage(int $inboxId, array $data): InboxMessage
    {
        return DB::transaction(function () use ($inboxId, $data) {
            $inbox = SharedInbox::findOrFail($inboxId);

            // Find or create conversation
            $conversation = null;
            if (!empty($data['in_reply_to'])) {
                $existingMessage = InboxMessage::where('external_message_id', $data['in_reply_to'])->first();
                if ($existingMessage) {
                    $conversation = $existingMessage->conversation;
                }
            }

            if (!$conversation) {
                $conversation = $this->createConversation($inboxId, [
                    'subject' => $data['subject'] ?? 'No Subject',
                    'contact_email' => $data['from_email'],
                    'contact_name' => $data['from_name'] ?? null,
                    'channel' => 'email',
                ]);
            }

            // Create the message
            $message = InboxMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'type' => 'email',
                'from_email' => $data['from_email'],
                'from_name' => $data['from_name'] ?? null,
                'to_emails' => $data['to_emails'] ?? [$inbox->email],
                'cc_emails' => $data['cc_emails'] ?? [],
                'subject' => $data['subject'] ?? 'No Subject',
                'body_text' => $data['body_text'] ?? null,
                'body_html' => $data['body_html'] ?? null,
                'attachments' => $data['attachments'] ?? [],
                'status' => 'received',
                'external_message_id' => $data['message_id'] ?? null,
                'in_reply_to' => $data['in_reply_to'] ?? null,
                'raw_headers' => $data['headers'] ?? null,
            ]);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'message_count' => $conversation->message_count + 1,
                'snippet' => $message->getSnippet(),
                'status' => 'open', // Reopen if it was resolved
            ]);

            // Apply rules to the new message
            $this->applyRules($conversation, $message);

            return $message->fresh(['conversation']);
        });
    }

    // ==========================================
    // CANNED RESPONSE USE CASES
    // ==========================================

    /**
     * List canned responses.
     */
    public function listCannedResponses(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = InboxCannedResponse::query()
            ->with('creator');

        if (!empty($filters['inbox_id'])) {
            $query->forInbox($filters['inbox_id']);
        } else {
            $query->global();
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('shortcut', 'like', "%{$filters['search']}%")
                    ->orWhere('body', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get canned response by shortcut.
     */
    public function getCannedResponseByShortcut(string $shortcut, ?int $inboxId = null): ?InboxCannedResponse
    {
        $query = InboxCannedResponse::byShortcut($shortcut)->active();

        if ($inboxId) {
            $query->forInbox($inboxId);
        } else {
            $query->global();
        }

        return $query->first();
    }

    /**
     * Create canned response.
     */
    public function createCannedResponse(array $data): InboxCannedResponse
    {
        return InboxCannedResponse::create([
            'inbox_id' => $data['inbox_id'] ?? null,
            'name' => $data['name'],
            'shortcut' => $data['shortcut'] ?? null,
            'category' => $data['category'] ?? null,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'attachments' => $data['attachments'] ?? [],
            'created_by' => Auth::id(),
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update canned response.
     */
    public function updateCannedResponse(int $id, array $data): InboxCannedResponse
    {
        $response = InboxCannedResponse::findOrFail($id);

        $response->update([
            'name' => $data['name'] ?? $response->name,
            'shortcut' => $data['shortcut'] ?? $response->shortcut,
            'category' => $data['category'] ?? $response->category,
            'subject' => $data['subject'] ?? $response->subject,
            'body' => $data['body'] ?? $response->body,
            'attachments' => $data['attachments'] ?? $response->attachments,
            'is_active' => $data['is_active'] ?? $response->is_active,
        ]);

        return $response->fresh();
    }

    /**
     * Delete canned response.
     */
    public function deleteCannedResponse(int $id): void
    {
        InboxCannedResponse::findOrFail($id)->delete();
    }

    /**
     * Use canned response.
     */
    public function useCannedResponse(int $id, array $variables = []): string
    {
        $response = InboxCannedResponse::findOrFail($id);
        $response->incrementUseCount();

        return $response->render($variables);
    }

    // ==========================================
    // INBOX RULE USE CASES
    // ==========================================

    /**
     * List inbox rules.
     */
    public function listRules(int $inboxId): Collection
    {
        return InboxRule::where('inbox_id', $inboxId)
            ->with('creator')
            ->ordered()
            ->get();
    }

    /**
     * Create inbox rule.
     */
    public function createRule(int $inboxId, array $data): InboxRule
    {
        return InboxRule::create([
            'inbox_id' => $inboxId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'conditions' => $data['conditions'],
            'condition_match' => $data['condition_match'] ?? 'all',
            'actions' => $data['actions'],
            'is_active' => $data['is_active'] ?? true,
            'stop_processing' => $data['stop_processing'] ?? false,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Update inbox rule.
     */
    public function updateRule(int $id, array $data): InboxRule
    {
        $rule = InboxRule::findOrFail($id);

        $rule->update([
            'name' => $data['name'] ?? $rule->name,
            'description' => $data['description'] ?? $rule->description,
            'priority' => $data['priority'] ?? $rule->priority,
            'conditions' => $data['conditions'] ?? $rule->conditions,
            'condition_match' => $data['condition_match'] ?? $rule->condition_match,
            'actions' => $data['actions'] ?? $rule->actions,
            'is_active' => $data['is_active'] ?? $rule->is_active,
            'stop_processing' => $data['stop_processing'] ?? $rule->stop_processing,
        ]);

        return $rule->fresh();
    }

    /**
     * Delete inbox rule.
     */
    public function deleteRule(int $id): void
    {
        InboxRule::findOrFail($id)->delete();
    }

    /**
     * Reorder inbox rules.
     */
    public function reorderRules(int $inboxId, array $ruleIds): void
    {
        foreach ($ruleIds as $priority => $ruleId) {
            InboxRule::where('id', $ruleId)
                ->where('inbox_id', $inboxId)
                ->update(['priority' => $priority]);
        }
    }

    // ==========================================
    // ANALYTICS USE CASES
    // ==========================================

    /**
     * Get inbox performance metrics.
     */
    public function getInboxMetrics(int $inboxId, string $period = 'week'): array
    {
        $dateFrom = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            default => now()->subWeek(),
        };

        $conversations = InboxConversation::where('inbox_id', $inboxId)
            ->where('created_at', '>=', $dateFrom);

        $resolved = (clone $conversations)->whereNotNull('resolved_at');

        return [
            'period' => $period,
            'date_from' => $dateFrom->toIso8601String(),
            'total_conversations' => $conversations->count(),
            'resolved_conversations' => $resolved->count(),
            'resolution_rate' => $conversations->count() > 0
                ? round(($resolved->count() / $conversations->count()) * 100, 1)
                : 0,
            'avg_response_time_seconds' => $conversations->whereNotNull('response_time_seconds')
                ->avg('response_time_seconds'),
            'avg_resolution_time_seconds' => $resolved->get()
                ->avg(fn ($c) => $c->resolved_at->diffInSeconds($c->created_at)),
            'by_status' => [
                'open' => (clone $conversations)->open()->count(),
                'pending' => (clone $conversations)->pending()->count(),
                'resolved' => (clone $conversations)->resolved()->count(),
                'closed' => (clone $conversations)->closed()->count(),
            ],
            'by_priority' => [
                'urgent' => (clone $conversations)->byPriority('urgent')->count(),
                'high' => (clone $conversations)->byPriority('high')->count(),
                'normal' => (clone $conversations)->byPriority('normal')->count(),
                'low' => (clone $conversations)->byPriority('low')->count(),
            ],
            'by_channel' => $conversations->selectRaw('channel, count(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
        ];
    }

    /**
     * Get agent performance.
     */
    public function getAgentPerformance(int $inboxId, string $period = 'week'): array
    {
        $dateFrom = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek(),
        };

        $members = SharedInboxMember::where('inbox_id', $inboxId)
            ->with('user')
            ->get();

        $performance = [];

        foreach ($members as $member) {
            $conversations = InboxConversation::where('inbox_id', $inboxId)
                ->where('assigned_to', $member->user_id)
                ->where('created_at', '>=', $dateFrom);

            $resolved = (clone $conversations)->whereNotNull('resolved_at');
            $replies = InboxMessage::whereHas('conversation', function ($q) use ($inboxId, $member) {
                $q->where('inbox_id', $inboxId)
                    ->where('assigned_to', $member->user_id);
            })
                ->where('sent_by', $member->user_id)
                ->where('created_at', '>=', $dateFrom);

            $performance[] = [
                'user_id' => $member->user_id,
                'user_name' => $member->user?->name,
                'current_active' => $member->current_active_count,
                'conversations_handled' => $conversations->count(),
                'resolved' => $resolved->count(),
                'replies_sent' => $replies->count(),
                'avg_response_time' => (clone $conversations)->whereNotNull('response_time_seconds')
                    ->avg('response_time_seconds'),
            ];
        }

        return [
            'period' => $period,
            'agents' => $performance,
        ];
    }

    /**
     * Get tag distribution.
     */
    public function getTagDistribution(int $inboxId): array
    {
        $conversations = InboxConversation::where('inbox_id', $inboxId)
            ->whereNotNull('tags')
            ->get();

        $tagCounts = [];

        foreach ($conversations as $conversation) {
            foreach ($conversation->tags ?? [] as $tag) {
                if (!isset($tagCounts[$tag])) {
                    $tagCounts[$tag] = 0;
                }
                $tagCounts[$tag]++;
            }
        }

        arsort($tagCounts);

        return $tagCounts;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Apply inbox rules to a conversation.
     */
    private function applyRules(InboxConversation $conversation, ?InboxMessage $message = null): void
    {
        $rules = InboxRule::where('inbox_id', $conversation->inbox_id)
            ->active()
            ->ordered()
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matches($conversation, $message)) {
                $rule->execute($conversation);

                if ($rule->stop_processing) {
                    break;
                }
            }
        }
    }

    /**
     * Send auto-reply to a conversation.
     */
    private function sendAutoReply(InboxConversation $conversation, SharedInbox $inbox): void
    {
        $message = $inbox->getAutoReplyMessage();

        if (!$message) {
            return;
        }

        InboxMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'auto_reply',
            'from_email' => $inbox->email,
            'from_name' => $inbox->name,
            'to_emails' => [$conversation->contact_email],
            'subject' => "Re: {$conversation->subject}",
            'body_text' => strip_tags($message),
            'body_html' => $message,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update([
            'message_count' => $conversation->message_count + 1,
        ]);
    }
}
