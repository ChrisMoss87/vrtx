<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class FileParser
{
    /**
     * Parse a file and return headers and preview rows.
     */
    public function parsePreview(string $filePath, string $fileType, int $previewRows = 10): array
    {
        $fullPath = Storage::disk('imports')->path($filePath);

        return match ($fileType) {
            'csv' => $this->parseCsvPreview($fullPath, $previewRows),
            'xlsx', 'xls' => $this->parseExcelPreview($fullPath, $previewRows),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Parse CSV file preview.
     */
    protected function parseCsvPreview(string $filePath, int $previewRows): array
    {
        $reader = new CsvReader();
        $reader->setDelimiter($this->detectDelimiter($filePath));
        $reader->setEnclosure('"');
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headers = [];
        $rows = [];
        $totalRows = 0;

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // Skip completely empty rows
            if (array_filter($rowData) === []) {
                continue;
            }

            if ($rowIndex === 1) {
                $headers = $rowData;
            } else {
                if (count($rows) < $previewRows) {
                    $rows[] = $rowData;
                }
                $totalRows++;
            }
        }

        return [
            'headers' => $headers,
            'preview_rows' => $rows,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Parse Excel file preview.
     */
    protected function parseExcelPreview(string $filePath, int $previewRows): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headers = [];
        $rows = [];
        $totalRows = 0;

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // Skip completely empty rows
            if (array_filter($rowData) === []) {
                continue;
            }

            if ($rowIndex === 1) {
                $headers = $rowData;
            } else {
                if (count($rows) < $previewRows) {
                    $rows[] = $rowData;
                }
                $totalRows++;
            }
        }

        return [
            'headers' => $headers,
            'preview_rows' => $rows,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Get all data rows from a file (for import).
     */
    public function getAllRows(string $filePath, string $fileType): \Generator
    {
        $fullPath = Storage::disk('imports')->path($filePath);

        return match ($fileType) {
            'csv' => $this->getCsvRows($fullPath),
            'xlsx', 'xls' => $this->getExcelRows($fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Get all CSV rows as generator.
     */
    protected function getCsvRows(string $filePath): \Generator
    {
        $reader = new CsvReader();
        $reader->setDelimiter($this->detectDelimiter($filePath));
        $reader->setEnclosure('"');
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headers = [];

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // Skip completely empty rows
            if (array_filter($rowData) === []) {
                continue;
            }

            if ($rowIndex === 1) {
                $headers = $rowData;
            } else {
                yield $rowIndex => array_combine($headers, array_pad($rowData, count($headers), null));
            }
        }
    }

    /**
     * Get all Excel rows as generator.
     */
    protected function getExcelRows(string $filePath): \Generator
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headers = [];

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // Skip completely empty rows
            if (array_filter($rowData) === []) {
                continue;
            }

            if ($rowIndex === 1) {
                $headers = $rowData;
            } else {
                yield $rowIndex => array_combine($headers, array_pad($rowData, count($headers), null));
            }
        }
    }

    /**
     * Detect CSV delimiter.
     */
    protected function detectDelimiter(string $filePath): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $handle = fopen($filePath, 'r');
        $firstLine = fgets($handle);
        fclose($handle);

        $counts = [];
        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($firstLine, $delimiter);
        }

        return array_search(max($counts), $counts);
    }

    /**
     * Count total rows in file.
     */
    public function countRows(string $filePath, string $fileType): int
    {
        $fullPath = Storage::disk('imports')->path($filePath);

        return match ($fileType) {
            'csv' => $this->countCsvRows($fullPath),
            'xlsx', 'xls' => $this->countExcelRows($fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Count CSV rows.
     */
    protected function countCsvRows(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');
        while (fgets($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return max(0, $count - 1); // Subtract header row
    }

    /**
     * Count Excel rows.
     */
    protected function countExcelRows(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        return max(0, $worksheet->getHighestRow() - 1); // Subtract header row
    }
}
