<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ModuleRecordModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'module_records';

    protected $fillable = [
        'module_id',
        'data',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'data' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleModel::class, 'module_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
