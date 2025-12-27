<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Infrastructure\Services\Spreadsheet\CsvService;
use App\Infrastructure\Services\Spreadsheet\XlsxReader;
use Illuminate\Support\Facades\Storage;

class FileParser
{
    public function __construct(
        protected CsvService $csvService,
        protected XlsxReader $xlsxReader
    ) {}

    /**
     * Parse a file and return headers and preview rows.
     */
    public function parsePreview(string $filePath, string $fileType, int $previewRows = 10): array
    {
        $fullPath = Storage::disk('imports')->path($filePath);

        return match ($fileType) {
            'csv' => $this->csvService->preview($fullPath, $previewRows),
            'xlsx', 'xls' => $this->xlsxReader->preview($fullPath, $previewRows),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Get all data rows from a file (for import).
     */
    public function getAllRows(string $filePath, string $fileType): \Generator
    {
        $fullPath = Storage::disk('imports')->path($filePath);

        return match ($fileType) {
            'csv' => $this->csvService->readGenerator($fullPath),
            'xlsx', 'xls' => $this->xlsxReader->readGenerator($fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Count total rows in file.
     */
    public function countRows(string $filePath, string $fileType): int
    {
        $fullPath = Storage::disk('imports')->path($filePath);

        return match ($fileType) {
            'csv' => $this->csvService->countRows($fullPath),
            'xlsx', 'xls' => $this->xlsxReader->countRows($fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Detect CSV delimiter.
     */
    public function detectDelimiter(string $filePath): string
    {
        $fullPath = Storage::disk('imports')->path($filePath);
        return $this->csvService->detectDelimiter($fullPath);
    }

    /**
     * Get sheet names from Excel file.
     */
    public function getSheetNames(string $filePath): array
    {
        $fullPath = Storage::disk('imports')->path($filePath);
        return $this->xlsxReader->getSheetNames($fullPath);
    }
}
