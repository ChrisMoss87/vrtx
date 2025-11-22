<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ModuleModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'modules';

    protected $fillable = [
        'name',
        'singular_name',
        'api_name',
        'icon',
        'description',
        'is_active',
        'settings',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'display_order' => 'integer',
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(BlockModel::class, 'module_id')->orderBy('display_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FieldModel::class, 'module_id')->orderBy('display_order');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ModuleRecordModel::class, 'module_id');
    }

    public function sourceRelationships(): HasMany
    {
        return $this->hasMany(ModuleRelationshipModel::class, 'source_module_id');
    }

    public function targetRelationships(): HasMany
    {
        return $this->hasMany(ModuleRelationshipModel::class, 'target_module_id');
    }
}
