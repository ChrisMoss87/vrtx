<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\DB;

class ExcelExportService
{
    /**
     * Export a report to Excel.
     */
    public function exportReport(Report $report, array $data, array $options = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName($report->name));

        $currentRow = 1;

        // Add header
        $currentRow = $this->addReportHeader($sheet, $report, $currentRow);

        // Add summary if available
        if (!empty($data['summary'])) {
            $currentRow = $this->addSummarySection($sheet, $data['summary'], $currentRow);
        }

        // Add data table
        $rows = $data['rows'] ?? [];
        $columns = $data['columns'] ?? [];

        if (!empty($rows)) {
            $currentRow = $this->addDataTable($sheet, $rows, $columns, $currentRow);
        }

        // Auto-size columns
        $this->autoSizeColumns($sheet);

        return $this->generateOutput($spreadsheet);
    }

    /**
     * Export a dashboard to Excel with multiple sheets.
     */
    public function exportDashboard(Dashboard $dashboard, array $widgetData, array $options = []): string
    {
        $spreadsheet = new Spreadsheet();

        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        // Add overview sheet
        $overviewSheet = $spreadsheet->createSheet();
        $overviewSheet->setTitle('Overview');
        $this->addDashboardOverview($overviewSheet, $dashboard, $widgetData);

        // Add individual widget sheets for data widgets
        $sheetIndex = 1;
        foreach ($widgetData as $widget) {
            if ($this->hasExportableData($widget)) {
                $sheetName = $this->sanitizeSheetName($widget['title'] ?? "Widget {$sheetIndex}");
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($sheetName);
                $this->addWidgetSheet($sheet, $widget);
                $sheetIndex++;
            }
        }

        // Set active sheet to overview
        $spreadsheet->setActiveSheetIndex(0);

        return $this->generateOutput($spreadsheet);
    }

    /**
     * Add report header to sheet.
     */
    protected function addReportHeader($sheet, Report $report, int $startRow): int
    {
        $row = $startRow;

        // Report name
        $sheet->setCellValue("A{$row}", $report->name);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $row++;

        // Report type and date
        $sheet->setCellValue("A{$row}", ucfirst($report->type) . ' Report');
        $sheet->setCellValue("B{$row}", 'Generated: ' . now()->format('F j, Y'));
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('666666'));
        $row++;

        // Empty row
        $row++;

        return $row;
    }

    /**
     * Add summary section.
     */
    protected function addSummarySection($sheet, array $summary, int $startRow): int
    {
        $row = $startRow;

        $sheet->setCellValue("A{$row}", 'Summary');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $col = 1;
        foreach ($summary as $item) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$colLetter}{$row}", $item['label'] ?? '');
            $sheet->setCellValue("{$colLetter}" . ($row + 1), $item['value'] ?? '');
            $sheet->getStyle("{$colLetter}" . ($row + 1))->getFont()->setBold(true)->setSize(14);

            // Apply number format if needed
            if (isset($item['type'])) {
                $format = $this->getNumberFormat($item['type']);
                if ($format) {
                    $sheet->getStyle("{$colLetter}" . ($row + 1))->getNumberFormat()->setFormatCode($format);
                }
            }

            $col++;
        }

        $row += 3;
        return $row;
    }

    /**
     * Add data table to sheet.
     */
    protected function addDataTable($sheet, array $rows, array $columns, int $startRow): int
    {
        if (empty($rows)) {
            return $startRow;
        }

        $row = $startRow;

        // Determine columns from data if not provided
        if (empty($columns) && !empty($rows[0])) {
            $columns = array_map(
                fn($key) => ['field' => $key, 'label' => ucfirst(str_replace('_', ' ', $key))],
                array_keys($rows[0])
            );
        }

        // Header row
        $col = 1;
        foreach ($columns as $column) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$colLetter}{$row}", $column['label'] ?? $column['field'] ?? '');
            $col++;
        }

        // Style header row
        $lastCol = Coordinate::stringFromColumnIndex(count($columns));
        $headerRange = "A{$row}:{$lastCol}{$row}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '374151']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'E5E7EB']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;

        // Data rows
        foreach ($rows as $dataRow) {
            $col = 1;
            foreach ($columns as $column) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $field = $column['field'] ?? '';
                $value = $dataRow[$field] ?? '';

                $sheet->setCellValue("{$colLetter}{$row}", $value);

                // Apply number format
                if (isset($column['type'])) {
                    $format = $this->getNumberFormat($column['type']);
                    if ($format) {
                        $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode($format);
                    }
                }

                $col++;
            }
            $row++;
        }

        // Add borders to data range
        $dataRange = "A{$startRow}:{$lastCol}" . ($row - 1);
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);

        return $row + 1;
    }

    /**
     * Add dashboard overview sheet.
     */
    protected function addDashboardOverview($sheet, Dashboard $dashboard, array $widgetData): void
    {
        $row = 1;

        // Dashboard name
        $sheet->setCellValue("A{$row}", $dashboard->name);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(18);
        $row++;

        // Description
        if ($dashboard->description) {
            $sheet->setCellValue("A{$row}", $dashboard->description);
            $sheet->getStyle("A{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('666666'));
            $row++;
        }

        // Export date
        $sheet->setCellValue("A{$row}", 'Exported: ' . now()->format('F j, Y g:i A'));
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(10);
        $row += 2;

        // KPI Summary section
        $sheet->setCellValue("A{$row}", 'Key Metrics');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row++;

        // Add KPI widgets as summary
        $col = 1;
        foreach ($widgetData as $widget) {
            if (in_array($widget['type'] ?? '', ['kpi', 'goal_kpi'])) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $widget['title'] ?? '');
                $sheet->getStyle("{$colLetter}{$row}")->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('666666'));

                $value = $widget['data']['value'] ?? 0;
                $sheet->setCellValue("{$colLetter}" . ($row + 1), $value);
                $sheet->getStyle("{$colLetter}" . ($row + 1))->getFont()->setBold(true)->setSize(16);

                $col++;
            }
        }

        $this->autoSizeColumns($sheet);
    }

    /**
     * Add widget data to a sheet.
     */
    protected function addWidgetSheet($sheet, array $widget): void
    {
        $row = 1;

        // Widget title
        $sheet->setCellValue("A{$row}", $widget['title'] ?? 'Widget');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row += 2;

        $data = $widget['data'] ?? [];
        $type = $widget['type'] ?? '';

        switch ($type) {
            case 'table':
            case 'recent_records':
                $rows = $data['rows'] ?? $data['records'] ?? [];
                $columns = $data['columns'] ?? [];
                $this->addDataTable($sheet, $rows, $columns, $row);
                break;

            case 'leaderboard':
                $entries = $data['entries'] ?? $data['items'] ?? $data;
                $this->addLeaderboardData($sheet, $entries, $row);
                break;

            case 'kpi':
            case 'goal_kpi':
                $this->addKpiData($sheet, $data, $row);
                break;

            case 'progress':
                $this->addProgressData($sheet, $data, $row);
                break;

            default:
                $sheet->setCellValue("A{$row}", 'Data format not supported for Excel export');
                break;
        }

        $this->autoSizeColumns($sheet);
    }

    /**
     * Add leaderboard data to sheet.
     */
    protected function addLeaderboardData($sheet, array $entries, int $startRow): void
    {
        $row = $startRow;

        // Headers
        $sheet->setCellValue("A{$row}", 'Rank');
        $sheet->setCellValue("B{$row}", 'Name');
        $sheet->setCellValue("C{$row}", 'Value');
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:C{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F3F4F6');
        $row++;

        foreach ($entries as $index => $entry) {
            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $entry['name'] ?? $entry['user']['name'] ?? 'Unknown');
            $sheet->setCellValue("C{$row}", $entry['value'] ?? $entry['score'] ?? 0);
            $row++;
        }
    }

    /**
     * Add KPI data to sheet.
     */
    protected function addKpiData($sheet, array $data, int $startRow): void
    {
        $row = $startRow;

        $sheet->setCellValue("A{$row}", 'Current Value');
        $sheet->setCellValue("B{$row}", $data['value'] ?? 0);
        $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(16);
        $row++;

        if (isset($data['target'])) {
            $sheet->setCellValue("A{$row}", 'Target');
            $sheet->setCellValue("B{$row}", $data['target']);
            $row++;
        }

        if (isset($data['change_percent'])) {
            $sheet->setCellValue("A{$row}", 'Change');
            $change = $data['change_percent'];
            $sheet->setCellValue("B{$row}", ($change >= 0 ? '+' : '') . $change . '%');
            $sheet->getStyle("B{$row}")->getFont()->setColor(
                new \PhpOffice\PhpSpreadsheet\Style\Color($change >= 0 ? '10B981' : 'EF4444')
            );
            $row++;
        }

        if (isset($data['label'])) {
            $sheet->setCellValue("A{$row}", 'Metric');
            $sheet->setCellValue("B{$row}", $data['label']);
        }
    }

    /**
     * Add progress data to sheet.
     */
    protected function addProgressData($sheet, array $data, int $startRow): void
    {
        $row = $startRow;
        $current = $data['current'] ?? $data['value'] ?? 0;
        $target = $data['target'] ?? 100;
        $percent = $target > 0 ? round(($current / $target) * 100, 1) : 0;

        $sheet->setCellValue("A{$row}", 'Current');
        $sheet->setCellValue("B{$row}", $current);
        $row++;

        $sheet->setCellValue("A{$row}", 'Target');
        $sheet->setCellValue("B{$row}", $target);
        $row++;

        $sheet->setCellValue("A{$row}", 'Progress');
        $sheet->setCellValue("B{$row}", $percent / 100);
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
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
     * Get number format for column type.
     */
    protected function getNumberFormat(string $type): ?string
    {
        return match ($type) {
            'currency' => '"$"#,##0.00',
            'percent' => '0.0%',
            'number', 'integer' => '#,##0',
            'decimal', 'float' => '#,##0.00',
            'date' => 'MMM D, YYYY',
            'datetime' => 'MMM D, YYYY HH:MM',
            default => null,
        };
    }

    /**
     * Auto-size all columns in a sheet.
     */
    protected function autoSizeColumns($sheet): void
    {
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }

    /**
     * Sanitize sheet name (max 31 chars, no special chars).
     */
    protected function sanitizeSheetName(string $name): string
    {
        // Remove invalid characters
        $name = preg_replace('/[\[\]\*\?\/\\\\:]/', '', $name);

        // Truncate to 31 characters
        return mb_substr($name, 0, 31);
    }

    /**
     * Generate Excel output as string.
     */
    protected function generateOutput(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        // Clean up
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $content;
    }
}
