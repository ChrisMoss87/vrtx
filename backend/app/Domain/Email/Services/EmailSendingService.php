<?php

declare(strict_types=1);

namespace App\Domain\Email\Services;

use App\Domain\Email\Entities\EmailMessage;
use App\Domain\Email\Entities\EmailTemplate;
use App\Domain\Email\Events\EmailSent;
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use App\Domain\Email\ValueObjects\EmailType;
use App\Domain\Shared\Contracts\EventDispatcherInterface;

class EmailSendingService
{
    public function __construct(
        private readonly EmailMessageRepositoryInterface $emailRepository,
        private readonly EmailTemplateRepositoryInterface $templateRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function send(EmailMessage $email): EmailMessage
    {
        $email->markAsQueued();
        $savedEmail = $this->emailRepository->save($email);

        // The actual sending would be handled by infrastructure layer
        // This just prepares the email for sending

        return $savedEmail;
    }

    public function sendFromTemplate(
        int $accountId,
        int $templateId,
        array $recipients,
        array $templateData,
        ?int $moduleId = null,
        ?int $recordId = null,
    ): EmailMessage {
        $template = $this->templateRepository->findById($templateId);
        if (!$template) {
            throw new \InvalidArgumentException("Template not found: {$templateId}");
        }

        $rendered = $template->render($templateData);

        $email = EmailMessage::create(
            accountId: $accountId,
            fromEmail: $templateData['from_email'] ?? '',
            toRecipients: $recipients,
            subject: $rendered['subject'],
            type: EmailType::TEMPLATE,
        );

        $email->setBody($rendered['body_html'], $rendered['body_text']);

        if ($moduleId && $recordId) {
            $email->linkToRecord($moduleId, $recordId);
        }

        return $this->send($email);
    }

    public function markAsSent(EmailMessage $email, string $messageId): void
    {
        $email->markAsSent($messageId);
        $this->emailRepository->save($email);

        $this->eventDispatcher->dispatch(new EmailSent(
            emailId: $email->getId(),
            accountId: $email->getAccountId(),
            messageId: $messageId,
            toRecipients: $email->getToRecipients(),
            recordId: $email->getRecordId(),
            moduleId: $email->getModuleId(),
        ));
    }
}
