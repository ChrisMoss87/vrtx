<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Billing;

use App\Domain\Billing\Entities\Product;
use App\Domain\Billing\Repositories\ProductRepositoryInterface;
use App\Domain\Billing\ValueObjects\Money;
use App\Models\Product as ProductModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the ProductRepository.
 */
class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?Product
    {
        $model = ProductModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findBySku(string $sku): ?Product
    {
        $model = ProductModel::where('sku', $sku)->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findAll(): array
    {
        $models = ProductModel::orderBy('name')->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findActive(): array
    {
        $models = ProductModel::where('is_active', true)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByCategoryId(int $categoryId): array
    {
        $models = ProductModel::where('category_id', $categoryId)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function search(string $query): array
    {
        $models = ProductModel::where(function ($q) use ($query) {
            $q->where('name', 'ilike', "%{$query}%")
                ->orWhere('sku', 'ilike', "%{$query}%")
                ->orWhere('description', 'ilike', "%{$query}%");
        })
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(Product $product): Product
    {
        $data = $this->toModelData($product);

        if ($product->getId() !== null) {
            $model = ProductModel::findOrFail($product->getId());
            $model->update($data);
        } else {
            $model = ProductModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = ProductModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        $query = ProductModel::where('sku', $sku);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(ProductModel $model): Product
    {
        $currency = $model->currency ?? 'USD';

        return Product::reconstitute(
            id: $model->id,
            name: $model->name,
            sku: $model->sku,
            description: $model->description,
            unitPrice: new Money((float) $model->unit_price, $currency),
            currency: $currency,
            taxRate: (float) $model->tax_rate,
            isActive: (bool) $model->is_active,
            categoryId: $model->category_id,
            unit: $model->unit ?? 'unit',
            settings: $model->settings ?? [],
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->format('Y-m-d H:i:s'))
                : null,
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->format('Y-m-d H:i:s'))
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(Product $product): array
    {
        return [
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'description' => $product->getDescription(),
            'unit_price' => $product->getUnitPrice()->amount(),
            'currency' => $product->getCurrency(),
            'tax_rate' => $product->getTaxRate(),
            'is_active' => $product->isActive(),
            'category_id' => $product->getCategoryId(),
            'unit' => $product->getUnit(),
            'settings' => $product->getSettings(),
        ];
    }
}
