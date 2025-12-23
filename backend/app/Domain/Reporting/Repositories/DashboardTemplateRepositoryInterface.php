<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

use App\Domain\Reporting\Entities\DashboardTemplate;
use App\Domain\Shared\ValueObjects\PaginatedResult;

/**
 * Repository interface for dashboard templates.
 */
interface DashboardTemplateRepositoryInterface
{
    /**
     * Find a template by ID.
     */
    public function findById(int $id): ?DashboardTemplate;

    /**
     * Find a template by ID and return as array.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdAsArray(int $id, bool $includeWidgets = false): ?array;

    /**
     * Find a template by slug.
     */
    public function findBySlug(string $slug): ?DashboardTemplate;

    /**
     * Find a template by slug and return as array.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlugAsArray(string $slug, bool $includeWidgets = false): ?array;

    /**
     * Get all active templates.
     *
     * @return array<DashboardTemplate>
     */
    public function findAllActive(): array;

    /**
     * Get all active templates as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllActiveAsArrays(bool $includeWidgets = false): array;

    /**
     * Get templates by category.
     *
     * @return array<DashboardTemplate>
     */
    public function findByCategory(string $category): array;

    /**
     * Get templates by category as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findByCategoryAsArrays(string $category, bool $includeWidgets = false): array;

    /**
     * Get all template categories.
     *
     * @return array<string>
     */
    public function getCategories(): array;

    /**
     * Save a template.
     */
    public function save(DashboardTemplate $template): DashboardTemplate;

    /**
     * Delete a template.
     */
    public function delete(DashboardTemplate $template): void;
}
