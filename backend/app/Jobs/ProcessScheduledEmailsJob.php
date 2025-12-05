<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process scheduled emails that are due to be sent.
 * This job should be run periodically via the scheduler.
 */
class ProcessScheduledEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Find all queued emails that are scheduled to be sent now or in the past
        $emails = EmailMessage::where('status', EmailMessage::STATUS_QUEUED)
            ->where(function ($query) {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->limit(100) // Process in batches
            ->get();

        Log::info('Processing scheduled emails', [
            'count' => $emails->count(),
        ]);

        foreach ($emails as $email) {
            // Dispatch each email to be sent
            SendEmailJob::dispatch($email);

            // Update status to indicate it's being processed
            $email->update([
                'status' => EmailMessage::STATUS_SENDING,
            ]);
        }
    }
}
