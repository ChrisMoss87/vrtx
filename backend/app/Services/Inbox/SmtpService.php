<?php

namespace App\Services\Inbox;

use App\Models\SharedInbox;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message as MailMessage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SmtpService
{
    protected SharedInbox $inbox;

    public function __construct(SharedInbox $inbox)
    {
        $this->inbox = $inbox;
    }

    public function sendReply(InboxConversation $conversation, string $body, array $options = []): ?InboxMessage
    {
        $toEmails = $options['to'] ?? [$conversation->contact_email];
        $ccEmails = $options['cc'] ?? [];
        $bccEmails = $options['bcc'] ?? [];
        $subject = $options['subject'] ?? 'Re: ' . $conversation->subject;
        $attachments = $options['attachments'] ?? [];
        $userId = $options['user_id'] ?? auth()->id();

        // Get the last inbound message to reply to
        $lastInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->orderBy('created_at', 'desc')
            ->first();

        // Create message record first
        $message = InboxMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'reply',
            'from_email' => $this->inbox->email,
            'from_name' => $this->inbox->name,
            'to_emails' => $toEmails,
            'cc_emails' => $ccEmails,
            'bcc_emails' => $bccEmails,
            'subject' => $subject,
            'body_text' => strip_tags($body),
            'body_html' => $body,
            'attachments' => $this->formatAttachments($attachments),
            'status' => 'queued',
            'sent_by' => $userId,
            'in_reply_to' => $lastInbound?->external_message_id,
        ]);

        try {
            // Send email
            $this->sendEmail($message, $conversation, $lastInbound);

            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Update conversation
            $conversation->update([
                'snippet' => $message->getSnippet(),
                'last_message_at' => now(),
                'message_count' => $conversation->message_count + 1,
            ]);

            // Track first response time
            if (!$conversation->first_response_at) {
                $conversation->update(['first_response_at' => now()]);
                $conversation->calculateResponseTime();
            }

            return $message;
        } catch (\Exception $e) {
            Log::error('SMTP send failed', [
                'inbox_id' => $this->inbox->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $message->update([
                'status' => 'failed',
            ]);

            return $message;
        }
    }

    protected function sendEmail(InboxMessage $message, InboxConversation $conversation, ?InboxMessage $inReplyTo): void
    {
        // Configure SMTP transport
        $transport = new EsmtpTransport(
            $this->inbox->smtp_host,
            $this->inbox->smtp_port,
            $this->inbox->smtp_encryption === 'tls'
        );
        $transport->setUsername($this->inbox->username);
        $transport->setPassword($this->inbox->password);

        // Build email body with signature
        $body = $message->body_html;
        $signature = $this->inbox->getSignature();
        if ($signature) {
            $body .= "\n\n" . $signature;
        }

        // Send using configured SMTP
        Mail::mailer('smtp')->send([], [], function (MailMessage $mail) use ($message, $conversation, $inReplyTo, $body) {
            $mail->from($this->inbox->email, $this->inbox->name)
                ->to($message->to_emails)
                ->subject($message->subject)
                ->html($body);

            if (!empty($message->cc_emails)) {
                $mail->cc($message->cc_emails);
            }

            if (!empty($message->bcc_emails)) {
                $mail->bcc($message->bcc_emails);
            }

            // Set reply headers
            if ($inReplyTo?->external_message_id) {
                $mail->getHeaders()->addTextHeader('In-Reply-To', $inReplyTo->external_message_id);
                $mail->getHeaders()->addTextHeader('References', $conversation->external_thread_id);
            }
        });
    }

    public function sendAutoReply(InboxConversation $conversation): ?InboxMessage
    {
        if (!$this->inbox->getAutoReplyEnabled()) {
            return null;
        }

        $autoReplyMessage = $this->inbox->getAutoReplyMessage();
        if (!$autoReplyMessage) {
            return null;
        }

        // Check if we already sent an auto-reply to this conversation
        $existingAutoReply = $conversation->messages()
            ->where('direction', 'outbound')
            ->where('type', 'auto_reply')
            ->exists();

        if ($existingAutoReply) {
            return null;
        }

        $message = InboxMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'auto_reply',
            'from_email' => $this->inbox->email,
            'from_name' => $this->inbox->name,
            'to_emails' => [$conversation->contact_email],
            'subject' => 'Re: ' . $conversation->subject,
            'body_text' => strip_tags($autoReplyMessage),
            'body_html' => $autoReplyMessage,
            'status' => 'queued',
        ]);

        try {
            $this->sendEmail($message, $conversation, null);

            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $message;
        } catch (\Exception $e) {
            Log::error('Auto-reply failed', [
                'inbox_id' => $this->inbox->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $message->update(['status' => 'failed']);
            return $message;
        }
    }

    protected function formatAttachments(array $attachments): array
    {
        return array_map(function ($attachment) {
            return [
                'name' => $attachment['name'] ?? 'attachment',
                'path' => $attachment['path'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? 'application/octet-stream',
                'size' => $attachment['size'] ?? 0,
            ];
        }, $attachments);
    }

    public function verifyConnection(): array
    {
        try {
            $transport = new EsmtpTransport(
                $this->inbox->smtp_host,
                $this->inbox->smtp_port,
                $this->inbox->smtp_encryption === 'tls'
            );
            $transport->setUsername($this->inbox->username);
            $transport->setPassword($this->inbox->password);

            // Test connection
            $transport->start();
            $transport->stop();

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
