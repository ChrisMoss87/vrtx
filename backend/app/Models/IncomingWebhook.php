<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class IncomingWebhook extends Model
{
    use HasFactory, SoftDeletes;

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_UPSERT = 'upsert';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'token',
        'module_id',
        'field_mapping',
        'is_active',
        'action',
        'upsert_field',
        'last_received_at',
        'received_count',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'is_active' => 'boolean',
        'last_received_at' => 'datetime',
        'received_count' => 'integer',
    ];

    protected $hidden = [
        'token',
    ];

    /**
     * Generate a unique token.
     */
    public static function generateToken(): string
    {
        return 'iwh_' . Str::random(32);
    }

    /**
     * Get the user who created this webhook.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the module this webhook creates records in.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get webhook logs.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(IncomingWebhookLog::class);
    }

    /**
     * Find a webhook by token.
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Get the webhook URL.
     */
    public function getUrl(): string
    {
        return url("/api/v1/webhooks/incoming/{$this->token}");
    }

    /**
     * Map incoming data to module fields.
     */
    public function mapData(array $data): array
    {
        $mapping = $this->field_mapping ?? [];
        $mapped = [];

        foreach ($mapping as $incomingField => $moduleField) {
            if ($moduleField && isset($data[$incomingField])) {
                $mapped[$moduleField] = $data[$incomingField];
            }
        }

        return $mapped;
    }

    /**
     * Record a received webhook.
     */
    public function recordReceived(): void
    {
        $this->update([
            'last_received_at' => now(),
            'received_count' => $this->received_count + 1,
        ]);
    }

    /**
     * Scope to active webhooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
