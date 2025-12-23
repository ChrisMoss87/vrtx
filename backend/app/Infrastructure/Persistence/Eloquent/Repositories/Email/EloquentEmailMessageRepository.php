<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Email;

use App\Domain\Email\Entities\EmailMessage;
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use App\Domain\Email\ValueObjects\EmailStatus;
use App\Domain\Email\ValueObjects\EmailType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentEmailMessageRepository implements EmailMessageRepositoryInterface
{
    private const TABLE = 'email_messages';
    private const TABLE_USERS = 'users';

    // Status constants
    private const STATUS_DRAFT = 'draft';
    private const STATUS_QUEUED = 'queued';
    private const STATUS_SENDING = 'sending';
    private const STATUS_SENT = 'sent';
    private const STATUS_FAILED = 'failed';
    private const STATUS_RECEIVED = 'received';

    // Direction constants
    private const DIRECTION_INBOUND = 'inbound';
    private const DIRECTION_OUTBOUND = 'outbound';

    public function findById(int $id): ?EmailMessage
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByRecordId(int $moduleId, int $recordId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('linked_record_id', $recordId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByAccountId(int $accountId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('account_id', $accountId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByThreadId(string $threadId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('thread_id', $threadId)
            ->orderBy('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByUserId(int $userId, ?string $folder = null): array
    {
        $query = DB::table(self::TABLE)->where('user_id', $userId);

        if ($folder !== null) {
            $query->where('folder', $folder);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findDrafts(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('status', self::STATUS_DRAFT)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findSent(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('direction', self::DIRECTION_OUTBOUND)
            ->where('status', self::STATUS_SENT)
            ->orderByDesc('sent_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findInbox(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('direction', self::DIRECTION_INBOUND)
            ->where('folder', 'INBOX')
            ->orderByDesc('received_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findUnread(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findStarred(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_starred', true)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function paginate(
        int $userId,
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): PaginatedResult {
        $query = DB::table(self::TABLE)->where('user_id', $userId);

        $this->applyFilters($query, $filters);

        $total = $query->count();

        $rows = $query
            ->orderBy($sortBy, $sortDirection)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->toArray($this->toDomainEntity($row)))->all();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function paginateByAccount(
        int $accountId,
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): PaginatedResult {
        $query = DB::table(self::TABLE)->where('account_id', $accountId);

        $this->applyFilters($query, $filters);

        $total = $query->count();

        $rows = $query
            ->orderBy($sortBy, $sortDirection)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->toArray($this->toDomainEntity($row)))->all();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function toArray(EmailMessage $email): array
    {
        return [
            'id' => $email->getId(),
            'account_id' => $email->getAccountId(),
            'from_email' => $email->getFromEmail(),
            'from_name' => $email->getFromName(),
            'to_recipients' => $email->getToRecipients(),
            'cc_recipients' => $email->getCcRecipients(),
            'bcc_recipients' => $email->getBccRecipients(),
            'subject' => $email->getSubject(),
            'body_html' => $email->getBodyHtml(),
            'body_text' => $email->getBodyText(),
            'type' => $email->getType()->value,
            'status' => $email->getStatus()->value,
            'attachments' => $email->getAttachments(),
            'record_id' => $email->getRecordId(),
            'module_id' => $email->getModuleId(),
            'message_id' => $email->getMessageId(),
            'sent_at' => $email->getSentAt()?->format('Y-m-d H:i:s'),
            'open_count' => $email->getOpenCount(),
            'click_count' => $email->getClickCount(),
        ];
    }

    public function save(EmailMessage $email): EmailMessage
    {
        $data = $this->toRowData($email);

        if ($email->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $email->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $email->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['folder'])) {
            $query->where('folder', $filters['folder']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (isset($filters['is_starred'])) {
            $query->where('is_starred', $filters['is_starred']);
        }

        if (isset($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('body_text', 'ilike', "%{$search}%")
                    ->orWhere('from_email', 'ilike', "%{$search}%");
            });
        }
    }

    /**
     * Map template_id to EmailType.
     */
    private function mapToEmailType(?int $templateId): EmailType
    {
        return $templateId ? EmailType::TEMPLATE : EmailType::MANUAL;
    }

    /**
     * Map status to EmailStatus.
     */
    private function mapToEmailStatus(string $status): EmailStatus
    {
        return match ($status) {
            self::STATUS_DRAFT => EmailStatus::DRAFT,
            self::STATUS_QUEUED => EmailStatus::QUEUED,
            self::STATUS_SENDING => EmailStatus::SENDING,
            self::STATUS_SENT => EmailStatus::SENT,
            self::STATUS_FAILED => EmailStatus::FAILED,
            self::STATUS_RECEIVED => EmailStatus::DELIVERED,
            default => EmailStatus::DRAFT,
        };
    }

    /**
     * Map EmailStatus to status.
     */
    private function mapFromEmailStatus(EmailStatus $status): string
    {
        return match ($status) {
            EmailStatus::DRAFT => self::STATUS_DRAFT,
            EmailStatus::QUEUED => self::STATUS_QUEUED,
            EmailStatus::SENDING => self::STATUS_SENDING,
            EmailStatus::SENT => self::STATUS_SENT,
            EmailStatus::FAILED => self::STATUS_FAILED,
            EmailStatus::DELIVERED => self::STATUS_RECEIVED,
            EmailStatus::OPENED => self::STATUS_SENT,
            EmailStatus::CLICKED => self::STATUS_SENT,
            EmailStatus::BOUNCED => self::STATUS_FAILED,
        };
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): EmailMessage
    {
        return EmailMessage::reconstitute(
            id: (int) $row->id,
            accountId: $row->account_id ? (int) $row->account_id : null,
            fromEmail: $row->from_email,
            fromName: $row->from_name,
            toRecipients: $row->to_emails ? (is_string($row->to_emails) ? json_decode($row->to_emails, true) : $row->to_emails) : [],
            ccRecipients: $row->cc_emails ? (is_string($row->cc_emails) ? json_decode($row->cc_emails, true) : $row->cc_emails) : [],
            bccRecipients: $row->bcc_emails ? (is_string($row->bcc_emails) ? json_decode($row->bcc_emails, true) : $row->bcc_emails) : [],
            subject: $row->subject ?? '',
            bodyHtml: $row->body_html,
            bodyText: $row->body_text,
            type: $this->mapToEmailType($row->template_id ?? null),
            status: $this->mapToEmailStatus($row->status),
            attachments: $row->attachments ? (is_string($row->attachments) ? json_decode($row->attachments, true) : $row->attachments) : [],
            headers: $row->headers ? (is_string($row->headers) ? json_decode($row->headers, true) : $row->headers) : [],
            recordId: $row->linked_record_id ? (int) $row->linked_record_id : null,
            moduleId: null,
            messageId: $row->message_id,
            threadId: $row->thread_id,
            sentAt: $row->sent_at ? new DateTimeImmutable($row->sent_at) : null,
            openedAt: $row->opened_at ? new DateTimeImmutable($row->opened_at) : null,
            openCount: $row->open_count ?? 0,
            clickCount: $row->click_count ?? 0,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : new DateTimeImmutable(),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(EmailMessage $email): array
    {
        return [
            'account_id' => $email->getAccountId(),
            'from_email' => $email->getFromEmail(),
            'from_name' => $email->getFromName(),
            'to_emails' => json_encode($email->getToRecipients()),
            'cc_emails' => json_encode($email->getCcRecipients()),
            'bcc_emails' => json_encode($email->getBccRecipients()),
            'subject' => $email->getSubject(),
            'body_html' => $email->getBodyHtml(),
            'body_text' => $email->getBodyText(),
            'status' => $this->mapFromEmailStatus($email->getStatus()),
            'attachments' => json_encode($email->getAttachments()),
            'linked_record_id' => $email->getRecordId(),
            'message_id' => $email->getMessageId(),
            'sent_at' => $email->getSentAt()?->format('Y-m-d H:i:s'),
            'open_count' => $email->getOpenCount(),
            'click_count' => $email->getClickCount(),
        ];
    }
}
