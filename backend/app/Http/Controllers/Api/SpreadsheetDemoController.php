<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Services\Spreadsheet\CsvService;
use App\Infrastructure\Services\Spreadsheet\XlsxReader;
use App\Infrastructure\Services\Spreadsheet\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SpreadsheetDemoController extends Controller
{
    public function __construct(
        protected CsvService $csvService,
        protected XlsxReader $xlsxReader,
        protected XlsxWriter $xlsxWriter
    ) {}

    /**
     * Generate sample data for demo.
     */
    public function sampleData(): JsonResponse
    {
        $data = $this->generateSampleData(25);

        return response()->json([
            'success' => true,
            'data' => $data,
            'columns' => [
                ['field' => 'id', 'label' => 'ID', 'type' => 'number'],
                ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['field' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['field' => 'company', 'label' => 'Company', 'type' => 'text'],
                ['field' => 'revenue', 'label' => 'Revenue', 'type' => 'currency'],
                ['field' => 'status', 'label' => 'Status', 'type' => 'text'],
                ['field' => 'created_at', 'label' => 'Created', 'type' => 'date'],
            ],
        ]);
    }

    /**
     * Export data to CSV.
     */
    public function exportCsv(Request $request): Response
    {
        $rows = $request->input('rows', $this->generateSampleData(100));

        $headers = ['ID', 'Name', 'Email', 'Company', 'Revenue', 'Status', 'Created'];

        $exportRows = array_map(fn($row) => [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['company'],
            $row['revenue'],
            $row['status'],
            $row['created_at'],
        ], $rows);

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        $this->csvService->write($tempFile, $exportRows, ['headers' => $headers]);

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="export-' . date('Y-m-d-His') . '.csv"');
    }

    /**
     * Export data to Excel.
     */
    public function exportExcel(Request $request): Response
    {
        $rows = $request->input('rows', $this->generateSampleData(100));

        $headers = ['ID', 'Name', 'Email', 'Company', 'Revenue', 'Status', 'Created'];

        $exportRows = array_map(fn($row) => [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['company'],
            $row['revenue'],
            $row['status'],
            $row['created_at'],
        ], $rows);

        $this->xlsxWriter->reset();
        $this->xlsxWriter->addSheet('Data', $exportRows, [
            'headers' => $headers,
            'header_style' => true,
        ]);

        // Add summary sheet
        $summaryRows = [
            ['Total Records', count($rows)],
            ['Total Revenue', array_sum(array_column($rows, 'revenue'))],
            ['Export Date', date('Y-m-d H:i:s')],
        ];
        $this->xlsxWriter->addSheet('Summary', $summaryRows, [
            'headers' => ['Metric', 'Value'],
            'header_style' => true,
        ]);

        $content = $this->xlsxWriter->generate();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="export-' . date('Y-m-d-His') . '.xlsx"');
    }

    /**
     * Parse uploaded file and return preview.
     */
    public function parseFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,txt',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('temp', uniqid() . '.' . $extension);
        $fullPath = Storage::path($path);

        try {
            $result = match ($extension) {
                'csv', 'txt' => $this->csvService->preview($fullPath, 10),
                'xlsx', 'xls' => $this->xlsxReader->preview($fullPath, 10),
                default => throw new \InvalidArgumentException("Unsupported file type: {$extension}"),
            };

            return response()->json([
                'success' => true,
                'filename' => $file->getClientOriginalName(),
                'file_type' => $extension,
                'headers' => $result['headers'],
                'preview_rows' => $result['preview_rows'],
                'total_rows' => $result['total_rows'],
            ]);
        } finally {
            Storage::delete($path);
        }
    }

    /**
     * Test Excel round-trip (write and read back).
     */
    public function testRoundTrip(): JsonResponse
    {
        $originalData = $this->generateSampleData(10);

        // Write to Excel
        $this->xlsxWriter->reset();
        $headers = ['ID', 'Name', 'Email', 'Company', 'Revenue', 'Status', 'Created'];
        $rows = array_map(fn($row) => array_values($row), $originalData);

        $this->xlsxWriter->addSheet('Test', $rows, ['headers' => $headers]);

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        $this->xlsxWriter->save($tempFile);

        // Read it back
        $readResult = $this->xlsxReader->read($tempFile);

        @unlink($tempFile);

        return response()->json([
            'success' => true,
            'original_count' => count($originalData),
            'read_back_count' => count($readResult['rows']),
            'headers_match' => $readResult['headers'] === $headers,
            'original_sample' => array_slice($originalData, 0, 3),
            'read_back_sample' => array_slice($readResult['rows'], 0, 3),
        ]);
    }

    /**
     * Benchmark: compare performance.
     */
    public function benchmark(Request $request): JsonResponse
    {
        $rowCount = min((int) $request->input('rows', 1000), 10000);
        $data = $this->generateSampleData($rowCount);

        $results = [];

        // CSV Write benchmark
        $start = microtime(true);
        $tempCsv = tempnam(sys_get_temp_dir(), 'bench_csv_');
        $headers = ['ID', 'Name', 'Email', 'Company', 'Revenue', 'Status', 'Created'];
        $rows = array_map(fn($row) => array_values($row), $data);
        $this->csvService->write($tempCsv, $rows, ['headers' => $headers]);
        $results['csv_write'] = [
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
            'file_size' => filesize($tempCsv),
        ];

        // CSV Read benchmark
        $start = microtime(true);
        $csvRead = $this->csvService->read($tempCsv);
        $results['csv_read'] = [
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
            'rows_read' => count($csvRead['rows']),
        ];
        @unlink($tempCsv);

        // Excel Write benchmark
        $start = microtime(true);
        $tempXlsx = tempnam(sys_get_temp_dir(), 'bench_xlsx_');
        $this->xlsxWriter->reset();
        $this->xlsxWriter->addSheet('Data', $rows, ['headers' => $headers]);
        $this->xlsxWriter->save($tempXlsx);
        $results['xlsx_write'] = [
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
            'file_size' => filesize($tempXlsx),
        ];

        // Excel Read benchmark
        $start = microtime(true);
        $xlsxRead = $this->xlsxReader->read($tempXlsx);
        $results['xlsx_read'] = [
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
            'rows_read' => count($xlsxRead['rows']),
        ];
        @unlink($tempXlsx);

        // Memory usage
        $results['memory'] = [
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];

        return response()->json([
            'success' => true,
            'row_count' => $rowCount,
            'results' => $results,
        ]);
    }

    /**
     * Generate sample data.
     */
    protected function generateSampleData(int $count): array
    {
        $companies = ['Acme Corp', 'TechStart Inc', 'Global Systems', 'DataFlow Ltd', 'CloudBase Co'];
        $statuses = ['Active', 'Pending', 'Inactive', 'Lead', 'Customer'];
        $firstNames = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Diana', 'Eve', 'Frank'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];

        $data = [];
        for ($i = 1; $i <= $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $data[] = [
                'id' => $i,
                'name' => "{$firstName} {$lastName}",
                'email' => strtolower("{$firstName}.{$lastName}") . $i . '@example.com',
                'company' => $companies[array_rand($companies)],
                'revenue' => rand(1000, 100000),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => date('Y-m-d', strtotime("-" . rand(1, 365) . " days")),
            ];
        }

        return $data;
    }
}
