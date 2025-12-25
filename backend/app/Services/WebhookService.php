<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendWebhookJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Trigger webhooks for a record event.
     */
    public function triggerForRecord(string $event, object $record, ?array $previousData = null): void
    {
        $moduleId = $record->module_id ?? null;
        $fullEvent = "record.{$event}";

        // Find all active webhooks that listen to this event
        $webhooks = DB::table('webhooks')
            ->where('is_active', true)
            ->where(function ($query) use ($moduleId) {
                $query->whereNull('module_id')
                    ->orWhere('module_id', $moduleId);
            })
            ->get()
            ->filter(function ($webhook) use ($fullEvent) {
                $events = json_decode($webhook->events ?? '[]', true);
                return in_array($fullEvent, $events);
            });

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $this->buildRecordPayload($fullEvent, $record, $previousData);

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $fullEvent, $payload);
        }
    }

    /**
     * Trigger webhooks for a module event.
     */
    public function triggerForModule(string $event, object $module): void
    {
        $fullEvent = "module.{$event}";

        $webhooks = DB::table('webhooks')
            ->where('is_active', true)
            ->get()
            ->filter(function ($webhook) use ($fullEvent) {
                $events = json_decode($webhook->events ?? '[]', true);
                return in_array($fullEvent, $events);
            });

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $this->buildModulePayload($fullEvent, $module);

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $fullEvent, $payload);
        }
    }

    /**
     * Trigger a custom event.
     */
    public function trigger(string $event, array $data): void
    {
        $webhooks = DB::table('webhooks')
            ->where('is_active', true)
            ->get()
            ->filter(function ($webhook) use ($event) {
                $events = json_decode($webhook->events ?? '[]', true);
                return in_array($event, $events);
            });

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $event, $payload);
        }
    }

    /**
     * Build payload for record events.
     */
    protected function buildRecordPayload(string $event, object $record, ?array $previousData = null): array
    {
        $module = DB::table('modules')->where('id', $record->module_id)->first();
        $recordData = is_string($record->data ?? null)
            ? json_decode($record->data, true)
            : ($record->data ?? []);

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'record' => [
                    'id' => $record->id,
                    'data' => $recordData,
                    'created_by' => $record->created_by ?? null,
                    'updated_by' => $record->updated_by ?? null,
                    'created_at' => $record->created_at ?? null,
                    'updated_at' => $record->updated_at ?? null,
                ],
                'module' => [
                    'id' => $module->id ?? null,
                    'name' => $module->name ?? null,
                    'api_name' => $module->api_name ?? null,
                ],
            ],
        ];

        if ($previousData !== null) {
            $payload['data']['previous_data'] = $previousData;
            $payload['data']['changes'] = $this->calculateChanges($previousData, $recordData);
        }

        return $payload;
    }

    /**
     * Build payload for module events.
     */
    protected function buildModulePayload(string $event, object $module): array
    {
        return [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'module' => [
                    'id' => $module->id,
                    'name' => $module->name,
                    'api_name' => $module->api_name,
                    'description' => $module->description ?? null,
                    'icon' => $module->icon ?? null,
                    'is_active' => $module->is_active ?? true,
                ],
            ],
        ];
    }

    /**
     * Calculate changes between previous and current data.
     */
    protected function calculateChanges(array $previousData, array $currentData): array
    {
        $changes = [];

        // Find modified and new fields
        foreach ($currentData as $key => $value) {
            if (!array_key_exists($key, $previousData)) {
                $changes[$key] = [
                    'type' => 'added',
                    'new' => $value,
                ];
            } elseif ($previousData[$key] !== $value) {
                $changes[$key] = [
                    'type' => 'modified',
                    'old' => $previousData[$key],
                    'new' => $value,
                ];
            }
        }

        // Find removed fields
        foreach ($previousData as $key => $value) {
            if (!array_key_exists($key, $currentData)) {
                $changes[$key] = [
                    'type' => 'removed',
                    'old' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Dispatch a webhook delivery.
     */
    protected function dispatchWebhook(object $webhook, string $event, array $payload): void
    {
        try {
            $deliveryId = DB::table('webhook_deliveries')->insertGetId([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => json_encode($payload),
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            SendWebhookJob::dispatch($deliveryId);

            Log::debug('Webhook dispatched', [
                'webhook_id' => $webhook->id,
                'delivery_id' => $deliveryId,
                'event' => $event,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch webhook', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
