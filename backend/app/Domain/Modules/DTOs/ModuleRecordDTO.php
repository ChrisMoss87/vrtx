<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

class ModuleRecordDTO
{
    public function __construct(
        public readonly int $moduleId,
        public readonly array $data,
        public readonly ?int $id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            moduleId: $data['module_id'],
            data: $data['data'],
            id: $data['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        $array = [
            'module_id' => $this->moduleId,
            'data' => $this->data,
        ];

        if ($this->id !== null) {
            $array['id'] = $this->id;
        }

        return $array;
    }
}
