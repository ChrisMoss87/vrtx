<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORY_CONTRACT = 'contract';
    public const CATEGORY_PROPOSAL = 'proposal';
    public const CATEGORY_LETTER = 'letter';
    public const CATEGORY_AGREEMENT = 'agreement';
    public const CATEGORY_QUOTE = 'quote';
    public const CATEGORY_INVOICE = 'invoice';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_CONTRACT,
        self::CATEGORY_PROPOSAL,
        self::CATEGORY_LETTER,
        self::CATEGORY_AGREEMENT,
        self::CATEGORY_QUOTE,
        self::CATEGORY_INVOICE,
        self::CATEGORY_OTHER,
    ];

    public const OUTPUT_PDF = 'pdf';
    public const OUTPUT_DOCX = 'docx';
    public const OUTPUT_HTML = 'html';

    public const OUTPUT_FORMATS = [
        self::OUTPUT_PDF,
        self::OUTPUT_DOCX,
        self::OUTPUT_HTML,
    ];

    protected $fillable = [
        'name',
        'category',
        'description',
        'content',
        'merge_fields',
        'conditional_blocks',
        'output_format',
        'page_settings',
        'header_settings',
        'footer_settings',
        'thumbnail_url',
        'is_active',
        'is_shared',
        'created_by',
        'updated_by',
        'version',
    ];

    protected $casts = [
        'merge_fields' => 'array',
        'conditional_blocks' => 'array',
        'page_settings' => 'array',
        'header_settings' => 'array',
        'footer_settings' => 'array',
        'is_active' => 'boolean',
        'is_shared' => 'boolean',
        'version' => 'integer',
    ];

    protected $attributes = [
        'output_format' => self::OUTPUT_PDF,
        'is_active' => true,
        'is_shared' => false,
        'version' => 1,
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_shared', true)
              ->orWhere('created_by', $userId);
        });
    }

    // Helpers
    public function getMergeFieldsList(): array
    {
        return $this->merge_fields ?? [];
    }

    public function incrementVersion(): void
    {
        $this->version++;
        $this->save();
    }

    public function duplicate(int $userId): self
    {
        $copy = $this->replicate();
        $copy->name = $this->name . ' (Copy)';
        $copy->created_by = $userId;
        $copy->version = 1;
        $copy->save();

        return $copy;
    }
}
