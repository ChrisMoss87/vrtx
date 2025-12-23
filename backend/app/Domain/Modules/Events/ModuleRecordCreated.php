<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a module record is created.
 */
final class ModuleRecordCreated extends DomainEvent
{
    public function __construct(
        private readonly int $recordId,
        private readonly int $moduleId,
        private readonly array $data,
        private readonly ?int $createdBy,
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

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function toPayload(): array
    {
        return [
            'record_id' => $this->recordId,
            'module_id' => $this->moduleId,
            'data' => $this->data,
            'created_by' => $this->createdBy,
        ];
    }
}
