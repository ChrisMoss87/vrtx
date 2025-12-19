<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dashboard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'is_default',
        'is_public',
        'layout',
        'settings',
        'filters',
        'refresh_interval',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'layout' => 'array',
        'settings' => 'array',
        'filters' => 'array',
        'refresh_interval' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_default' => false,
        'is_public' => false,
        'layout' => '[]',
        'settings' => '{}',
        'filters' => '{}',
        'refresh_interval' => 0,
    ];

    /**
     * Get the user who owns this dashboard.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the widgets on this dashboard.
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('position');
    }

    /**
     * Scope to public dashboards.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to default dashboards.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to dashboards accessible by a user.
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_public', true);
        });
    }

    /**
     * Set this dashboard as the default for the user.
     */
    public function setAsDefault(): void
    {
        // Unset other defaults for this user
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Duplicate this dashboard for another user.
     */
    public function duplicate(?int $userId = null): self
    {
        $dashboard = $this->replicate();
        $dashboard->user_id = $userId ?? $this->user_id;
        $dashboard->name = $this->name . ' (Copy)';
        $dashboard->is_default = false;
        $dashboard->save();

        // Duplicate widgets
        foreach ($this->widgets as $widget) {
            $newWidget = $widget->replicate();
            $newWidget->dashboard_id = $dashboard->id;
            $newWidget->save();
        }

        return $dashboard;
    }

    /**
     * Get the layout configuration for a specific breakpoint.
     */
    public function getLayoutForBreakpoint(string $breakpoint = 'lg'): array
    {
        return $this->layout[$breakpoint] ?? $this->layout['lg'] ?? [];
    }

    /**
     * Update widget position in layout.
     */
    public function updateWidgetPosition(int $widgetId, array $position, string $breakpoint = 'lg'): void
    {
        $layout = $this->layout;

        if (!isset($layout[$breakpoint])) {
            $layout[$breakpoint] = [];
        }

        // Find and update widget position
        $found = false;
        foreach ($layout[$breakpoint] as &$item) {
            if ($item['i'] === $widgetId) {
                $item = array_merge($item, $position);
                $found = true;
                break;
            }
        }

        // If not found, add new position
        if (!$found) {
            $layout[$breakpoint][] = array_merge(['i' => $widgetId], $position);
        }

        $this->update(['layout' => $layout]);
    }
}
