<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Contract;

use App\Domain\Contract\Entities\Contract as ContractEntity;
use App\Domain\Contract\Repositories\ContractRepositoryInterface;
use App\Domain\Contract\ValueObjects\BillingFrequency;
use App\Domain\Contract\ValueObjects\ContractStatus;
use App\Domain\Contract\ValueObjects\RenewalStatus;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbContractRepository implements ContractRepositoryInterface
{
    private const TABLE = 'contracts';
    private const LINE_ITEMS_TABLE = 'contract_line_items';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    /**
     * Find a contract by ID (returns domain entity).
     */
    public function findById(int $id): ?ContractEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    /**
     * Save a contract entity (create or update).
     */
    public function save(ContractEntity $contract): ContractEntity
    {
        $data = $this->toRowData($contract);

        if ($contract->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $contract->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $contract->getId();
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

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    /**
     * Find a contract by ID (returns array for backward compatibility).
     */
    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load owner relation
        if ($row->owner_id) {
            $owner = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $row->owner_id)
                ->first();
            $result['owner'] = $owner ? (array) $owner : null;
        }

        // Load line items with products
        $lineItems = DB::table(self::LINE_ITEMS_TABLE)
            ->where('contract_id', $id)
            ->orderBy('display_order')
            ->get();

        $result['lineItems'] = $lineItems->map(function ($item) {
            $itemArray = (array) $item;
            // Load product if exists
            if ($item->product_id) {
                $product = DB::table('products')
                    ->where('id', $item->product_id)
                    ->first();
                $itemArray['product'] = $product ? (array) $product : null;
            }
            return $itemArray;
        })->all();

        // Load renewals
        $renewals = DB::table('renewals')
            ->where('contract_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
        $result['renewals'] = $renewals;

        // Load reminders
        $reminders = DB::table('renewal_reminders')
            ->where('contract_id', $id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
        $result['reminders'] = $reminders;

        return $result;
    }

    /**
     * Find all contracts.
     */
    public function findAll(): array
    {
        $contracts = DB::table(self::TABLE)->get();

        return $contracts->map(function ($contract) {
            $result = (array) $contract;

            // Load owner
            if ($contract->owner_id) {
                $owner = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $contract->owner_id)
                    ->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            // Load line items
            $lineItems = DB::table(self::LINE_ITEMS_TABLE)
                ->where('contract_id', $contract->id)
                ->orderBy('display_order')
                ->get()
                ->map(fn($item) => (array) $item)
                ->all();
            $result['lineItems'] = $lineItems;

            return $result;
        })->all();
    }

    /**
     * List contracts with filtering and pagination.
     */
    public function listContracts(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter active only (scope: active)
        if (!empty($filters['active'])) {
            $query->where('status', 'active');
        }

        // Filter by owner
        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        // Filter by related module/record (scope: forModule)
        if (!empty($filters['related_module']) && !empty($filters['related_id'])) {
            $query->where('related_module', $filters['related_module'])
                ->where('related_id', $filters['related_id']);
        }

        // Filter expiring soon (scope: expiring)
        if (!empty($filters['expiring_within'])) {
            $query->where('status', 'active')
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDays($filters['expiring_within']));
        }

        // Filter expired (scope: expired)
        if (!empty($filters['expired'])) {
            $query->where('status', 'active')
                ->where('end_date', '<', now());
        }

        // Filter by renewal status
        if (!empty($filters['renewal_status'])) {
            $query->where('renewal_status', $filters['renewal_status']);
        }

        // Filter by auto-renew
        if (isset($filters['auto_renew'])) {
            $query->where('auto_renew', $filters['auto_renew']);
        }

        // Filter by date range
        if (!empty($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }
        if (!empty($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }
        if (!empty($filters['end_date_from'])) {
            $query->where('end_date', '>=', $filters['end_date_from']);
        }
        if (!empty($filters['end_date_to'])) {
            $query->where('end_date', '<=', $filters['end_date_to']);
        }

        // Filter by value range
        if (!empty($filters['min_value'])) {
            $query->where('value', '>=', $filters['min_value']);
        }
        if (!empty($filters['max_value'])) {
            $query->where('value', '<=', $filters['max_value']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contract_number', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get paginated items
        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Map items with relations
        $mappedItems = $items->map(function ($contract) {
            $result = (array) $contract;

            // Load owner
            if ($contract->owner_id) {
                $owner = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $contract->owner_id)
                    ->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            // Load line items
            $lineItems = DB::table(self::LINE_ITEMS_TABLE)
                ->where('contract_id', $contract->id)
                ->orderBy('display_order')
                ->get()
                ->map(fn($item) => (array) $item)
                ->all();
            $result['lineItems'] = $lineItems;

            return $result;
        })->all();

        return PaginatedResult::create(
            items: $mappedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    /**
     * Get contracts for a related record.
     */
    public function getContractsForRecord(string $module, int $recordId): array
    {
        $contracts = DB::table(self::TABLE)
            ->where('related_module', $module)
            ->where('related_id', $recordId)
            ->orderBy('end_date')
            ->get();

        return $contracts->map(function ($contract) {
            $result = (array) $contract;

            // Load owner
            if ($contract->owner_id) {
                $owner = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $contract->owner_id)
                    ->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            // Load line items
            $lineItems = DB::table(self::LINE_ITEMS_TABLE)
                ->where('contract_id', $contract->id)
                ->orderBy('display_order')
                ->get()
                ->map(fn($item) => (array) $item)
                ->all();
            $result['lineItems'] = $lineItems;

            return $result;
        })->all();
    }

    /**
     * Get contracts expiring within days.
     */
    public function getExpiringContracts(int $withinDays = 30): array
    {
        $contracts = DB::table(self::TABLE)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->where('end_date', '<=', now()->addDays($withinDays))
            ->orderBy('end_date')
            ->get();

        return $contracts->map(function ($contract) {
            $result = (array) $contract;

            // Load owner
            if ($contract->owner_id) {
                $owner = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $contract->owner_id)
                    ->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            return $result;
        })->all();
    }

    /**
     * Get expired contracts.
     */
    public function getExpiredContracts(): array
    {
        $contracts = DB::table(self::TABLE)
            ->where('status', 'active')
            ->where('end_date', '<', now())
            ->orderBy('end_date', 'desc')
            ->get();

        return $contracts->map(function ($contract) {
            $result = (array) $contract;

            // Load owner
            if ($contract->owner_id) {
                $owner = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $contract->owner_id)
                    ->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            return $result;
        })->all();
    }

    /**
     * Get contract statistics.
     */
    public function getContractStats(?int $ownerId = null): array
    {
        $baseQuery = function () use ($ownerId) {
            $query = DB::table(self::TABLE);
            if ($ownerId) {
                $query->where('owner_id', $ownerId);
            }
            return $query;
        };

        $active = $baseQuery()->where('status', 'active')->count();
        $activeValue = $baseQuery()->where('status', 'active')->sum('value');

        $expiring = $baseQuery()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->where('end_date', '<=', now()->addDays(30))
            ->count();

        $expiringValue = $baseQuery()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->where('end_date', '<=', now()->addDays(30))
            ->sum('value');

        $expired = $baseQuery()
            ->where('status', 'active')
            ->where('end_date', '<', now())
            ->count();

        $byType = $baseQuery()
            ->where('status', 'active')
            ->selectRaw('type, COUNT(*) as count, SUM(value) as total_value')
            ->groupBy('type')
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();

        $byStatus = $baseQuery()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'active_contracts' => $active,
            'active_value' => (float) $activeValue,
            'expiring_contracts' => $expiring,
            'expiring_value' => (float) $expiringValue,
            'expired_contracts' => $expired,
            'by_type' => $byType,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get renewal pipeline.
     */
    public function getRenewalPipeline(?int $ownerId = null): array
    {
        $baseQuery = function () use ($ownerId) {
            $query = DB::table(self::TABLE)
                ->where('status', 'active')
                ->where('end_date', '>=', now());

            if ($ownerId) {
                $query->where('owner_id', $ownerId);
            }

            return $query;
        };

        $next30Days = $baseQuery()
            ->where('end_date', '<=', now()->addDays(30))
            ->get();

        $next60Days = $baseQuery()
            ->where('end_date', '>', now()->addDays(30))
            ->where('end_date', '<=', now()->addDays(60))
            ->get();

        $next90Days = $baseQuery()
            ->where('end_date', '>', now()->addDays(60))
            ->where('end_date', '<=', now()->addDays(90))
            ->get();

        return [
            'next_30_days' => [
                'count' => $next30Days->count(),
                'value' => (float) $next30Days->sum('value'),
                'contracts' => $next30Days->map(fn($row) => (array) $row)->all(),
            ],
            'next_60_days' => [
                'count' => $next60Days->count(),
                'value' => (float) $next60Days->sum('value'),
                'contracts' => $next60Days->map(fn($row) => (array) $row)->all(),
            ],
            'next_90_days' => [
                'count' => $next90Days->count(),
                'value' => (float) $next90Days->sum('value'),
                'contracts' => $next90Days->map(fn($row) => (array) $row)->all(),
            ],
        ];
    }

    /**
     * Get contracts needing renewal attention.
     */
    public function getContractsNeedingRenewalAttention(): array
    {
        $contracts = DB::table(self::TABLE)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereRaw('end_date <= NOW() + INTERVAL renewal_notice_days DAY')
                    ->where('end_date', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('renewal_status')
                    ->orWhere('renewal_status', 'pending');
            })
            ->orderBy('end_date')
            ->get();

        return $contracts->map(function ($contract) {
            $result = (array) $contract;

            // Load owner
            if ($contract->owner_id) {
                $owner = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->where('id', $contract->owner_id)
                    ->first();
                $result['owner'] = $owner ? (array) $owner : null;
            }

            return $result;
        })->all();
    }

    /**
     * Create a new contract.
     */
    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        $contract = (array) DB::table(self::TABLE)->where('id', $id)->first();

        // Load line items
        $lineItems = DB::table(self::LINE_ITEMS_TABLE)
            ->where('contract_id', $id)
            ->orderBy('display_order')
            ->get()
            ->map(fn($item) => (array) $item)
            ->all();
        $contract['lineItems'] = $lineItems;

        return $contract;
    }

    /**
     * Update a contract.
     */
    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $contract = (array) DB::table(self::TABLE)->where('id', $id)->first();

        // Load line items
        $lineItems = DB::table(self::LINE_ITEMS_TABLE)
            ->where('contract_id', $id)
            ->orderBy('display_order')
            ->get()
            ->map(fn($item) => (array) $item)
            ->all();
        $contract['lineItems'] = $lineItems;

        return $contract;
    }

    /**
     * Delete a contract.
     */
    public function delete(int $id): bool
    {
        // Soft delete
        $affected = DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]);

        return $affected > 0;
    }

    /**
     * Generate a contract number.
     */
    public function generateContractNumber(): string
    {
        $prefix = 'CON';
        $year = now()->format('Y');
        $count = DB::table(self::TABLE)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $count);
    }

    /**
     * Add line items to a contract.
     */
    public function addLineItems(int $contractId, array $items): array
    {
        $contract = DB::table(self::TABLE)->where('id', $contractId)->first();

        if (!$contract) {
            throw new \RuntimeException("Contract not found: {$contractId}");
        }

        $maxOrder = DB::table(self::LINE_ITEMS_TABLE)
            ->where('contract_id', $contractId)
            ->max('display_order') ?? 0;

        $created = [];

        foreach ($items as $index => $item) {
            $id = DB::table(self::LINE_ITEMS_TABLE)->insertGetId([
                'contract_id' => $contractId,
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'start_date' => $item['start_date'] ?? $contract->start_date,
                'end_date' => $item['end_date'] ?? $contract->end_date,
                'display_order' => $maxOrder + $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lineItem = DB::table(self::LINE_ITEMS_TABLE)->where('id', $id)->first();
            $created[] = (array) $lineItem;
        }

        $this->recalculateContractValue($contractId);

        return $created;
    }

    /**
     * Update a line item.
     */
    public function updateLineItem(int $lineItemId, array $data): array
    {
        $item = DB::table(self::LINE_ITEMS_TABLE)->where('id', $lineItemId)->first();

        if (!$item) {
            throw new \RuntimeException("Line item not found: {$lineItemId}");
        }

        DB::table(self::LINE_ITEMS_TABLE)
            ->where('id', $lineItemId)
            ->update([
                'product_id' => $data['product_id'] ?? $item->product_id,
                'name' => $data['name'] ?? $item->name,
                'description' => $data['description'] ?? $item->description,
                'quantity' => $data['quantity'] ?? $item->quantity,
                'unit_price' => $data['unit_price'] ?? $item->unit_price,
                'discount_percent' => $data['discount_percent'] ?? $item->discount_percent,
                'start_date' => $data['start_date'] ?? $item->start_date,
                'end_date' => $data['end_date'] ?? $item->end_date,
                'updated_at' => now(),
            ]);

        $this->recalculateContractValue($item->contract_id);

        $updated = DB::table(self::LINE_ITEMS_TABLE)->where('id', $lineItemId)->first();
        return (array) $updated;
    }

    /**
     * Delete a line item.
     */
    public function deleteLineItem(int $lineItemId): bool
    {
        $item = DB::table(self::LINE_ITEMS_TABLE)->where('id', $lineItemId)->first();

        if (!$item) {
            throw new \RuntimeException("Line item not found: {$lineItemId}");
        }

        $contractId = $item->contract_id;

        $deleted = DB::table(self::LINE_ITEMS_TABLE)
            ->where('id', $lineItemId)
            ->delete();

        if ($deleted) {
            $this->recalculateContractValue($contractId);
        }

        return $deleted > 0;
    }

    /**
     * Reorder line items.
     */
    public function reorderLineItems(int $contractId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $itemId) {
            DB::table(self::LINE_ITEMS_TABLE)
                ->where('id', $itemId)
                ->where('contract_id', $contractId)
                ->update([
                    'display_order' => $index + 1,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Recalculate contract value from line items.
     */
    public function recalculateContractValue(int $contractId): void
    {
        $total = DB::table(self::LINE_ITEMS_TABLE)
            ->where('contract_id', $contractId)
            ->sum('total');

        DB::table(self::TABLE)
            ->where('id', $contractId)
            ->update([
                'value' => $total,
                'updated_at' => now(),
            ]);
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    /**
     * Map database row to domain entity.
     */
    private function toDomainEntity(stdClass $row): ContractEntity
    {
        return ContractEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            contractNumber: $row->contract_number,
            relatedModule: $row->related_module,
            relatedId: $row->related_id ? (int) $row->related_id : null,
            type: $row->type,
            status: ContractStatus::from($row->status),
            value: $row->value,
            currency: $row->currency ?? 'USD',
            billingFrequency: $row->billing_frequency ? BillingFrequency::from($row->billing_frequency) : null,
            startDate: $row->start_date ? new DateTimeImmutable($row->start_date) : null,
            endDate: $row->end_date ? new DateTimeImmutable($row->end_date) : null,
            renewalDate: $row->renewal_date ? new DateTimeImmutable($row->renewal_date) : null,
            renewalNoticeDays: $row->renewal_notice_days ?? 30,
            autoRenew: (bool) ($row->auto_renew ?? false),
            renewalStatus: $row->renewal_status ? RenewalStatus::from($row->renewal_status) : null,
            ownerId: $row->owner_id ? (int) $row->owner_id : null,
            terms: $row->terms,
            notes: $row->notes,
            customFields: $row->custom_fields ? json_decode($row->custom_fields, true) : [],
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    /**
     * Map domain entity to database row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(ContractEntity $contract): array
    {
        return [
            'name' => $contract->getName(),
            'contract_number' => $contract->getContractNumber(),
            'related_module' => $contract->getRelatedModule(),
            'related_id' => $contract->getRelatedId(),
            'type' => $contract->getType(),
            'status' => $contract->getStatus()->value,
            'value' => $contract->getValue(),
            'currency' => $contract->getCurrency(),
            'billing_frequency' => $contract->getBillingFrequency()?->value,
            'start_date' => $contract->getStartDate()?->format('Y-m-d H:i:s'),
            'end_date' => $contract->getEndDate()?->format('Y-m-d H:i:s'),
            'renewal_date' => $contract->getRenewalDate()?->format('Y-m-d H:i:s'),
            'renewal_notice_days' => $contract->getRenewalNoticeDays(),
            'auto_renew' => $contract->isAutoRenew(),
            'renewal_status' => $contract->getRenewalStatus()?->value,
            'owner_id' => $contract->getOwnerId(),
            'terms' => $contract->getTerms(),
            'notes' => $contract->getNotes(),
            'custom_fields' => json_encode($contract->getCustomFields()),
        ];
    }
}
