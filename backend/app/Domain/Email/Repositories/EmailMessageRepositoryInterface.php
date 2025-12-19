<?php

declare(strict_types=1);

namespace App\Domain\Email\Repositories;

use App\Domain\Email\Entities\EmailMessage;

interface EmailMessageRepositoryInterface
{
    public function findById(int $id): ?EmailMessage;

    public function findByRecordId(int $moduleId, int $recordId): array;

    public function findByAccountId(int $accountId): array;

    public function findByThreadId(string $threadId): array;

    public function save(EmailMessage $email): EmailMessage;

    public function delete(int $id): bool;
}
