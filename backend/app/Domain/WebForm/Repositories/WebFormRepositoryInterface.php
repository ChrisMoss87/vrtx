<?php

declare(strict_types=1);

namespace App\Domain\WebForm\Repositories;

use App\Domain\WebForm\Entities\WebForm;

interface WebFormRepositoryInterface
{
    public function findById(int $id): ?WebForm;
    
    public function findAll(): array;
    
    public function save(WebForm $entity): WebForm;
    
    public function delete(int $id): bool;
}
