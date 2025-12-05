<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Fields to exclude from audit logging.
     */
    protected static array $auditExclude = [
        'password',
        'remember_token',
        'updated_at',
    ];

    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::logAudit($model, AuditLog::EVENT_CREATED, null, $model->getAuditableAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            $original = collect($model->getOriginal())
                ->only(array_keys($changes))
                ->toArray();

            // Filter out excluded fields
            $filtered = static::filterAuditAttributes($changes);
            $filteredOriginal = static::filterAuditAttributes($original);

            if (!empty($filtered)) {
                static::logAudit($model, AuditLog::EVENT_UPDATED, $filteredOriginal, $filtered);
            }
        });

        static::deleted(function (Model $model) {
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? AuditLog::EVENT_FORCE_DELETED
                : AuditLog::EVENT_DELETED;

            static::logAudit($model, $event, $model->getAuditableAttributes(), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                static::logAudit($model, AuditLog::EVENT_RESTORED, null, $model->getAuditableAttributes());
            });
        }
    }

    /**
     * Log an audit entry.
     */
    protected static function logAudit(
        Model $model,
        string $event,
        ?array $oldValues,
        ?array $newValues
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get auditable attributes.
     */
    public function getAuditableAttributes(): array
    {
        return static::filterAuditAttributes($this->getAttributes());
    }

    /**
     * Filter out excluded attributes.
     */
    protected static function filterAuditAttributes(array $attributes): array
    {
        $exclude = property_exists(static::class, 'auditExclude')
            ? static::$auditExclude
            : [];

        $exclude = array_merge($exclude, ['password', 'remember_token']);

        return collect($attributes)
            ->except($exclude)
            ->filter(fn($value) => !is_null($value))
            ->toArray();
    }

    /**
     * Get audit history for this model.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get the audit trail.
     */
    public function getAuditTrail(int $limit = 50)
    {
        return AuditLog::forAuditable(get_class($this), $this->getKey())
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
