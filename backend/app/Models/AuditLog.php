<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    // Event types
    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_RESTORED = 'restored';
    public const EVENT_FORCE_DELETED = 'force_deleted';
    public const EVENT_ATTACHED = 'attached';
    public const EVENT_DETACHED = 'detached';
    public const EVENT_SYNCED = 'synced';
    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_FAILED_LOGIN = 'failed_login';

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'tags',
        'batch_id',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'tags' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who made the change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable entity.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for a specific auditable.
     */
    public function scopeForAuditable($query, string $type, int $id)
    {
        return $query->where('auditable_type', $type)
            ->where('auditable_id', $id);
    }

    /**
     * Scope for a specific event type.
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope for a specific user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for a batch.
     */
    public function scopeInBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope for a date range.
     */
    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope with specific tags.
     */
    public function scopeWithTags($query, array $tags)
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
        return $query;
    }

    /**
     * Get the changed fields.
     */
    public function getChangedFieldsAttribute(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return array_keys($this->new_values ?? $this->old_values ?? []);
        }

        $changed = [];
        foreach ($this->new_values as $key => $value) {
            if (!array_key_exists($key, $this->old_values) || $this->old_values[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * Get human-readable event description.
     */
    public function getEventDescriptionAttribute(): string
    {
        $entityName = class_basename($this->auditable_type);

        return match ($this->event) {
            self::EVENT_CREATED => "Created {$entityName}",
            self::EVENT_UPDATED => "Updated {$entityName}",
            self::EVENT_DELETED => "Deleted {$entityName}",
            self::EVENT_RESTORED => "Restored {$entityName}",
            self::EVENT_FORCE_DELETED => "Permanently deleted {$entityName}",
            self::EVENT_ATTACHED => "Attached to {$entityName}",
            self::EVENT_DETACHED => "Detached from {$entityName}",
            self::EVENT_LOGIN => "Logged in",
            self::EVENT_LOGOUT => "Logged out",
            self::EVENT_FAILED_LOGIN => "Failed login attempt",
            default => ucfirst($this->event) . " {$entityName}",
        };
    }

    /**
     * Get diff of changes.
     */
    public function getDiff(): array
    {
        if ($this->event === self::EVENT_CREATED) {
            return collect($this->new_values ?? [])->map(fn($v) => [
                'old' => null,
                'new' => $v,
            ])->toArray();
        }

        if ($this->event === self::EVENT_DELETED) {
            return collect($this->old_values ?? [])->map(fn($v) => [
                'old' => $v,
                'new' => null,
            ])->toArray();
        }

        $diff = [];
        $allKeys = array_unique(array_merge(
            array_keys($this->old_values ?? []),
            array_keys($this->new_values ?? [])
        ));

        foreach ($allKeys as $key) {
            $oldValue = $this->old_values[$key] ?? null;
            $newValue = $this->new_values[$key] ?? null;

            if ($oldValue !== $newValue) {
                $diff[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $diff;
    }

    /**
     * Create a new audit log entry.
     */
    public static function record(
        Model $model,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $tags = null
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'tags' => $tags,
            'created_at' => now(),
        ]);
    }
}
