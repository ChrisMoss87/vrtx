<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Repositories;

use App\Domain\Plugin\Entities\Plugin;

interface PluginRepositoryInterface
{
    public function findById(int $id): ?Plugin;
    
    public function findAll(): array;
    
    public function save(Plugin $entity): Plugin;
    
    public function delete(int $id): bool;
}
