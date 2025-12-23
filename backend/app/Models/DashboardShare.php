<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardShare extends Model
{
    protected $fillable = [
        'dashboard_id',
        'user_id',
        'team_id',
        'permission',
        'shared_by',
    ];

    // Permission constants
    public const PERMISSION_VIEW = 'view';
    public const PERMISSION_EDIT = 'edit';

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeWithEditPermission($query)
    {
        return $query->where('permission', self::PERMISSION_EDIT);
    }

    public function scopeForDashboard($query, int $dashboardId)
    {
        return $query->where('dashboard_id', $dashboardId);
    }

    /**
     * Get the type of this share (user or team)
     */
    public function getTypeAttribute(): string
    {
        return $this->user_id ? 'user' : 'team';
    }

    /**
     * Check if this share allows editing
     */
    public function canEdit(): bool
    {
        return $this->permission === self::PERMISSION_EDIT;
    }

    /**
     * Get available permissions
     */
    public static function getPermissions(): array
    {
        return [
            self::PERMISSION_VIEW => 'View',
            self::PERMISSION_EDIT => 'Edit',
        ];
    }
}
