<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintTransitionRequirement extends Model
{
    use HasFactory;

    // Requirement types
    public const TYPE_MANDATORY_FIELD = 'mandatory_field';
    public const TYPE_ATTACHMENT = 'attachment';
    public const TYPE_NOTE = 'note';
    public const TYPE_CHECKLIST = 'checklist';

    protected $fillable = [
        'transition_id',
        'type',
        'field_id',
        'label',
        'description',
        'is_required',
        'config',
        'display_order',
    ];

    protected $casts = [
        'transition_id' => 'integer',
        'field_id' => 'integer',
        'is_required' => 'boolean',
        'config' => 'array',
        'display_order' => 'integer',
    ];

    protected $attributes = [
        'is_required' => true,
        'display_order' => 0,
    ];

    /**
     * Get the transition this requirement belongs to.
     */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransition::class, 'transition_id');
    }

    /**
     * Get the field (for mandatory_field type).
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    /**
     * Get available requirement types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_MANDATORY_FIELD => [
                'label' => 'Mandatory Field',
                'description' => 'User must fill in a specific field',
                'requires_field' => true,
            ],
            self::TYPE_ATTACHMENT => [
                'label' => 'Attachment',
                'description' => 'User must upload a file',
                'requires_field' => false,
            ],
            self::TYPE_NOTE => [
                'label' => 'Note',
                'description' => 'User must add a note or comment',
                'requires_field' => false,
            ],
            self::TYPE_CHECKLIST => [
                'label' => 'Checklist',
                'description' => 'User must complete a checklist of items',
                'requires_field' => false,
            ],
        ];
    }

    /**
     * Get the checklist items (for checklist type).
     */
    public function getChecklistItems(): array
    {
        if ($this->type !== self::TYPE_CHECKLIST) {
            return [];
        }

        return $this->config['items'] ?? [];
    }

    /**
     * Get allowed file types (for attachment type).
     */
    public function getAllowedFileTypes(): array
    {
        if ($this->type !== self::TYPE_ATTACHMENT) {
            return [];
        }

        return $this->config['allowed_types'] ?? [];
    }

    /**
     * Get max file size in bytes (for attachment type).
     */
    public function getMaxFileSize(): ?int
    {
        if ($this->type !== self::TYPE_ATTACHMENT) {
            return null;
        }

        return $this->config['max_size'] ?? null;
    }

    /**
     * Get minimum note length (for note type).
     */
    public function getMinNoteLength(): int
    {
        if ($this->type !== self::TYPE_NOTE) {
            return 0;
        }

        return $this->config['min_length'] ?? 0;
    }
}
