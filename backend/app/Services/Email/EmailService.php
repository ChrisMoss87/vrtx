<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Domain\Email\Entities\EmailAccount;
use App\Domain\Email\Entities\EmailMessage;
use App\Domain\Email\Entities\EmailTemplate;
use App\Domain\Email\Repositories\EmailAccountRepositoryInterface;
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailService
{
    protected ?ImapConnection $imapConnection = null;
    protected ?SmtpConnection $smtpConnection = null;

    public function __construct(
        protected EmailAccountRepositoryInterface $accountRepository,
        protected EmailMessageRepositoryInterface $messageRepository,
        protected EmailTemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * Connect to an email account.
     */
    public function connect(EmailAccount $account): bool
    {
        try {
            if ($account->getProvider() === 'smtp_only') {
                $this->smtpConnection = new SmtpConnection($account);
                return $this->smtpConnection->connect();
            }

            $this->imapConnection = new ImapConnection($account);
            $this->smtpConnection = new SmtpConnection($account);

            return $this->imapConnection->connect() && $this->smtpConnection->connect();
        } catch (\Exception $e) {
            Log::error('Email connection failed', [
                'account_id' => $account->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Disconnect from current account.
     */
    public function disconnect(): void
    {
        $this->imapConnection?->disconnect();
        $this->smtpConnection?->disconnect();
        $this->imapConnection = null;
        $this->smtpConnection = null;
    }

    /**
     * Fetch new emails from account.
     */
    public function fetchNewEmails(EmailAccount $account, ?string $folder = null): Collection
    {
        if (!$this->imapConnection) {
            $this->connect($account);
        }

        $folders = $folder ? [$folder] : $account->getSyncFolders();
        $messages = collect();

        foreach ($folders as $syncFolder) {
            $newMessages = $this->imapConnection->fetchMessages(
                $syncFolder,
                $account->getLastSyncUid()
            );

            foreach ($newMessages as $message) {
                $emailMessage = $this->storeInboundEmail($account, $message, $syncFolder);
                $messages->push($emailMessage);
            }
        }

        // Update last sync
        $account->updateLastSync(now(), $this->imapConnection->getLastUid());
        $this->accountRepository->save($account);

        return $messages;
    }

    /**
     * Send an email.
     */
    public function send(EmailMessage $message): bool
    {
        if (!$this->smtpConnection) {
            $this->connect($message->account);
        }

        try {
            // Generate tracking if not present
            if (!$message->tracking_id) {
                $message->tracking_id = EmailMessage::generateTrackingId();
            }

            // Inject tracking pixel if HTML body exists
            $bodyHtml = $this->injectTrackingPixel($message);

            $result = $this->smtpConnection->send([
                'from' => [
                    'email' => $message->from_email,
                    'name' => $message->from_name,
                ],
                'to' => $message->to_emails,
                'cc' => $message->cc_emails,
                'bcc' => $message->bcc_emails,
                'reply_to' => $message->reply_to,
                'subject' => $message->subject,
                'html' => $bodyHtml,
                'text' => $message->body_text,
                'headers' => $message->headers ?? [],
            ]);

            if ($result) {
                $message->update([
                    'status' => EmailMessage::STATUS_SENT,
                    'sent_at' => now(),
                    'message_id' => $result['message_id'] ?? null,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $message->update([
                'status' => EmailMessage::STATUS_FAILED,
                'failed_reason' => $e->getMessage(),
            ]);
            Log::error('Email send failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Queue an email for sending.
     */
    public function queue(EmailMessage $message, ?\DateTimeInterface $sendAt = null): EmailMessage
    {
        $message->update([
            'status' => EmailMessage::STATUS_QUEUED,
            'scheduled_at' => $sendAt,
        ]);

        return $message;
    }

    /**
     * Create a draft email.
     */
    public function createDraft(EmailAccount $account, array $data): EmailMessage
    {
        return EmailMessage::create([
            'account_id' => $account->id,
            'user_id' => $data['user_id'] ?? auth()->id(),
            'direction' => EmailMessage::DIRECTION_OUTBOUND,
            'status' => EmailMessage::STATUS_DRAFT,
            'from_email' => $account->email_address,
            'from_name' => $account->name,
            'to_emails' => $data['to'] ?? [],
            'cc_emails' => $data['cc'] ?? [],
            'bcc_emails' => $data['bcc'] ?? [],
            'reply_to' => $data['reply_to'] ?? null,
            'subject' => $data['subject'] ?? '',
            'body_html' => $data['body_html'] ?? '',
            'body_text' => $data['body_text'] ?? null,
            'thread_id' => $data['thread_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'linked_record_type' => $data['linked_record_type'] ?? null,
            'linked_record_id' => $data['linked_record_id'] ?? null,
            'template_id' => $data['template_id'] ?? null,
        ]);
    }

    /**
     * Create a reply to an email.
     */
    public function createReply(EmailMessage $originalMessage, array $data): EmailMessage
    {
        $subject = $originalMessage->subject;
        if (!Str::startsWith(strtolower($subject), 're:')) {
            $subject = 'Re: ' . $subject;
        }

        return $this->createDraft($originalMessage->account, [
            'to' => [$originalMessage->from_email],
            'subject' => $subject,
            'thread_id' => $originalMessage->thread_id ?? EmailMessage::generateThreadId(),
            'parent_id' => $originalMessage->id,
            'linked_record_type' => $originalMessage->linked_record_type,
            'linked_record_id' => $originalMessage->linked_record_id,
            ...$data,
        ]);
    }

    /**
     * Create a forward of an email.
     */
    public function createForward(EmailMessage $originalMessage, array $data): EmailMessage
    {
        $subject = $originalMessage->subject;
        if (!Str::startsWith(strtolower($subject), 'fwd:')) {
            $subject = 'Fwd: ' . $subject;
        }

        // Build forwarded body
        $forwardedBody = $this->buildForwardedBody($originalMessage);

        return $this->createDraft($originalMessage->account, [
            'subject' => $subject,
            'body_html' => ($data['body_html'] ?? '') . $forwardedBody,
            'linked_record_type' => $originalMessage->linked_record_type,
            'linked_record_id' => $originalMessage->linked_record_id,
            ...$data,
        ]);
    }

    /**
     * Send email using a template.
     */
    public function sendFromTemplate(
        EmailAccount $account,
        EmailTemplate $template,
        array $recipients,
        array $data,
        ?array $linkedRecord = null
    ): EmailMessage {
        // Render template
        $rendered = $template->render($data);

        // Create message
        $message = EmailMessage::create([
            'account_id' => $account->id,
            'user_id' => auth()->id(),
            'direction' => EmailMessage::DIRECTION_OUTBOUND,
            'status' => EmailMessage::STATUS_DRAFT,
            'from_email' => $account->email_address,
            'from_name' => $account->name,
            'to_emails' => $recipients['to'] ?? [],
            'cc_emails' => $recipients['cc'] ?? [],
            'bcc_emails' => $recipients['bcc'] ?? [],
            'subject' => $rendered['subject'],
            'body_html' => $rendered['body_html'],
            'body_text' => $rendered['body_text'],
            'template_id' => $template->id,
            'linked_record_type' => $linkedRecord['type'] ?? null,
            'linked_record_id' => $linkedRecord['id'] ?? null,
        ]);

        // Record template usage
        $template->recordUsage();

        return $message;
    }

    /**
     * Move email to folder.
     */
    public function moveToFolder(EmailMessage $message, string $folder): bool
    {
        if (!$this->imapConnection) {
            $this->connect($message->account);
        }

        $result = $this->imapConnection->moveMessage($message->message_id, $folder);

        if ($result) {
            $message->update(['folder' => $folder]);
        }

        return $result;
    }

    /**
     * Delete an email (move to trash).
     */
    public function trash(EmailMessage $message): bool
    {
        return $this->moveToFolder($message, 'Trash');
    }

    /**
     * Permanently delete an email.
     */
    public function permanentDelete(EmailMessage $message): bool
    {
        if (!$this->imapConnection) {
            $this->connect($message->account);
        }

        $result = $this->imapConnection->deleteMessage($message->message_id);

        if ($result) {
            $message->forceDelete();
        }

        return $result;
    }

    /**
     * Get folder list for account.
     */
    public function getFolders(EmailAccount $account): array
    {
        if (!$this->imapConnection) {
            $this->connect($account);
        }

        return $this->imapConnection->getFolders();
    }

    /**
     * Search emails.
     */
    public function search(EmailAccount $account, string $query, ?string $folder = null): Collection
    {
        if (!$this->imapConnection) {
            $this->connect($account);
        }

        return $this->imapConnection->search($query, $folder);
    }

    /**
     * Store an inbound email.
     */
    protected function storeInboundEmail(
        EmailAccount $account,
        array $message,
        string $folder
    ): EmailMessage {
        // Try to find existing thread
        $threadId = $this->findOrCreateThreadId($message);

        return EmailMessage::create([
            'account_id' => $account->id,
            'user_id' => $account->user_id,
            'message_id' => $message['message_id'] ?? null,
            'thread_id' => $threadId,
            'direction' => EmailMessage::DIRECTION_INBOUND,
            'status' => EmailMessage::STATUS_RECEIVED,
            'from_email' => $message['from']['email'] ?? '',
            'from_name' => $message['from']['name'] ?? null,
            'to_emails' => $message['to'] ?? [],
            'cc_emails' => $message['cc'] ?? [],
            'bcc_emails' => [],
            'reply_to' => $message['reply_to'] ?? null,
            'subject' => $message['subject'] ?? '(No Subject)',
            'body_html' => $message['html'] ?? null,
            'body_text' => $message['text'] ?? null,
            'headers' => $message['headers'] ?? null,
            'folder' => $folder,
            'is_read' => false,
            'has_attachments' => !empty($message['attachments']),
            'attachments' => $message['attachments'] ?? [],
            'received_at' => $message['date'] ?? now(),
        ]);
    }

    /**
     * Find or create thread ID based on message headers.
     */
    protected function findOrCreateThreadId(array $message): string
    {
        $inReplyTo = $message['headers']['in-reply-to'] ?? null;
        $references = $message['headers']['references'] ?? null;

        if ($inReplyTo) {
            // Look for existing message with this message_id
            $existing = EmailMessage::where('message_id', $inReplyTo)->first();
            if ($existing && $existing->thread_id) {
                return $existing->thread_id;
            }
        }

        if ($references) {
            // Check reference chain
            $refList = is_array($references) ? $references : explode(' ', $references);
            foreach ($refList as $ref) {
                $existing = EmailMessage::where('message_id', trim($ref))->first();
                if ($existing && $existing->thread_id) {
                    return $existing->thread_id;
                }
            }
        }

        return EmailMessage::generateThreadId();
    }

    /**
     * Inject tracking pixel into HTML body.
     */
    protected function injectTrackingPixel(EmailMessage $message): string
    {
        if (!$message->body_html || !$message->tracking_id) {
            return $message->body_html ?? '';
        }

        $trackingUrl = route('email.track.open', ['id' => $message->tracking_id]);
        $pixel = sprintf(
            '<img src="%s" width="1" height="1" style="display:none;" alt="" />',
            $trackingUrl
        );

        // Insert before closing body tag or at the end
        if (stripos($message->body_html, '</body>') !== false) {
            return str_ireplace('</body>', $pixel . '</body>', $message->body_html);
        }

        return $message->body_html . $pixel;
    }

    /**
     * Build forwarded email body.
     */
    protected function buildForwardedBody(EmailMessage $original): string
    {
        $date = $original->received_at?->format('D, M j, Y \a\t g:i A') ?? 'Unknown';

        return sprintf(
            '<br><br>---------- Forwarded message ---------<br>' .
            '<b>From:</b> %s &lt;%s&gt;<br>' .
            '<b>Date:</b> %s<br>' .
            '<b>Subject:</b> %s<br>' .
            '<b>To:</b> %s<br><br>' .
            '%s',
            htmlspecialchars($original->from_name ?? ''),
            htmlspecialchars($original->from_email),
            $date,
            htmlspecialchars($original->subject ?? ''),
            htmlspecialchars($original->formatted_to),
            $original->body_html ?? nl2br(htmlspecialchars($original->body_text ?? ''))
        );
    }
}
