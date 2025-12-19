<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

use App\Domain\Reporting\Entities\DashboardTemplate;

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
     * Find a template by slug.
     */
    public function findBySlug(string $slug): ?DashboardTemplate;

    /**
     * Get all active templates.
     *
     * @return array<DashboardTemplate>
     */
    public function findAllActive(): array;

    /**
     * Get templates by category.
     *
     * @return array<DashboardTemplate>
     */
    public function findByCategory(string $category): array;

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
