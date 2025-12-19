<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\Entities\Block;

interface BlockRepositoryInterface
{
    public function findById(int $id): ?Block;

    public function findByModuleId(int $moduleId): array;

    public function save(Block $block): Block;

    public function delete(int $id): bool;
}
