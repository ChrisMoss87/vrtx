<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportShare extends Model
{
    protected $fillable = [
        'report_id',
        'user_id',
        'team_id',
        'permission',
        'shared_by',
    ];

    /**
     * The report that is being shared.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * The user the report is shared with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The team the report is shared with.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The user who shared the report.
     */
    public function sharedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    /**
     * Check if the share grants edit permission.
     */
    public function canEdit(): bool
    {
        return $this->permission === 'edit';
    }

    /**
     * Scope to find shares for a specific user (direct or via team).
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereHas('team', function ($teamQuery) use ($userId) {
                    $teamQuery->whereHas('users', function ($userQuery) use ($userId) {
                        $userQuery->where('users.id', $userId);
                    });
                });
        });
    }
}
