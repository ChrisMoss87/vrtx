<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

use JsonSerializable;

/**
 * Represents a join between modules in a cross-object report.
 */
final readonly class ReportJoin implements JsonSerializable
{
    public const TYPE_INNER = 'inner';
    public const TYPE_LEFT = 'left';
    public const TYPE_RIGHT = 'right';

    /**
     * @param int $sourceModuleId The source module ID
     * @param string $sourceField The lookup field on the source module
     * @param int $targetModuleId The target module ID being joined
     * @param string $targetField The field on target module to match (usually 'id')
     * @param string $alias Alias for the joined module (e.g., 'company', 'contact')
     * @param string $joinType Type of join (inner, left, right)
     */
    public function __construct(
        public int $sourceModuleId,
        public string $sourceField,
        public int $targetModuleId,
        public string $targetField,
        public string $alias,
        public string $joinType = self::TYPE_LEFT,
    ) {
        $this->validateJoinType();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceModuleId: $data['source_module_id'],
            sourceField: $data['source_field'],
            targetModuleId: $data['target_module_id'],
            targetField: $data['target_field'] ?? 'id',
            alias: $data['alias'],
            joinType: $data['join_type'] ?? self::TYPE_LEFT,
        );
    }

    /**
     * Validate the join type.
     */
    private function validateJoinType(): void
    {
        $validTypes = [self::TYPE_INNER, self::TYPE_LEFT, self::TYPE_RIGHT];

        if (!in_array($this->joinType, $validTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid join type '{$this->joinType}'. Must be one of: " . implode(', ', $validTypes)
            );
        }
    }

    /**
     * Get SQL join type keyword.
     */
    public function getSqlJoinType(): string
    {
        return match ($this->joinType) {
            self::TYPE_INNER => 'INNER JOIN',
            self::TYPE_LEFT => 'LEFT JOIN',
            self::TYPE_RIGHT => 'RIGHT JOIN',
        };
    }

    public function jsonSerialize(): array
    {
        return [
            'source_module_id' => $this->sourceModuleId,
            'source_field' => $this->sourceField,
            'target_module_id' => $this->targetModuleId,
            'target_field' => $this->targetField,
            'alias' => $this->alias,
            'join_type' => $this->joinType,
        ];
    }
}
