<?php

declare(strict_types=1);

namespace App\Domain\ImportExport\Repositories;

use App\Domain\ImportExport\Entities\Import;

interface ImportRepositoryInterface
{
    public function findById(int $id): ?Import;
    
    public function findAll(): array;
    
    public function save(Import $entity): Import;
    
    public function delete(int $id): bool;
}
