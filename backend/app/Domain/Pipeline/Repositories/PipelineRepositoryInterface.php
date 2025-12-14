<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\Repositories;

use App\Domain\Pipeline\Entities\Pipeline;

interface PipelineRepositoryInterface
{
    public function findById(int $id): ?Pipeline;
    
    public function findAll(): array;
    
    public function save(Pipeline $entity): Pipeline;
    
    public function delete(int $id): bool;
}
