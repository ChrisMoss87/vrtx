<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Sms;

use App\Domain\Sms\Entities\SmsMessage;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use DateTimeImmutable;

class EloquentSmsMessageRepository implements SmsMessageRepositoryInterface
{
    public function findById(int $id): ?SmsMessage
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(SmsMessage $entity): SmsMessage
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
