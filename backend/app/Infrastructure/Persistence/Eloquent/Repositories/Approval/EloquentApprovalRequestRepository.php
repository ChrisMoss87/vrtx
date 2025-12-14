<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Approval;

use App\Domain\Approval\Entities\ApprovalRequest;
use App\Domain\Approval\Repositories\ApprovalRequestRepositoryInterface;
use DateTimeImmutable;

class EloquentApprovalRequestRepository implements ApprovalRequestRepositoryInterface
{
    public function findById(int $id): ?ApprovalRequest
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(ApprovalRequest $entity): ApprovalRequest
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
