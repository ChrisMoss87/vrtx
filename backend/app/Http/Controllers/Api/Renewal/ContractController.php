<?php

namespace App\Http\Controllers\Api\Renewal;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\Renewal\RenewalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ContractController extends Controller
{
    public function __construct(
        protected RenewalService $renewalService
    ) {}

    /**
     * List contracts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contract::with(['owner', 'lineItems'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->related_module, fn($q, $module) => $q->where('related_module', $module))
            ->when($request->related_id, fn($q, $id) => $q->where('related_id', $id))
            ->when($request->expiring_within, fn($q, $days) => $q->expiring($days))
            ->when($request->expired, fn($q) => $q->expired())
            ->when($request->search, fn($q, $search) =>
                $q->where(fn($query) =>
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('contract_number', 'like', "%{$search}%")
                )
            );

        $contracts = $query->orderBy($request->sort_by ?? 'end_date', $request->sort_order ?? 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json($contracts);
    }

    /**
     * Get a single contract
     */
    public function show(int $id): JsonResponse
    {
        $contract = Contract::with(['owner', 'lineItems', 'renewals.owner', 'reminders'])
            ->findOrFail($id);

        return response()->json([
            'contract' => $contract,
        ]);
    }

    /**
     * Create a new contract
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contract_number' => 'nullable|string|unique:contracts,contract_number',
            'related_module' => 'required|string',
            'related_id' => 'required|integer',
            'type' => 'nullable|string',
            'status' => 'nullable|string|in:draft,pending,active,expired,cancelled',
            'value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_frequency' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'renewal_date' => 'nullable|date',
            'renewal_notice_days' => 'nullable|integer|min:1',
            'auto_renew' => 'nullable|boolean',
            'owner_id' => 'nullable|exists:users,id',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'line_items' => 'nullable|array',
            'line_items.*.name' => 'required|string',
            'line_items.*.quantity' => 'nullable|numeric|min:0',
            'line_items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $contract = $this->renewalService->createContract($validated);

        return response()->json([
            'contract' => $contract,
            'message' => 'Contract created successfully',
        ], 201);
    }

    /**
     * Update a contract
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string',
            'status' => 'nullable|string|in:draft,pending,active,expired,cancelled',
            'value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_frequency' => 'nullable|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'renewal_date' => 'nullable|date',
            'renewal_notice_days' => 'nullable|integer|min:1',
            'auto_renew' => 'nullable|boolean',
            'owner_id' => 'nullable|exists:users,id',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'line_items' => 'nullable|array',
        ]);

        $contract = $this->renewalService->updateContract($contract, $validated);

        return response()->json([
            'contract' => $contract,
            'message' => 'Contract updated successfully',
        ]);
    }

    /**
     * Delete a contract
     */
    public function destroy(int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        // Check for active renewals
        if ($contract->renewals()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete contract with active renewals',
            ], 422);
        }

        $contract->delete();

        return response()->json([
            'message' => 'Contract deleted successfully',
        ]);
    }

    /**
     * Get contracts for a specific record
     */
    public function forRecord(Request $request): JsonResponse
    {
        $request->validate([
            'module' => 'required|string',
            'record_id' => 'required|integer',
        ]);

        $contracts = Contract::with(['owner', 'lineItems'])
            ->forModule($request->module, $request->record_id)
            ->orderBy('end_date', 'desc')
            ->get();

        return response()->json([
            'contracts' => $contracts,
        ]);
    }

    /**
     * Get expiring contracts
     */
    public function expiring(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $contracts = $this->renewalService->getExpiringContracts($days);

        return response()->json([
            'contracts' => $contracts,
        ]);
    }
}
