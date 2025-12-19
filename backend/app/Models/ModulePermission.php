<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'module_id',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
        'can_export',
        'can_import',
        'record_access_level',
        'field_restrictions',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'module_id' => 'integer',
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_export' => 'boolean',
        'can_import' => 'boolean',
        'field_restrictions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'can_view' => true,
        'can_create' => false,
        'can_edit' => false,
        'can_delete' => false,
        'can_export' => false,
        'can_import' => false,
        'record_access_level' => 'own',
        'field_restrictions' => '[]',
    ];

    // Record access levels
    public const ACCESS_OWN = 'own';           // Only records owned by user
    public const ACCESS_TEAM = 'team';          // Records owned by team members
    public const ACCESS_ALL = 'all';            // All records in module
    public const ACCESS_NONE = 'none';          // No access

    /**
     * Get the role this permission belongs to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    /**
     * Get the module this permission applies to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get available record access levels.
     */
    public static function getAccessLevels(): array
    {
        return [
            self::ACCESS_OWN => 'Own Records Only',
            self::ACCESS_TEAM => 'Team Records',
            self::ACCESS_ALL => 'All Records',
            self::ACCESS_NONE => 'No Access',
        ];
    }

    /**
     * Check if a specific field is restricted.
     */
    public function isFieldRestricted(string $fieldApiName): bool
    {
        $restrictions = $this->field_restrictions ?? [];

        return in_array($fieldApiName, $restrictions);
    }

    /**
     * Get hidden fields for this permission.
     */
    public function getHiddenFields(): array
    {
        return $this->field_restrictions ?? [];
    }

    /**
     * Check if user can perform action.
     */
    public function canPerform(string $action): bool
    {
        return match ($action) {
            'view' => $this->can_view,
            'create' => $this->can_create,
            'edit' => $this->can_edit,
            'delete' => $this->can_delete,
            'export' => $this->can_export,
            'import' => $this->can_import,
            default => false,
        };
    }
}
