<?php

declare(strict_types=1);

namespace App\Domain\KnowledgeBase\Repositories;

use App\Domain\KnowledgeBase\Entities\KbArticle;

interface KbArticleRepositoryInterface
{
    public function findById(int $id): ?KbArticle;
    
    public function findAll(): array;
    
    public function save(KbArticle $entity): KbArticle;
    
    public function delete(int $id): bool;
}
