<?php

declare(strict_types=1);

namespace App\Domain\Duplicate\Repositories;

use App\Domain\Duplicate\Entities\DuplicateCandidate;

interface DuplicateCandidateRepositoryInterface
{
    public function findById(int $id): ?DuplicateCandidate;
    
    public function findAll(): array;
    
    public function save(DuplicateCandidate $entity): DuplicateCandidate;
    
    public function delete(int $id): bool;
}
