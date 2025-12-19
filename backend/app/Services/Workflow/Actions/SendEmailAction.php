<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Email\EmailService;
use Illuminate\Support\Facades\Log;

/**
 * Action to send an email.
 */
class SendEmailAction implements ActionInterface
{
    protected EmailService $emailService;

    public function __construct()
    {
        $this->emailService = new EmailService();
    }

    /**
     * Execute the send email action.
     */
    public function execute(array $config, array $context): array
    {
        $to = $this->resolveRecipients($config['to'] ?? [], $context);
        $cc = $this->resolveRecipients($config['cc'] ?? [], $context);
        $bcc = $this->resolveRecipients($config['bcc'] ?? [], $context);

        $subject = $this->interpolate($config['subject'] ?? '', $context);
        $body = $this->interpolate($config['body'] ?? '', $context);

        if (empty($to)) {
            throw new \InvalidArgumentException('No recipients specified for email');
        }

        // Get email account (use system default or configured account)
        $account = $this->getEmailAccount($config, $context);

        if (!$account) {
            Log::warning('No email account configured for workflow email action', [
                'config' => $config,
            ]);
            throw new \RuntimeException('No email account available for sending');
        }

        // If template_id is provided, use template
        if (!empty($config['template_id'])) {
            return $this->sendWithTemplate(
                $account,
                $config['template_id'],
                ['to' => $to, 'cc' => $cc, 'bcc' => $bcc],
                $context
            );
        }

        // Create email message
        $message = EmailMessage::create([
            'account_id' => $account->id,
            'user_id' => $context['triggered_by'] ?? null,
            'direction' => EmailMessage::DIRECTION_OUTBOUND,
            'status' => EmailMessage::STATUS_DRAFT,
            'from_email' => $account->email_address,
            'from_name' => $account->name,
            'to_emails' => $to,
            'cc_emails' => $cc,
            'bcc_emails' => $bcc,
            'subject' => $subject,
            'body_html' => $body,
            'body_text' => strip_tags($body),
            'linked_record_type' => $context['module']['api_name'] ?? null,
            'linked_record_id' => $context['record']['id'] ?? null,
        ]);

        // Send the email
        $sent = $this->emailService->send($message);

        Log::info('Workflow sent email', [
            'message_id' => $message->id,
            'to' => $to,
            'subject' => $subject,
            'sent' => $sent,
        ]);

        return [
            'sent' => $sent,
            'message_id' => $message->id,
            'recipients' => count($to),
            'subject' => $subject,
        ];
    }

    /**
     * Send email using a template.
     */
    protected function sendWithTemplate(
        EmailAccount $account,
        int $templateId,
        array $recipients,
        array $context
    ): array {
        $template = EmailTemplate::find($templateId);

        if (!$template) {
            throw new \InvalidArgumentException("Email template {$templateId} not found");
        }

        // Build template data from context
        $data = [
            'record' => $context['record']['data'] ?? [],
            'module' => $context['module'] ?? [],
            'user' => $context['user'] ?? [],
        ];

        $message = $this->emailService->sendFromTemplate(
            $account,
            $template,
            $recipients,
            $data,
            [
                'type' => $context['module']['api_name'] ?? null,
                'id' => $context['record']['id'] ?? null,
            ]
        );

        // Send the message
        $sent = $this->emailService->send($message);

        Log::info('Workflow sent templated email', [
            'template_id' => $templateId,
            'message_id' => $message->id,
            'sent' => $sent,
        ]);

        return [
            'sent' => $sent,
            'message_id' => $message->id,
            'template_id' => $templateId,
            'recipients' => count($recipients['to'] ?? []),
            'subject' => $message->subject,
        ];
    }

    /**
     * Get the email account to use for sending.
     */
    protected function getEmailAccount(array $config, array $context): ?EmailAccount
    {
        // If account_id is specified in config, use that
        if (!empty($config['account_id'])) {
            return EmailAccount::where('id', $config['account_id'])
                ->where('is_active', true)
                ->first();
        }

        // Try to get the user's default account
        $userId = $context['triggered_by'] ?? null;
        if ($userId) {
            $account = EmailAccount::getDefaultForUser($userId);
            if ($account) {
                return $account;
            }
        }

        // Fall back to any active account (system account)
        return EmailAccount::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();
    }

    /**
     * Get the configuration schema.
     */
    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'account_id',
                    'label' => 'Email Account',
                    'type' => 'select',
                    'required' => false,
                    'description' => 'Select email account to send from (uses default if not specified)',
                ],
                [
                    'name' => 'to',
                    'label' => 'To',
                    'type' => 'recipients',
                    'required' => true,
                    'description' => 'Email recipients (users, roles, or email addresses)',
                ],
                [
                    'name' => 'cc',
                    'label' => 'CC',
                    'type' => 'recipients',
                    'required' => false,
                ],
                [
                    'name' => 'bcc',
                    'label' => 'BCC',
                    'type' => 'recipients',
                    'required' => false,
                ],
                [
                    'name' => 'subject',
                    'label' => 'Subject',
                    'type' => 'text',
                    'required' => true,
                    'supports_variables' => true,
                ],
                [
                    'name' => 'body',
                    'label' => 'Email Body',
                    'type' => 'richtext',
                    'required' => true,
                    'supports_variables' => true,
                ],
                [
                    'name' => 'template_id',
                    'label' => 'Email Template',
                    'type' => 'select',
                    'required' => false,
                    'description' => 'Use a predefined email template (overrides body)',
                ],
            ],
        ];
    }

    /**
     * Validate the configuration.
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['to'])) {
            $errors['to'] = 'At least one recipient is required';
        }

        if (empty($config['subject'])) {
            $errors['subject'] = 'Subject is required';
        }

        if (empty($config['body']) && empty($config['template_id'])) {
            $errors['body'] = 'Either body or template is required';
        }

        return $errors;
    }

    /**
     * Resolve recipient configuration to email addresses.
     */
    protected function resolveRecipients(array $recipients, array $context): array
    {
        $emails = [];

        foreach ($recipients as $recipient) {
            if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                // Direct email address
                $emails[] = $recipient;
            } elseif (is_array($recipient)) {
                // User or role reference
                if (($recipient['type'] ?? '') === 'user') {
                    $user = User::find($recipient['id']);
                    if ($user) {
                        $emails[] = $user->email;
                    }
                } elseif (($recipient['type'] ?? '') === 'field') {
                    // Get email from record field
                    $fieldValue = $context['record']['data'][$recipient['field']] ?? null;
                    if ($fieldValue && filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $fieldValue;
                    }
                } elseif (($recipient['type'] ?? '') === 'owner') {
                    // Get record owner's email
                    $ownerId = $context['record']['data']['owner_id'] ?? null;
                    if ($ownerId) {
                        $user = User::find($ownerId);
                        if ($user) {
                            $emails[] = $user->email;
                        }
                    }
                }
            }
        }

        return array_unique($emails);
    }

    /**
     * Interpolate variables in a string.
     */
    protected function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
            $path = trim($matches[1]);
            return $this->getValueByPath($context, $path) ?? $matches[0];
        }, $template);
    }

    /**
     * Get a value from context by dot-notation path.
     */
    protected function getValueByPath(array $context, string $path): ?string
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }
}
