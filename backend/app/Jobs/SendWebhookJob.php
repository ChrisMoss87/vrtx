<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // We handle retries manually
    public int $timeout = 60;

    public function __construct(
        protected WebhookDelivery $delivery
    ) {}

    public function handle(): void
    {
        $delivery = $this->delivery->fresh(['webhook']);

        if (!$delivery || !$delivery->webhook) {
            Log::warning('Webhook delivery or webhook not found', [
                'delivery_id' => $this->delivery->id,
            ]);
            return;
        }

        $webhook = $delivery->webhook;

        if (!$webhook->is_active) {
            Log::info('Webhook is inactive, skipping delivery', [
                'webhook_id' => $webhook->id,
                'delivery_id' => $delivery->id,
            ]);
            return;
        }

        $payload = $delivery->payload;
        $signature = $webhook->sign($payload);

        $headers = array_merge(
            $webhook->headers ?? [],
            [
                'Content-Type' => 'application/json',
                'X-Webhook-ID' => (string) $webhook->id,
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Timestamp' => (string) time(),
                'User-Agent' => 'VRTX-CRM-Webhook/1.0',
            ]
        );

        $startTime = microtime(true);
        $attempts = $delivery->attempts + 1;

        try {
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout)
                ->withOptions([
                    'verify' => $webhook->verify_ssl,
                ])
                ->post($webhook->url, $payload);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $delivery->update(['attempts' => $attempts]);
                $delivery->markAsSuccess(
                    $response->status(),
                    $response->body(),
                    $responseTimeMs
                );

                Log::info('Webhook delivered successfully', [
                    'webhook_id' => $webhook->id,
                    'delivery_id' => $delivery->id,
                    'status_code' => $response->status(),
                    'response_time_ms' => $responseTimeMs,
                ]);
            } else {
                $delivery->markAsFailed(
                    $attempts,
                    $response->status(),
                    "HTTP {$response->status()}: " . substr($response->body(), 0, 500),
                    $responseTimeMs
                );

                Log::warning('Webhook delivery failed with non-success status', [
                    'webhook_id' => $webhook->id,
                    'delivery_id' => $delivery->id,
                    'status_code' => $response->status(),
                    'attempts' => $attempts,
                ]);

                // Schedule retry if eligible
                if ($delivery->canRetry()) {
                    $this->scheduleRetry($delivery);
                }
            }
        } catch (\Exception $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $delivery->markAsFailed(
                $attempts,
                null,
                $e->getMessage(),
                $responseTimeMs
            );

            Log::error('Webhook delivery failed with exception', [
                'webhook_id' => $webhook->id,
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
                'attempts' => $attempts,
            ]);

            // Schedule retry if eligible
            if ($delivery->canRetry()) {
                $this->scheduleRetry($delivery);
            }
        }
    }

    protected function scheduleRetry(WebhookDelivery $delivery): void
    {
        $webhook = $delivery->webhook;
        $delay = $webhook->retry_delay * pow(2, $delivery->attempts - 1);

        static::dispatch($delivery)->delay(now()->addSeconds($delay));

        Log::info('Webhook delivery scheduled for retry', [
            'webhook_id' => $webhook->id,
            'delivery_id' => $delivery->id,
            'retry_delay_seconds' => $delay,
            'attempt' => $delivery->attempts,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendWebhookJob failed permanently', [
            'delivery_id' => $this->delivery->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
