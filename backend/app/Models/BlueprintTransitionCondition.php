<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintTransitionCondition extends Model
{
    use HasFactory;

    // Common operators
    public const OPERATOR_EQUALS = 'eq';
    public const OPERATOR_NOT_EQUALS = 'ne';
    public const OPERATOR_GREATER_THAN = 'gt';
    public const OPERATOR_GREATER_THAN_OR_EQUAL = 'gte';
    public const OPERATOR_LESS_THAN = 'lt';
    public const OPERATOR_LESS_THAN_OR_EQUAL = 'lte';
    public const OPERATOR_CONTAINS = 'contains';
    public const OPERATOR_NOT_CONTAINS = 'not_contains';
    public const OPERATOR_STARTS_WITH = 'starts_with';
    public const OPERATOR_ENDS_WITH = 'ends_with';
    public const OPERATOR_IS_EMPTY = 'is_empty';
    public const OPERATOR_IS_NOT_EMPTY = 'is_not_empty';
    public const OPERATOR_IN = 'in';
    public const OPERATOR_NOT_IN = 'not_in';

    protected $fillable = [
        'transition_id',
        'field_id',
        'operator',
        'value',
        'logical_group',
        'display_order',
    ];

    protected $casts = [
        'transition_id' => 'integer',
        'field_id' => 'integer',
        'display_order' => 'integer',
    ];

    protected $attributes = [
        'logical_group' => 'AND',
        'display_order' => 0,
    ];

    /**
     * Get the transition this condition belongs to.
     */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransition::class, 'transition_id');
    }

    /**
     * Get the field being evaluated.
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    /**
     * Get available operators.
     */
    public static function getOperators(): array
    {
        return [
            self::OPERATOR_EQUALS => 'Equals',
            self::OPERATOR_NOT_EQUALS => 'Not equals',
            self::OPERATOR_GREATER_THAN => 'Greater than',
            self::OPERATOR_GREATER_THAN_OR_EQUAL => 'Greater than or equal',
            self::OPERATOR_LESS_THAN => 'Less than',
            self::OPERATOR_LESS_THAN_OR_EQUAL => 'Less than or equal',
            self::OPERATOR_CONTAINS => 'Contains',
            self::OPERATOR_NOT_CONTAINS => 'Does not contain',
            self::OPERATOR_STARTS_WITH => 'Starts with',
            self::OPERATOR_ENDS_WITH => 'Ends with',
            self::OPERATOR_IS_EMPTY => 'Is empty',
            self::OPERATOR_IS_NOT_EMPTY => 'Is not empty',
            self::OPERATOR_IN => 'Is one of',
            self::OPERATOR_NOT_IN => 'Is not one of',
        ];
    }
}
