<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\Product;

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
