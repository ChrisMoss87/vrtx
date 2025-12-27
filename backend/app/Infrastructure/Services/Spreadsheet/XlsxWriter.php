<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Spreadsheet;

use ZipArchive;

/**
 * Native Excel (.xlsx) writer using ZipArchive.
 *
 * Creates valid xlsx files without external dependencies.
 * Supports multiple sheets, basic styling, and large datasets.
 */
class XlsxWriter
{
    private array $sheets = [];
    private array $sharedStrings = [];
    private array $sharedStringsIndex = [];
    private array $styles = [];
    private string $creator = 'VRTX CRM';

    /**
     * Add a worksheet.
     */
    public function addSheet(string $name, array $rows, array $options = []): self
    {
        $this->sheets[] = [
            'name' => $this->sanitizeSheetName($name),
            'rows' => $rows,
            'headers' => $options['headers'] ?? [],
            'column_widths' => $options['column_widths'] ?? [],
            'header_style' => $options['header_style'] ?? true,
        ];

        return $this;
    }

    /**
     * Add a worksheet from generator for memory efficiency.
     */
    public function addSheetFromGenerator(string $name, \Generator $rows, array $options = []): self
    {
        // Convert generator to array (for simplicity in this implementation)
        // For very large files, you'd want to stream directly
        $this->sheets[] = [
            'name' => $this->sanitizeSheetName($name),
            'rows' => iterator_to_array($rows),
            'headers' => $options['headers'] ?? [],
            'column_widths' => $options['column_widths'] ?? [],
            'header_style' => $options['header_style'] ?? true,
        ];

        return $this;
    }

    /**
     * Set document creator.
     */
    public function setCreator(string $creator): self
    {
        $this->creator = $creator;
        return $this;
    }

    /**
     * Save to file.
     */
    public function save(string $filePath): void
    {
        $content = $this->generate();
        file_put_contents($filePath, $content);
    }

    /**
     * Generate xlsx content as string.
     */
    public function generate(): string
    {
        if (empty($this->sheets)) {
            throw new \RuntimeException('No sheets added to workbook');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create xlsx file');
        }

        try {
            // Build shared strings from all sheets
            $this->buildSharedStrings();

            // Add required files
            $zip->addFromString('[Content_Types].xml', $this->getContentTypes());
            $zip->addFromString('_rels/.rels', $this->getRels());
            $zip->addFromString('docProps/app.xml', $this->getAppProps());
            $zip->addFromString('docProps/core.xml', $this->getCoreProps());
            $zip->addFromString('xl/workbook.xml', $this->getWorkbook());
            $zip->addFromString('xl/styles.xml', $this->getStyles());
            $zip->addFromString('xl/sharedStrings.xml', $this->getSharedStrings());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRels());

            // Add worksheets
            foreach ($this->sheets as $index => $sheet) {
                $zip->addFromString(
                    "xl/worksheets/sheet" . ($index + 1) . ".xml",
                    $this->getSheetXml($sheet)
                );
            }

            $zip->close();

            $content = file_get_contents($tempFile);
            return $content;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Stream xlsx to output.
     */
    public function stream(): void
    {
        echo $this->generate();
    }

    /**
     * Build shared strings table from all sheets.
     */
    private function buildSharedStrings(): void
    {
        $this->sharedStrings = [];
        $this->sharedStringsIndex = [];

        foreach ($this->sheets as $sheet) {
            // Add headers
            foreach ($sheet['headers'] as $header) {
                $this->addSharedString((string) $header);
            }

            // Add row values
            foreach ($sheet['rows'] as $row) {
                foreach ($row as $value) {
                    if (is_string($value) && $value !== '' && !is_numeric($value)) {
                        $this->addSharedString($value);
                    }
                }
            }
        }
    }

    /**
     * Add string to shared strings table.
     */
    private function addSharedString(string $value): int
    {
        if (!isset($this->sharedStringsIndex[$value])) {
            $this->sharedStringsIndex[$value] = count($this->sharedStrings);
            $this->sharedStrings[] = $value;
        }

        return $this->sharedStringsIndex[$value];
    }

    /**
     * Get shared string index.
     */
    private function getSharedStringIndex(string $value): int
    {
        return $this->sharedStringsIndex[$value] ?? $this->addSharedString($value);
    }

    /**
     * Generate [Content_Types].xml
     */
    private function getContentTypes(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $_) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
    ' . $sheets . '
</Types>';
    }

    /**
     * Generate _rels/.rels
     */
    private function getRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>';
    }

    /**
     * Generate docProps/app.xml
     */
    private function getAppProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
    <Application>' . $this->escape($this->creator) . '</Application>
</Properties>';
    }

    /**
     * Generate docProps/core.xml
     */
    private function getCoreProps(): string
    {
        $now = date('c');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>' . $this->escape($this->creator) . '</dc:creator>
    <dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>
</cp:coreProperties>';
    }

    /**
     * Generate xl/workbook.xml
     */
    private function getWorkbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="' . $this->escape($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>' . $sheets . '</sheets>
</workbook>';
    }

    /**
     * Generate xl/styles.xml
     */
    private function getStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border/>
        <border><bottom style="thin"><color rgb="FFE5E7EB"/></bottom></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="3">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
    </cellXfs>
</styleSheet>';
    }

    /**
     * Generate xl/sharedStrings.xml
     */
    private function getSharedStrings(): string
    {
        $count = count($this->sharedStrings);
        $strings = '';

        foreach ($this->sharedStrings as $string) {
            $strings .= '<si><t>' . $this->escape($string) . '</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">' . $strings . '</sst>';
    }

    /**
     * Generate xl/_rels/workbook.xml.rels
     */
    private function getWorkbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $index => $_) {
            $rels .= '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
        }

        $nextId = count($this->sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    ' . $rels . '
    <Relationship Id="rId' . $nextId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId' . ($nextId + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
    }

    /**
     * Generate worksheet XML.
     */
    private function getSheetXml(array $sheet): string
    {
        $rows = $sheet['rows'];
        $headers = $sheet['headers'];
        $headerStyle = $sheet['header_style'];
        $columnWidths = $sheet['column_widths'];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        // Column widths
        if (!empty($columnWidths) || !empty($headers)) {
            $colCount = max(count($headers), !empty($rows) ? count(reset($rows)) : 0);
            $xml .= '<cols>';
            for ($i = 1; $i <= $colCount; $i++) {
                $width = $columnWidths[$i - 1] ?? 15;
                $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        $rowNum = 1;

        // Write headers
        if (!empty($headers)) {
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($headers as $colIndex => $header) {
                $colRef = $this->indexToColumn($colIndex) . $rowNum;
                $stringIndex = $this->getSharedStringIndex((string) $header);
                $style = $headerStyle ? ' s="1"' : '';
                $xml .= '<c r="' . $colRef . '" t="s"' . $style . '><v>' . $stringIndex . '</v></c>';
            }
            $xml .= '</row>';
            $rowNum++;
        }

        // Write data rows
        foreach ($rows as $row) {
            $rowData = is_array($row) ? array_values($row) : [$row];
            $xml .= '<row r="' . $rowNum . '">';

            foreach ($rowData as $colIndex => $value) {
                $colRef = $this->indexToColumn($colIndex) . $rowNum;
                $xml .= $this->getCellXml($colRef, $value);
            }

            $xml .= '</row>';
            $rowNum++;
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /**
     * Generate cell XML.
     */
    private function getCellXml(string $ref, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return '<c r="' . $ref . '" t="b"><v>' . ($value ? '1' : '0') . '</v></c>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
        }

        if (is_numeric($value)) {
            return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
        }

        // String value - use shared strings
        $stringIndex = $this->getSharedStringIndex((string) $value);
        return '<c r="' . $ref . '" t="s"><v>' . $stringIndex . '</v></c>';
    }

    /**
     * Convert zero-based column index to Excel column letter.
     */
    private function indexToColumn(int $index): string
    {
        $column = '';

        while ($index >= 0) {
            $column = chr(($index % 26) + ord('A')) . $column;
            $index = intval($index / 26) - 1;
        }

        return $column;
    }

    /**
     * Sanitize sheet name (max 31 chars, no special chars).
     */
    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\[\]\*\?\/\\\\:]/', '', $name);
        return mb_substr($name, 0, 31) ?: 'Sheet';
    }

    /**
     * Escape XML special characters.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Reset writer for reuse.
     */
    public function reset(): self
    {
        $this->sheets = [];
        $this->sharedStrings = [];
        $this->sharedStringsIndex = [];

        return $this;
    }
}
