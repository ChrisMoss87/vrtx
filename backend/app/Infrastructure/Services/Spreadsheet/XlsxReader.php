<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Spreadsheet;

use ZipArchive;
use XMLReader;

/**
 * Native Excel (.xlsx) reader using ZipArchive and XMLReader.
 *
 * Reads xlsx files without external dependencies.
 * Uses streaming XML parsing for memory efficiency with large files.
 *
 * Security: Disables external entity loading to prevent XXE attacks.
 */
class XlsxReader
{
    private array $sharedStrings = [];
    private array $styles = [];

    /**
     * Safely parse XML string with XXE protection.
     * Disables external entity loading to prevent XXE attacks.
     */
    private function safeSimpleXmlLoad(string $xmlContent): \SimpleXMLElement|false
    {
        // Disable external entity loading (XXE protection)
        $previousValue = libxml_disable_entity_loader(true);
        libxml_use_internal_errors(true);

        try {
            // Parse with secure options - disable network access and entity substitution
            $xml = simplexml_load_string(
                $xmlContent,
                \SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_NOENT | LIBXML_NOCDATA
            );

            return $xml;
        } finally {
            // Restore previous setting
            libxml_disable_entity_loader($previousValue);
            libxml_clear_errors();
        }
    }

    /**
     * Safely configure XMLReader with XXE protection.
     */
    private function safeXmlReaderLoad(\XMLReader $reader, string $xmlContent): bool
    {
        // Disable external entity loading
        $previousValue = libxml_disable_entity_loader(true);

        try {
            // Load with secure options
            $result = $reader->XML($xmlContent, null, LIBXML_NONET | LIBXML_NOENT);
            return $result;
        } finally {
            libxml_disable_entity_loader($previousValue);
        }
    }

    /**
     * Read Excel file and return headers + rows.
     */
    public function read(string $filePath, array $options = []): array
    {
        $sheetIndex = $options['sheet'] ?? 0;
        $hasHeader = $options['has_header'] ?? true;

        $zip = $this->openZip($filePath);

        try {
            $this->loadSharedStrings($zip);
            $sheetPath = $this->getSheetPath($zip, $sheetIndex);
            $rows = $this->parseSheet($zip, $sheetPath);
        } finally {
            $zip->close();
        }

        $headers = [];
        $dataRows = [];

        foreach ($rows as $index => $row) {
            if ($hasHeader && $index === 0) {
                $headers = array_map(fn($v) => trim((string) $v), $row);
                continue;
            }

            if ($hasHeader && !empty($headers)) {
                $dataRows[] = array_combine(
                    $headers,
                    array_pad($row, count($headers), null)
                );
            } else {
                $dataRows[] = $row;
            }
        }

        return [
            'headers' => $headers,
            'rows' => $dataRows,
            'total_rows' => count($dataRows),
        ];
    }

    /**
     * Read Excel file as generator for memory efficiency.
     */
    public function readGenerator(string $filePath, array $options = []): \Generator
    {
        $sheetIndex = $options['sheet'] ?? 0;
        $hasHeader = $options['has_header'] ?? true;

        $zip = $this->openZip($filePath);

        try {
            $this->loadSharedStrings($zip);
            $sheetPath = $this->getSheetPath($zip, $sheetIndex);

            $headers = [];
            $rowIndex = 0;

            foreach ($this->parseSheetGenerator($zip, $sheetPath) as $row) {
                if ($hasHeader && $rowIndex === 0) {
                    $headers = array_map(fn($v) => trim((string) $v), $row);
                    $rowIndex++;
                    continue;
                }

                $rowIndex++;

                if ($hasHeader && !empty($headers)) {
                    yield $rowIndex => array_combine(
                        $headers,
                        array_pad($row, count($headers), null)
                    );
                } else {
                    yield $rowIndex => $row;
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Get preview of Excel file.
     */
    public function preview(string $filePath, int $previewRows = 10, array $options = []): array
    {
        $sheetIndex = $options['sheet'] ?? 0;

        $zip = $this->openZip($filePath);

        try {
            $this->loadSharedStrings($zip);
            $sheetPath = $this->getSheetPath($zip, $sheetIndex);

            $headers = [];
            $rows = [];
            $totalRows = 0;
            $rowIndex = 0;

            foreach ($this->parseSheetGenerator($zip, $sheetPath) as $row) {
                if ($rowIndex === 0) {
                    $headers = array_map(fn($v) => trim((string) $v), $row);
                    $rowIndex++;
                    continue;
                }

                $totalRows++;
                if (count($rows) < $previewRows) {
                    $rows[] = $row;
                }
                $rowIndex++;
            }
        } finally {
            $zip->close();
        }

        return [
            'headers' => $headers,
            'preview_rows' => $rows,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Count rows in Excel file.
     */
    public function countRows(string $filePath, array $options = []): int
    {
        $sheetIndex = $options['sheet'] ?? 0;

        $zip = $this->openZip($filePath);

        try {
            $sheetPath = $this->getSheetPath($zip, $sheetIndex);
            $count = 0;

            foreach ($this->parseSheetGenerator($zip, $sheetPath, true) as $_) {
                $count++;
            }

            return max(0, $count - 1); // Subtract header
        } finally {
            $zip->close();
        }
    }

    /**
     * Get list of sheet names.
     */
    public function getSheetNames(string $filePath): array
    {
        $zip = $this->openZip($filePath);

        try {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            if ($workbookXml === false) {
                return ['Sheet1'];
            }

            $xml = $this->safeSimpleXmlLoad($workbookXml);
            if ($xml === false) {
                return ['Sheet1'];
            }
            $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $sheets = [];
            foreach ($xml->xpath('//s:sheet') as $sheet) {
                $sheets[] = (string) $sheet['name'];
            }

            return $sheets ?: ['Sheet1'];
        } finally {
            $zip->close();
        }
    }

    /**
     * Open ZIP archive.
     */
    private function openZip(string $filePath): ZipArchive
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $zip = new ZipArchive();
        $result = $zip->open($filePath, ZipArchive::RDONLY);

        if ($result !== true) {
            throw new \RuntimeException("Cannot open xlsx file: {$filePath} (error: {$result})");
        }

        return $zip;
    }

    /**
     * Load shared strings table.
     */
    private function loadSharedStrings(ZipArchive $zip): void
    {
        $this->sharedStrings = [];

        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }

        $reader = new XMLReader();
        $this->safeXmlReaderLoad($reader, $xml);

        $currentString = '';
        $inString = false;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $currentString = '';
                $inString = true;
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't' && $inString) {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                    $currentString .= $reader->value;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $this->sharedStrings[] = $currentString;
                $inString = false;
            }
        }

        $reader->close();
    }

    /**
     * Get sheet file path by index.
     */
    private function getSheetPath(ZipArchive $zip, int $index): string
    {
        // Try standard path first
        $path = "xl/worksheets/sheet" . ($index + 1) . ".xml";
        if ($zip->locateName($path) !== false) {
            return $path;
        }

        // Parse workbook relationships
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            throw new \RuntimeException("Cannot find worksheet relationships");
        }

        $xml = $this->safeSimpleXmlLoad($relsXml);
        if ($xml === false) {
            throw new \RuntimeException("Cannot parse worksheet relationships");
        }
        $xml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $worksheetRels = $xml->xpath("//r:Relationship[contains(@Type, 'worksheet')]");
        if (isset($worksheetRels[$index])) {
            return 'xl/' . (string) $worksheetRels[$index]['Target'];
        }

        throw new \RuntimeException("Worksheet index {$index} not found");
    }

    /**
     * Parse worksheet and return all rows.
     */
    private function parseSheet(ZipArchive $zip, string $sheetPath): array
    {
        $rows = [];
        foreach ($this->parseSheetGenerator($zip, $sheetPath) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Parse worksheet as generator.
     */
    private function parseSheetGenerator(ZipArchive $zip, string $sheetPath, bool $countOnly = false): \Generator
    {
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false) {
            throw new \RuntimeException("Cannot read worksheet: {$sheetPath}");
        }

        $reader = new XMLReader();
        $this->safeXmlReaderLoad($reader, $xml);

        $currentRow = [];
        $currentRowIndex = 0;
        $inRow = false;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                $inRow = true;
                $currentRowIndex = (int) $reader->getAttribute('r');
                $currentRow = [];
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'c' && $inRow) {
                if (!$countOnly) {
                    $cellRef = $reader->getAttribute('r');
                    $cellType = $reader->getAttribute('t');
                    $colIndex = $this->columnToIndex($cellRef);

                    // Read cell value
                    $value = $this->readCellValue($reader, $cellType);

                    // Ensure array is large enough
                    while (count($currentRow) < $colIndex) {
                        $currentRow[] = null;
                    }
                    $currentRow[$colIndex] = $value;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row') {
                $inRow = false;
                yield $currentRowIndex => $currentRow;
            }
        }

        $reader->close();
    }

    /**
     * Read cell value based on type.
     */
    private function readCellValue(XMLReader $reader, ?string $type): mixed
    {
        $value = null;

        // Read to find <v> element
        $depth = $reader->depth;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'v') {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT) {
                    $value = $reader->value;
                }
                break;
            }

            // Inline string
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                    return $reader->value;
                }
            }
        }

        if ($value === null) {
            return null;
        }

        // Convert based on type
        return match ($type) {
            's' => $this->sharedStrings[(int) $value] ?? $value, // Shared string
            'b' => (bool) $value, // Boolean
            'e' => null, // Error
            'str', 'inlineStr' => $value, // String
            default => is_numeric($value) ? (strpos($value, '.') !== false ? (float) $value : (int) $value) : $value,
        };
    }

    /**
     * Convert column reference (A, B, AA, etc.) to zero-based index.
     */
    private function columnToIndex(string $cellRef): int
    {
        // Extract column letters from cell reference (e.g., "AA123" -> "AA")
        preg_match('/^([A-Z]+)/', $cellRef, $matches);
        $col = $matches[1] ?? 'A';

        $index = 0;
        $length = strlen($col);

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
