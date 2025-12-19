<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketCannedResponse extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'shortcut',
        'content',
        'category_id',
        'created_by',
        'is_shared',
        'usage_count',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
