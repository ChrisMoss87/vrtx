<?php

declare(strict_types=1);

namespace App\Domain\Sms\Repositories;

use App\Domain\Sms\Entities\SmsMessage;

interface SmsMessageRepositoryInterface
{
    public function findById(int $id): ?SmsMessage;
    
    public function findAll(): array;
    
    public function save(SmsMessage $entity): SmsMessage;
    
    public function delete(int $id): bool;
}
