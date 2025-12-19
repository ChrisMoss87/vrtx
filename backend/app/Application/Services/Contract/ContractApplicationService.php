<?php

declare(strict_types=1);

namespace App\Application\Services\Contract;

use App\Domain\Contract\Repositories\ContractRepositoryInterface;
use App\Models\Contract;
use App\Models\ContractLineItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContractApplicationService
{
    public function __construct(
        private ContractRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CONTRACTS
    // =========================================================================

    /**
     * List contracts with filtering and pagination.
     */
    public function listContracts(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Contract::query()->with(['owner:id,name,email', 'lineItems']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->active();
        }

        // Filter by owner
        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        // Filter by related module/record
        if (!empty($filters['related_module']) && !empty($filters['related_id'])) {
            $query->forModule($filters['related_module'], $filters['related_id']);
        }

        // Filter expiring soon
        if (!empty($filters['expiring_within'])) {
            $query->expiring($filters['expiring_within']);
        }

        // Filter expired
        if (!empty($filters['expired'])) {
            $query->expired();
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

        return $query->paginate($perPage);
    }

    /**
     * Get a single contract by ID.
     */
    public function getContract(int $id): ?Contract
    {
        return Contract::with(['owner:id,name,email', 'lineItems.product', 'renewals', 'reminders'])->find($id);
    }

    /**
     * Get contracts for a related record.
     */
    public function getContractsForRecord(string $module, int $recordId): Collection
    {
        return Contract::forModule($module, $recordId)
            ->with(['owner:id,name,email', 'lineItems'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Get contracts expiring soon.
     */
    public function getExpiringContracts(int $withinDays = 30): Collection
    {
        return Contract::expiring($withinDays)
            ->with(['owner:id,name,email'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Get expired contracts.
     */
    public function getExpiredContracts(): Collection
    {
        return Contract::expired()
            ->with(['owner:id,name,email'])
            ->orderBy('end_date', 'desc')
            ->get();
    }

    /**
     * Get contract statistics.
     */
    public function getContractStats(?int $ownerId = null): array
    {
        $query = Contract::query();

        if ($ownerId) {
            $query->where('owner_id', $ownerId);
        }

        $active = (clone $query)->active()->count();
        $activeValue = (clone $query)->active()->sum('value');
        $expiring = (clone $query)->expiring(30)->count();
        $expiringValue = (clone $query)->expiring(30)->sum('value');
        $expired = (clone $query)->expired()->count();

        $byType = (clone $query)->active()
            ->selectRaw('type, COUNT(*) as count, SUM(value) as total_value')
            ->groupBy('type')
            ->get();

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'active_contracts' => $active,
            'active_value' => $activeValue,
            'expiring_contracts' => $expiring,
            'expiring_value' => $expiringValue,
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
        $query = Contract::active()->where('end_date', '>=', now());

        if ($ownerId) {
            $query->where('owner_id', $ownerId);
        }

        $next30Days = (clone $query)->expiring(30)->get();
        $next60Days = (clone $query)->where('end_date', '>', now()->addDays(30))
            ->where('end_date', '<=', now()->addDays(60))->get();
        $next90Days = (clone $query)->where('end_date', '>', now()->addDays(60))
            ->where('end_date', '<=', now()->addDays(90))->get();

        return [
            'next_30_days' => [
                'count' => $next30Days->count(),
                'value' => $next30Days->sum('value'),
                'contracts' => $next30Days,
            ],
            'next_60_days' => [
                'count' => $next60Days->count(),
                'value' => $next60Days->sum('value'),
                'contracts' => $next60Days,
            ],
            'next_90_days' => [
                'count' => $next90Days->count(),
                'value' => $next90Days->sum('value'),
                'contracts' => $next90Days,
            ],
        ];
    }

    // =========================================================================
    // COMMAND USE CASES - CONTRACTS
    // =========================================================================

    /**
     * Create a new contract.
     */
    public function createContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $contract = Contract::create([
                'name' => $data['name'],
                'contract_number' => $data['contract_number'] ?? Contract::generateContractNumber(),
                'related_module' => $data['related_module'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'type' => $data['type'] ?? 'service',
                'status' => $data['status'] ?? 'draft',
                'value' => $data['value'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'billing_frequency' => $data['billing_frequency'] ?? 'monthly',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'renewal_date' => $data['renewal_date'] ?? null,
                'renewal_notice_days' => $data['renewal_notice_days'] ?? 30,
                'auto_renew' => $data['auto_renew'] ?? false,
                'renewal_status' => $data['renewal_status'] ?? null,
                'owner_id' => $data['owner_id'] ?? Auth::id(),
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? [],
            ]);

            // Add line items if provided
            if (!empty($data['line_items'])) {
                $this->addLineItems($contract->id, $data['line_items']);
                $this->recalculateContractValue($contract->id);
            }

            return $contract->fresh(['lineItems']);
        });
    }

    /**
     * Update a contract.
     */
    public function updateContract(int $id, array $data): Contract
    {
        $contract = Contract::findOrFail($id);

        $contract->update([
            'name' => $data['name'] ?? $contract->name,
            'related_module' => $data['related_module'] ?? $contract->related_module,
            'related_id' => $data['related_id'] ?? $contract->related_id,
            'type' => $data['type'] ?? $contract->type,
            'status' => $data['status'] ?? $contract->status,
            'value' => $data['value'] ?? $contract->value,
            'currency' => $data['currency'] ?? $contract->currency,
            'billing_frequency' => $data['billing_frequency'] ?? $contract->billing_frequency,
            'start_date' => $data['start_date'] ?? $contract->start_date,
            'end_date' => $data['end_date'] ?? $contract->end_date,
            'renewal_date' => $data['renewal_date'] ?? $contract->renewal_date,
            'renewal_notice_days' => $data['renewal_notice_days'] ?? $contract->renewal_notice_days,
            'auto_renew' => $data['auto_renew'] ?? $contract->auto_renew,
            'renewal_status' => $data['renewal_status'] ?? $contract->renewal_status,
            'owner_id' => $data['owner_id'] ?? $contract->owner_id,
            'terms' => $data['terms'] ?? $contract->terms,
            'notes' => $data['notes'] ?? $contract->notes,
            'custom_fields' => array_merge($contract->custom_fields ?? [], $data['custom_fields'] ?? []),
        ]);

        return $contract->fresh(['lineItems']);
    }

    /**
     * Delete a contract.
     */
    public function deleteContract(int $id): bool
    {
        $contract = Contract::findOrFail($id);
        return $contract->delete();
    }

    /**
     * Activate a contract.
     */
    public function activateContract(int $id): Contract
    {
        $contract = Contract::findOrFail($id);

        if ($contract->status === 'active') {
            return $contract;
        }

        $contract->update([
            'status' => 'active',
        ]);

        return $contract->fresh();
    }

    /**
     * Terminate a contract.
     */
    public function terminateContract(int $id, ?string $reason = null): Contract
    {
        $contract = Contract::findOrFail($id);

        $contract->update([
            'status' => 'terminated',
            'end_date' => now(),
            'notes' => $reason
                ? ($contract->notes ? $contract->notes . "\n\nTermination reason: " . $reason : "Termination reason: " . $reason)
                : $contract->notes,
        ]);

        return $contract->fresh();
    }

    /**
     * Renew a contract.
     */
    public function renewContract(int $id, array $renewalData = []): Contract
    {
        $original = Contract::with('lineItems')->findOrFail($id);

        return DB::transaction(function () use ($original, $renewalData) {
            // Mark original as renewed
            $original->update([
                'status' => 'renewed',
                'renewal_status' => 'completed',
            ]);

            // Calculate new dates
            $duration = $original->start_date->diffInDays($original->end_date);
            $newStartDate = $renewalData['start_date'] ?? $original->end_date->addDay();
            $newEndDate = $renewalData['end_date'] ?? $newStartDate->copy()->addDays($duration);

            // Create new contract
            $newContract = Contract::create([
                'name' => $original->name,
                'contract_number' => Contract::generateContractNumber(),
                'related_module' => $original->related_module,
                'related_id' => $original->related_id,
                'type' => $original->type,
                'status' => 'active',
                'value' => $renewalData['value'] ?? $original->value,
                'currency' => $original->currency,
                'billing_frequency' => $original->billing_frequency,
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'renewal_notice_days' => $original->renewal_notice_days,
                'auto_renew' => $renewalData['auto_renew'] ?? $original->auto_renew,
                'owner_id' => $original->owner_id,
                'terms' => $renewalData['terms'] ?? $original->terms,
                'custom_fields' => $original->custom_fields,
            ]);

            // Copy line items
            foreach ($original->lineItems as $item) {
                ContractLineItem::create([
                    'contract_id' => $newContract->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $renewalData['adjust_prices'] ?? false
                        ? $item->unit_price * (1 + ($renewalData['price_adjustment'] ?? 0) / 100)
                        : $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'display_order' => $item->display_order,
                ]);
            }

            // Recalculate value
            $this->recalculateContractValue($newContract->id);

            return $newContract->fresh(['lineItems']);
        });
    }

    /**
     * Duplicate a contract as draft.
     */
    public function duplicateContract(int $id): Contract
    {
        $original = Contract::with('lineItems')->findOrFail($id);

        return DB::transaction(function () use ($original) {
            $newContract = Contract::create([
                'name' => $original->name . ' (Copy)',
                'contract_number' => Contract::generateContractNumber(),
                'related_module' => $original->related_module,
                'related_id' => $original->related_id,
                'type' => $original->type,
                'status' => 'draft',
                'value' => $original->value,
                'currency' => $original->currency,
                'billing_frequency' => $original->billing_frequency,
                'start_date' => now(),
                'end_date' => now()->addYear(),
                'renewal_notice_days' => $original->renewal_notice_days,
                'auto_renew' => $original->auto_renew,
                'owner_id' => Auth::id(),
                'terms' => $original->terms,
                'custom_fields' => $original->custom_fields,
            ]);

            // Copy line items
            foreach ($original->lineItems as $item) {
                ContractLineItem::create([
                    'contract_id' => $newContract->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'display_order' => $item->display_order,
                ]);
            }

            return $newContract->fresh(['lineItems']);
        });
    }

    // =========================================================================
    // LINE ITEM USE CASES
    // =========================================================================

    /**
     * Add line items to a contract.
     */
    public function addLineItems(int $contractId, array $items): Collection
    {
        $contract = Contract::findOrFail($contractId);
        $maxOrder = $contract->lineItems()->max('display_order') ?? 0;
        $created = collect();

        foreach ($items as $index => $item) {
            $lineItem = ContractLineItem::create([
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
            ]);
            $created->push($lineItem);
        }

        $this->recalculateContractValue($contractId);

        return $created;
    }

    /**
     * Update a line item.
     */
    public function updateLineItem(int $lineItemId, array $data): ContractLineItem
    {
        $item = ContractLineItem::findOrFail($lineItemId);

        $item->update([
            'product_id' => $data['product_id'] ?? $item->product_id,
            'name' => $data['name'] ?? $item->name,
            'description' => $data['description'] ?? $item->description,
            'quantity' => $data['quantity'] ?? $item->quantity,
            'unit_price' => $data['unit_price'] ?? $item->unit_price,
            'discount_percent' => $data['discount_percent'] ?? $item->discount_percent,
            'start_date' => $data['start_date'] ?? $item->start_date,
            'end_date' => $data['end_date'] ?? $item->end_date,
        ]);

        $this->recalculateContractValue($item->contract_id);

        return $item->fresh();
    }

    /**
     * Delete a line item.
     */
    public function deleteLineItem(int $lineItemId): bool
    {
        $item = ContractLineItem::findOrFail($lineItemId);
        $contractId = $item->contract_id;

        $deleted = $item->delete();

        if ($deleted) {
            $this->recalculateContractValue($contractId);
        }

        return $deleted;
    }

    /**
     * Reorder line items.
     */
    public function reorderLineItems(int $contractId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $itemId) {
            ContractLineItem::where('id', $itemId)
                ->where('contract_id', $contractId)
                ->update(['display_order' => $index + 1]);
        }
    }

    // =========================================================================
    // RENEWAL MANAGEMENT
    // =========================================================================

    /**
     * Set renewal reminder.
     */
    public function setRenewalReminder(int $contractId, int $daysBefore): Contract
    {
        $contract = Contract::findOrFail($contractId);

        $contract->update([
            'renewal_notice_days' => $daysBefore,
            'renewal_date' => $contract->end_date->subDays($daysBefore),
        ]);

        return $contract->fresh();
    }

    /**
     * Mark renewal status.
     */
    public function markRenewalStatus(int $contractId, string $status): Contract
    {
        $contract = Contract::findOrFail($contractId);

        $validStatuses = ['pending', 'in_progress', 'completed', 'declined', 'expired'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid renewal status');
        }

        $contract->update(['renewal_status' => $status]);

        return $contract->fresh();
    }

    /**
     * Get contracts needing renewal attention.
     */
    public function getContractsNeedingRenewalAttention(): Collection
    {
        return Contract::active()
            ->where(function ($query) {
                $query->whereRaw('end_date <= NOW() + INTERVAL renewal_notice_days DAY')
                    ->where('end_date', '>=', now());
            })
            ->whereNull('renewal_status')
            ->orWhere('renewal_status', 'pending')
            ->with(['owner:id,name,email'])
            ->orderBy('end_date')
            ->get();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Recalculate contract value from line items.
     */
    private function recalculateContractValue(int $contractId): void
    {
        $total = ContractLineItem::where('contract_id', $contractId)->sum('total');
        Contract::where('id', $contractId)->update(['value' => $total]);
    }
}
