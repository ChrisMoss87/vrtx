<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Base interface for all domain entities.
 *
 * Entities are objects that have a distinct identity that runs through time
 * and different states. They are defined by their identity, not by their attributes.
 */
interface Entity
{
    /**
     * Get the entity's unique identifier.
     *
     * @return int|string|null Null for new/unsaved entities
     */
    public function getId(): int|string|null;

    /**
     * Check if two entities are the same based on identity.
     */
    public function equals(Entity $other): bool;
}
