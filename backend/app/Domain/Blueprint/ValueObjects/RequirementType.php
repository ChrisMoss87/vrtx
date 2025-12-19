<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

/**
 * Represents the type of requirement for a transition.
 */
enum RequirementType: string
{
    case FIELD_VALUE = 'field_value';
    case ATTACHMENT = 'attachment';
    case COMMENT = 'comment';
    case APPROVAL = 'approval';
    case CUSTOM_FORM = 'custom_form';
    case CHECKLIST = 'checklist';

    public function label(): string
    {
        return match ($this) {
            self::FIELD_VALUE => 'Field Value Required',
            self::ATTACHMENT => 'Attachment Required',
            self::COMMENT => 'Comment Required',
            self::APPROVAL => 'Approval Required',
            self::CUSTOM_FORM => 'Custom Form',
            self::CHECKLIST => 'Checklist',
        };
    }

    public function requiresUserInput(): bool
    {
        return match ($this) {
            self::FIELD_VALUE, self::ATTACHMENT, self::COMMENT, self::CUSTOM_FORM, self::CHECKLIST => true,
            self::APPROVAL => false,
        };
    }

    /**
     * Get all requirement types as options array for UI.
     */
    public static function options(): array
    {
        return array_map(
            fn(self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
