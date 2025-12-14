<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Email;

use App\Domain\Email\Entities\EmailMessage;
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use DateTimeImmutable;

class EloquentEmailMessageRepository implements EmailMessageRepositoryInterface
{
    public function findById(int $id): ?EmailMessage
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(EmailMessage $entity): EmailMessage
    {
        // TODO: Implement with Eloquent model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Eloquent model
        return false;
    }
}
