<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ModuleRelationshipModel extends Model
{
    use HasFactory;

    protected $table = 'module_relationships';

    protected $fillable = [
        'source_module_id',
        'target_module_id',
        'field_id',
        'type',
        'name',
        'inverse_name',
    ];

    protected $casts = [
        'source_module_id' => 'integer',
        'target_module_id' => 'integer',
        'field_id' => 'integer',
    ];

    public function sourceModule(): BelongsTo
    {
        return $this->belongsTo(ModuleModel::class, 'source_module_id');
    }

    public function targetModule(): BelongsTo
    {
        return $this->belongsTo(ModuleModel::class, 'target_module_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldModel::class, 'field_id');
    }
}
