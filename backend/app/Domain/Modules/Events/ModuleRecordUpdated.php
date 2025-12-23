<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a module record is updated.
 */
final class ModuleRecordUpdated extends DomainEvent
{
    public function __construct(
        private readonly int $recordId,
        private readonly int $moduleId,
        private readonly array $oldData,
        private readonly array $newData,
        private readonly ?int $updatedBy,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->recordId;
    }

    public function aggregateType(): string
    {
        return 'ModuleRecord';
    }

    public function recordId(): int
    {
        return $this->recordId;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function oldData(): array
    {
        return $this->oldData;
    }

    public function newData(): array
    {
        return $this->newData;
    }

    public function updatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function toPayload(): array
    {
        return [
            'record_id' => $this->recordId,
            'module_id' => $this->moduleId,
            'old_data' => $this->oldData,
            'new_data' => $this->newData,
            'updated_by' => $this->updatedBy,
        ];
    }
}
