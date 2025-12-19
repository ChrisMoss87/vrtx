<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendWebhookJob;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Trigger webhooks for a record event.
     */
    public function triggerForRecord(string $event, ModuleRecord $record, ?array $previousData = null): void
    {
        $module = $record->module;
        $fullEvent = "record.{$event}";

        // Find all active webhooks that listen to this event
        $webhooks = Webhook::active()
            ->where(function ($query) use ($module) {
                $query->whereNull('module_id')
                    ->orWhere('module_id', $module->id);
            })
            ->get()
            ->filter(fn ($webhook) => $webhook->hasEvent($fullEvent));

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
    public function triggerForModule(string $event, Module $module): void
    {
        $fullEvent = "module.{$event}";

        $webhooks = Webhook::active()
            ->get()
            ->filter(fn ($webhook) => $webhook->hasEvent($fullEvent));

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
        $webhooks = Webhook::active()
            ->get()
            ->filter(fn ($webhook) => $webhook->hasEvent($event));

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
    protected function buildRecordPayload(string $event, ModuleRecord $record, ?array $previousData = null): array
    {
        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'record' => [
                    'id' => $record->id,
                    'data' => $record->data,
                    'created_by' => $record->created_by,
                    'updated_by' => $record->updated_by,
                    'created_at' => $record->created_at?->toIso8601String(),
                    'updated_at' => $record->updated_at?->toIso8601String(),
                ],
                'module' => [
                    'id' => $record->module->id,
                    'name' => $record->module->name,
                    'api_name' => $record->module->api_name,
                ],
            ],
        ];

        if ($previousData !== null) {
            $payload['data']['previous_data'] = $previousData;
            $payload['data']['changes'] = $this->calculateChanges($previousData, $record->data);
        }

        return $payload;
    }

    /**
     * Build payload for module events.
     */
    protected function buildModulePayload(string $event, Module $module): array
    {
        return [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'module' => [
                    'id' => $module->id,
                    'name' => $module->name,
                    'api_name' => $module->api_name,
                    'description' => $module->description,
                    'icon' => $module->icon,
                    'is_active' => $module->is_active,
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
    protected function dispatchWebhook(Webhook $webhook, string $event, array $payload): void
    {
        try {
            $delivery = WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => $payload,
                'status' => WebhookDelivery::STATUS_PENDING,
                'attempts' => 0,
            ]);

            SendWebhookJob::dispatch($delivery);

            Log::debug('Webhook dispatched', [
                'webhook_id' => $webhook->id,
                'delivery_id' => $delivery->id,
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
