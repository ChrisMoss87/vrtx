<?php

namespace App\Services\Inbox;

use App\Models\SharedInbox;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Message;
use Illuminate\Support\Facades\Log;

class ImapService
{
    protected SharedInbox $inbox;
    protected ?Client $client = null;

    public function __construct(SharedInbox $inbox)
    {
        $this->inbox = $inbox;
    }

    public function connect(): bool
    {
        try {
            $clientManager = new ClientManager();

            $this->client = $clientManager->make([
                'host' => $this->inbox->imap_host,
                'port' => $this->inbox->imap_port,
                'encryption' => $this->inbox->imap_encryption,
                'validate_cert' => true,
                'username' => $this->inbox->username,
                'password' => $this->inbox->password,
                'protocol' => 'imap',
            ]);

            $this->client->connect();

            $this->inbox->update(['is_connected' => true]);

            return true;
        } catch (\Exception $e) {
            Log::error('IMAP connection failed', [
                'inbox_id' => $this->inbox->id,
                'error' => $e->getMessage(),
            ]);

            $this->inbox->update(['is_connected' => false]);

            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
    }

    public function fetchNewMessages(): array
    {
        if (!$this->client) {
            if (!$this->connect()) {
                return [];
            }
        }

        $messages = [];

        try {
            $folder = $this->client->getFolder('INBOX');

            // Fetch unseen messages
            $imapMessages = $folder->query()
                ->unseen()
                ->since(now()->subDays(7))
                ->get();

            foreach ($imapMessages as $imapMessage) {
                $message = $this->processMessage($imapMessage);
                if ($message) {
                    $messages[] = $message;
                }
            }

            $this->inbox->update(['last_synced_at' => now()]);
        } catch (\Exception $e) {
            Log::error('IMAP fetch failed', [
                'inbox_id' => $this->inbox->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $messages;
    }

    protected function processMessage(Message $imapMessage): ?InboxMessage
    {
        $messageId = $imapMessage->getMessageId()?->toString();

        // Check if message already exists
        $existing = InboxMessage::where('external_message_id', $messageId)->first();
        if ($existing) {
            return null;
        }

        // Find or create conversation
        $inReplyTo = $imapMessage->getInReplyTo()?->toString();
        $threadId = $imapMessage->getReferences()?->toString() ?? $inReplyTo ?? $messageId;

        $conversation = $this->findOrCreateConversation($imapMessage, $threadId);

        // Create message
        $fromAddress = $imapMessage->getFrom()[0] ?? null;
        $toAddresses = collect($imapMessage->getTo())->map(fn($addr) => $addr->mail)->toArray();
        $ccAddresses = collect($imapMessage->getCc())->map(fn($addr) => $addr->mail)->toArray();

        $message = InboxMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => $inReplyTo ? 'reply' : 'original',
            'from_email' => $fromAddress?->mail,
            'from_name' => $fromAddress?->personal,
            'to_emails' => $toAddresses,
            'cc_emails' => $ccAddresses,
            'subject' => $imapMessage->getSubject()?->toString(),
            'body_text' => $imapMessage->getTextBody(),
            'body_html' => $imapMessage->getHTMLBody(),
            'attachments' => $this->processAttachments($imapMessage),
            'status' => 'delivered',
            'external_message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'raw_headers' => $imapMessage->getHeader()?->raw,
            'sent_at' => $imapMessage->getDate()?->toDateTime(),
            'delivered_at' => now(),
        ]);

        // Update conversation
        $conversation->update([
            'snippet' => $message->getSnippet(),
            'last_message_at' => now(),
            'message_count' => $conversation->message_count + 1,
        ]);

        // Mark as seen
        $imapMessage->setFlag('Seen');

        return $message;
    }

    protected function findOrCreateConversation(Message $imapMessage, string $threadId): InboxConversation
    {
        // Try to find existing conversation by thread ID
        $conversation = InboxConversation::where('inbox_id', $this->inbox->id)
            ->where('external_thread_id', $threadId)
            ->first();

        if ($conversation) {
            // Reopen if resolved
            if ($conversation->isResolved()) {
                $conversation->reopen();
            }
            return $conversation;
        }

        // Create new conversation
        $fromAddress = $imapMessage->getFrom()[0] ?? null;

        $conversation = InboxConversation::create([
            'inbox_id' => $this->inbox->id,
            'subject' => $imapMessage->getSubject()?->toString() ?? '(No Subject)',
            'status' => 'open',
            'priority' => 'normal',
            'channel' => 'email',
            'contact_email' => $fromAddress?->mail,
            'contact_name' => $fromAddress?->personal,
            'external_thread_id' => $threadId,
            'last_message_at' => now(),
        ]);

        // Auto-assign
        $assignee = $this->inbox->getNextAssignee();
        if ($assignee) {
            $conversation->assignTo($assignee->id);
        }

        return $conversation;
    }

    protected function processAttachments(Message $imapMessage): array
    {
        $attachments = [];

        foreach ($imapMessage->getAttachments() as $attachment) {
            $attachments[] = [
                'name' => $attachment->getName(),
                'mime_type' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
                // In production, save to storage and store path
            ];
        }

        return $attachments;
    }

    public function verifyConnection(): array
    {
        try {
            if ($this->connect()) {
                $this->disconnect();
                return ['success' => true];
            }

            return ['success' => false, 'error' => 'Connection failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
