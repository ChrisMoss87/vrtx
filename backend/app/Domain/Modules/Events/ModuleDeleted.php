<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a module is deleted.
 */
final class ModuleDeleted extends DomainEvent
{
    public function __construct(
        private readonly int $moduleId,
        private readonly string $name,
        private readonly string $slug,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->moduleId;
    }

    public function aggregateType(): string
    {
        return 'Module';
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function toPayload(): array
    {
        return [
            'module_id' => $this->moduleId,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
