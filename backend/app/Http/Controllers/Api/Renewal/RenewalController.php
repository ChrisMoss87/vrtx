<?php

namespace App\Http\Controllers\Api\Renewal;

use App\Http\Controllers\Controller;
use App\Services\Renewal\RenewalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RenewalController extends Controller
{
    public function __construct(
        protected RenewalService $renewalService
    ) {}

    /**
     * List renewals
     */
    public function index(Request $request): JsonResponse
    {
        $query = Renewal::with(['contract.owner', 'owner', 'activities' => fn($q) => $q->latest()->limit(5)])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->owner_id, fn($q, $ownerId) => $q->where('owner_id', $ownerId))
            ->when($request->upcoming_days, fn($q, $days) => $q->upcoming($days))
            ->when($request->overdue, fn($q) => $q->overdue());

        $renewals = $query->orderBy($request->sort_by ?? 'due_date', $request->sort_order ?? 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json($renewals);
    }

    /**
     * Get a single renewal
     */
    public function show(int $id): JsonResponse
    {
        $renewal = Renewal::with([
            'contract.owner',
            'contract.lineItems',
            'owner',
            'activities.user',
            'newContract',
        ])->findOrFail($id);

        return response()->json([
            'renewal' => $renewal,
        ]);
    }

    /**
     * Create a renewal for a contract
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $contract = Contract::findOrFail($validated['contract_id']);

        // Check if renewal already exists
        if ($contract->renewals()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Active renewal already exists for this contract',
            ], 422);
        }

        $renewal = $this->renewalService->createRenewal($contract, $validated['owner_id'] ?? null);

        return response()->json([
            'renewal' => $renewal->load(['contract', 'owner']),
            'message' => 'Renewal created successfully',
        ], 201);
    }

    /**
     * Start working on a renewal
     */
    public function start(int $id): JsonResponse
    {
        $renewal = DB::table('renewals')->where('id', $id)->first();

        if ($renewal->status !== 'pending') {
            return response()->json([
                'message' => 'Renewal is not in pending status',
            ], 422);
        }

        $renewal = $this->renewalService->startRenewal($renewal);

        return response()->json([
            'renewal' => $renewal->load(['contract', 'owner']),
            'message' => 'Renewal started',
        ]);
    }

    /**
     * Mark renewal as won
     */
    public function win(Request $request, int $id): JsonResponse
    {
        $renewal = DB::table('renewals')->where('id', $id)->first();

        if (!in_array($renewal->status, ['pending', 'in_progress'])) {
            return response()->json([
                'message' => 'Renewal is not active',
            ], 422);
        }

        $validated = $request->validate([
            'renewal_value' => 'nullable|numeric|min:0',
            'upsell_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'create_new_contract' => 'nullable|boolean',
            'new_end_date' => 'nullable|date',
            'new_terms' => 'nullable|string',
            'line_items' => 'nullable|array',
        ]);

        $renewal = $this->renewalService->winRenewal($renewal, $validated);

        return response()->json([
            'renewal' => $renewal->load(['contract', 'owner', 'newContract']),
            'message' => 'Renewal marked as won',
        ]);
    }

    /**
     * Mark renewal as lost
     */
    public function lose(Request $request, int $id): JsonResponse
    {
        $renewal = DB::table('renewals')->where('id', $id)->first();

        if (!in_array($renewal->status, ['pending', 'in_progress'])) {
            return response()->json([
                'message' => 'Renewal is not active',
            ], 422);
        }

        $validated = $request->validate([
            'loss_reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $renewal = $this->renewalService->loseRenewal($renewal, $validated['loss_reason'], $validated['notes'] ?? null);

        return response()->json([
            'renewal' => $renewal->load(['contract', 'owner']),
            'message' => 'Renewal marked as lost',
        ]);
    }

    /**
     * Add activity to renewal
     */
    public function addActivity(Request $request, int $id): JsonResponse
    {
        $renewal = DB::table('renewals')->where('id', $id)->first();

        $validated = $request->validate([
            'type' => 'required|string',
            'subject' => 'nullable|string',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $activity = $this->renewalService->logActivity(
            $renewal,
            $validated['type'],
            $validated['description'] ?? $validated['subject'],
            $validated['metadata'] ?? []
        );

        return response()->json([
            'activity' => $activity->load('user'),
            'message' => 'Activity added',
        ]);
    }

    /**
     * Get renewal pipeline summary
     */
    public function pipeline(): JsonResponse
    {
        $summary = $this->renewalService->getPipelineSummary();

        return response()->json($summary);
    }

    /**
     * Get renewal forecast
     */
    public function forecast(Request $request): JsonResponse
    {
        $periodType = $request->input('period', 'month');
        $forecast = $this->renewalService->calculateForecast($periodType);

        return response()->json([
            'forecast' => $forecast,
        ]);
    }

    /**
     * Auto-generate pending renewals
     */
    public function generate(): JsonResponse
    {
        $count = $this->renewalService->generatePendingRenewals();

        return response()->json([
            'count' => $count,
            'message' => "Generated {$count} renewal opportunities",
        ]);
    }
}
