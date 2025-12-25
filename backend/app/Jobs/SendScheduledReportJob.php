<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Reporting\ReportService;
use App\Services\Reporting\PdfExportService;
use App\Services\Reporting\ExcelExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SendScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected int $reportId
    ) {}

    public function handle(
        ReportService $reportService,
        PdfExportService $pdfExportService,
        ExcelExportService $excelExportService
    ): void {
        $report = Report::find($this->reportId);

        if (!$report) {
            Log::warning("Scheduled report not found: {$this->reportId}");
            return;
        }

        $schedule = $report->schedule;
        if (!$schedule || !($schedule['enabled'] ?? false)) {
            Log::info("Report schedule not enabled: {$this->reportId}");
            return;
        }

        $recipients = $schedule['recipients'] ?? [];
        if (empty($recipients)) {
            Log::warning("No recipients for scheduled report: {$this->reportId}");
            return;
        }

        $format = $schedule['format'] ?? 'pdf';

        try {
            // Execute the report
            $reportData = $reportService->executeReport($report);

            // Generate export based on format
            $content = match ($format) {
                'pdf' => $pdfExportService->exportReport($report, $reportData),
                'xlsx', 'excel' => $excelExportService->exportReport($report, $reportData),
                'csv' => $reportService->exportReport($report, 'csv'),
                default => $pdfExportService->exportReport($report, $reportData),
            };

            // Generate filename
            $extension = match ($format) {
                'pdf' => 'pdf',
                'xlsx', 'excel' => 'xlsx',
                'csv' => 'csv',
                default => 'pdf',
            };
            $filename = str_replace(' ', '_', $report->name) . '_' . now()->format('Y-m-d') . '.' . $extension;

            // Store temporarily
            $tempPath = 'temp/reports/' . $filename;
            Storage::put($tempPath, $content);

            // Send email to each recipient
            foreach ($recipients as $email) {
                $this->sendReportEmail($report, $email, $tempPath, $filename, $format);
            }

            // Clean up temp file
            Storage::delete($tempPath);

            // Update last run timestamp
            $report->update(['last_run_at' => now()]);

            Log::info("Scheduled report sent successfully", [
                'report_id' => $this->reportId,
                'report_name' => $report->name,
                'recipients' => count($recipients),
                'format' => $format,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send scheduled report", [
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function sendReportEmail(Report $report, string $email, string $attachmentPath, string $filename, string $format): void
    {
        $contentType = match ($format) {
            'pdf' => 'application/pdf',
            'xlsx', 'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            default => 'application/pdf',
        };

        Mail::send([], [], function ($message) use ($report, $email, $attachmentPath, $filename, $contentType) {
            $message->to($email)
                ->subject("Scheduled Report: {$report->name}")
                ->html($this->getEmailBody($report))
                ->attach(Storage::path($attachmentPath), [
                    'as' => $filename,
                    'mime' => $contentType,
                ]);
        });
    }

    protected function getEmailBody(Report $report): string
    {
        $reportName = htmlspecialchars($report->name);
        $reportDescription = $report->description ? htmlspecialchars($report->description) : '';
        $generatedAt = now()->format('F j, Y g:i A');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3b82f6; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f9fafb; padding: 20px; border-radius: 0 0 8px 8px; }
        .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$reportName}</h1>
        </div>
        <div class="content">
            <p>Your scheduled report is attached to this email.</p>
            {$reportDescription}
            <p><strong>Generated:</strong> {$generatedAt}</p>
        </div>
        <div class="footer">
            <p>This is an automated report from VRTX CRM.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    public function tags(): array
    {
        return ['scheduled-report', 'report:' . $this->reportId];
    }
}
