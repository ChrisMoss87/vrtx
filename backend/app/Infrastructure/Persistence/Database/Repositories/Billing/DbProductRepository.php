<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Billing;

use App\Domain\Billing\Entities\Product;
use App\Domain\Billing\Repositories\ProductRepositoryInterface;
use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the ProductRepository.
 */
class DbProductRepository implements ProductRepositoryInterface
{
    private const TABLE = 'products';

    public function findById(int $id): ?Product
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findBySku(string $sku): ?Product
    {
        $row = DB::table(self::TABLE)->where('sku', $sku)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)->orderBy('name')->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findActive(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByCategoryId(int $categoryId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('category_id', $categoryId)
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function search(string $query): array
    {
        $rows = DB::table(self::TABLE)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                    ->orWhere('sku', 'ilike', "%{$query}%")
                    ->orWhere('description', 'ilike', "%{$query}%");
            })
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function save(Product $product): Product
    {
        $data = $this->toRowData($product);

        if ($product->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $product->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $product->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)->where('sku', $sku);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function searchPaginated(
        array $filters = [],
        array $orderBy = ['name' => 'asc'],
        int $page = 1,
        int $perPage = 25
    ): PaginatedResult {
        $query = DB::table(self::TABLE);

        // Apply filters
        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Apply ordering
        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        $total = $query->count();

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->toDomainEntity($row))->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getAllAsArray(): array
    {
        $rows = DB::table(self::TABLE)->orderBy('name')->get();

        return $rows->map(fn($row) => $this->rowToArray($row))->all();
    }

    public function getByFiltersAsArray(array $filters): array
    {
        $query = DB::table(self::TABLE);

        // Apply filters
        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderBy('name')->get();

        return $rows->map(fn($row) => $this->rowToArray($row))->all();
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): Product
    {
        $currency = $row->currency ?? 'USD';

        return Product::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            sku: $row->sku,
            description: $row->description,
            unitPrice: new Money((float) $row->unit_price, $currency),
            currency: $currency,
            taxRate: (float) ($row->tax_rate ?? 0),
            isActive: (bool) $row->is_active,
            categoryId: $row->category_id ? (int) $row->category_id : null,
            unit: $row->unit ?? 'unit',
            settings: $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(Product $product): array
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
            'settings' => json_encode($product->getSettings()),
        ];
    }

    /**
     * Convert a database row to array.
     *
     * @return array<string, mixed>
     */
    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'sku' => $row->sku,
            'description' => $row->description,
            'unit_price' => (float) $row->unit_price,
            'currency' => $row->currency,
            'tax_rate' => (float) ($row->tax_rate ?? 0),
            'is_active' => (bool) $row->is_active,
            'category_id' => $row->category_id,
            'unit' => $row->unit,
            'settings' => $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
