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
    // Phase 2 widget types for enhanced dashboards
    case GOAL_KPI = 'goal_kpi';
    case LEADERBOARD = 'leaderboard';
    case FUNNEL = 'funnel';
    case PROGRESS = 'progress';
    case RECENT_RECORDS = 'recent_records';
    case HEATMAP = 'heatmap';
    case QUICK_LINKS = 'quick_links';
    case EMBED = 'embed';
    case FORECAST = 'forecast';

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
            self::GOAL_KPI => 'Goal KPI',
            self::LEADERBOARD => 'Leaderboard',
            self::FUNNEL => 'Funnel Chart',
            self::PROGRESS => 'Progress Bar',
            self::RECENT_RECORDS => 'Recent Records',
            self::HEATMAP => 'Heatmap',
            self::QUICK_LINKS => 'Quick Links',
            self::EMBED => 'Embed',
            self::FORECAST => 'Sales Forecast',
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
            self::GOAL_KPI => 'KPI with target goal and progress indicator',
            self::LEADERBOARD => 'Ranked list of users, records, or items',
            self::FUNNEL => 'Sales or conversion funnel visualization',
            self::PROGRESS => 'Progress bar toward a goal',
            self::RECENT_RECORDS => 'Latest records from a module',
            self::HEATMAP => 'Activity density grid visualization',
            self::QUICK_LINKS => 'Navigation shortcuts panel',
            self::EMBED => 'External URL, video, or form embed',
            self::FORECAST => 'Sales forecast with pipeline categories and quota tracking',
        };
    }

    /**
     * Get the category for this widget type.
     */
    public function category(): string
    {
        return match ($this) {
            self::REPORT, self::KPI, self::CHART, self::TABLE, self::GOAL_KPI, self::FUNNEL, self::FORECAST => 'analytics',
            self::ACTIVITY, self::PIPELINE, self::TASKS, self::CALENDAR, self::RECENT_RECORDS => 'activity',
            self::TEXT, self::IFRAME, self::QUICK_LINKS, self::EMBED => 'content',
            self::LEADERBOARD, self::PROGRESS => 'performance',
            self::HEATMAP => 'visualization',
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
            self::GOAL_KPI, self::LEADERBOARD, self::FUNNEL, self::PROGRESS, self::RECENT_RECORDS, self::HEATMAP, self::FORECAST => true,
            self::CALENDAR, self::TEXT, self::IFRAME, self::QUICK_LINKS, self::EMBED => false,
        };
    }

    /**
     * Get default grid size for this widget type.
     */
    public function defaultGridPosition(): array
    {
        return match ($this) {
            self::KPI, self::GOAL_KPI => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2, 'minW' => 2, 'minH' => 2],
            self::PROGRESS => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2, 'minW' => 3, 'minH' => 2],
            self::CHART, self::FUNNEL, self::EMBED => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'minW' => 3, 'minH' => 3],
            self::TABLE, self::PIPELINE => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 6, 'minW' => 6, 'minH' => 4],
            self::LEADERBOARD, self::RECENT_RECORDS => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 6, 'minW' => 3, 'minH' => 4],
            self::ACTIVITY, self::TASKS, self::QUICK_LINKS => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 4, 'minW' => 3, 'minH' => 3],
            self::TEXT => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2, 'minW' => 2, 'minH' => 1],
            self::IFRAME => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'minW' => 3, 'minH' => 2],
            self::CALENDAR => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 4, 'minW' => 3, 'minH' => 3],
            self::HEATMAP => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 5, 'minW' => 4, 'minH' => 4],
            self::FORECAST => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 6, 'minW' => 3, 'minH' => 4],
            default => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
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
