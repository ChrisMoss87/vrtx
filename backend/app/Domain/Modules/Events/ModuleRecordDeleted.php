<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a module record is deleted.
 */
final class ModuleRecordDeleted extends DomainEvent
{
    public function __construct(
        private readonly int $recordId,
        private readonly int $moduleId,
        private readonly array $data,
        private readonly ?int $deletedBy,
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

    public function data(): array
    {
        return $this->data;
    }

    public function deletedBy(): ?int
    {
        return $this->deletedBy;
    }

    public function toPayload(): array
    {
        return [
            'record_id' => $this->recordId,
            'module_id' => $this->moduleId,
            'data' => $this->data,
            'deleted_by' => $this->deletedBy,
        ];
    }
}
