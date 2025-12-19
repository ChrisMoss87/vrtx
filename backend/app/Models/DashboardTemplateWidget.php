<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class DashboardTemplateWidget extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'template_id',
        'title',
        'type',
        'config',
        'grid_position',
        'refresh_interval',
    ];

    protected $casts = [
        'config' => 'array',
        'grid_position' => 'array',
        'refresh_interval' => 'integer',
    ];

    /**
     * Get the template that owns this widget.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DashboardTemplate::class, 'template_id');
    }
}
