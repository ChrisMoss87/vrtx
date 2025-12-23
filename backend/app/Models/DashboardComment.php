<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'widget_id',
        'user_id',
        'parent_id',
        'content',
        'resolved',
    ];

    protected $casts = [
        'resolved' => 'boolean',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'widget_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function scopeForDashboard($query, int $dashboardId)
    {
        return $query->where('dashboard_id', $dashboardId);
    }

    public function scopeForWidget($query, int $widgetId)
    {
        return $query->where('widget_id', $widgetId);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    /**
     * Mark this comment as resolved
     */
    public function resolve(): void
    {
        $this->update(['resolved' => true]);
    }

    /**
     * Mark this comment as unresolved
     */
    public function unresolve(): void
    {
        $this->update(['resolved' => false]);
    }

    /**
     * Check if this comment has replies
     */
    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    /**
     * Get reply count
     */
    public function getReplyCountAttribute(): int
    {
        return $this->replies()->count();
    }
}
