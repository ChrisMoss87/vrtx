<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

/**
 * Enum representing workflow trigger types.
 */
enum TriggerType: string
{
    case RECORD_CREATED = 'record_created';
    case RECORD_UPDATED = 'record_updated';
    case RECORD_DELETED = 'record_deleted';
    case RECORD_SAVED = 'record_saved';
    case FIELD_CHANGED = 'field_changed';
    case RELATED_CREATED = 'related_created';
    case RELATED_UPDATED = 'related_updated';
    case RECORD_CONVERTED = 'record_converted';
    case TIME_BASED = 'time_based';
    case WEBHOOK = 'webhook';
    case MANUAL = 'manual';

    /**
     * Get human-readable label for this trigger type.
     */
    public function label(): string
    {
        return match ($this) {
            self::RECORD_CREATED => 'When a record is created',
            self::RECORD_UPDATED => 'When a record is updated',
            self::RECORD_DELETED => 'When a record is deleted',
            self::RECORD_SAVED => 'When a record is saved (create or update)',
            self::FIELD_CHANGED => 'When a field value changes',
            self::RELATED_CREATED => 'When a related record is created',
            self::RELATED_UPDATED => 'When a related record is updated',
            self::RECORD_CONVERTED => 'When a record is converted',
            self::TIME_BASED => 'On a schedule',
            self::WEBHOOK => 'When a webhook is received',
            self::MANUAL => 'Manual trigger only',
        };
    }

    /**
     * Get description for this trigger type.
     */
    public function description(): string
    {
        return match ($this) {
            self::RECORD_CREATED => 'Triggers when a new record is created in the module',
            self::RECORD_UPDATED => 'Triggers when an existing record is modified',
            self::RECORD_DELETED => 'Triggers when a record is removed',
            self::RECORD_SAVED => 'Triggers on both record creation and updates',
            self::FIELD_CHANGED => 'Triggers when specific field(s) change value',
            self::RELATED_CREATED => 'Triggers when a related record is created (e.g., new task on a deal)',
            self::RELATED_UPDATED => 'Triggers when a related record is modified',
            self::RECORD_CONVERTED => 'Triggers when a record is converted (e.g., Lead to Contact)',
            self::TIME_BASED => 'Triggers at scheduled times (cron or relative to field date)',
            self::WEBHOOK => 'Triggers when an external system sends data via webhook',
            self::MANUAL => 'Only runs when manually triggered by a user',
        };
    }

    /**
     * Get the category for this trigger type.
     */
    public function category(): string
    {
        return match ($this) {
            self::RECORD_CREATED,
            self::RECORD_UPDATED,
            self::RECORD_DELETED,
            self::RECORD_SAVED,
            self::RECORD_CONVERTED => 'record',
            self::FIELD_CHANGED => 'field',
            self::RELATED_CREATED,
            self::RELATED_UPDATED => 'related',
            self::TIME_BASED => 'scheduled',
            self::WEBHOOK => 'external',
            self::MANUAL => 'manual',
        };
    }

    /**
     * Check if this trigger type requires additional configuration.
     */
    public function requiresConfig(): bool
    {
        return match ($this) {
            self::FIELD_CHANGED,
            self::RELATED_CREATED,
            self::RELATED_UPDATED,
            self::TIME_BASED => true,
            default => false,
        };
    }

    /**
     * Check if the given event type matches this trigger.
     */
    public function matchesEvent(string $eventType): bool
    {
        if ($this->value === $eventType) {
            return true;
        }

        // RECORD_SAVED matches both create and update
        if ($this === self::RECORD_SAVED) {
            return in_array($eventType, [
                self::RECORD_CREATED->value,
                self::RECORD_UPDATED->value,
            ]);
        }

        // FIELD_CHANGED should match on updates
        if ($this === self::FIELD_CHANGED && $eventType === self::RECORD_UPDATED->value) {
            return true;
        }

        return false;
    }

    /**
     * Get all available trigger types as an array.
     *
     * @return array<string, array{label: string, description: string, category: string, requires_config: bool}>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'category' => $case->category(),
                'requires_config' => $case->requiresConfig(),
            ];
        }
        return $result;
    }
}
