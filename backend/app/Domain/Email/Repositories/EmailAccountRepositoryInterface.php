<?php

declare(strict_types=1);

namespace App\Domain\Email\Repositories;

use App\Domain\Email\Entities\EmailAccount;

interface EmailAccountRepositoryInterface
{
    public function findById(int $id): ?EmailAccount;

    public function findByUserId(int $userId): array;

    public function findDefaultForUser(int $userId): ?EmailAccount;

    public function findByEmail(string $email): ?EmailAccount;

    public function save(EmailAccount $account): EmailAccount;

    public function delete(int $id): bool;
}
