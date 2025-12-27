<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Domain\Reporting\Entities\Dashboard;
use App\Domain\Reporting\Entities\Report;
use App\Infrastructure\Services\Spreadsheet\XlsxWriter;

class ExcelExportService
{
    public function __construct(
        protected XlsxWriter $xlsxWriter
    ) {}

    /**
     * Export a report to Excel.
     */
    public function exportReport(Report $report, array $data, array $options = []): string
    {
        $this->xlsxWriter->reset();

        $rows = $data['rows'] ?? [];
        $columns = $data['columns'] ?? [];
        $summary = $data['summary'] ?? null;

        // Prepare headers
        $headers = [];
        foreach ($columns as $column) {
            $headers[] = $column['label'] ?? $column['field'] ?? '';
        }

        // Prepare data rows
        $exportRows = [];
        foreach ($rows as $row) {
            $exportRow = [];
            foreach ($columns as $column) {
                $field = $column['field'] ?? '';
                $value = $row[$field] ?? '';
                $exportRow[] = $this->formatValue($value, $column['type'] ?? 'text');
            }
            $exportRows[] = $exportRow;
        }

        // Add main data sheet
        $this->xlsxWriter->addSheet(
            $this->sanitizeSheetName($report->name),
            $exportRows,
            ['headers' => $headers, 'header_style' => true]
        );

        // Add summary sheet if available
        if (!empty($summary)) {
            $summaryRows = [];
            foreach ($summary as $item) {
                $summaryRows[] = [
                    'Metric' => $item['label'] ?? '',
                    'Value' => $this->formatValue($item['value'] ?? 0, $item['type'] ?? 'number'),
                ];
            }
            $this->xlsxWriter->addSheet('Summary', $summaryRows, [
                'headers' => ['Metric', 'Value'],
                'header_style' => true,
            ]);
        }

        return $this->xlsxWriter->generate();
    }

    /**
     * Export a dashboard to Excel with multiple sheets.
     */
    public function exportDashboard(Dashboard $dashboard, array $widgetData, array $options = []): string
    {
        $this->xlsxWriter->reset();

        // Add overview sheet with KPIs
        $overviewRows = [];
        foreach ($widgetData as $widget) {
            if (in_array($widget['type'] ?? '', ['kpi', 'goal_kpi'])) {
                $overviewRows[] = [
                    'Metric' => $widget['title'] ?? '',
                    'Value' => $widget['data']['value'] ?? 0,
                    'Change' => isset($widget['data']['change_percent'])
                        ? $widget['data']['change_percent'] . '%'
                        : '',
                ];
            }
        }

        if (!empty($overviewRows)) {
            $this->xlsxWriter->addSheet('Overview', $overviewRows, [
                'headers' => ['Metric', 'Value', 'Change'],
                'header_style' => true,
            ]);
        }

        // Add individual widget sheets for data widgets
        $sheetIndex = 1;
        foreach ($widgetData as $widget) {
            if ($this->hasExportableData($widget)) {
                $sheetName = $this->sanitizeSheetName($widget['title'] ?? "Widget {$sheetIndex}");
                $this->addWidgetSheet($sheetName, $widget);
                $sheetIndex++;
            }
        }

        return $this->xlsxWriter->generate();
    }

    /**
     * Add widget data as a sheet.
     */
    protected function addWidgetSheet(string $sheetName, array $widget): void
    {
        $data = $widget['data'] ?? [];
        $type = $widget['type'] ?? '';

        switch ($type) {
            case 'table':
            case 'recent_records':
                $rows = $data['rows'] ?? $data['records'] ?? [];
                $columns = $data['columns'] ?? [];

                if (empty($columns) && !empty($rows)) {
                    $columns = array_map(
                        fn($key) => ['field' => $key, 'label' => ucfirst(str_replace('_', ' ', $key))],
                        array_keys($rows[0] ?? [])
                    );
                }

                $headers = array_map(fn($c) => $c['label'] ?? $c['field'] ?? '', $columns);
                $exportRows = [];
                foreach ($rows as $row) {
                    $exportRow = [];
                    foreach ($columns as $column) {
                        $field = $column['field'] ?? '';
                        $exportRow[] = $row[$field] ?? '';
                    }
                    $exportRows[] = $exportRow;
                }

                $this->xlsxWriter->addSheet($sheetName, $exportRows, [
                    'headers' => $headers,
                    'header_style' => true,
                ]);
                break;

            case 'leaderboard':
                $entries = $data['entries'] ?? $data['items'] ?? $data;
                $leaderboardRows = [];
                foreach ($entries as $index => $entry) {
                    $leaderboardRows[] = [
                        'Rank' => $index + 1,
                        'Name' => $entry['name'] ?? $entry['user']['name'] ?? 'Unknown',
                        'Value' => $entry['value'] ?? $entry['score'] ?? 0,
                    ];
                }
                $this->xlsxWriter->addSheet($sheetName, $leaderboardRows, [
                    'headers' => ['Rank', 'Name', 'Value'],
                    'header_style' => true,
                ]);
                break;

            case 'kpi':
            case 'goal_kpi':
                $kpiRows = [
                    ['Metric' => 'Current Value', 'Value' => $data['value'] ?? 0],
                ];
                if (isset($data['target'])) {
                    $kpiRows[] = ['Metric' => 'Target', 'Value' => $data['target']];
                }
                if (isset($data['change_percent'])) {
                    $kpiRows[] = ['Metric' => 'Change', 'Value' => $data['change_percent'] . '%'];
                }
                $this->xlsxWriter->addSheet($sheetName, $kpiRows, [
                    'headers' => ['Metric', 'Value'],
                    'header_style' => true,
                ]);
                break;

            case 'progress':
                $current = $data['current'] ?? $data['value'] ?? 0;
                $target = $data['target'] ?? 100;
                $percent = $target > 0 ? round(($current / $target) * 100, 1) : 0;

                $progressRows = [
                    ['Metric' => 'Current', 'Value' => $current],
                    ['Metric' => 'Target', 'Value' => $target],
                    ['Metric' => 'Progress', 'Value' => $percent . '%'],
                ];
                $this->xlsxWriter->addSheet($sheetName, $progressRows, [
                    'headers' => ['Metric', 'Value'],
                    'header_style' => true,
                ]);
                break;
        }
    }

    /**
     * Check if widget has exportable data.
     */
    protected function hasExportableData(array $widget): bool
    {
        $type = $widget['type'] ?? '';
        $exportableTypes = ['table', 'recent_records', 'leaderboard', 'kpi', 'goal_kpi', 'progress'];

        return in_array($type, $exportableTypes) && !empty($widget['data']);
    }

    /**
     * Format value based on type.
     */
    protected function formatValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            'currency' => is_numeric($value) ? round((float) $value, 2) : $value,
            'percent' => is_numeric($value) ? $value . '%' : $value,
            'date' => $value,
            'datetime' => $value,
            default => $value,
        };
    }

    /**
     * Sanitize sheet name (max 31 chars, no special chars).
     */
    protected function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\[\]\*\?\/\\\\:]/', '', $name);
        return mb_substr($name, 0, 31) ?: 'Sheet';
    }
}
