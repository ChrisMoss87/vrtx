<?php

namespace App\Services\Inbox;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Support\Facades\DB;

class InboxService
{
    public function syncInbox(SharedInbox $inbox): array
    {
        $imapService = new ImapService($inbox);
        $messages = $imapService->fetchNewMessages();
        $imapService->disconnect();

        // Process rules for new conversations
        foreach ($messages as $message) {
            $this->processRulesForMessage($message);
        }

        return [
            'synced_count' => count($messages),
            'messages' => $messages,
        ];
    }

    public function sendReply(InboxConversation $conversation, string $body, array $options = []): InboxMessage
    {
        $smtpService = new SmtpService($conversation->inbox);
        return $smtpService->sendReply($conversation, $body, $options);
    }

    public function addNote(InboxConversation $conversation, string $body, ?int $userId = null): InboxMessage
    {
        return DB::table('inbox_messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'note',
            'body_text' => strip_tags($body),
            'body_html' => $body,
            'status' => 'sent',
            'sent_by' => $userId ?? auth()->id(),
            'sent_at' => now(),
        ]);
    }

    public function assignConversation(InboxConversation $conversation, ?int $userId): InboxConversation
    {
        $conversation->assignTo($userId);
        return $conversation->fresh();
    }

    public function bulkAssign(array $conversationIds, int $userId): int
    {
        $count = 0;

        foreach ($conversationIds as $id) {
            $conversation = DB::table('inbox_conversations')->where('id', $id)->first();
            if ($conversation) {
                $conversation->assignTo($userId);
                $count++;
            }
        }

        return $count;
    }

    public function resolveConversation(InboxConversation $conversation): InboxConversation
    {
        $conversation->resolve();
        return $conversation->fresh();
    }

    public function reopenConversation(InboxConversation $conversation): InboxConversation
    {
        $conversation->reopen();
        return $conversation->fresh();
    }

    public function closeConversation(InboxConversation $conversation): InboxConversation
    {
        $conversation->update(['status' => 'closed']);
        return $conversation->fresh();
    }

    public function markAsSpam(InboxConversation $conversation): InboxConversation
    {
        $conversation->markAsSpam();
        return $conversation->fresh();
    }

    public function toggleStar(InboxConversation $conversation): InboxConversation
    {
        $conversation->toggleStar();
        return $conversation->fresh();
    }

    public function updatePriority(InboxConversation $conversation, string $priority): InboxConversation
    {
        $conversation->update(['priority' => $priority]);
        return $conversation->fresh();
    }

    public function addTag(InboxConversation $conversation, string $tag): InboxConversation
    {
        $conversation->addTag($tag);
        return $conversation->fresh();
    }

    public function removeTag(InboxConversation $conversation, string $tag): InboxConversation
    {
        $conversation->removeTag($tag);
        return $conversation->fresh();
    }

    public function mergeConversations(InboxConversation $primary, array $secondaryIds): InboxConversation
    {
        DB::transaction(function () use ($primary, $secondaryIds) {
            foreach ($secondaryIds as $id) {
                $secondary = DB::table('inbox_conversations')->where('id', $id)->first();
                if (!$secondary || $secondary->id === $primary->id) {
                    continue;
                }

                // Move messages to primary conversation
                DB::table('inbox_messages')->where('conversation_id', $secondary->id)
                    ->update(['conversation_id' => $primary->id]);

                // Merge tags
                foreach ($secondary->tags ?? [] as $tag) {
                    $primary->addTag($tag);
                }

                // Update message count
                $primary->update([
                    'message_count' => $primary->messages()->count(),
                ]);

                // Delete secondary conversation
                $secondary->delete();
            }
        });

        return $primary->fresh();
    }

    protected function processRulesForMessage(InboxMessage $message): void
    {
        $conversation = $message->conversation;
        $inbox = $conversation->inbox;

        $rules = $inbox->rules()
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

        // Send auto-reply if enabled
        if ($message->isInbound() && $message->type === 'original') {
            $smtpService = new SmtpService($inbox);
            $smtpService->sendAutoReply($conversation);
        }
    }

    public function getInboxStats(SharedInbox $inbox): array
    {
        $baseQuery = DB::table('inbox_conversations')->where('inbox_id', $inbox->id);

        return [
            'total' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->open()->notSpam()->count(),
            'pending' => (clone $baseQuery)->pending()->notSpam()->count(),
            'resolved' => (clone $baseQuery)->resolved()->notSpam()->count(),
            'unassigned' => (clone $baseQuery)->open()->unassigned()->notSpam()->count(),
            'spam' => (clone $baseQuery)->where('is_spam', true)->count(),
            'starred' => (clone $baseQuery)->starred()->count(),
            'avg_response_time' => (clone $baseQuery)
                ->whereNotNull('response_time_seconds')
                ->avg('response_time_seconds'),
        ];
    }

    public function getUserStats(SharedInbox $inbox, int $userId): array
    {
        $baseQuery = DB::table('inbox_conversations')->where('inbox_id', $inbox->id)
            ->where('assigned_to', $userId);

        return [
            'assigned' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->open()->count(),
            'resolved_today' => (clone $baseQuery)
                ->whereDate('resolved_at', today())
                ->count(),
            'avg_response_time' => (clone $baseQuery)
                ->whereNotNull('response_time_seconds')
                ->avg('response_time_seconds'),
        ];
    }

    public function verifyInboxConnection(SharedInbox $inbox): array
    {
        $results = [
            'imap' => ['success' => false, 'error' => 'Not configured'],
            'smtp' => ['success' => false, 'error' => 'Not configured'],
        ];

        if ($inbox->imap_host) {
            $imapService = new ImapService($inbox);
            $results['imap'] = $imapService->verifyConnection();
        }

        if ($inbox->smtp_host) {
            $smtpService = new SmtpService($inbox);
            $results['smtp'] = $smtpService->verifyConnection();
        }

        $isConnected = ($results['imap']['success'] ?? false) || ($results['smtp']['success'] ?? false);
        $inbox->update(['is_connected' => $isConnected]);

        return $results;
    }
}
