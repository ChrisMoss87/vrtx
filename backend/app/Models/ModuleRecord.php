<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleRecord extends Model
{
    use HasFactory, SoftDeletes;

    // Forecast categories
    public const FORECAST_COMMIT = 'commit';
    public const FORECAST_BEST_CASE = 'best_case';
    public const FORECAST_PIPELINE = 'pipeline';
    public const FORECAST_OMITTED = 'omitted';

    protected $fillable = [
        'module_id',
        'data',
        'created_by',
        'updated_by',
        'last_activity_at',
        'forecast_category',
        'forecast_override',
        'expected_close_date',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'data' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'last_activity_at' => 'datetime',
        'forecast_override' => 'decimal:2',
        'expected_close_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    /**
     * Get the module that owns the record.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created the record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the owner (alias for creator) for RBAC purposes.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get owner_id attribute (alias for created_by) for RBAC purposes.
     */
    public function getOwnerIdAttribute(): ?int
    {
        return $this->created_by;
    }

    /**
     * Get a field value from the data JSON.
     */
    public function getField(string $fieldName): mixed
    {
        return $this->data[$fieldName] ?? null;
    }

    /**
     * Set a field value in the data JSON.
     */
    public function setField(string $fieldName, mixed $value): void
    {
        $data = $this->data;
        $data[$fieldName] = $value;
        $this->data = $data;
    }

    /**
     * Scope a query to search across searchable fields.
     */
    public function scopeSearch($query, string $searchTerm, array $searchableFields)
    {
        if (empty($searchTerm) || empty($searchableFields)) {
            return $query;
        }

        // Escape LIKE pattern special characters to prevent pattern injection
        $escapedSearch = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $searchTerm);

        return $query->where(function ($q) use ($escapedSearch, $searchableFields) {
            foreach ($searchableFields as $field) {
                $q->orWhereRaw("data->>? ILIKE ?", [$field, '%' . $escapedSearch . '%']);
            }
        });
    }

    /**
     * Allowed SQL operators for filtering
     */
    private const ALLOWED_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'ILIKE'];

    /**
     * Scope a query to filter by a specific field value.
     * Validates operator to prevent SQL injection.
     */
    public function scopeWhereField($query, string $field, string $operator, mixed $value)
    {
        $operator = strtoupper(trim($operator));

        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}. Allowed: " . implode(', ', self::ALLOWED_OPERATORS));
        }

        return $query->whereRaw("data->>? {$operator} ?", [$field, $value]);
    }

    /**
     * Scope a query to order by a specific field.
     * Validates direction to prevent SQL injection.
     */
    public function scopeOrderByField($query, string $field, string $direction = 'asc')
    {
        $direction = strtoupper(trim($direction));

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Invalid sort direction: {$direction}. Allowed: ASC, DESC");
        }

        return $query->orderByRaw("data->>? {$direction}", [$field]);
    }
}
