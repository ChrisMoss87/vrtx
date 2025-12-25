<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Email\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Job to send a single email message.
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailMessage $message
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        try {
            $sent = $emailService->send($this->message);

            if ($sent) {
                Log::info('Email sent successfully via job', [
                    'message_id' => $this->message->id,
                    'to' => $this->message->to_emails,
                ]);
            } else {
                Log::warning('Email send returned false', [
                    'message_id' => $this->message->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Email send job failed', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Email send job permanently failed', [
            'message_id' => $this->message->id,
            'error' => $exception->getMessage(),
        ]);

        // Update message status to failed
        $this->message->update([
            'status' => EmailMessage::STATUS_FAILED,
            'failed_reason' => $exception->getMessage(),
        ]);
    }
}
