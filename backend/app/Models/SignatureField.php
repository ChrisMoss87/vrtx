<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureField extends Model
{
    use HasFactory;
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_INITIALS = 'initials';
    public const TYPE_DATE = 'date';
    public const TYPE_TEXT = 'text';
    public const TYPE_CHECKBOX = 'checkbox';

    public const TYPES = [
        self::TYPE_SIGNATURE,
        self::TYPE_INITIALS,
        self::TYPE_DATE,
        self::TYPE_TEXT,
        self::TYPE_CHECKBOX,
    ];

    protected $fillable = [
        'request_id',
        'signer_id',
        'field_type',
        'page_number',
        'x_position',
        'y_position',
        'width',
        'height',
        'required',
        'label',
        'value',
        'filled_at',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'x_position' => 'decimal:2',
        'y_position' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'required' => 'boolean',
        'filled_at' => 'datetime',
    ];

    protected $attributes = [
        'page_number' => 1,
        'width' => 200,
        'height' => 50,
        'required' => true,
    ];

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class, 'request_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(SignatureSigner::class, 'signer_id');
    }

    // Helpers
    public function fillValue(string $value): void
    {
        $this->value = $value;
        $this->filled_at = now();
        $this->save();
    }

    public function isFilled(): bool
    {
        return !empty($this->value);
    }

    public function getPosition(): array
    {
        return [
            'page' => $this->page_number,
            'x' => $this->x_position,
            'y' => $this->y_position,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
