<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

/**
 * Enum representing dashboard widget types.
 */
enum WidgetType: string
{
    case REPORT = 'report';
    case KPI = 'kpi';
    case CHART = 'chart';
    case TABLE = 'table';
    case ACTIVITY = 'activity';
    case PIPELINE = 'pipeline';
    case TASKS = 'tasks';
    case CALENDAR = 'calendar';
    case TEXT = 'text';
    case IFRAME = 'iframe';

    /**
     * Get human-readable label for this widget type.
     */
    public function label(): string
    {
        return match ($this) {
            self::REPORT => 'Report',
            self::KPI => 'KPI Card',
            self::CHART => 'Quick Chart',
            self::TABLE => 'Data Table',
            self::ACTIVITY => 'Activity Feed',
            self::PIPELINE => 'Pipeline Summary',
            self::TASKS => 'My Tasks',
            self::CALENDAR => 'Calendar',
            self::TEXT => 'Text/Markdown',
            self::IFRAME => 'Embed URL',
        };
    }

    /**
     * Get description for this widget type.
     */
    public function description(): string
    {
        return match ($this) {
            self::REPORT => 'Display a saved report',
            self::KPI => 'Show a single key performance indicator',
            self::CHART => 'Quick chart without saving as a report',
            self::TABLE => 'Display data in a table format',
            self::ACTIVITY => 'Show recent activity feed',
            self::PIPELINE => 'Display pipeline stage summary',
            self::TASKS => 'Show user tasks',
            self::CALENDAR => 'Display calendar events',
            self::TEXT => 'Display custom text or markdown',
            self::IFRAME => 'Embed external content via iframe',
        };
    }

    /**
     * Get the category for this widget type.
     */
    public function category(): string
    {
        return match ($this) {
            self::REPORT, self::KPI, self::CHART, self::TABLE => 'analytics',
            self::ACTIVITY, self::PIPELINE, self::TASKS, self::CALENDAR => 'activity',
            self::TEXT, self::IFRAME => 'content',
        };
    }

    /**
     * Check if this widget type requires a report.
     */
    public function requiresReport(): bool
    {
        return match ($this) {
            self::REPORT, self::CHART, self::TABLE => true,
            default => false,
        };
    }

    /**
     * Check if this widget type supports refresh intervals.
     */
    public function supportsRefresh(): bool
    {
        return match ($this) {
            self::REPORT, self::KPI, self::CHART, self::TABLE, self::ACTIVITY, self::PIPELINE, self::TASKS => true,
            self::CALENDAR, self::TEXT, self::IFRAME => false,
        };
    }

    /**
     * Get all available widget types as an array.
     *
     * @return array<string, array{label: string, description: string, category: string, requires_report: bool, supports_refresh: bool}>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'category' => $case->category(),
                'requires_report' => $case->requiresReport(),
                'supports_refresh' => $case->supportsRefresh(),
            ];
        }
        return $result;
    }
}
