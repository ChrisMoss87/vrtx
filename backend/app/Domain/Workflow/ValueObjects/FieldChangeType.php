<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

/**
 * Enum representing field change detection types.
 */
enum FieldChangeType: string
{
    case ANY = 'any';
    case FROM_VALUE = 'from_value';
    case TO_VALUE = 'to_value';
    case FROM_TO = 'from_to';

    /**
     * Get human-readable label for this change type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ANY => 'Any change',
            self::FROM_VALUE => 'Changes from specific value',
            self::TO_VALUE => 'Changes to specific value',
            self::FROM_TO => 'Changes from X to Y',
        };
    }

    /**
     * Get description for this change type.
     */
    public function description(): string
    {
        return match ($this) {
            self::ANY => 'Triggers when the field value changes to anything',
            self::FROM_VALUE => 'Triggers when the field changes FROM a specific value',
            self::TO_VALUE => 'Triggers when the field changes TO a specific value',
            self::FROM_TO => 'Triggers when the field changes from one specific value to another',
        };
    }

    /**
     * Check if this change type requires a "from" value.
     */
    public function requiresFromValue(): bool
    {
        return in_array($this, [self::FROM_VALUE, self::FROM_TO]);
    }

    /**
     * Check if this change type requires a "to" value.
     */
    public function requiresToValue(): bool
    {
        return in_array($this, [self::TO_VALUE, self::FROM_TO]);
    }

    /**
     * Get all change types as an array.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
            ];
        }
        return $result;
    }
}
