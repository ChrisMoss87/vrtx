<?php

namespace App\Http\Controllers\Api\AbTest;

use App\Http\Controllers\Controller;
use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Services\AbTest\AbTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AbTestController extends Controller
{
    public function __construct(
        protected AbTestService $abTestService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'type', 'entity_type', 'search', 'sort_field', 'sort_order']);
        $perPage = $request->integer('per_page', 20);

        $tests = $this->abTestService->getTests($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $tests->items(),
            'meta' => [
                'current_page' => $tests->currentPage(),
                'last_page' => $tests->lastPage(),
                'per_page' => $tests->perPage(),
                'total' => $tests->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $test = AbTest::with(['variants', 'winnerVariant', 'creator'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $test,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:email_subject,email_content,cta_button,send_time,form_layout',
            'entity_type' => 'required|string|in:email_template,campaign,web_form',
            'entity_id' => 'required|integer',
            'goal' => 'nullable|string|in:conversion,click_rate,open_rate',
            'min_sample_size' => 'nullable|integer|min:10',
            'confidence_level' => 'nullable|numeric|min:80|max:99.9',
            'auto_select_winner' => 'nullable|boolean',
            'scheduled_end_at' => 'nullable|date|after:now',
            'control_content' => 'nullable|array',
        ]);

        $test = $this->abTestService->createTest($validated, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'A/B test created successfully',
            'data' => $test->load(['variants', 'creator']),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);

        if ($test->isRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a running test. Please pause it first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'goal' => 'nullable|string|in:conversion,click_rate,open_rate',
            'min_sample_size' => 'nullable|integer|min:10',
            'confidence_level' => 'nullable|numeric|min:80|max:99.9',
            'auto_select_winner' => 'nullable|boolean',
            'scheduled_end_at' => 'nullable|date|after:now',
        ]);

        $test->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'A/B test updated successfully',
            'data' => $test->fresh(['variants', 'winnerVariant', 'creator']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);

        if ($test->isRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a running test. Please complete or pause it first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $test->delete();

        return response()->json([
            'success' => true,
            'message' => 'A/B test deleted successfully',
        ]);
    }

    public function start(int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);

        try {
            $test = $this->abTestService->startTest($test);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'message' => 'A/B test started',
            'data' => $test,
        ]);
    }

    public function pause(int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);
        $test->pause();

        return response()->json([
            'success' => true,
            'message' => 'A/B test paused',
            'data' => $test->fresh(),
        ]);
    }

    public function resume(int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);
        $test->resume();

        return response()->json([
            'success' => true,
            'message' => 'A/B test resumed',
            'data' => $test->fresh(),
        ]);
    }

    public function complete(int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);
        $test->complete();

        return response()->json([
            'success' => true,
            'message' => 'A/B test completed',
            'data' => $test->fresh(['variants', 'winnerVariant']),
        ]);
    }

    public function statistics(int $id): JsonResponse
    {
        $test = AbTest::with('variants.results')->findOrFail($id);

        $statistics = $this->abTestService->getTestStatistics($test);

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AbTest::getTypes(),
        ]);
    }

    public function entityTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AbTest::getEntityTypes(),
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AbTest::getStatuses(),
        ]);
    }

    public function goals(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AbTest::getGoals(),
        ]);
    }

    // ===== VARIANT ENDPOINTS =====

    public function variants(int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);
        $statistics = $this->abTestService->getTestStatistics($test);

        return response()->json([
            'success' => true,
            'data' => $statistics['variants'],
        ]);
    }

    public function createVariant(Request $request, int $id): JsonResponse
    {
        $test = AbTest::findOrFail($id);

        if ($test->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add variants to a completed test',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'traffic_percentage' => 'nullable|integer|min:1|max:100',
        ]);

        $variant = $this->abTestService->createVariant($test, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => $variant,
        ], Response::HTTP_CREATED);
    }

    public function updateVariant(Request $request, int $id, int $variantId): JsonResponse
    {
        $test = AbTest::findOrFail($id);
        $variant = AbTestVariant::where('test_id', $test->id)->findOrFail($variantId);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'traffic_percentage' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $variant = $this->abTestService->updateVariant($variant, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => $variant,
        ]);
    }

    public function deleteVariant(int $id, int $variantId): JsonResponse
    {
        $test = AbTest::findOrFail($id);
        $variant = AbTestVariant::where('test_id', $test->id)->findOrFail($variantId);

        if ($variant->is_control) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the control variant',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($variant->is_winner) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the winning variant',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->abTestService->deleteVariant($variant);

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully',
        ]);
    }

    public function declareWinner(int $id, int $variantId): JsonResponse
    {
        $test = AbTest::findOrFail($id);
        $variant = AbTestVariant::where('test_id', $test->id)->findOrFail($variantId);

        $variant->declareWinner();

        return response()->json([
            'success' => true,
            'message' => 'Variant declared as winner',
            'data' => $test->fresh(['variants', 'winnerVariant']),
        ]);
    }
}
