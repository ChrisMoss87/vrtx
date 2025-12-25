<?php

namespace App\Services\Renewal;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RenewalService
{
    /**
     * Create a new contract
     */
    public function createContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $contract = DB::table('contracts')->insertGetId([
                'name' => $data['name'],
                'contract_number' => $data['contract_number'] ?? Contract::generateContractNumber(),
                'related_module' => $data['related_module'],
                'related_id' => $data['related_id'],
                'type' => $data['type'] ?? 'subscription',
                'status' => $data['status'] ?? 'active',
                'value' => $data['value'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'billing_frequency' => $data['billing_frequency'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'renewal_date' => $data['renewal_date'] ?? null,
                'renewal_notice_days' => $data['renewal_notice_days'] ?? 30,
                'auto_renew' => $data['auto_renew'] ?? false,
                'owner_id' => $data['owner_id'] ?? Auth::id(),
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
            ]);

            // Create line items if provided
            if (!empty($data['line_items'])) {
                foreach ($data['line_items'] as $index => $item) {
                    $contract->lineItems()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'discount_percent' => $item['discount_percent'] ?? 0,
                        'start_date' => $item['start_date'] ?? null,
                        'end_date' => $item['end_date'] ?? null,
                        'display_order' => $index,
                    ]);
                }

                // Recalculate contract value
                $contract->value = $contract->lineItems->sum('total');
                $contract->save();
            }

            // Set up default renewal reminders
            $this->createDefaultReminders($contract);

            return $contract->load('lineItems', 'owner');
        });
    }

    /**
     * Update a contract
     */
    public function updateContract(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data) {
            $contract->update($data);

            if (isset($data['line_items'])) {
                // Remove existing and recreate
                $contract->lineItems()->delete();

                foreach ($data['line_items'] as $index => $item) {
                    $contract->lineItems()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'discount_percent' => $item['discount_percent'] ?? 0,
                        'start_date' => $item['start_date'] ?? null,
                        'end_date' => $item['end_date'] ?? null,
                        'display_order' => $index,
                    ]);
                }

                // Recalculate contract value
                $contract->value = $contract->lineItems()->sum('total');
                $contract->save();
            }

            return $contract->fresh(['lineItems', 'owner']);
        });
    }

    /**
     * Create default renewal reminders for a contract
     */
    public function createDefaultReminders(Contract $contract): void
    {
        $defaultReminders = [
            ['days_before' => 90, 'reminder_type' => 'email'],
            ['days_before' => 60, 'reminder_type' => 'email'],
            ['days_before' => 30, 'reminder_type' => 'email'],
            ['days_before' => 14, 'reminder_type' => 'email'],
            ['days_before' => 7, 'reminder_type' => 'email'],
        ];

        foreach ($defaultReminders as $reminder) {
            $scheduledAt = $contract->end_date->subDays($reminder['days_before']);

            if ($scheduledAt->isFuture()) {
                $contract->reminders()->create([
                    'days_before' => $reminder['days_before'],
                    'reminder_type' => $reminder['reminder_type'],
                    'scheduled_at' => $scheduledAt,
                ]);
            }
        }
    }

    /**
     * Create a renewal opportunity for a contract
     */
    public function createRenewal(Contract $contract, ?int $ownerId = null): Renewal
    {
        $renewal = DB::table('renewals')->insertGetId([
            'contract_id' => $contract->id,
            'status' => 'pending',
            'original_value' => $contract->value,
            'due_date' => $contract->end_date,
            'owner_id' => $ownerId ?? $contract->owner_id ?? Auth::id(),
        ]);

        $contract->update(['renewal_status' => 'pending']);

        $this->logActivity($renewal, 'created', 'Renewal opportunity created');

        return $renewal;
    }

    /**
     * Start working on a renewal
     */
    public function startRenewal(Renewal $renewal): Renewal
    {
        $renewal->update(['status' => 'in_progress']);
        $renewal->contract->update(['renewal_status' => 'in_progress']);

        $this->logActivity($renewal, 'status_change', 'Renewal started', [
            'old_status' => 'pending',
            'new_status' => 'in_progress',
        ]);

        return $renewal;
    }

    /**
     * Mark a renewal as won
     */
    public function winRenewal(Renewal $renewal, array $data): Renewal
    {
        return DB::transaction(function () use ($renewal, $data) {
            $renewalValue = $data['renewal_value'] ?? $renewal->original_value;
            $upsellValue = $data['upsell_value'] ?? 0;
            $renewalType = $this->determineRenewalType($renewal->original_value, $renewalValue);

            $renewal->update([
                'status' => 'won',
                'renewal_value' => $renewalValue,
                'upsell_value' => $upsellValue,
                'renewal_type' => $renewalType,
                'closed_date' => now(),
                'notes' => $data['notes'] ?? $renewal->notes,
            ]);

            $renewal->contract->update(['renewal_status' => 'renewed']);

            // Create new contract if specified
            if (!empty($data['create_new_contract'])) {
                $newContract = $this->createRenewalContract($renewal, $data);
                $renewal->update(['new_contract_id' => $newContract->id]);
            }

            $this->logActivity($renewal, 'status_change', 'Renewal won', [
                'renewal_value' => $renewalValue,
                'upsell_value' => $upsellValue,
                'renewal_type' => $renewalType,
            ]);

            return $renewal->fresh();
        });
    }

    /**
     * Mark a renewal as lost
     */
    public function loseRenewal(Renewal $renewal, string $lossReason, ?string $notes = null): Renewal
    {
        $renewal->update([
            'status' => 'lost',
            'renewal_type' => 'churn',
            'closed_date' => now(),
            'loss_reason' => $lossReason,
            'notes' => $notes ?? $renewal->notes,
        ]);

        $renewal->contract->update([
            'renewal_status' => 'lost',
            'status' => 'expired',
        ]);

        $this->logActivity($renewal, 'status_change', 'Renewal lost', [
            'loss_reason' => $lossReason,
        ]);

        return $renewal;
    }

    /**
     * Create a new contract from a renewal
     */
    protected function createRenewalContract(Renewal $renewal, array $data): Contract
    {
        $oldContract = $renewal->contract;
        $newEndDate = $oldContract->end_date->addYear(); // Default to 1 year

        if (!empty($data['new_end_date'])) {
            $newEndDate = $data['new_end_date'];
        }

        return $this->createContract([
            'name' => $oldContract->name,
            'related_module' => $oldContract->related_module,
            'related_id' => $oldContract->related_id,
            'type' => $oldContract->type,
            'value' => $renewal->renewal_value + $renewal->upsell_value,
            'currency' => $oldContract->currency,
            'billing_frequency' => $oldContract->billing_frequency,
            'start_date' => $oldContract->end_date->addDay(),
            'end_date' => $newEndDate,
            'renewal_notice_days' => $oldContract->renewal_notice_days,
            'auto_renew' => $oldContract->auto_renew,
            'owner_id' => $oldContract->owner_id,
            'terms' => $data['new_terms'] ?? $oldContract->terms,
            'line_items' => $data['line_items'] ?? $oldContract->lineItems->map(fn($item) => [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
            ])->toArray(),
        ]);
    }

    /**
     * Determine renewal type based on value change
     */
    protected function determineRenewalType(float $originalValue, float $renewalValue): string
    {
        if ($renewalValue > $originalValue * 1.05) {
            return 'expansion';
        } elseif ($renewalValue < $originalValue * 0.95) {
            return 'contraction';
        }
        return 'renewal';
    }

    /**
     * Log an activity for a renewal
     */
    public function logActivity(Renewal $renewal, string $type, ?string $description = null, array $metadata = []): RenewalActivity
    {
        return $renewal->activities()->create([
            'type' => $type,
            'description' => $description,
            'user_id' => Auth::id(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get renewal pipeline summary
     */
    public function getPipelineSummary(): array
    {
        $renewals = Renewal::with('contract')
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();

        return [
            'pending' => [
                'count' => $renewals->where('status', 'pending')->count(),
                'value' => $renewals->where('status', 'pending')->sum('original_value'),
            ],
            'in_progress' => [
                'count' => $renewals->where('status', 'in_progress')->count(),
                'value' => $renewals->where('status', 'in_progress')->sum('original_value'),
            ],
            'total' => [
                'count' => $renewals->count(),
                'value' => $renewals->sum('original_value'),
            ],
        ];
    }

    /**
     * Calculate forecast for a period
     */
    public function calculateForecast(string $periodType = 'month'): RenewalForecast
    {
        $now = now();

        switch ($periodType) {
            case 'quarter':
                $periodStart = $now->copy()->startOfQuarter();
                $periodEnd = $now->copy()->endOfQuarter();
                break;
            case 'year':
                $periodStart = $now->copy()->startOfYear();
                $periodEnd = $now->copy()->endOfYear();
                break;
            default:
                $periodStart = $now->copy()->startOfMonth();
                $periodEnd = $now->copy()->endOfMonth();
        }

        // Get contracts expiring in this period
        $contracts = Contract::active()
            ->whereBetween('end_date', [$periodStart, $periodEnd])
            ->get();

        // Get renewals for this period
        $renewals = Renewal::whereBetween('due_date', [$periodStart, $periodEnd])
            ->get();

        $expectedRenewals = $contracts->sum('value');
        $renewedValue = $renewals->where('status', 'won')->sum('renewal_value');
        $expansionValue = $renewals->where('status', 'won')->sum('upsell_value');
        $churnedValue = $renewals->where('status', 'lost')->sum('original_value');
        $atRiskValue = $renewals->whereIn('status', ['pending', 'in_progress'])
            ->where('due_date', '<', $now)
            ->sum('original_value');

        $retentionRate = $expectedRenewals > 0
            ? ($renewedValue / $expectedRenewals) * 100
            : null;

        return RenewalForecast::updateOrCreate(
            ['period_start' => $periodStart, 'period_type' => $periodType],
            [
                'period_end' => $periodEnd,
                'expected_renewals' => $expectedRenewals,
                'at_risk_value' => $atRiskValue,
                'churned_value' => $churnedValue,
                'renewed_value' => $renewedValue,
                'expansion_value' => $expansionValue,
                'total_contracts' => $contracts->count(),
                'at_risk_count' => $renewals->whereIn('status', ['pending', 'in_progress'])
                    ->where('due_date', '<', $now)->count(),
                'renewed_count' => $renewals->where('status', 'won')->count(),
                'churned_count' => $renewals->where('status', 'lost')->count(),
                'retention_rate' => $retentionRate,
            ]
        );
    }

    /**
     * Get contracts expiring soon
     */
    public function getExpiringContracts(int $withinDays = 30): \Illuminate\Database\Eloquent\Collection
    {
        return Contract::with(['owner', 'lineItems'])
            ->expiring($withinDays)
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Auto-generate renewals for expiring contracts
     */
    public function generatePendingRenewals(): int
    {
        $contracts = Contract::active()
            ->where('end_date', '<=', now()->addDays(90))
            ->whereNull('renewal_status')
            ->orWhere('renewal_status', '')
            ->get();

        $count = 0;
        foreach ($contracts as $contract) {
            // Check if renewal already exists
            $existingRenewal = DB::table('renewals')->where('contract_id', $contract->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();

            if (!$existingRenewal) {
                $this->createRenewal($contract);
                $count++;
            }
        }

        return $count;
    }
}
