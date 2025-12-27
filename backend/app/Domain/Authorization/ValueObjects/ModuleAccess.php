<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use JsonSerializable;

/**
 * Value object representing module-level access permissions.
 * This is a DTO-style value object that aggregates CRUD permissions for a module.
 */
final readonly class ModuleAccess implements JsonSerializable
{
    public function __construct(
        public bool $canView = false,
        public bool $canCreate = false,
        public bool $canEdit = false,
        public bool $canDelete = false,
        public bool $canExport = false,
        public bool $canImport = false,
        public RecordAccessLevel $recordAccessLevel = RecordAccessLevel::NONE,
        public array $restrictedFields = [],
    ) {}

    public static function none(): self
    {
        return new self();
    }

    public static function fullAccess(): self
    {
        return new self(
            canView: true,
            canCreate: true,
            canEdit: true,
            canDelete: true,
            canExport: true,
            canImport: true,
            recordAccessLevel: RecordAccessLevel::ALL,
            restrictedFields: [],
        );
    }

    public static function viewOnly(): self
    {
        return new self(
            canView: true,
            canCreate: false,
            canEdit: false,
            canDelete: false,
            canExport: false,
            canImport: false,
            recordAccessLevel: RecordAccessLevel::OWN,
            restrictedFields: [],
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            canView: (bool) ($data['can_view'] ?? false),
            canCreate: (bool) ($data['can_create'] ?? false),
            canEdit: (bool) ($data['can_edit'] ?? false),
            canDelete: (bool) ($data['can_delete'] ?? false),
            canExport: (bool) ($data['can_export'] ?? false),
            canImport: (bool) ($data['can_import'] ?? false),
            recordAccessLevel: RecordAccessLevel::tryFrom($data['record_access_level'] ?? 'none') ?? RecordAccessLevel::NONE,
            restrictedFields: $data['field_restrictions'] ?? $data['restricted_fields'] ?? [],
        );
    }

    /**
     * Check if a specific action is allowed.
     */
    public function can(string $action): bool
    {
        return match ($action) {
            'view' => $this->canView,
            'create' => $this->canCreate,
            'edit', 'update' => $this->canEdit,
            'delete' => $this->canDelete,
            'export' => $this->canExport,
            'import' => $this->canImport,
            default => false,
        };
    }

    /**
     * Check if a field is restricted.
     */
    public function isFieldRestricted(string $fieldName): bool
    {
        return in_array($fieldName, $this->restrictedFields, true);
    }

    /**
     * Get the list of allowed actions.
     *
     * @return array<string>
     */
    public function allowedActions(): array
    {
        $actions = [];

        if ($this->canView) {
            $actions[] = 'view';
        }
        if ($this->canCreate) {
            $actions[] = 'create';
        }
        if ($this->canEdit) {
            $actions[] = 'edit';
        }
        if ($this->canDelete) {
            $actions[] = 'delete';
        }
        if ($this->canExport) {
            $actions[] = 'export';
        }
        if ($this->canImport) {
            $actions[] = 'import';
        }

        return $actions;
    }

    /**
     * Merge with another ModuleAccess, taking the more permissive option for each.
     */
    public function merge(self $other): self
    {
        return new self(
            canView: $this->canView || $other->canView,
            canCreate: $this->canCreate || $other->canCreate,
            canEdit: $this->canEdit || $other->canEdit,
            canDelete: $this->canDelete || $other->canDelete,
            canExport: $this->canExport || $other->canExport,
            canImport: $this->canImport || $other->canImport,
            recordAccessLevel: $this->recordAccessLevel->merge($other->recordAccessLevel),
            // Take intersection of restricted fields (fewer restrictions)
            restrictedFields: array_values(array_intersect($this->restrictedFields, $other->restrictedFields)),
        );
    }

    /**
     * Check if any access is granted.
     */
    public function hasAnyAccess(): bool
    {
        return $this->canView || $this->canCreate || $this->canEdit ||
               $this->canDelete || $this->canExport || $this->canImport;
    }

    public function toArray(): array
    {
        return [
            'can_view' => $this->canView,
            'can_create' => $this->canCreate,
            'can_edit' => $this->canEdit,
            'can_delete' => $this->canDelete,
            'can_export' => $this->canExport,
            'can_import' => $this->canImport,
            'record_access_level' => $this->recordAccessLevel->value,
            'restricted_fields' => $this->restrictedFields,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
