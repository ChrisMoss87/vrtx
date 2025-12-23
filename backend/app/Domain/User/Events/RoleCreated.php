<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a role is created.
 */
final class RoleCreated extends DomainEvent
{
    public function __construct(
        private readonly int $roleId,
        private readonly string $name,
        private readonly ?string $guardName,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->roleId;
    }

    public function aggregateType(): string
    {
        return 'Role';
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function guardName(): ?string
    {
        return $this->guardName;
    }

    public function toPayload(): array
    {
        return [
            'role_id' => $this->roleId,
            'name' => $this->name,
            'guard_name' => $this->guardName,
        ];
    }
}
