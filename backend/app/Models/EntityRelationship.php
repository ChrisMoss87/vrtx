<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityRelationship extends Model
{
    // Entity types
    public const TYPE_CONTACT = 'contact';
    public const TYPE_COMPANY = 'company';
    public const TYPE_DEAL = 'deal';
    public const TYPE_USER = 'user';

    // Relationship types
    public const REL_WORKS_AT = 'works_at';
    public const REL_REPORTS_TO = 'reports_to';
    public const REL_REFERRED_BY = 'referred_by';
    public const REL_INFLUENCED = 'influenced';
    public const REL_DECISION_MAKER = 'decision_maker';
    public const REL_PARTNER = 'partner';
    public const REL_PARENT_COMPANY = 'parent_company';
    public const REL_OWNED = 'owned';
    public const REL_WORKED_ON = 'worked_on';

    protected $fillable = [
        'from_entity_type',
        'from_entity_id',
        'to_entity_type',
        'to_entity_id',
        'relationship_type',
        'strength',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'strength' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all valid entity types.
     */
    public static function getEntityTypes(): array
    {
        return [
            self::TYPE_CONTACT,
            self::TYPE_COMPANY,
            self::TYPE_DEAL,
            self::TYPE_USER,
        ];
    }

    /**
     * Get all valid relationship types.
     */
    public static function getRelationshipTypes(): array
    {
        return [
            self::REL_WORKS_AT => 'Works at',
            self::REL_REPORTS_TO => 'Reports to',
            self::REL_REFERRED_BY => 'Referred by',
            self::REL_INFLUENCED => 'Influenced',
            self::REL_DECISION_MAKER => 'Decision maker',
            self::REL_PARTNER => 'Partner',
            self::REL_PARENT_COMPANY => 'Parent company',
            self::REL_OWNED => 'Owned by',
            self::REL_WORKED_ON => 'Worked on',
        ];
    }

    /**
     * Scope to get relationships from a specific entity.
     */
    public function scopeFrom($query, string $entityType, int $entityId)
    {
        return $query->where('from_entity_type', $entityType)
            ->where('from_entity_id', $entityId);
    }

    /**
     * Scope to get relationships to a specific entity.
     */
    public function scopeTo($query, string $entityType, int $entityId)
    {
        return $query->where('to_entity_type', $entityType)
            ->where('to_entity_id', $entityId);
    }

    /**
     * Scope to get relationships involving a specific entity (either from or to).
     */
    public function scopeInvolving($query, string $entityType, int $entityId)
    {
        return $query->where(function ($q) use ($entityType, $entityId) {
            $q->where(function ($q2) use ($entityType, $entityId) {
                $q2->where('from_entity_type', $entityType)
                    ->where('from_entity_id', $entityId);
            })->orWhere(function ($q2) use ($entityType, $entityId) {
                $q2->where('to_entity_type', $entityType)
                    ->where('to_entity_id', $entityId);
            });
        });
    }

    /**
     * Scope by relationship type.
     */
    public function scopeOfType($query, string $relationshipType)
    {
        return $query->where('relationship_type', $relationshipType);
    }

    /**
     * Get the inverse relationship.
     */
    public static function getInverseType(string $relationshipType): ?string
    {
        $inverses = [
            self::REL_WORKS_AT => 'employs',
            self::REL_REPORTS_TO => 'manages',
            self::REL_REFERRED_BY => 'referred',
            self::REL_PARTNER => self::REL_PARTNER, // symmetric
            self::REL_PARENT_COMPANY => 'subsidiary_of',
        ];

        return $inverses[$relationshipType] ?? null;
    }
}
