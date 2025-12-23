<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\Product;
use App\Domain\Shared\ValueObjects\PaginatedResult;

/**
 * Repository interface for Product entity.
 */
interface ProductRepositoryInterface
{
    /**
     * Find a product by its ID.
     */
    public function findById(int $id): ?Product;

    /**
     * Find a product by its SKU.
     */
    public function findBySku(string $sku): ?Product;

    /**
     * Find all products.
     *
     * @return array<Product>
     */
    public function findAll(): array;

    /**
     * Find active products.
     *
     * @return array<Product>
     */
    public function findActive(): array;

    /**
     * Find products by category.
     *
     * @return array<Product>
     */
    public function findByCategoryId(int $categoryId): array;

    /**
     * Search products by name, SKU, or description.
     *
     * @return array<Product>
     */
    public function search(string $query): array;

    /**
     * Search products with filters and pagination.
     *
     * @param array<string, mixed> $filters
     * @param array<string, string> $orderBy ['field' => 'asc|desc']
     */
    public function searchPaginated(
        array $filters = [],
        array $orderBy = ['name' => 'asc'],
        int $page = 1,
        int $perPage = 25
    ): PaginatedResult;

    /**
     * Get all products as array data.
     *
     * @return array<array<string, mixed>>
     */
    public function getAllAsArray(): array;

    /**
     * Get products by filters as array data.
     *
     * @param array<string, mixed> $filters
     * @return array<array<string, mixed>>
     */
    public function getByFiltersAsArray(array $filters): array;

    /**
     * Save a product (insert or update).
     */
    public function save(Product $product): Product;

    /**
     * Delete a product.
     */
    public function delete(int $id): bool;

    /**
     * Check if a SKU already exists.
     */
    public function skuExists(string $sku, ?int $excludeId = null): bool;
}
