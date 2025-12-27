<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Spreadsheet;

/**
 * Native CSV reader/writer service.
 *
 * Uses PHP's built-in fgetcsv/fputcsv for maximum performance.
 *
 * Security: Includes formula injection prevention for exported CSV files.
 */
class CsvService
{
    /**
     * Characters that can trigger formula execution in Excel/LibreOffice.
     */
    private const FORMULA_TRIGGER_CHARS = ['=', '+', '-', '@', "\t", "\r", "\n"];

    /**
     * Escape a cell value to prevent CSV formula injection.
     * Prepends a single quote to values starting with formula trigger characters.
     */
    private function escapeFormulaInjection(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $firstChar = $value[0];

        // Check if the value starts with a formula trigger character
        if (in_array($firstChar, self::FORMULA_TRIGGER_CHARS, true)) {
            // Prepend single quote to neutralize formula execution
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Sanitize an array of values for CSV export.
     */
    private function sanitizeRow(array $row): array
    {
        return array_map([$this, 'escapeFormulaInjection'], $row);
    }

    /**
     * Read CSV file and return headers + rows.
     */
    public function read(string $filePath, array $options = []): array
    {
        $delimiter = $options['delimiter'] ?? $this->detectDelimiter($filePath);
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $hasHeader = $options['has_header'] ?? true;

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        $headers = [];
        $rows = [];

        try {
            $lineNumber = 0;
            while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                // Skip empty rows
                if ($row === [null] || $row === ['']) {
                    continue;
                }

                $lineNumber++;

                if ($hasHeader && $lineNumber === 1) {
                    $headers = array_map('trim', $row);
                    continue;
                }

                if ($hasHeader && !empty($headers)) {
                    // Map row to associative array
                    $rows[] = array_combine(
                        $headers,
                        array_pad($row, count($headers), null)
                    );
                } else {
                    $rows[] = $row;
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => count($rows),
        ];
    }

    /**
     * Read CSV file as generator for memory efficiency with large files.
     */
    public function readGenerator(string $filePath, array $options = []): \Generator
    {
        $delimiter = $options['delimiter'] ?? $this->detectDelimiter($filePath);
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $hasHeader = $options['has_header'] ?? true;

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            $headers = [];
            $lineNumber = 0;

            while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                if ($row === [null] || $row === ['']) {
                    continue;
                }

                $lineNumber++;

                if ($hasHeader && $lineNumber === 1) {
                    $headers = array_map('trim', $row);
                    continue;
                }

                if ($hasHeader && !empty($headers)) {
                    yield $lineNumber => array_combine(
                        $headers,
                        array_pad($row, count($headers), null)
                    );
                } else {
                    yield $lineNumber => $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get preview of CSV file (headers + first N rows).
     */
    public function preview(string $filePath, int $previewRows = 10, array $options = []): array
    {
        $delimiter = $options['delimiter'] ?? $this->detectDelimiter($filePath);
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        $headers = [];
        $rows = [];
        $totalRows = 0;

        try {
            $lineNumber = 0;
            while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                if ($row === [null] || $row === ['']) {
                    continue;
                }

                $lineNumber++;

                if ($lineNumber === 1) {
                    $headers = array_map('trim', $row);
                    continue;
                }

                $totalRows++;
                if (count($rows) < $previewRows) {
                    $rows[] = $row;
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'preview_rows' => $rows,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Write data to CSV file.
     */
    public function write(string $filePath, array $rows, array $options = []): int
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $headers = $options['headers'] ?? [];
        $includeHeaders = $options['include_headers'] ?? true;

        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$filePath}");
        }

        $rowCount = 0;

        try {
            // Write headers
            if ($includeHeaders && !empty($headers)) {
                fputcsv($handle, $headers, $delimiter, $enclosure, $escape);
            } elseif ($includeHeaders && !empty($rows)) {
                // Extract headers from first row if associative
                $firstRow = reset($rows);
                if (is_array($firstRow) && array_keys($firstRow) !== range(0, count($firstRow) - 1)) {
                    fputcsv($handle, array_keys($firstRow), $delimiter, $enclosure, $escape);
                }
            }

            // Write rows with formula injection prevention
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $sanitizedRow = $this->sanitizeRow(array_values($row));
                    fputcsv($handle, $sanitizedRow, $delimiter, $enclosure, $escape);
                    $rowCount++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $rowCount;
    }

    /**
     * Write data to CSV using a generator for memory efficiency.
     */
    public function writeFromGenerator(string $filePath, \Generator $rows, array $options = []): int
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $headers = $options['headers'] ?? [];

        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$filePath}");
        }

        $rowCount = 0;
        $headersWritten = empty($headers);

        try {
            if (!empty($headers)) {
                fputcsv($handle, $headers, $delimiter, $enclosure, $escape);
            }

            foreach ($rows as $row) {
                if (!$headersWritten && is_array($row)) {
                    $keys = array_keys($row);
                    if ($keys !== range(0, count($keys) - 1)) {
                        fputcsv($handle, $keys, $delimiter, $enclosure, $escape);
                    }
                    $headersWritten = true;
                }

                if (is_array($row)) {
                    $sanitizedRow = $this->sanitizeRow(array_values($row));
                    fputcsv($handle, $sanitizedRow, $delimiter, $enclosure, $escape);
                    $rowCount++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $rowCount;
    }

    /**
     * Stream CSV directly to output.
     */
    public function stream(array $rows, array $options = []): void
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $headers = $options['headers'] ?? [];

        $output = fopen('php://output', 'w');

        if (!empty($headers)) {
            fputcsv($output, $headers, $delimiter, $enclosure, $escape);
        } elseif (!empty($rows)) {
            $firstRow = reset($rows);
            if (is_array($firstRow) && array_keys($firstRow) !== range(0, count($firstRow) - 1)) {
                fputcsv($output, array_keys($firstRow), $delimiter, $enclosure, $escape);
            }
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                $sanitizedRow = $this->sanitizeRow(array_values($row));
                fputcsv($output, $sanitizedRow, $delimiter, $enclosure, $escape);
            }
        }

        fclose($output);
    }

    /**
     * Count rows in CSV file.
     */
    public function countRows(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            while (fgets($handle) !== false) {
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return max(0, $count - 1); // Subtract header row
    }

    /**
     * Detect CSV delimiter by analyzing first line.
     */
    public function detectDelimiter(string $filePath): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return ',';
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return ',';
        }

        $counts = [];
        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($firstLine, $delimiter);
        }

        return array_search(max($counts), $counts) ?: ',';
    }
}
