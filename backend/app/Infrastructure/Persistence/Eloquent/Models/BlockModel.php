<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BlockModel extends Model
{
    use HasFactory;

    protected $table = 'blocks';

    protected $fillable = [
        'module_id',
        'name',
        'type',
        'display_order',
        'settings',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'display_order' => 'integer',
        'settings' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleModel::class, 'module_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FieldModel::class, 'block_id')->orderBy('display_order');
    }
}
