<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'module_id',
        'user_id',
        'name',
        'description',
        'selected_fields',
        'filters',
        'sorting',
        'export_options',
        'default_file_type',
        'is_shared',
    ];

    protected $casts = [
        'selected_fields' => 'array',
        'filters' => 'array',
        'sorting' => 'array',
        'export_options' => 'array',
        'is_shared' => 'boolean',
    ];

    protected $attributes = [
        'default_file_type' => Export::FILE_TYPE_CSV,
        'is_shared' => false,
    ];

    /**
     * Get the module this template belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to shared templates.
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope to user's own templates.
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to templates accessible by a user.
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }

    /**
     * Create an export from this template.
     */
    public function createExport(int $userId, ?string $name = null, ?string $fileType = null): Export
    {
        return Export::create([
            'module_id' => $this->module_id,
            'user_id' => $userId,
            'name' => $name ?? $this->name,
            'file_type' => $fileType ?? $this->default_file_type,
            'selected_fields' => $this->selected_fields,
            'filters' => $this->filters,
            'sorting' => $this->sorting,
            'export_options' => $this->export_options,
        ]);
    }
}
