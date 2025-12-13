<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleView extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'user_id',
        'name',
        'description',
        'view_type',
        'kanban_config',
        'filters',
        'sorting',
        'column_visibility',
        'column_order',
        'column_widths',
        'page_size',
        'is_default',
        'is_shared',
        'display_order',
    ];

    /**
     * View type constants
     */
    public const TYPE_TABLE = 'table';
    public const TYPE_KANBAN = 'kanban';

    protected $casts = [
        'filters' => 'array',
        'sorting' => 'array',
        'column_visibility' => 'array',
        'column_order' => 'array',
        'column_widths' => 'array',
        'kanban_config' => 'array',
        'page_size' => 'integer',
        'is_default' => 'boolean',
        'is_shared' => 'boolean',
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'view_type' => self::TYPE_TABLE,
        'filters' => '[]',
        'sorting' => '[]',
        'column_visibility' => '{}',
        'page_size' => 50,
        'is_default' => false,
        'is_shared' => false,
        'display_order' => 0,
    ];

    /**
     * Get the module that owns the view.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user that owns the view.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include default views.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include shared views.
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope a query to include views accessible by a user.
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Scope a query to only include table views.
     */
    public function scopeTable($query)
    {
        return $query->where('view_type', self::TYPE_TABLE);
    }

    /**
     * Scope a query to only include kanban views.
     */
    public function scopeKanban($query)
    {
        return $query->where('view_type', self::TYPE_KANBAN);
    }

    /**
     * Check if this is a kanban view.
     */
    public function isKanban(): bool
    {
        return $this->view_type === self::TYPE_KANBAN;
    }

    /**
     * Check if this is a table view.
     */
    public function isTable(): bool
    {
        return $this->view_type === self::TYPE_TABLE;
    }

    /**
     * Get the group by field for kanban views.
     */
    public function getKanbanGroupByField(): ?string
    {
        return $this->kanban_config['group_by_field'] ?? null;
    }
}
