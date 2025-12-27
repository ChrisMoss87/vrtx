<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entities;

use App\Domain\Authorization\ValueObjects\ModuleAccess;
use App\Domain\Authorization\ValueObjects\RecordAccessLevel;
use App\Domain\Authorization\ValueObjects\RoleId;
use DateTimeImmutable;

final class ModulePermission
{
    private function __construct(
        private ?int $id,
        private RoleId $roleId,
        private int $moduleId,
        private ModuleAccess $access,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        RoleId $roleId,
        int $moduleId,
        ModuleAccess $access,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            roleId: $roleId,
            moduleId: $moduleId,
            access: $access,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        int $id,
        int $roleId,
        int $moduleId,
        bool $canView,
        bool $canCreate,
        bool $canEdit,
        bool $canDelete,
        bool $canExport,
        bool $canImport,
        string $recordAccessLevel,
        array $fieldRestrictions,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            roleId: RoleId::fromInt($roleId),
            moduleId: $moduleId,
            access: new ModuleAccess(
                canView: $canView,
                canCreate: $canCreate,
                canEdit: $canEdit,
                canDelete: $canDelete,
                canExport: $canExport,
                canImport: $canImport,
                recordAccessLevel: RecordAccessLevel::tryFrom($recordAccessLevel) ?? RecordAccessLevel::NONE,
                restrictedFields: $fieldRestrictions,
            ),
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoleId(): RoleId
    {
        return $this->roleId;
    }

    public function getRoleIdValue(): int
    {
        return $this->roleId->value();
    }

    public function getModuleId(): int
    {
        return $this->moduleId;
    }

    public function getAccess(): ModuleAccess
    {
        return $this->access;
    }

    public function canView(): bool
    {
        return $this->access->canView;
    }

    public function canCreate(): bool
    {
        return $this->access->canCreate;
    }

    public function canEdit(): bool
    {
        return $this->access->canEdit;
    }

    public function canDelete(): bool
    {
        return $this->access->canDelete;
    }

    public function canExport(): bool
    {
        return $this->access->canExport;
    }

    public function canImport(): bool
    {
        return $this->access->canImport;
    }

    public function getRecordAccessLevel(): RecordAccessLevel
    {
        return $this->access->recordAccessLevel;
    }

    /**
     * @return array<string>
     */
    public function getRestrictedFields(): array
    {
        return $this->access->restrictedFields;
    }

    public function isFieldRestricted(string $fieldName): bool
    {
        return $this->access->isFieldRestricted($fieldName);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function can(string $action): bool
    {
        return $this->access->can($action);
    }

    public function withAccess(ModuleAccess $access): self
    {
        return new self(
            id: $this->id,
            roleId: $this->roleId,
            moduleId: $this->moduleId,
            access: $access,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'role_id' => $this->roleId->value(),
            'module_id' => $this->moduleId,
            'can_view' => $this->access->canView,
            'can_create' => $this->access->canCreate,
            'can_edit' => $this->access->canEdit,
            'can_delete' => $this->access->canDelete,
            'can_export' => $this->access->canExport,
            'can_import' => $this->access->canImport,
            'record_access_level' => $this->access->recordAccessLevel->value,
            'field_restrictions' => $this->access->restrictedFields,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
