<?php

declare(strict_types=1);

namespace App\Domain\Email\Repositories;

use App\Domain\Email\Entities\EmailTemplate;

interface EmailTemplateRepositoryInterface
{
    public function findById(int $id): ?EmailTemplate;

    public function findByModuleId(int $moduleId): array;

    public function findShared(): array;

    public function findByUserId(int $userId): array;

    public function findActive(): array;

    public function save(EmailTemplate $template): EmailTemplate;

    public function delete(int $id): bool;
}
