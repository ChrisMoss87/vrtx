<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRule extends Model
{
    use HasFactory;

    public const ENTITY_QUOTE = 'quote';
    public const ENTITY_PROPOSAL = 'proposal';
    public const ENTITY_DISCOUNT = 'discount';
    public const ENTITY_CONTRACT = 'contract';
    public const ENTITY_EXPENSE = 'expense';
    public const ENTITY_CUSTOM = 'custom';

    public const ENTITY_TYPES = [
        self::ENTITY_QUOTE,
        self::ENTITY_PROPOSAL,
        self::ENTITY_DISCOUNT,
        self::ENTITY_CONTRACT,
        self::ENTITY_EXPENSE,
        self::ENTITY_CUSTOM,
    ];

    public const TYPE_SEQUENTIAL = 'sequential';
    public const TYPE_PARALLEL = 'parallel';
    public const TYPE_ANY = 'any';

    public const APPROVAL_TYPES = [
        self::TYPE_SEQUENTIAL,
        self::TYPE_PARALLEL,
        self::TYPE_ANY,
    ];

    protected $fillable = [
        'name',
        'description',
        'entity_type',
        'module_id',
        'conditions',
        'approver_chain',
        'approval_type',
        'allow_self_approval',
        'require_comments',
        'sla_hours',
        'escalation_rules',
        'notification_settings',
        'is_active',
        'priority',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'approver_chain' => 'array',
        'escalation_rules' => 'array',
        'notification_settings' => 'array',
        'allow_self_approval' => 'boolean',
        'require_comments' => 'boolean',
        'sla_hours' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected $attributes = [
        'approval_type' => self::TYPE_SEQUENTIAL,
        'allow_self_approval' => false,
        'require_comments' => false,
        'is_active' => true,
        'priority' => 0,
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'rule_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // Helpers
    public function matchesConditions(array $data): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (!$field || !isset($data[$field])) {
                continue;
            }

            $fieldValue = $data[$field];

            $matches = match ($operator) {
                '=' => $fieldValue == $value,
                '!=' => $fieldValue != $value,
                '>' => $fieldValue > $value,
                '>=' => $fieldValue >= $value,
                '<' => $fieldValue < $value,
                '<=' => $fieldValue <= $value,
                'in' => in_array($fieldValue, (array) $value),
                'not_in' => !in_array($fieldValue, (array) $value),
                default => false,
            };

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    public function getApprovers(): array
    {
        return $this->approver_chain ?? [];
    }

    public static function findMatchingRule(string $entityType, array $data): ?self
    {
        return static::active()
            ->forEntity($entityType)
            ->ordered()
            ->get()
            ->first(fn ($rule) => $rule->matchesConditions($data));
    }
}
