<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Inbox;

use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbInboxConversationRepository implements InboxConversationRepositoryInterface
{
    private const TABLE_SHARED_INBOXES = 'shared_inboxes';
    private const TABLE_INBOX_CONVERSATIONS = 'inbox_conversations';
    private const TABLE_INBOX_MESSAGES = 'inbox_messages';
    private const TABLE_SHARED_INBOX_MEMBERS = 'shared_inbox_members';
    private const TABLE_INBOX_CANNED_RESPONSES = 'inbox_canned_responses';
    private const TABLE_INBOX_RULES = 'inbox_rules';
    private const TABLE_USERS = 'users';
    private const TABLE_MODULE_RECORDS = 'module_records';

    // ==========================================
    // SHARED INBOX QUERY METHODS
    // ==========================================

    public function listInboxes(array $filters = []): array
    {
        $query = DB::table(self::TABLE_SHARED_INBOXES);

        if (!empty($filters['active_only'])) {
            $query->where('is_active', true);
        }

        if (!empty($filters['connected_only'])) {
            $query->where('is_connected', true);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['user_id'])) {
            $query->whereExists(function ($q) use ($filters) {
                $q->select(DB::raw(1))
                    ->from(self::TABLE_SHARED_INBOX_MEMBERS)
                    ->whereColumn(self::TABLE_SHARED_INBOX_MEMBERS . '.inbox_id', self::TABLE_SHARED_INBOXES . '.id')
                    ->where(self::TABLE_SHARED_INBOX_MEMBERS . '.user_id', $filters['user_id']);
            });
        }

        $inboxes = $query->orderBy('name')->get();

        $result = [];
        foreach ($inboxes as $inbox) {
            $inboxArray = $this->toArray($inbox);

            // Load default assignee
            if ($inbox->default_assignee_id) {
                $assignee = DB::table(self::TABLE_USERS)->where('id', $inbox->default_assignee_id)->first();
                $inboxArray['default_assignee'] = $assignee ? $this->toArray($assignee) : null;
            } else {
                $inboxArray['default_assignee'] = null;
            }

            $result[] = $inboxArray;
        }

        return $result;
    }

    public function getInbox(int $id): ?array
    {
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->first();

        if (!$inbox) {
            return null;
        }

        $inboxArray = $this->toArray($inbox);

        // Load default assignee
        if ($inbox->default_assignee_id) {
            $assignee = DB::table(self::TABLE_USERS)->where('id', $inbox->default_assignee_id)->first();
            $inboxArray['default_assignee'] = $assignee ? $this->toArray($assignee) : null;
        } else {
            $inboxArray['default_assignee'] = null;
        }

        // Load members with users
        $members = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
            ->where('inbox_id', $id)
            ->get();

        $membersArray = [];
        foreach ($members as $member) {
            $memberArray = $this->toArray($member);
            $user = DB::table(self::TABLE_USERS)->where('id', $member->user_id)->first();
            $memberArray['user'] = $user ? $this->toArray($user) : null;
            $membersArray[] = $memberArray;
        }

        $inboxArray['members'] = $membersArray;

        return $inboxArray;
    }

    public function getInboxWithStats(int $id): array
    {
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->first();

        if (!$inbox) {
            throw new \RuntimeException("Inbox not found with ID: {$id}");
        }

        $conversationsQuery = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('inbox_id', $id);

        return [
            'inbox' => $this->toArray($inbox),
            'stats' => [
                'total_conversations' => (clone $conversationsQuery)->count(),
                'open_conversations' => (clone $conversationsQuery)->where('status', 'open')->count(),
                'pending_conversations' => (clone $conversationsQuery)->where('status', 'pending')->count(),
                'unassigned_conversations' => (clone $conversationsQuery)->whereNull('assigned_to')->count(),
                'resolved_today' => (clone $conversationsQuery)
                    ->where('status', 'resolved')
                    ->whereDate('resolved_at', today())
                    ->count(),
                'avg_response_time' => (clone $conversationsQuery)
                    ->whereNotNull('response_time_seconds')
                    ->avg('response_time_seconds'),
                'member_count' => DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('inbox_id', $id)->count(),
            ],
        ];
    }

    public function getInboxMembers(int $inboxId): array
    {
        $members = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
            ->where('inbox_id', $inboxId)
            ->get();

        $result = [];
        foreach ($members as $member) {
            $memberArray = $this->toArray($member);
            $user = DB::table(self::TABLE_USERS)->where('id', $member->user_id)->first();
            $memberArray['user'] = $user ? $this->toArray($user) : null;
            $result[] = $memberArray;
        }

        return $result;
    }

    // ==========================================
    // SHARED INBOX COMMAND METHODS
    // ==========================================

    public function createInbox(array $data, int $creatorUserId): array
    {
        return DB::transaction(function () use ($data, $creatorUserId) {
            $now = now();

            $inboxId = DB::table(self::TABLE_SHARED_INBOXES)->insertGetId([
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
                'settings' => isset($data['settings']) ? json_encode($data['settings']) : json_encode([]),
                'default_assignee_id' => $data['default_assignee_id'] ?? null,
                'assignment_method' => $data['assignment_method'] ?? 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->insert([
                'inbox_id' => $inboxId,
                'user_id' => $creatorUserId,
                'role' => 'admin',
                'can_reply' => true,
                'can_assign' => true,
                'can_close' => true,
                'receives_notifications' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->getInbox($inboxId);
        });
    }

    public function updateInbox(int $id, array $data): array
    {
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->first();

        if (!$inbox) {
            throw new \RuntimeException("Inbox not found with ID: {$id}");
        }

        $updateData = [
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
            'default_assignee_id' => $data['default_assignee_id'] ?? $inbox->default_assignee_id,
            'assignment_method' => $data['assignment_method'] ?? $inbox->assignment_method,
            'updated_at' => now(),
        ];

        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings']);
        }

        DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->update($updateData);

        return $this->getInbox($id);
    }

    public function deleteInbox(int $id): void
    {
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->first();

        if (!$inbox) {
            throw new \RuntimeException("Inbox not found with ID: {$id}");
        }

        DB::transaction(function () use ($id) {
            // Delete rules
            DB::table(self::TABLE_INBOX_RULES)->where('inbox_id', $id)->delete();

            // Delete canned responses
            DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('inbox_id', $id)->delete();

            // Get conversation IDs and delete messages
            $conversationIds = DB::table(self::TABLE_INBOX_CONVERSATIONS)
                ->where('inbox_id', $id)
                ->pluck('id');

            if ($conversationIds->isNotEmpty()) {
                DB::table(self::TABLE_INBOX_MESSAGES)
                    ->whereIn('conversation_id', $conversationIds->toArray())
                    ->delete();
            }

            // Delete conversations
            DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('inbox_id', $id)->delete();

            // Delete members
            DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('inbox_id', $id)->delete();

            // Delete inbox
            DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->delete();
        });
    }

    public function addInboxMember(int $inboxId, array $data): array
    {
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $inboxId)->first();

        if (!$inbox) {
            throw new \RuntimeException("Inbox not found with ID: {$inboxId}");
        }

        $existing = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
            ->where('inbox_id', $inboxId)
            ->where('user_id', $data['user_id'])
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('User is already a member of this inbox');
        }

        $now = now();

        $memberId = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->insertGetId([
            'inbox_id' => $inboxId,
            'user_id' => $data['user_id'],
            'role' => $data['role'] ?? 'member',
            'can_reply' => $data['can_reply'] ?? true,
            'can_assign' => $data['can_assign'] ?? false,
            'can_close' => $data['can_close'] ?? false,
            'receives_notifications' => $data['receives_notifications'] ?? true,
            'active_conversation_limit' => $data['active_conversation_limit'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $member = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('id', $memberId)->first();
        return $this->toArray($member);
    }

    public function updateInboxMember(int $memberId, array $data): array
    {
        $member = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('id', $memberId)->first();

        if (!$member) {
            throw new \RuntimeException("Member not found with ID: {$memberId}");
        }

        DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('id', $memberId)->update([
            'role' => $data['role'] ?? $member->role,
            'can_reply' => $data['can_reply'] ?? $member->can_reply,
            'can_assign' => $data['can_assign'] ?? $member->can_assign,
            'can_close' => $data['can_close'] ?? $member->can_close,
            'receives_notifications' => $data['receives_notifications'] ?? $member->receives_notifications,
            'active_conversation_limit' => $data['active_conversation_limit'] ?? $member->active_conversation_limit,
            'updated_at' => now(),
        ]);

        $updated = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('id', $memberId)->first();
        return $this->toArray($updated);
    }

    public function removeInboxMember(int $memberId): void
    {
        $member = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('id', $memberId)->first();

        if (!$member) {
            throw new \RuntimeException("Member not found with ID: {$memberId}");
        }

        DB::table(self::TABLE_INBOX_CONVERSATIONS)
            ->where('inbox_id', $member->inbox_id)
            ->where('assigned_to', $member->user_id)
            ->update([
                'assigned_to' => null,
                'updated_at' => now(),
            ]);

        DB::table(self::TABLE_SHARED_INBOX_MEMBERS)->where('id', $memberId)->delete();
    }

    public function testInboxConnection(int $id): array
    {
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->first();

        if (!$inbox) {
            throw new \RuntimeException("Inbox not found with ID: {$id}");
        }

        $success = !empty($inbox->imap_host) && !empty($inbox->smtp_host);

        DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->update([
            'is_connected' => $success,
            'updated_at' => now(),
        ]);

        return [
            'success' => $success,
            'imap_status' => $success ? 'connected' : 'failed',
            'smtp_status' => $success ? 'connected' : 'failed',
            'message' => $success ? 'Connection successful' : 'Connection failed',
        ];
    }

    public function syncInbox(int $id): array
    {
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->first();

        if (!$inbox) {
            throw new \RuntimeException("Inbox not found with ID: {$id}");
        }

        $now = now();

        DB::table(self::TABLE_SHARED_INBOXES)->where('id', $id)->update([
            'last_synced_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'success' => true,
            'new_messages' => 0,
            'synced_at' => $now->toIso8601String(),
        ];
    }

    // ==========================================
    // CONVERSATION QUERY METHODS
    // ==========================================

    public function listConversations(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_INBOX_CONVERSATIONS);

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
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereNull('assigned_to');
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['starred']) && $filters['starred']) {
            $query->where('is_starred', true);
        }

        if (isset($filters['not_spam']) && $filters['not_spam']) {
            $query->where('is_spam', false);
        }

        if (!empty($filters['tag'])) {
            $query->whereRaw("JSON_CONTAINS(tags, ?)", [json_encode($filters['tag'])]);
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

        $total = (clone $query)->count();
        $conversations = $query->forPage($page, $perPage)->get();

        $items = [];
        foreach ($conversations as $conversation) {
            $conversationArray = $this->toArray($conversation);

            // Load inbox
            $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $conversation->inbox_id)->first();
            $conversationArray['inbox'] = $inbox ? $this->toArray($inbox) : null;

            // Load assignee
            if ($conversation->assigned_to) {
                $assignee = DB::table(self::TABLE_USERS)->where('id', $conversation->assigned_to)->first();
                $conversationArray['assignee'] = $assignee ? $this->toArray($assignee) : null;
            } else {
                $conversationArray['assignee'] = null;
            }

            $items[] = $conversationArray;
        }

        return PaginatedResult::create(
            $items,
            $total,
            $perPage,
            $page
        );
    }

    public function getConversation(int $id): ?array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->first();

        if (!$conversation) {
            return null;
        }

        $conversationArray = $this->toArray($conversation);

        // Load inbox
        $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $conversation->inbox_id)->first();
        $conversationArray['inbox'] = $inbox ? $this->toArray($inbox) : null;

        // Load assignee
        if ($conversation->assigned_to) {
            $assignee = DB::table(self::TABLE_USERS)->where('id', $conversation->assigned_to)->first();
            $conversationArray['assignee'] = $assignee ? $this->toArray($assignee) : null;
        } else {
            $conversationArray['assignee'] = null;
        }

        // Load contact
        if ($conversation->contact_id) {
            $contact = DB::table(self::TABLE_MODULE_RECORDS)->where('id', $conversation->contact_id)->first();
            $conversationArray['contact'] = $contact ? $this->toArray($contact) : null;
        } else {
            $conversationArray['contact'] = null;
        }

        // Load messages with senders
        $messages = DB::table(self::TABLE_INBOX_MESSAGES)
            ->where('conversation_id', $id)
            ->get();

        $messagesArray = [];
        foreach ($messages as $message) {
            $messageArray = $this->toArray($message);
            if ($message->sent_by) {
                $sender = DB::table(self::TABLE_USERS)->where('id', $message->sent_by)->first();
                $messageArray['sender'] = $sender ? $this->toArray($sender) : null;
            } else {
                $messageArray['sender'] = null;
            }
            $messagesArray[] = $messageArray;
        }

        $conversationArray['messages'] = $messagesArray;

        return $conversationArray;
    }

    public function getConversationMessages(int $conversationId, int $perPage = 50, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_INBOX_MESSAGES)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc');

        $total = (clone $query)->count();
        $messages = $query->forPage($page, $perPage)->get();

        $items = [];
        foreach ($messages as $message) {
            $messageArray = $this->toArray($message);
            if ($message->sent_by) {
                $sender = DB::table(self::TABLE_USERS)->where('id', $message->sent_by)->first();
                $messageArray['sender'] = $sender ? $this->toArray($sender) : null;
            } else {
                $messageArray['sender'] = null;
            }
            $items[] = $messageArray;
        }

        return PaginatedResult::create(
            $items,
            $total,
            $perPage,
            $page
        );
    }

    public function getConversationCounts(int $inboxId): array
    {
        $base = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('inbox_id', $inboxId);

        return [
            'all' => (clone $base)->count(),
            'open' => (clone $base)->where('status', 'open')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'resolved' => (clone $base)->where('status', 'resolved')->count(),
            'closed' => (clone $base)->where('status', 'closed')->count(),
            'unassigned' => (clone $base)->whereNull('assigned_to')->count(),
            'starred' => (clone $base)->where('is_starred', true)->count(),
            'spam' => (clone $base)->where('is_spam', true)->count(),
        ];
    }

    public function getMyConversations(int $userId, array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $filters['assigned_to'] = $userId;
        return $this->listConversations($filters, $perPage, $page);
    }

    // ==========================================
    // CONVERSATION COMMAND METHODS
    // ==========================================

    public function createConversation(int $inboxId, array $data): array
    {
        return DB::transaction(function () use ($inboxId, $data) {
            $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $inboxId)->first();

            if (!$inbox) {
                throw new \RuntimeException("Inbox not found with ID: {$inboxId}");
            }

            $now = now();

            $conversationId = DB::table(self::TABLE_INBOX_CONVERSATIONS)->insertGetId([
                'inbox_id' => $inboxId,
                'subject' => $data['subject'],
                'status' => $data['status'] ?? 'open',
                'priority' => $data['priority'] ?? 'normal',
                'channel' => $data['channel'] ?? 'email',
                'contact_email' => $data['contact_email'],
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'tags' => isset($data['tags']) ? json_encode($data['tags']) : json_encode([]),
                'custom_fields' => isset($data['custom_fields']) ? json_encode($data['custom_fields']) : json_encode([]),
                'message_count' => 0,
                'is_spam' => false,
                'is_starred' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Handle assignment based on assignment method
            $assignmentMethod = $inbox->assignment_method ?? 'manual';
            if ($assignmentMethod !== 'manual') {
                $assigneeId = $this->getNextAssigneeId($inbox);
                if ($assigneeId) {
                    $this->assignConversationInternal($conversationId, $assigneeId, $inboxId);
                }
            }

            // Apply rules
            $this->applyRulesInternal($conversationId, $inboxId);

            // Send auto-reply if enabled
            $settings = is_string($inbox->settings) ? json_decode($inbox->settings, true) : (array)$inbox->settings;
            if ($settings['auto_reply']['enabled'] ?? false) {
                $this->sendAutoReplyInternal($conversationId, $inbox);
            }

            return $this->getConversation($conversationId);
        });
    }

    public function updateConversation(int $id, array $data): array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->first();

        if (!$conversation) {
            throw new \RuntimeException("Conversation not found with ID: {$id}");
        }

        $updateData = [
            'subject' => $data['subject'] ?? $conversation->subject,
            'priority' => $data['priority'] ?? $conversation->priority,
            'updated_at' => now(),
        ];

        if (isset($data['tags'])) {
            $updateData['tags'] = json_encode($data['tags']);
        }

        if (isset($data['custom_fields'])) {
            $updateData['custom_fields'] = json_encode($data['custom_fields']);
        }

        DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->update($updateData);

        return $this->getConversation($id);
    }

    public function assignConversation(int $id, ?int $userId): array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->first();

        if (!$conversation) {
            throw new \RuntimeException("Conversation not found with ID: {$id}");
        }

        $this->assignConversationInternal($id, $userId, $conversation->inbox_id);

        return $this->getConversation($id);
    }

    public function changeStatus(int $id, string $status): array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->first();

        if (!$conversation) {
            throw new \RuntimeException("Conversation not found with ID: {$id}");
        }

        $updateData = ['status' => $status, 'updated_at' => now()];

        if ($status === 'resolved') {
            $updateData['resolved_at'] = now();

            // Update assignee's count
            if ($conversation->assigned_to) {
                $this->decrementMemberActiveCount($conversation->inbox_id, $conversation->assigned_to);
            }
        } elseif ($status === 'open' && in_array($conversation->status, ['resolved', 'closed'])) {
            $updateData['resolved_at'] = null;

            // Update assignee's count
            if ($conversation->assigned_to) {
                $this->incrementMemberActiveCount($conversation->inbox_id, $conversation->assigned_to);
            }
        }

        DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->update($updateData);

        return $this->getConversation($id);
    }

    public function toggleStar(int $id): array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->first();

        if (!$conversation) {
            throw new \RuntimeException("Conversation not found with ID: {$id}");
        }

        DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->update([
            'is_starred' => !$conversation->is_starred,
            'updated_at' => now(),
        ]);

        return $this->getConversation($id);
    }

    public function markAsSpam(int $id): array
    {
        DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->update([
            'is_spam' => true,
            'updated_at' => now(),
        ]);

        return $this->getConversation($id);
    }

    public function addTag(int $id, string $tag): array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->first();

        if (!$conversation) {
            throw new \RuntimeException("Conversation not found with ID: {$id}");
        }

        $tags = is_string($conversation->tags) ? json_decode($conversation->tags, true) : (array)$conversation->tags;

        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->update([
                'tags' => json_encode($tags),
                'updated_at' => now(),
            ]);
        }

        return $this->getConversation($id);
    }

    public function removeTag(int $id, string $tag): array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->first();

        if (!$conversation) {
            throw new \RuntimeException("Conversation not found with ID: {$id}");
        }

        $tags = is_string($conversation->tags) ? json_decode($conversation->tags, true) : (array)$conversation->tags;
        $tags = array_values(array_diff($tags, [$tag]));

        DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $id)->update([
            'tags' => json_encode($tags),
            'updated_at' => now(),
        ]);

        return $this->getConversation($id);
    }

    public function mergeConversations(int $targetId, array $sourceIds): array
    {
        return DB::transaction(function () use ($targetId, $sourceIds) {
            $target = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $targetId)->first();

            if (!$target) {
                throw new \RuntimeException("Target conversation not found with ID: {$targetId}");
            }

            foreach ($sourceIds as $sourceId) {
                if ($sourceId === $targetId) {
                    continue;
                }

                $source = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $sourceId)->first();
                if (!$source) {
                    continue;
                }

                // Move messages
                DB::table(self::TABLE_INBOX_MESSAGES)
                    ->where('conversation_id', $sourceId)
                    ->update([
                        'conversation_id' => $targetId,
                        'updated_at' => now(),
                    ]);

                // Merge tags
                $targetTags = is_string($target->tags) ? json_decode($target->tags, true) : (array)$target->tags;
                $sourceTags = is_string($source->tags) ? json_decode($source->tags, true) : (array)$source->tags;
                $mergedTags = array_unique(array_merge($targetTags, $sourceTags));

                DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $targetId)->update([
                    'tags' => json_encode($mergedTags),
                    'updated_at' => now(),
                ]);

                // Update message count
                $messageCount = DB::table(self::TABLE_INBOX_MESSAGES)->where('conversation_id', $targetId)->count();
                DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $targetId)->update([
                    'message_count' => $messageCount,
                    'updated_at' => now(),
                ]);

                // Delete source conversation
                DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $sourceId)->delete();

                // Refresh target for next iteration
                $target = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $targetId)->first();
            }

            return $this->getConversation($targetId);
        });
    }

    // ==========================================
    // MESSAGE METHODS
    // ==========================================

    public function sendReply(int $conversationId, array $data, int $sentByUserId, ?string $userName): array
    {
        return DB::transaction(function () use ($conversationId, $data, $sentByUserId, $userName) {
            $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->first();

            if (!$conversation) {
                throw new \RuntimeException("Conversation not found with ID: {$conversationId}");
            }

            $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $conversation->inbox_id)->first();

            $now = now();

            $messageId = DB::table(self::TABLE_INBOX_MESSAGES)->insertGetId([
                'conversation_id' => $conversationId,
                'direction' => 'outbound',
                'type' => 'reply',
                'from_email' => $inbox->email,
                'from_name' => $userName ?? $inbox->name,
                'to_emails' => json_encode([$conversation->contact_email]),
                'cc_emails' => isset($data['cc_emails']) ? json_encode($data['cc_emails']) : json_encode([]),
                'bcc_emails' => isset($data['bcc_emails']) ? json_encode($data['bcc_emails']) : json_encode([]),
                'subject' => $data['subject'] ?? "Re: {$conversation->subject}",
                'body_text' => strip_tags($data['body']),
                'body_html' => $data['body'],
                'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : json_encode([]),
                'status' => 'sending',
                'sent_by' => $sentByUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $message = DB::table(self::TABLE_INBOX_MESSAGES)->where('id', $messageId)->first();
            $snippet = $this->getMessageSnippet($message->body_text ?? strip_tags($message->body_html ?? ''));

            DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->update([
                'last_message_at' => $now,
                'message_count' => $conversation->message_count + 1,
                'snippet' => $snippet,
                'updated_at' => $now,
            ]);

            // Handle first response time
            if (!$conversation->first_response_at) {
                DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->update([
                    'first_response_at' => $now,
                    'updated_at' => $now,
                ]);

                // Calculate response time
                $responseTime = \Carbon\Carbon::parse($now)->diffInSeconds($conversation->created_at);
                DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->update([
                    'response_time_seconds' => $responseTime,
                    'updated_at' => $now,
                ]);
            }

            // Mark as sent
            DB::table(self::TABLE_INBOX_MESSAGES)->where('id', $messageId)->update([
                'status' => 'sent',
                'sent_at' => $now,
                'updated_at' => $now,
            ]);

            $message = DB::table(self::TABLE_INBOX_MESSAGES)->where('id', $messageId)->first();
            $messageArray = $this->toArray($message);

            $sender = DB::table(self::TABLE_USERS)->where('id', $message->sent_by)->first();
            $messageArray['sender'] = $sender ? $this->toArray($sender) : null;

            return $messageArray;
        });
    }

    public function addNote(int $conversationId, array $data, int $sentByUserId, ?string $userName): array
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->first();

        if (!$conversation) {
            throw new \RuntimeException("Conversation not found with ID: {$conversationId}");
        }

        $now = now();

        $messageId = DB::table(self::TABLE_INBOX_MESSAGES)->insertGetId([
            'conversation_id' => $conversationId,
            'direction' => 'outbound',
            'type' => 'note',
            'from_name' => $userName,
            'body_text' => strip_tags($data['body']),
            'body_html' => $data['body'],
            'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : json_encode([]),
            'status' => 'delivered',
            'sent_by' => $sentByUserId,
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $message = DB::table(self::TABLE_INBOX_MESSAGES)->where('id', $messageId)->first();
        $messageArray = $this->toArray($message);

        $sender = DB::table(self::TABLE_USERS)->where('id', $message->sent_by)->first();
        $messageArray['sender'] = $sender ? $this->toArray($sender) : null;

        return $messageArray;
    }

    public function markMessagesAsRead(int $conversationId): void
    {
        DB::table(self::TABLE_INBOX_MESSAGES)
            ->where('conversation_id', $conversationId)
            ->whereNull('read_at')
            ->where('direction', 'inbound')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function processIncomingMessage(int $inboxId, array $data): array
    {
        return DB::transaction(function () use ($inboxId, $data) {
            $inbox = DB::table(self::TABLE_SHARED_INBOXES)->where('id', $inboxId)->first();

            if (!$inbox) {
                throw new \RuntimeException("Inbox not found with ID: {$inboxId}");
            }

            $conversationId = null;

            // Try to find existing conversation
            if (!empty($data['in_reply_to'])) {
                $existingMessage = DB::table(self::TABLE_INBOX_MESSAGES)
                    ->where('external_message_id', $data['in_reply_to'])
                    ->first();

                if ($existingMessage) {
                    $conversationId = $existingMessage->conversation_id;
                }
            }

            // Create new conversation if needed
            if (!$conversationId) {
                $conversationData = $this->createConversation($inboxId, [
                    'subject' => $data['subject'] ?? 'No Subject',
                    'contact_email' => $data['from_email'],
                    'contact_name' => $data['from_name'] ?? null,
                    'channel' => 'email',
                ]);
                $conversationId = $conversationData['id'];
            }

            $now = now();

            $messageId = DB::table(self::TABLE_INBOX_MESSAGES)->insertGetId([
                'conversation_id' => $conversationId,
                'direction' => 'inbound',
                'type' => 'email',
                'from_email' => $data['from_email'],
                'from_name' => $data['from_name'] ?? null,
                'to_emails' => isset($data['to_emails']) ? json_encode($data['to_emails']) : json_encode([$inbox->email]),
                'cc_emails' => isset($data['cc_emails']) ? json_encode($data['cc_emails']) : json_encode([]),
                'subject' => $data['subject'] ?? 'No Subject',
                'body_text' => $data['body_text'] ?? null,
                'body_html' => $data['body_html'] ?? null,
                'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : json_encode([]),
                'status' => 'received',
                'external_message_id' => $data['message_id'] ?? null,
                'in_reply_to' => $data['in_reply_to'] ?? null,
                'raw_headers' => $data['headers'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $message = DB::table(self::TABLE_INBOX_MESSAGES)->where('id', $messageId)->first();
            $snippet = $this->getMessageSnippet($message->body_text ?? strip_tags($message->body_html ?? ''));

            $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->first();

            DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->update([
                'last_message_at' => $now,
                'message_count' => $conversation->message_count + 1,
                'snippet' => $snippet,
                'status' => 'open',
                'updated_at' => $now,
            ]);

            $this->applyRulesInternal($conversationId, $inboxId, $messageId);

            $message = DB::table(self::TABLE_INBOX_MESSAGES)->where('id', $messageId)->first();
            $messageArray = $this->toArray($message);

            $conversationFull = $this->getConversation($conversationId);
            $messageArray['conversation'] = $conversationFull;

            return $messageArray;
        });
    }

    // ==========================================
    // CANNED RESPONSE METHODS
    // ==========================================

    public function listCannedResponses(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_INBOX_CANNED_RESPONSES);

        if (!empty($filters['inbox_id'])) {
            $query->where('inbox_id', $filters['inbox_id']);
        } else {
            $query->whereNull('inbox_id');
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('is_active', true);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('shortcut', 'like', "%{$filters['search']}%")
                    ->orWhere('body', 'like', "%{$filters['search']}%");
            });
        }

        $total = (clone $query)->count();
        $responses = $query->orderBy('name')->forPage($page, $perPage)->get();

        $items = [];
        foreach ($responses as $response) {
            $responseArray = $this->toArray($response);

            if ($response->created_by) {
                $creator = DB::table(self::TABLE_USERS)->where('id', $response->created_by)->first();
                $responseArray['creator'] = $creator ? $this->toArray($creator) : null;
            } else {
                $responseArray['creator'] = null;
            }

            $items[] = $responseArray;
        }

        return PaginatedResult::create(
            $items,
            $total,
            $perPage,
            $page
        );
    }

    public function getCannedResponseByShortcut(string $shortcut, ?int $inboxId = null): ?array
    {
        $query = DB::table(self::TABLE_INBOX_CANNED_RESPONSES)
            ->where('shortcut', $shortcut)
            ->where('is_active', true);

        if ($inboxId) {
            $query->where('inbox_id', $inboxId);
        } else {
            $query->whereNull('inbox_id');
        }

        $response = $query->first();
        return $response ? $this->toArray($response) : null;
    }

    public function createCannedResponse(array $data, int $createdByUserId): array
    {
        $now = now();

        $responseId = DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->insertGetId([
            'inbox_id' => $data['inbox_id'] ?? null,
            'name' => $data['name'],
            'shortcut' => $data['shortcut'] ?? null,
            'category' => $data['category'] ?? null,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : json_encode([]),
            'created_by' => $createdByUserId,
            'is_active' => $data['is_active'] ?? true,
            'use_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $responseId)->first();
        return $this->toArray($response);
    }

    public function updateCannedResponse(int $id, array $data): array
    {
        $response = DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $id)->first();

        if (!$response) {
            throw new \RuntimeException("Canned response not found with ID: {$id}");
        }

        $updateData = [
            'name' => $data['name'] ?? $response->name,
            'shortcut' => $data['shortcut'] ?? $response->shortcut,
            'category' => $data['category'] ?? $response->category,
            'subject' => $data['subject'] ?? $response->subject,
            'body' => $data['body'] ?? $response->body,
            'is_active' => $data['is_active'] ?? $response->is_active,
            'updated_at' => now(),
        ];

        if (isset($data['attachments'])) {
            $updateData['attachments'] = json_encode($data['attachments']);
        }

        DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $id)->first();
        return $this->toArray($updated);
    }

    public function deleteCannedResponse(int $id): void
    {
        $response = DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $id)->first();

        if (!$response) {
            throw new \RuntimeException("Canned response not found with ID: {$id}");
        }

        DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $id)->delete();
    }

    public function useCannedResponse(int $id, array $variables = []): string
    {
        $response = DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $id)->first();

        if (!$response) {
            throw new \RuntimeException("Canned response not found with ID: {$id}");
        }

        // Increment use count
        DB::table(self::TABLE_INBOX_CANNED_RESPONSES)->where('id', $id)->update([
            'use_count' => ($response->use_count ?? 0) + 1,
            'updated_at' => now(),
        ]);

        // Render template with variables
        $body = $response->body;
        foreach ($variables as $key => $value) {
            $body = str_replace("{{{$key}}}", $value, $body);
        }

        return $body;
    }

    // ==========================================
    // INBOX RULE METHODS
    // ==========================================

    public function listRules(int $inboxId): array
    {
        $rules = DB::table(self::TABLE_INBOX_RULES)
            ->where('inbox_id', $inboxId)
            ->orderBy('priority')
            ->get();

        $result = [];
        foreach ($rules as $rule) {
            $ruleArray = $this->toArray($rule);

            if ($rule->created_by) {
                $creator = DB::table(self::TABLE_USERS)->where('id', $rule->created_by)->first();
                $ruleArray['creator'] = $creator ? $this->toArray($creator) : null;
            } else {
                $ruleArray['creator'] = null;
            }

            $result[] = $ruleArray;
        }

        return $result;
    }

    public function createRule(int $inboxId, array $data, int $createdByUserId): array
    {
        $now = now();

        $ruleId = DB::table(self::TABLE_INBOX_RULES)->insertGetId([
            'inbox_id' => $inboxId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'conditions' => json_encode($data['conditions']),
            'condition_match' => $data['condition_match'] ?? 'all',
            'actions' => json_encode($data['actions']),
            'is_active' => $data['is_active'] ?? true,
            'stop_processing' => $data['stop_processing'] ?? false,
            'created_by' => $createdByUserId,
            'execution_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rule = DB::table(self::TABLE_INBOX_RULES)->where('id', $ruleId)->first();
        return $this->toArray($rule);
    }

    public function updateRule(int $id, array $data): array
    {
        $rule = DB::table(self::TABLE_INBOX_RULES)->where('id', $id)->first();

        if (!$rule) {
            throw new \RuntimeException("Rule not found with ID: {$id}");
        }

        $updateData = [
            'name' => $data['name'] ?? $rule->name,
            'description' => $data['description'] ?? $rule->description,
            'priority' => $data['priority'] ?? $rule->priority,
            'condition_match' => $data['condition_match'] ?? $rule->condition_match,
            'is_active' => $data['is_active'] ?? $rule->is_active,
            'stop_processing' => $data['stop_processing'] ?? $rule->stop_processing,
            'updated_at' => now(),
        ];

        if (isset($data['conditions'])) {
            $updateData['conditions'] = json_encode($data['conditions']);
        }

        if (isset($data['actions'])) {
            $updateData['actions'] = json_encode($data['actions']);
        }

        DB::table(self::TABLE_INBOX_RULES)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE_INBOX_RULES)->where('id', $id)->first();
        return $this->toArray($updated);
    }

    public function deleteRule(int $id): void
    {
        $rule = DB::table(self::TABLE_INBOX_RULES)->where('id', $id)->first();

        if (!$rule) {
            throw new \RuntimeException("Rule not found with ID: {$id}");
        }

        DB::table(self::TABLE_INBOX_RULES)->where('id', $id)->delete();
    }

    public function reorderRules(int $inboxId, array $ruleIds): void
    {
        foreach ($ruleIds as $priority => $ruleId) {
            DB::table(self::TABLE_INBOX_RULES)
                ->where('id', $ruleId)
                ->where('inbox_id', $inboxId)
                ->update([
                    'priority' => $priority,
                    'updated_at' => now(),
                ]);
        }
    }

    // ==========================================
    // ANALYTICS METHODS
    // ==========================================

    public function getInboxMetrics(int $inboxId, string $period = 'week'): array
    {
        $dateFrom = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            default => now()->subWeek(),
        };

        $conversationsQuery = DB::table(self::TABLE_INBOX_CONVERSATIONS)
            ->where('inbox_id', $inboxId)
            ->where('created_at', '>=', $dateFrom);

        $resolvedQuery = (clone $conversationsQuery)->whereNotNull('resolved_at');

        $totalCount = (clone $conversationsQuery)->count();
        $resolvedCount = $resolvedQuery->count();

        return [
            'period' => $period,
            'date_from' => $dateFrom->toIso8601String(),
            'total_conversations' => $totalCount,
            'resolved_conversations' => $resolvedCount,
            'resolution_rate' => $totalCount > 0
                ? round(($resolvedCount / $totalCount) * 100, 1)
                : 0,
            'avg_response_time_seconds' => (clone $conversationsQuery)
                ->whereNotNull('response_time_seconds')
                ->avg('response_time_seconds'),
            'avg_resolution_time_seconds' => DB::table(self::TABLE_INBOX_CONVERSATIONS)
                ->where('inbox_id', $inboxId)
                ->where('created_at', '>=', $dateFrom)
                ->whereNotNull('resolved_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) as avg')
                ->value('avg'),
            'by_status' => [
                'open' => (clone $conversationsQuery)->where('status', 'open')->count(),
                'pending' => (clone $conversationsQuery)->where('status', 'pending')->count(),
                'resolved' => (clone $conversationsQuery)->where('status', 'resolved')->count(),
                'closed' => (clone $conversationsQuery)->where('status', 'closed')->count(),
            ],
            'by_priority' => [
                'urgent' => (clone $conversationsQuery)->where('priority', 'urgent')->count(),
                'high' => (clone $conversationsQuery)->where('priority', 'high')->count(),
                'normal' => (clone $conversationsQuery)->where('priority', 'normal')->count(),
                'low' => (clone $conversationsQuery)->where('priority', 'low')->count(),
            ],
            'by_channel' => DB::table(self::TABLE_INBOX_CONVERSATIONS)
                ->where('inbox_id', $inboxId)
                ->where('created_at', '>=', $dateFrom)
                ->selectRaw('channel, count(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
        ];
    }

    public function getAgentPerformance(int $inboxId, string $period = 'week'): array
    {
        $dateFrom = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek(),
        };

        $members = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
            ->where('inbox_id', $inboxId)
            ->get();

        $performance = [];

        foreach ($members as $member) {
            $conversationsQuery = DB::table(self::TABLE_INBOX_CONVERSATIONS)
                ->where('inbox_id', $inboxId)
                ->where('assigned_to', $member->user_id)
                ->where('created_at', '>=', $dateFrom);

            $resolvedQuery = (clone $conversationsQuery)->whereNotNull('resolved_at');

            $repliesCount = DB::table(self::TABLE_INBOX_MESSAGES)
                ->whereExists(function ($q) use ($inboxId, $member) {
                    $q->select(DB::raw(1))
                        ->from(self::TABLE_INBOX_CONVERSATIONS)
                        ->whereColumn(self::TABLE_INBOX_CONVERSATIONS . '.id', self::TABLE_INBOX_MESSAGES . '.conversation_id')
                        ->where(self::TABLE_INBOX_CONVERSATIONS . '.inbox_id', $inboxId)
                        ->where(self::TABLE_INBOX_CONVERSATIONS . '.assigned_to', $member->user_id);
                })
                ->where('sent_by', $member->user_id)
                ->where('created_at', '>=', $dateFrom)
                ->count();

            $user = DB::table(self::TABLE_USERS)->where('id', $member->user_id)->first();

            $performance[] = [
                'user_id' => $member->user_id,
                'user_name' => $user?->name,
                'current_active' => $member->current_active_count ?? 0,
                'conversations_handled' => $conversationsQuery->count(),
                'resolved' => $resolvedQuery->count(),
                'replies_sent' => $repliesCount,
                'avg_response_time' => (clone $conversationsQuery)
                    ->whereNotNull('response_time_seconds')
                    ->avg('response_time_seconds'),
            ];
        }

        return [
            'period' => $period,
            'agents' => $performance,
        ];
    }

    public function getTagDistribution(int $inboxId): array
    {
        $conversations = DB::table(self::TABLE_INBOX_CONVERSATIONS)
            ->where('inbox_id', $inboxId)
            ->whereNotNull('tags')
            ->get();

        $tagCounts = [];

        foreach ($conversations as $conversation) {
            $tags = is_string($conversation->tags) ? json_decode($conversation->tags, true) : (array)$conversation->tags;

            foreach ($tags as $tag) {
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

    private function applyRulesInternal(int $conversationId, int $inboxId, ?int $messageId = null): void
    {
        $rules = DB::table(self::TABLE_INBOX_RULES)
            ->where('inbox_id', $inboxId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->first();
        $message = $messageId ? DB::table(self::TABLE_INBOX_MESSAGES)->where('id', $messageId)->first() : null;

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $conversation, $message)) {
                $this->executeRule($rule, $conversation);

                // Increment execution count
                DB::table(self::TABLE_INBOX_RULES)->where('id', $rule->id)->update([
                    'execution_count' => ($rule->execution_count ?? 0) + 1,
                    'last_executed_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($rule->stop_processing) {
                    break;
                }
            }
        }
    }

    private function ruleMatches(stdClass $rule, stdClass $conversation, ?stdClass $message): bool
    {
        // Simplified rule matching - implement full logic based on your requirements
        $conditions = is_string($rule->conditions) ? json_decode($rule->conditions, true) : (array)$rule->conditions;
        $conditionMatch = $rule->condition_match ?? 'all';

        if (empty($conditions)) {
            return true;
        }

        // This is a simplified implementation
        // You should implement the full condition matching logic based on your requirements
        return true;
    }

    private function executeRule(stdClass $rule, stdClass $conversation): void
    {
        // Simplified rule execution - implement full logic based on your requirements
        $actions = is_string($rule->actions) ? json_decode($rule->actions, true) : (array)$rule->actions;

        foreach ($actions as $action) {
            // Execute actions based on type
            // This is a simplified implementation
        }
    }

    private function sendAutoReplyInternal(int $conversationId, stdClass $inbox): void
    {
        $settings = is_string($inbox->settings) ? json_decode($inbox->settings, true) : (array)$inbox->settings;
        $message = $settings['auto_reply']['message'] ?? null;

        if (!$message) {
            return;
        }

        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->first();
        $now = now();

        DB::table(self::TABLE_INBOX_MESSAGES)->insert([
            'conversation_id' => $conversationId,
            'direction' => 'outbound',
            'type' => 'auto_reply',
            'from_email' => $inbox->email,
            'from_name' => $inbox->name,
            'to_emails' => json_encode([$conversation->contact_email]),
            'subject' => "Re: {$conversation->subject}",
            'body_text' => strip_tags($message),
            'body_html' => $message,
            'status' => 'sent',
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->update([
            'message_count' => $conversation->message_count + 1,
            'updated_at' => $now,
        ]);
    }

    private function getNextAssigneeId(stdClass $inbox): ?int
    {
        $assignmentMethod = $inbox->assignment_method ?? 'manual';

        if ($assignmentMethod === 'manual') {
            return $inbox->default_assignee_id;
        }

        $members = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
            ->where('inbox_id', $inbox->id)
            ->where('can_reply', true)
            ->get();

        if ($members->isEmpty()) {
            return $inbox->default_assignee_id;
        }

        if ($assignmentMethod === 'round_robin') {
            // Get member with oldest last assignment
            $memberUserIds = $members->pluck('user_id')->toArray();
            $user = DB::table(self::TABLE_USERS)
                ->whereIn('id', $memberUserIds)
                ->orderBy('last_assignment_at', 'asc')
                ->first();

            return $user?->id;
        }

        if ($assignmentMethod === 'load_balanced') {
            // Get member with lowest active conversation count
            $member = $members
                ->filter(function ($m) {
                    return $m->active_conversation_limit === null
                        || ($m->current_active_count ?? 0) < $m->active_conversation_limit;
                })
                ->sortBy('current_active_count')
                ->first();

            return $member?->user_id;
        }

        return $inbox->default_assignee_id;
    }

    private function assignConversationInternal(int $conversationId, ?int $userId, int $inboxId): void
    {
        $conversation = DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->first();

        // Update old assignee's count
        if ($conversation->assigned_to) {
            $this->decrementMemberActiveCount($inboxId, $conversation->assigned_to);
        }

        // Update new assignee's count
        if ($userId) {
            $this->incrementMemberActiveCount($inboxId, $userId);
        }

        DB::table(self::TABLE_INBOX_CONVERSATIONS)->where('id', $conversationId)->update([
            'assigned_to' => $userId,
            'updated_at' => now(),
        ]);
    }

    private function incrementMemberActiveCount(int $inboxId, int $userId): void
    {
        $member = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
            ->where('inbox_id', $inboxId)
            ->where('user_id', $userId)
            ->first();

        if ($member) {
            DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
                ->where('inbox_id', $inboxId)
                ->where('user_id', $userId)
                ->update([
                    'current_active_count' => ($member->current_active_count ?? 0) + 1,
                    'updated_at' => now(),
                ]);
        }
    }

    private function decrementMemberActiveCount(int $inboxId, int $userId): void
    {
        $member = DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
            ->where('inbox_id', $inboxId)
            ->where('user_id', $userId)
            ->first();

        if ($member && ($member->current_active_count ?? 0) > 0) {
            DB::table(self::TABLE_SHARED_INBOX_MEMBERS)
                ->where('inbox_id', $inboxId)
                ->where('user_id', $userId)
                ->update([
                    'current_active_count' => $member->current_active_count - 1,
                    'updated_at' => now(),
                ]);
        }
    }

    private function getMessageSnippet(string $text, int $length = 100): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    private function toArray(stdClass $object): array
    {
        $array = (array) $object;

        // Decode JSON fields
        $jsonFields = ['tags', 'custom_fields', 'settings', 'to_emails', 'cc_emails', 'bcc_emails',
                       'attachments', 'conditions', 'actions'];

        foreach ($jsonFields as $field) {
            if (isset($array[$field]) && is_string($array[$field])) {
                $decoded = json_decode($array[$field], true);
                $array[$field] = $decoded ?? $array[$field];
            }
        }

        return $array;
    }
}
