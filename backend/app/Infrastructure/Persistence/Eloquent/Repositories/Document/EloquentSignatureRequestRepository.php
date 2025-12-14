<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Document;

use App\Domain\Document\Entities\SignatureRequest;
use App\Domain\Document\Repositories\SignatureRequestRepositoryInterface;
use DateTimeImmutable;

class EloquentSignatureRequestRepository implements SignatureRequestRepositoryInterface
{
    public function findById(int $id): ?SignatureRequest
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(SignatureRequest $entity): SignatureRequest
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
