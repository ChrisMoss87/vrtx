<?php

declare(strict_types=1);

namespace App\Domain\Activity\ValueObjects;

/**
 * Value Object representing the type of an activity.
 */
enum ActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Meeting = 'meeting';
    case Task = 'task';
    case Email = 'email';
    case StatusChange = 'status_change';
    case FieldUpdate = 'field_update';
    case Comment = 'comment';
    case Attachment = 'attachment';
    case Created = 'created';
    case Deleted = 'deleted';

    /**
     * Get the display label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Call => 'Call',
            self::Meeting => 'Meeting',
            self::Task => 'Task',
            self::Email => 'Email',
            self::StatusChange => 'Status Change',
            self::FieldUpdate => 'Field Update',
            self::Comment => 'Comment',
            self::Attachment => 'Attachment',
            self::Created => 'Created',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * Get the icon name for this type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Note => 'sticky-note',
            self::Call => 'phone',
            self::Meeting => 'calendar',
            self::Task => 'check-square',
            self::Email => 'mail',
            self::StatusChange => 'git-branch',
            self::FieldUpdate => 'edit',
            self::Comment => 'message-circle',
            self::Attachment => 'paperclip',
            self::Created => 'plus-circle',
            self::Deleted => 'trash',
        };
    }

    /**
     * Get the color for this type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Note => 'yellow',
            self::Call => 'blue',
            self::Meeting => 'purple',
            self::Task => 'green',
            self::Email => 'cyan',
            self::StatusChange => 'orange',
            self::FieldUpdate => 'gray',
            self::Comment => 'pink',
            self::Attachment => 'indigo',
            self::Created => 'green',
            self::Deleted => 'red',
        };
    }

    /**
     * Check if this type represents a scheduled activity.
     */
    public function isSchedulable(): bool
    {
        return match ($this) {
            self::Call, self::Meeting, self::Task => true,
            default => false,
        };
    }

    /**
     * Check if this type is a system-generated activity.
     */
    public function isSystemType(): bool
    {
        return match ($this) {
            self::StatusChange, self::FieldUpdate, self::Created, self::Deleted => true,
            default => false,
        };
    }

    /**
     * Get all types as an associative array.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
