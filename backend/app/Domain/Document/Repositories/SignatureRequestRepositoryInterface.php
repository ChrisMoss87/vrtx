<?php

declare(strict_types=1);

namespace App\Domain\Document\Repositories;

use App\Domain\Document\Entities\SignatureRequest;

interface SignatureRequestRepositoryInterface
{
    public function findById(int $id): ?SignatureRequest;
    
    public function findAll(): array;
    
    public function save(SignatureRequest $entity): SignatureRequest;
    
    public function delete(int $id): bool;
}
