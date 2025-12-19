<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PortalAnnouncement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'type',
        'is_active',
        'is_dismissible',
        'starts_at',
        'ends_at',
        'target_accounts',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_dismissible' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'target_accounts' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForAccount(Builder $query, ?int $accountId): Builder
    {
        return $query->where(function ($q) use ($accountId) {
            $q->whereJsonLength('target_accounts', 0)
                ->orWhereJsonContains('target_accounts', $accountId);
        });
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public static function getTypes(): array
    {
        return [
            'info' => 'Information',
            'warning' => 'Warning',
            'success' => 'Success',
            'error' => 'Error',
        ];
    }
}
