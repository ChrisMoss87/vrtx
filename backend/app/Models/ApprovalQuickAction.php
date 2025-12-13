<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalQuickAction extends Model
{
    public const TYPE_APPROVE = 'approve';
    public const TYPE_REJECT = 'reject';

    public const TYPES = [
        self::TYPE_APPROVE,
        self::TYPE_REJECT,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'action_type',
        'default_comment',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeApprove($query)
    {
        return $query->where('action_type', self::TYPE_APPROVE);
    }

    public function scopeReject($query)
    {
        return $query->where('action_type', self::TYPE_REJECT);
    }
}
