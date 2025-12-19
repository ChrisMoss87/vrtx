<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\EmailAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ImapConnection
{
    protected EmailAccount $account;
    protected $connection = null;
    protected ?string $lastUid = null;

    public function __construct(EmailAccount $account)
    {
        $this->account = $account;
    }

    /**
     * Connect to IMAP server.
     */
    public function connect(): bool
    {
        if ($this->connection) {
            return true;
        }

        try {
            $mailbox = sprintf(
                '{%s:%d/imap/%s}',
                $this->account->imap_host,
                $this->account->imap_port,
                $this->account->imap_encryption === 'ssl' ? 'ssl' : 'notls'
            );

            $username = $this->account->username ?? $this->account->email_address;
            $password = $this->account->password;

            // For OAuth providers
            if ($this->account->oauth_token && in_array($this->account->provider, [
                EmailAccount::PROVIDER_GMAIL,
                EmailAccount::PROVIDER_OUTLOOK,
            ])) {
                return $this->connectOAuth();
            }

            $this->connection = @imap_open(
                $mailbox,
                $username,
                $password,
                0,
                1
            );

            if (!$this->connection) {
                $error = imap_last_error();
                Log::error('IMAP connection failed', [
                    'account_id' => $this->account->id,
                    'error' => $error,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('IMAP connection exception', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Connect using OAuth.
     */
    protected function connectOAuth(): bool
    {
        // Check if token needs refresh
        if ($this->account->oauth_expires_at && $this->account->oauth_expires_at->isPast()) {
            $this->refreshOAuthToken();
        }

        // OAuth IMAP authentication using XOAUTH2
        $authString = base64_encode(sprintf(
            "user=%s\x01auth=Bearer %s\x01\x01",
            $this->account->email_address,
            $this->account->oauth_token
        ));

        $mailbox = sprintf(
            '{%s:%d/imap/ssl}',
            $this->account->imap_host,
            $this->account->imap_port
        );

        $this->connection = @imap_open(
            $mailbox,
            $this->account->email_address,
            $authString,
            0,
            1
        );

        return $this->connection !== false;
    }

    /**
     * Refresh OAuth token.
     */
    protected function refreshOAuthToken(): bool
    {
        $oauthService = new OAuthTokenService();
        return $oauthService->refreshToken($this->account);
    }

    /**
     * Disconnect from IMAP server.
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Fetch messages from a folder.
     */
    public function fetchMessages(string $folder, ?string $sinceUid = null): Collection
    {
        if (!$this->connection) {
            $this->connect();
        }

        $mailbox = sprintf(
            '{%s:%d/imap/%s}%s',
            $this->account->imap_host,
            $this->account->imap_port,
            $this->account->imap_encryption === 'ssl' ? 'ssl' : 'notls',
            $folder
        );

        if (!@imap_reopen($this->connection, $mailbox)) {
            Log::error('Failed to open folder', ['folder' => $folder]);
            return collect();
        }

        // Get message UIDs
        $searchCriteria = $sinceUid ? 'UID ' . ($sinceUid + 1) . ':*' : 'ALL';
        $uids = @imap_search($this->connection, $searchCriteria, SE_UID);

        if (!$uids) {
            return collect();
        }

        $messages = collect();
        $this->lastUid = max($uids);

        foreach ($uids as $uid) {
            $message = $this->fetchMessage($uid);
            if ($message) {
                $messages->push($message);
            }
        }

        return $messages;
    }

    /**
     * Fetch a single message by UID.
     */
    public function fetchMessage(int $uid): ?array
    {
        if (!$this->connection) {
            return null;
        }

        $msgno = @imap_msgno($this->connection, $uid);
        if (!$msgno) {
            return null;
        }

        $header = @imap_headerinfo($this->connection, $msgno);
        $structure = @imap_fetchstructure($this->connection, $uid, FT_UID);

        if (!$header || !$structure) {
            return null;
        }

        // Parse body
        $body = $this->parseBody($uid, $structure);

        // Parse attachments
        $attachments = $this->parseAttachments($uid, $structure);

        return [
            'message_id' => $header->message_id ?? null,
            'subject' => $this->decodeHeader($header->subject ?? ''),
            'from' => [
                'email' => $header->from[0]->mailbox . '@' . $header->from[0]->host,
                'name' => $this->decodeHeader($header->from[0]->personal ?? ''),
            ],
            'to' => $this->parseAddressList($header->to ?? []),
            'cc' => $this->parseAddressList($header->cc ?? []),
            'reply_to' => isset($header->reply_to[0])
                ? $header->reply_to[0]->mailbox . '@' . $header->reply_to[0]->host
                : null,
            'date' => isset($header->date) ? \Carbon\Carbon::parse($header->date) : null,
            'html' => $body['html'] ?? null,
            'text' => $body['text'] ?? null,
            'headers' => [
                'in-reply-to' => $header->in_reply_to ?? null,
                'references' => $header->references ?? null,
            ],
            'attachments' => $attachments,
        ];
    }

    /**
     * Parse message body.
     */
    protected function parseBody(int $uid, object $structure, string $partNumber = ''): array
    {
        $body = ['html' => null, 'text' => null];

        if ($structure->type === 0) { // Text
            $content = $this->fetchPart($uid, $partNumber ?: '1', $structure->encoding);
            $charset = $this->getCharset($structure->parameters ?? []);
            $content = $this->convertCharset($content, $charset);

            if (strtolower($structure->subtype) === 'html') {
                $body['html'] = $content;
            } else {
                $body['text'] = $content;
            }
        } elseif ($structure->type === 1 && isset($structure->parts)) { // Multipart
            foreach ($structure->parts as $index => $part) {
                $subPartNumber = $partNumber ? $partNumber . '.' . ($index + 1) : (string) ($index + 1);
                $subBody = $this->parseBody($uid, $part, $subPartNumber);
                $body['html'] = $body['html'] ?? $subBody['html'];
                $body['text'] = $body['text'] ?? $subBody['text'];
            }
        }

        return $body;
    }

    /**
     * Fetch a specific part of the message.
     */
    protected function fetchPart(int $uid, string $partNumber, int $encoding): string
    {
        $content = @imap_fetchbody($this->connection, $uid, $partNumber, FT_UID);

        switch ($encoding) {
            case 0: // 7BIT
            case 1: // 8BIT
                break;
            case 2: // BINARY
                break;
            case 3: // BASE64
                $content = base64_decode($content);
                break;
            case 4: // QUOTED-PRINTABLE
                $content = quoted_printable_decode($content);
                break;
        }

        return $content ?: '';
    }

    /**
     * Get charset from parameters.
     */
    protected function getCharset(array $parameters): string
    {
        foreach ($parameters as $param) {
            if (strtolower($param->attribute) === 'charset') {
                return $param->value;
            }
        }
        return 'UTF-8';
    }

    /**
     * Convert charset to UTF-8.
     */
    protected function convertCharset(string $content, string $charset): string
    {
        $charset = strtoupper($charset);
        if ($charset === 'UTF-8' || empty($charset)) {
            return $content;
        }

        return @mb_convert_encoding($content, 'UTF-8', $charset) ?: $content;
    }

    /**
     * Parse attachments from structure.
     */
    protected function parseAttachments(int $uid, object $structure, string $partNumber = ''): array
    {
        $attachments = [];

        if (isset($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $subPartNumber = $partNumber ? $partNumber . '.' . ($index + 1) : (string) ($index + 1);

                // Check if this is an attachment
                if ($this->isAttachment($part)) {
                    $filename = $this->getAttachmentFilename($part);
                    $attachments[] = [
                        'filename' => $filename,
                        'mime_type' => $this->getMimeType($part),
                        'size' => $part->bytes ?? 0,
                        'part_number' => $subPartNumber,
                    ];
                }

                // Recursively check sub-parts
                if (isset($part->parts)) {
                    $attachments = array_merge(
                        $attachments,
                        $this->parseAttachments($uid, $part, $subPartNumber)
                    );
                }
            }
        }

        return $attachments;
    }

    /**
     * Check if a part is an attachment.
     */
    protected function isAttachment(object $part): bool
    {
        if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
            return true;
        }

        // Check for inline with filename
        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get attachment filename.
     */
    protected function getAttachmentFilename(object $part): string
    {
        // Check disposition parameters first
        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename') {
                    return $this->decodeHeader($param->value);
                }
            }
        }

        // Check regular parameters
        if (isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) === 'name') {
                    return $this->decodeHeader($param->value);
                }
            }
        }

        return 'attachment';
    }

    /**
     * Get MIME type.
     */
    protected function getMimeType(object $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
        $type = $types[$part->type] ?? 'application';
        return $type . '/' . strtolower($part->subtype);
    }

    /**
     * Parse address list.
     */
    protected function parseAddressList(array $addresses): array
    {
        $result = [];
        foreach ($addresses as $addr) {
            if (isset($addr->mailbox) && isset($addr->host)) {
                $result[] = [
                    'email' => $addr->mailbox . '@' . $addr->host,
                    'name' => $this->decodeHeader($addr->personal ?? ''),
                ];
            }
        }
        return $result;
    }

    /**
     * Decode MIME header.
     */
    protected function decodeHeader(string $text): string
    {
        $elements = imap_mime_header_decode($text);
        $decoded = '';
        foreach ($elements as $element) {
            $decoded .= $element->text;
        }
        return $decoded;
    }

    /**
     * Move message to folder.
     */
    public function moveMessage(string $messageId, string $folder): bool
    {
        if (!$this->connection) {
            return false;
        }

        // Find message by message ID
        $uids = @imap_search($this->connection, 'HEADER Message-ID "' . $messageId . '"', SE_UID);
        if (!$uids) {
            return false;
        }

        return @imap_mail_move($this->connection, (string) $uids[0], $folder, CP_UID);
    }

    /**
     * Delete message.
     */
    public function deleteMessage(string $messageId): bool
    {
        if (!$this->connection) {
            return false;
        }

        $uids = @imap_search($this->connection, 'HEADER Message-ID "' . $messageId . '"', SE_UID);
        if (!$uids) {
            return false;
        }

        @imap_delete($this->connection, (string) $uids[0], FT_UID);
        @imap_expunge($this->connection);

        return true;
    }

    /**
     * Get folder list.
     */
    public function getFolders(): array
    {
        if (!$this->connection) {
            return [];
        }

        $mailbox = sprintf(
            '{%s:%d/imap/%s}',
            $this->account->imap_host,
            $this->account->imap_port,
            $this->account->imap_encryption === 'ssl' ? 'ssl' : 'notls'
        );

        $folders = @imap_list($this->connection, $mailbox, '*');
        if (!$folders) {
            return [];
        }

        return array_map(function ($folder) use ($mailbox) {
            return str_replace($mailbox, '', $folder);
        }, $folders);
    }

    /**
     * Search messages.
     */
    public function search(string $query, ?string $folder = null): Collection
    {
        if (!$this->connection) {
            return collect();
        }

        if ($folder) {
            $mailbox = sprintf(
                '{%s:%d/imap/%s}%s',
                $this->account->imap_host,
                $this->account->imap_port,
                $this->account->imap_encryption === 'ssl' ? 'ssl' : 'notls',
                $folder
            );
            @imap_reopen($this->connection, $mailbox);
        }

        // Build IMAP search string
        $searchString = 'OR OR SUBJECT "' . $query . '" FROM "' . $query . '" BODY "' . $query . '"';
        $uids = @imap_search($this->connection, $searchString, SE_UID);

        if (!$uids) {
            return collect();
        }

        return collect($uids)->map(fn($uid) => $this->fetchMessage($uid))->filter();
    }

    /**
     * Get last fetched UID.
     */
    public function getLastUid(): ?string
    {
        return $this->lastUid;
    }
}
