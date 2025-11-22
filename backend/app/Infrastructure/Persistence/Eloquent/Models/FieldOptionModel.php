<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FieldOptionModel extends Model
{
    use HasFactory;

    protected $table = 'field_options';

    protected $fillable = [
        'field_id',
        'label',
        'value',
        'color',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'field_id' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldModel::class, 'field_id');
    }
}
