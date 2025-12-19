<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use HasFactory, SoftDeletes;

    // Event types
    public const EVENT_RECORD_CREATED = 'record.created';
    public const EVENT_RECORD_UPDATED = 'record.updated';
    public const EVENT_RECORD_DELETED = 'record.deleted';
    public const EVENT_DEAL_STAGE_CHANGED = 'deal.stage_changed';
    public const EVENT_DEAL_WON = 'deal.won';
    public const EVENT_DEAL_LOST = 'deal.lost';
    public const EVENT_EMAIL_RECEIVED = 'email.received';
    public const EVENT_EMAIL_OPENED = 'email.opened';
    public const EVENT_EMAIL_CLICKED = 'email.clicked';
    public const EVENT_WORKFLOW_TRIGGERED = 'workflow.triggered';
    public const EVENT_IMPORT_COMPLETED = 'import.completed';
    public const EVENT_EXPORT_COMPLETED = 'export.completed';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'url',
        'secret',
        'events',
        'module_id',
        'headers',
        'is_active',
        'verify_ssl',
        'timeout',
        'retry_count',
        'retry_delay',
        'last_triggered_at',
        'last_status',
        'success_count',
        'failure_count',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'verify_ssl' => 'boolean',
        'timeout' => 'integer',
        'retry_count' => 'integer',
        'retry_delay' => 'integer',
        'last_triggered_at' => 'datetime',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Generate a webhook secret.
     */
    public static function generateSecret(): string
    {
        return 'whsec_' . Str::random(32);
    }

    /**
     * Get the user who created this webhook.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the module this webhook is for.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get webhook deliveries.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Check if this webhook should be triggered for an event.
     */
    public function shouldTrigger(string $event, ?int $moduleId = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check if event is subscribed
        if (!in_array($event, $this->events ?? [])) {
            return false;
        }

        // Check module filter
        if ($this->module_id && $moduleId && $this->module_id !== $moduleId) {
            return false;
        }

        return true;
    }

    /**
     * Sign a payload with the webhook secret.
     */
    public function signPayload(array $payload): string
    {
        $jsonPayload = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", $this->secret);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Record successful delivery.
     */
    public function recordSuccess(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'last_status' => 'success',
            'success_count' => $this->success_count + 1,
        ]);
    }

    /**
     * Record failed delivery.
     */
    public function recordFailure(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'last_status' => 'failed',
            'failure_count' => $this->failure_count + 1,
        ]);
    }

    /**
     * Get all available events.
     */
    public static function getAvailableEvents(): array
    {
        return [
            'Record Events' => [
                self::EVENT_RECORD_CREATED => 'Record Created',
                self::EVENT_RECORD_UPDATED => 'Record Updated',
                self::EVENT_RECORD_DELETED => 'Record Deleted',
            ],
            'Deal Events' => [
                self::EVENT_DEAL_STAGE_CHANGED => 'Deal Stage Changed',
                self::EVENT_DEAL_WON => 'Deal Won',
                self::EVENT_DEAL_LOST => 'Deal Lost',
            ],
            'Email Events' => [
                self::EVENT_EMAIL_RECEIVED => 'Email Received',
                self::EVENT_EMAIL_OPENED => 'Email Opened',
                self::EVENT_EMAIL_CLICKED => 'Email Link Clicked',
            ],
            'System Events' => [
                self::EVENT_WORKFLOW_TRIGGERED => 'Workflow Triggered',
                self::EVENT_IMPORT_COMPLETED => 'Import Completed',
                self::EVENT_EXPORT_COMPLETED => 'Export Completed',
            ],
        ];
    }

    /**
     * Scope to active webhooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to webhooks for a specific event.
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }

    /**
     * Scope to webhooks for a specific module.
     */
    public function scopeForModule($query, ?int $moduleId)
    {
        return $query->where(function ($q) use ($moduleId) {
            $q->whereNull('module_id')
                ->orWhere('module_id', $moduleId);
        });
    }
}
