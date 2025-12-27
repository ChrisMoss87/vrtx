<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\RottingDealsDigest;
use App\Domain\User\Entities\User;
use App\Services\Rotting\DealRottingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SendRottingDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $frequency = 'daily'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DealRottingService $rottingService): void
    {
        Log::info("SendRottingDigestJob: Starting {$this->frequency} digest");

        // Get users who have enabled email digest with this frequency
        $settings = DB::table('rotting_alert_settings')->where('email_digest_enabled', true)
            ->where('digest_frequency', $this->frequency)
            ->with('user')
            ->get();

        // Also include users who have default settings (not explicitly set)
        // and should receive this frequency
        $usersWithExplicitSettings = $settings->pluck('user_id')->toArray();

        $emailsSent = 0;

        // Send to users with explicit settings
        foreach ($settings as $setting) {
            if (!$setting->user) {
                continue;
            }

            $sent = $this->sendDigestToUser($setting->user, $rottingService, $setting->pipeline_id);
            if ($sent) {
                $emailsSent++;
            }
        }

        // For daily frequency, also send to users without explicit settings
        // (since daily is the default)
        if ($this->frequency === 'daily') {
            $usersWithoutSettings = User::whereNotIn('id', $usersWithExplicitSettings)->get();

            foreach ($usersWithoutSettings as $user) {
                $sent = $this->sendDigestToUser($user, $rottingService);
                if ($sent) {
                    $emailsSent++;
                }
            }
        }

        Log::info("SendRottingDigestJob: Completed {$this->frequency} digest", [
            'emails_sent' => $emailsSent,
        ]);
    }

    /**
     * Send digest email to a specific user.
     */
    protected function sendDigestToUser(User $user, DealRottingService $rottingService, ?int $pipelineId = null): bool
    {
        $rottingDeals = $rottingService->getRottingDealsForUser($user->id, $pipelineId);

        // Don't send empty digests
        if ($rottingDeals->isEmpty()) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new RottingDealsDigest($user, $rottingDeals));
            return true;
        } catch (\Exception $e) {
            Log::error('SendRottingDigestJob: Failed to send email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendRottingDigestJob: Failed {$this->frequency} digest", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
