<?php

declare(strict_types=1);

namespace App\Domain\Communication\ValueObjects;

final readonly class RecordContext
{
    public function __construct(
        public string $moduleApiName,
        public int $recordId,
    ) {
        if (empty($moduleApiName)) {
            throw new \InvalidArgumentException('Module API name cannot be empty');
        }

        if ($recordId <= 0) {
            throw new \InvalidArgumentException('Record ID must be positive');
        }
    }

    public static function fromArray(array $data): ?self
    {
        $moduleApiName = $data['module_api_name']
            ?? $data['moduleApiName']
            ?? $data['module']
            ?? null;

        $recordId = $data['record_id']
            ?? $data['recordId']
            ?? $data['id']
            ?? null;

        if (empty($moduleApiName) || empty($recordId)) {
            return null;
        }

        return new self($moduleApiName, (int) $recordId);
    }

    public static function forContact(int $recordId): self
    {
        return new self('contacts', $recordId);
    }

    public static function forLead(int $recordId): self
    {
        return new self('leads', $recordId);
    }

    public static function forDeal(int $recordId): self
    {
        return new self('deals', $recordId);
    }

    public static function forAccount(int $recordId): self
    {
        return new self('accounts', $recordId);
    }

    public function toArray(): array
    {
        return [
            'module_api_name' => $this->moduleApiName,
            'record_id' => $this->recordId,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->moduleApiName === $other->moduleApiName
            && $this->recordId === $other->recordId;
    }

    public function __toString(): string
    {
        return "{$this->moduleApiName}:{$this->recordId}";
    }
}
