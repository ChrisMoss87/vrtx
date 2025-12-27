<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Pdf;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * PDF generation service using headless Chromium.
 *
 * Generates high-quality PDFs by rendering HTML through Chrome's print-to-PDF feature.
 * Requires Chrome/Chromium to be installed on the system.
 */
class ChromePdfService
{
    private string $chromePath;
    private string $tempDir;

    public function __construct()
    {
        $this->chromePath = $this->detectChromePath();
        $this->tempDir = storage_path('app/temp/pdf');

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Generate PDF from HTML string.
     */
    public function generateFromHtml(string $html, array $options = []): string
    {
        $tempHtmlPath = $this->tempDir . '/' . Str::uuid() . '.html';
        $tempPdfPath = $this->tempDir . '/' . Str::uuid() . '.pdf';

        try {
            // Write HTML to temp file
            file_put_contents($tempHtmlPath, $this->wrapHtml($html, $options));

            // Generate PDF using Chrome
            $this->runChrome($tempHtmlPath, $tempPdfPath, $options);

            // Read and return PDF content
            $pdfContent = file_get_contents($tempPdfPath);

            if ($pdfContent === false) {
                throw new \RuntimeException('Failed to read generated PDF');
            }

            return $pdfContent;
        } finally {
            // Cleanup temp files
            @unlink($tempHtmlPath);
            @unlink($tempPdfPath);
        }
    }

    /**
     * Generate PDF from a URL.
     */
    public function generateFromUrl(string $url, array $options = []): string
    {
        $tempPdfPath = $this->tempDir . '/' . Str::uuid() . '.pdf';

        try {
            $this->runChrome($url, $tempPdfPath, $options);

            $pdfContent = file_get_contents($tempPdfPath);

            if ($pdfContent === false) {
                throw new \RuntimeException('Failed to read generated PDF');
            }

            return $pdfContent;
        } finally {
            @unlink($tempPdfPath);
        }
    }

    /**
     * Run Chrome headless to generate PDF.
     */
    private function runChrome(string $source, string $outputPath, array $options): void
    {
        $paperSize = $options['paper_size'] ?? 'A4';
        $landscape = $options['landscape'] ?? false;
        $printBackground = $options['print_background'] ?? true;
        $margins = $options['margins'] ?? true;
        $scale = $options['scale'] ?? 1;
        $timeout = $options['timeout'] ?? 30;

        // Build Chrome arguments
        $args = [
            $this->chromePath,
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-software-rasterizer',
            '--disable-extensions',
            '--run-all-compositor-stages-before-draw',
            '--print-to-pdf=' . $outputPath,
            '--no-pdf-header-footer',
        ];

        if ($printBackground) {
            $args[] = '--print-to-pdf-no-header';
        }

        // Add paper size
        $paperDimensions = $this->getPaperDimensions($paperSize, $landscape);
        if ($paperDimensions) {
            // Chrome uses inches for paper size via virtual-time-budget workaround
            // We'll use the default A4 and rely on CSS @page for custom sizes
        }

        // Source (file:// URL or http URL)
        if (str_starts_with($source, '/') || str_starts_with($source, 'C:')) {
            $args[] = 'file://' . $source;
        } else {
            $args[] = $source;
        }

        $command = implode(' ', array_map('escapeshellarg', $args));

        Log::debug('Running Chrome PDF generation', ['command' => $command]);

        $result = Process::timeout($timeout)->run($command);

        if (!$result->successful() && !file_exists($outputPath)) {
            Log::error('Chrome PDF generation failed', [
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException('PDF generation failed: ' . $result->errorOutput());
        }

        if (!file_exists($outputPath)) {
            throw new \RuntimeException('PDF file was not created');
        }
    }

    /**
     * Wrap HTML with proper document structure and print styles.
     */
    private function wrapHtml(string $html, array $options): string
    {
        $paperSize = $options['paper_size'] ?? 'A4';
        $landscape = $options['landscape'] ?? false;
        $orientation = $landscape ? 'landscape' : 'portrait';

        // Check if HTML already has DOCTYPE
        if (stripos($html, '<!DOCTYPE') !== false || stripos($html, '<html') !== false) {
            // Inject print styles into existing HTML
            $printStyles = $this->getPrintStyles($paperSize, $orientation);

            if (stripos($html, '</head>') !== false) {
                return str_replace('</head>', $printStyles . '</head>', $html);
            }

            return $html;
        }

        // Wrap plain HTML content
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {$this->getPrintStyles($paperSize, $orientation)}
</head>
<body>
    {$html}
</body>
</html>
HTML;
    }

    /**
     * Get print-specific CSS styles.
     */
    private function getPrintStyles(string $paperSize, string $orientation): string
    {
        return <<<CSS
<style>
    @page {
        size: {$paperSize} {$orientation};
        margin: 15mm;
    }

    @media print {
        body {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .no-print {
            display: none !important;
        }

        .page-break {
            page-break-before: always;
        }

        .avoid-break {
            page-break-inside: avoid;
        }
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.5;
        color: #1f2937;
    }
</style>
CSS;
    }

    /**
     * Get paper dimensions for a given size.
     */
    private function getPaperDimensions(string $size, bool $landscape): ?array
    {
        $dimensions = [
            'A4' => ['width' => 210, 'height' => 297],
            'Letter' => ['width' => 216, 'height' => 279],
            'Legal' => ['width' => 216, 'height' => 356],
            'A3' => ['width' => 297, 'height' => 420],
            'A5' => ['width' => 148, 'height' => 210],
        ];

        if (!isset($dimensions[$size])) {
            return null;
        }

        $dim = $dimensions[$size];

        if ($landscape) {
            return ['width' => $dim['height'], 'height' => $dim['width']];
        }

        return $dim;
    }

    /**
     * Detect Chrome/Chromium executable path.
     */
    private function detectChromePath(): string
    {
        // Check environment variable first
        $envPath = env('CHROME_PATH');
        if ($envPath && is_executable($envPath)) {
            return $envPath;
        }

        // Common Chrome/Chromium paths
        $possiblePaths = [
            // Linux
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/snap/bin/chromium',
            // macOS
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            // Windows
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        ];

        foreach ($possiblePaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // Try which command on Unix systems
        if (PHP_OS_FAMILY !== 'Windows') {
            $result = Process::run('which chromium google-chrome chromium-browser 2>/dev/null | head -1');
            $path = trim($result->output());
            if ($path && is_executable($path)) {
                return $path;
            }
        }

        throw new \RuntimeException(
            'Chrome/Chromium not found. Please install Chrome or set CHROME_PATH environment variable.'
        );
    }

    /**
     * Check if Chrome is available.
     */
    public function isAvailable(): bool
    {
        try {
            $this->detectChromePath();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Get the detected Chrome path.
     */
    public function getChromePath(): string
    {
        return $this->chromePath;
    }
}
