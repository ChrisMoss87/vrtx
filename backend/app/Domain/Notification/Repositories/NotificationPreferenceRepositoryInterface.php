<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

/**
 * Repository interface for NotificationPreference.
 */
interface NotificationPreferenceRepositoryInterface
{
    /**
     * Get all preferences for a user, keyed by category.
     *
     * @return array<string, array>
     */
    public function getForUser(int $userId): array;

    /**
     * Get preference for a specific category.
     */
    public function getForCategory(int $userId, string $category): ?array;

    /**
     * Update or create a preference.
     *
     * @param array<string, mixed> $settings
     */
    public function updateOrCreate(int $userId, string $category, array $settings): array;

    /**
     * Update multiple preferences in a transaction.
     *
     * @param array<string, array<string, mixed>> $preferences
     */
    public function updateMany(int $userId, array $preferences): void;

    /**
     * Get default preferences for all categories.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDefaults(): array;
}
