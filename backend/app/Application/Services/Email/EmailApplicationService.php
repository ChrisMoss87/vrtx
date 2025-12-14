<?php

declare(strict_types=1);

namespace App\Application\Services\Email;

use App\Domain\Email\DTOs\CreateEmailDTO;
use App\Domain\Email\DTOs\EmailResponseDTO;
use App\Domain\Email\Entities\EmailMessage;
use App\Domain\Email\Entities\EmailTemplate;
use App\Domain\Email\Repositories\EmailAccountRepositoryInterface;
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use App\Domain\Email\Services\EmailSendingService;
use App\Domain\Email\ValueObjects\EmailType;
use Illuminate\Support\Facades\DB;

class EmailApplicationService
{
    public function __construct(
        private readonly EmailMessageRepositoryInterface $emailRepository,
        private readonly EmailTemplateRepositoryInterface $templateRepository,
        private readonly EmailAccountRepositoryInterface $accountRepository,
        private readonly EmailSendingService $sendingService,
    ) {}

    public function sendEmail(CreateEmailDTO $dto): EmailResponseDTO
    {
        return DB::transaction(function () use ($dto) {
            $type = $dto->templateId ? EmailType::TEMPLATE : EmailType::MANUAL;

            $email = EmailMessage::create(
                accountId: $dto->accountId,
                fromEmail: $dto->fromEmail,
                toRecipients: $dto->toRecipients,
                subject: $dto->subject,
                type: $type,
            );

            if ($dto->bodyHtml) {
                $email->setBody($dto->bodyHtml, $dto->bodyText);
            }

            $email->setRecipients($dto->toRecipients, $dto->ccRecipients, $dto->bccRecipients);

            if ($dto->moduleId && $dto->recordId) {
                $email->linkToRecord($dto->moduleId, $dto->recordId);
            }

            $sentEmail = $this->sendingService->send($email);

            return EmailResponseDTO::fromEntity($sentEmail);
        });
    }

    public function getEmailsForRecord(int $moduleId, int $recordId): array
    {
        $emails = $this->emailRepository->findByRecordId($moduleId, $recordId);
        return array_map(fn($e) => EmailResponseDTO::fromEntity($e), $emails);
    }

    public function getEmailThread(string $threadId): array
    {
        $emails = $this->emailRepository->findByThreadId($threadId);
        return array_map(fn($e) => EmailResponseDTO::fromEntity($e), $emails);
    }

    public function createTemplate(string $name, ?string $subject, ?string $bodyHtml, ?int $userId = null): EmailTemplate
    {
        $template = EmailTemplate::create($name, $userId);
        $template->update($name, $subject, $bodyHtml);
        return $this->templateRepository->save($template);
    }

    public function getTemplates(?int $moduleId = null): array
    {
        if ($moduleId) {
            return $this->templateRepository->findByModuleId($moduleId);
        }
        return $this->templateRepository->findActive();
    }

    public function getUserAccounts(int $userId): array
    {
        return $this->accountRepository->findByUserId($userId);
    }

    public function recordEmailOpen(int $emailId): void
    {
        $email = $this->emailRepository->findById($emailId);
        if ($email) {
            $email->recordOpen();
            $this->emailRepository->save($email);
        }
    }

    public function recordEmailClick(int $emailId): void
    {
        $email = $this->emailRepository->findById($emailId);
        if ($email) {
            $email->recordClick();
            $this->emailRepository->save($email);
        }
    }
}
