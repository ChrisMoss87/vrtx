<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        $emails = DB::table('email_messages')
            ->where('status', 'queued')
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
            DB::table('email_messages')
                ->where('id', $email->id)
                ->update(['status' => 'sending']);
        }
    }
}
