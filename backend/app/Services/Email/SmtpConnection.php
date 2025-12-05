<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SmtpConnection
{
    protected EmailAccount $account;
    protected ?PHPMailer $mailer = null;

    public function __construct(EmailAccount $account)
    {
        $this->account = $account;
    }

    /**
     * Connect to SMTP server.
     */
    public function connect(): bool
    {
        if ($this->mailer) {
            return true;
        }

        try {
            $this->mailer = new PHPMailer(true);

            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->account->smtp_host;
            $this->mailer->Port = $this->account->smtp_port;
            $this->mailer->SMTPAuth = true;

            // Encryption
            if ($this->account->smtp_encryption === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->account->smtp_encryption === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Authentication
            if ($this->account->oauth_token && in_array($this->account->provider, [
                EmailAccount::PROVIDER_GMAIL,
                EmailAccount::PROVIDER_OUTLOOK,
            ])) {
                // Ensure OAuth token is valid before connecting
                $oauthService = new OAuthTokenService();
                if (!$oauthService->ensureValidToken($this->account)) {
                    Log::warning('Failed to refresh OAuth token for SMTP', [
                        'account_id' => $this->account->id,
                    ]);
                }
                // Reload account to get fresh token
                $this->account->refresh();

                $this->mailer->AuthType = 'XOAUTH2';
                $this->mailer->setOAuth($this->getOAuthProvider());
            } else {
                $this->mailer->Username = $this->account->username ?? $this->account->email_address;
                $this->mailer->Password = $this->account->password;
            }

            // Character encoding
            $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;

            return true;
        } catch (Exception $e) {
            Log::error('SMTP connection failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get OAuth provider for PHPMailer.
     */
    protected function getOAuthProvider(): object
    {
        // This would return the appropriate OAuth provider based on the account provider
        // For example, PHPMailer\PHPMailer\OAuth for Google or Microsoft
        return new class($this->account) {
            protected $account;

            public function __construct($account)
            {
                $this->account = $account;
            }

            public function getOauth64(): string
            {
                return base64_encode(sprintf(
                    "user=%s\x01auth=Bearer %s\x01\x01",
                    $this->account->email_address,
                    $this->account->oauth_token
                ));
            }
        };
    }

    /**
     * Disconnect SMTP.
     */
    public function disconnect(): void
    {
        if ($this->mailer) {
            $this->mailer->smtpClose();
            $this->mailer = null;
        }
    }

    /**
     * Send an email.
     */
    public function send(array $data): array
    {
        if (!$this->mailer) {
            $this->connect();
        }

        try {
            // Reset for new message
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearReplyTos();
            $this->mailer->clearAttachments();

            // From
            $this->mailer->setFrom(
                $data['from']['email'],
                $data['from']['name'] ?? ''
            );

            // To
            foreach ($data['to'] as $to) {
                if (is_array($to)) {
                    $this->mailer->addAddress($to['email'], $to['name'] ?? '');
                } else {
                    $this->mailer->addAddress($to);
                }
            }

            // CC
            foreach ($data['cc'] ?? [] as $cc) {
                if (is_array($cc)) {
                    $this->mailer->addCC($cc['email'], $cc['name'] ?? '');
                } else {
                    $this->mailer->addCC($cc);
                }
            }

            // BCC
            foreach ($data['bcc'] ?? [] as $bcc) {
                if (is_array($bcc)) {
                    $this->mailer->addBCC($bcc['email'], $bcc['name'] ?? '');
                } else {
                    $this->mailer->addBCC($bcc);
                }
            }

            // Reply-To
            if (!empty($data['reply_to'])) {
                $this->mailer->addReplyTo($data['reply_to']);
            }

            // Custom headers
            foreach ($data['headers'] ?? [] as $name => $value) {
                $this->mailer->addCustomHeader($name, $value);
            }

            // Subject
            $this->mailer->Subject = $data['subject'] ?? '';

            // Body
            if (!empty($data['html'])) {
                $this->mailer->isHTML(true);
                $this->mailer->Body = $data['html'];
                $this->mailer->AltBody = $data['text'] ?? strip_tags($data['html']);
            } else {
                $this->mailer->isHTML(false);
                $this->mailer->Body = $data['text'] ?? '';
            }

            // Attachments
            foreach ($data['attachments'] ?? [] as $attachment) {
                if (isset($attachment['path'])) {
                    $this->mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? '',
                        PHPMailer::ENCODING_BASE64,
                        $attachment['type'] ?? ''
                    );
                } elseif (isset($attachment['content'])) {
                    $this->mailer->addStringAttachment(
                        $attachment['content'],
                        $attachment['name'] ?? 'attachment',
                        PHPMailer::ENCODING_BASE64,
                        $attachment['type'] ?? ''
                    );
                }
            }

            // Send
            $this->mailer->send();

            return [
                'success' => true,
                'message_id' => $this->mailer->getLastMessageID(),
            ];
        } catch (Exception $e) {
            Log::error('SMTP send failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify connection settings.
     */
    public function verify(): bool
    {
        if (!$this->mailer) {
            $this->connect();
        }

        try {
            return $this->mailer->smtpConnect();
        } catch (Exception $e) {
            return false;
        }
    }
}
