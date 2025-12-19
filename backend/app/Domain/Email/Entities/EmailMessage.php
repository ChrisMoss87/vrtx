<?php

declare(strict_types=1);

namespace App\Domain\Email\Entities;

use App\Domain\Email\ValueObjects\EmailStatus;
use App\Domain\Email\ValueObjects\EmailType;

class EmailMessage
{
    private ?int $id = null;
    private int $accountId;
    private ?int $recordId;
    private ?int $moduleId;
    private EmailType $type;
    private EmailStatus $status;
    private string $fromEmail;
    private ?string $fromName;
    private array $toRecipients;
    private array $ccRecipients;
    private array $bccRecipients;
    private string $subject;
    private ?string $bodyHtml;
    private ?string $bodyText;
    private array $attachments;
    private array $headers;
    private ?string $messageId;
    private ?string $threadId;
    private ?\DateTimeImmutable $sentAt;
    private ?\DateTimeImmutable $openedAt;
    private int $openCount;
    private int $clickCount;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(
        int $accountId,
        string $fromEmail,
        array $toRecipients,
        string $subject,
        EmailType $type = EmailType::MANUAL,
    ) {
        $this->accountId = $accountId;
        $this->fromEmail = $fromEmail;
        $this->toRecipients = $toRecipients;
        $this->subject = $subject;
        $this->type = $type;
        $this->status = EmailStatus::DRAFT;
        $this->fromName = null;
        $this->ccRecipients = [];
        $this->bccRecipients = [];
        $this->bodyHtml = null;
        $this->bodyText = null;
        $this->attachments = [];
        $this->headers = [];
        $this->recordId = null;
        $this->moduleId = null;
        $this->messageId = null;
        $this->threadId = null;
        $this->sentAt = null;
        $this->openedAt = null;
        $this->openCount = 0;
        $this->clickCount = 0;
    }

    public static function create(
        int $accountId,
        string $fromEmail,
        array $toRecipients,
        string $subject,
        EmailType $type = EmailType::MANUAL,
    ): self {
        return new self($accountId, $fromEmail, $toRecipients, $subject, $type);
    }

    public static function reconstitute(
        int $id,
        int $accountId,
        string $fromEmail,
        ?string $fromName,
        array $toRecipients,
        array $ccRecipients,
        array $bccRecipients,
        string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        EmailType $type,
        EmailStatus $status,
        array $attachments,
        array $headers,
        ?int $recordId,
        ?int $moduleId,
        ?string $messageId,
        ?string $threadId,
        ?\DateTimeImmutable $sentAt,
        ?\DateTimeImmutable $openedAt,
        int $openCount,
        int $clickCount,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $email = new self($accountId, $fromEmail, $toRecipients, $subject, $type);
        $email->id = $id;
        $email->fromName = $fromName;
        $email->ccRecipients = $ccRecipients;
        $email->bccRecipients = $bccRecipients;
        $email->bodyHtml = $bodyHtml;
        $email->bodyText = $bodyText;
        $email->status = $status;
        $email->attachments = $attachments;
        $email->headers = $headers;
        $email->recordId = $recordId;
        $email->moduleId = $moduleId;
        $email->messageId = $messageId;
        $email->threadId = $threadId;
        $email->sentAt = $sentAt;
        $email->openedAt = $openedAt;
        $email->openCount = $openCount;
        $email->clickCount = $clickCount;
        $email->createdAt = $createdAt;
        $email->updatedAt = $updatedAt;
        return $email;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getAccountId(): int { return $this->accountId; }
    public function getFromEmail(): string { return $this->fromEmail; }
    public function getFromName(): ?string { return $this->fromName; }
    public function getToRecipients(): array { return $this->toRecipients; }
    public function getCcRecipients(): array { return $this->ccRecipients; }
    public function getBccRecipients(): array { return $this->bccRecipients; }
    public function getSubject(): string { return $this->subject; }
    public function getBodyHtml(): ?string { return $this->bodyHtml; }
    public function getBodyText(): ?string { return $this->bodyText; }
    public function getType(): EmailType { return $this->type; }
    public function getStatus(): EmailStatus { return $this->status; }
    public function getAttachments(): array { return $this->attachments; }
    public function getRecordId(): ?int { return $this->recordId; }
    public function getModuleId(): ?int { return $this->moduleId; }
    public function getMessageId(): ?string { return $this->messageId; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getOpenCount(): int { return $this->openCount; }
    public function getClickCount(): int { return $this->clickCount; }

    // Domain methods
    public function setBody(string $html, ?string $text = null): void
    {
        $this->bodyHtml = $html;
        $this->bodyText = $text ?? strip_tags($html);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setRecipients(array $to, array $cc = [], array $bcc = []): void
    {
        $this->toRecipients = $to;
        $this->ccRecipients = $cc;
        $this->bccRecipients = $bcc;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function linkToRecord(int $moduleId, int $recordId): void
    {
        $this->moduleId = $moduleId;
        $this->recordId = $recordId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsQueued(): void
    {
        $this->status = EmailStatus::QUEUED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsSent(string $messageId): void
    {
        $this->status = EmailStatus::SENT;
        $this->messageId = $messageId;
        $this->sentAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsDelivered(): void
    {
        $this->status = EmailStatus::DELIVERED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordOpen(): void
    {
        if ($this->openedAt === null) {
            $this->openedAt = new \DateTimeImmutable();
            $this->status = EmailStatus::OPENED;
        }
        $this->openCount++;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordClick(): void
    {
        $this->clickCount++;
        $this->status = EmailStatus::CLICKED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsBounced(string $reason): void
    {
        $this->status = EmailStatus::BOUNCED;
        $this->headers['bounce_reason'] = $reason;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsFailed(string $error): void
    {
        $this->status = EmailStatus::FAILED;
        $this->headers['error'] = $error;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
