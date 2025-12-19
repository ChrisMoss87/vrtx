<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
        'header_html',
        'footer_html',
        'styling',
        'company_info',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'styling' => 'array',
        'company_info' => 'array',
    ];

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    public function setAsDefault(): void
    {
        // Remove default from all others
        static::where('is_default', true)->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }
}
