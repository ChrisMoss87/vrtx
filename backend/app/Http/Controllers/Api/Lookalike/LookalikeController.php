<?php

namespace App\Http\Controllers\Api\Lookalike;

use App\Http\Controllers\Controller;
use App\Services\Lookalike\LookalikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LookalikeController extends Controller
{
    public function __construct(
        protected LookalikeService $lookalikeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'sort_field', 'sort_order']);
        $perPage = $request->integer('per_page', 20);

        $audiences = $this->lookalikeService->getAudiences($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $audiences->items(),
            'meta' => [
                'current_page' => $audiences->currentPage(),
                'last_page' => $audiences->lastPage(),
                'per_page' => $audiences->perPage(),
                'total' => $audiences->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $audience = LookalikeAudience::with(['creator', 'buildJobs' => fn($q) => $q->latest()->limit(5)])
            ->withCount('matches')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $audience,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_type' => 'required|string|in:saved_search,manual,segment',
            'source_id' => 'nullable|integer',
            'source_criteria' => 'nullable|array',
            'match_criteria' => 'nullable|array',
            'weights' => 'nullable|array',
            'min_similarity_score' => 'nullable|numeric|min:0|max:100',
            'size_limit' => 'nullable|integer|min:1',
            'auto_refresh' => 'nullable|boolean',
            'refresh_frequency' => 'nullable|string|in:daily,weekly,monthly',
            'export_destinations' => 'nullable|array',
        ]);

        $audience = $this->lookalikeService->createAudience($validated, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Lookalike audience created successfully',
            'data' => $audience->load('creator'),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $audience = DB::table('lookalike_audiences')->where('id', $id)->first();

        if ($audience->isBuilding()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update audience while building',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'match_criteria' => 'nullable|array',
            'weights' => 'nullable|array',
            'min_similarity_score' => 'nullable|numeric|min:0|max:100',
            'size_limit' => 'nullable|integer|min:1',
            'auto_refresh' => 'nullable|boolean',
            'refresh_frequency' => 'nullable|string|in:daily,weekly,monthly',
            'export_destinations' => 'nullable|array',
        ]);

        $audience = $this->lookalikeService->updateAudience($audience, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Lookalike audience updated successfully',
            'data' => $audience,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $audience = DB::table('lookalike_audiences')->where('id', $id)->first();

        if ($audience->isBuilding()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete audience while building',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $audience->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lookalike audience deleted successfully',
        ]);
    }

    public function build(int $id): JsonResponse
    {
        $audience = DB::table('lookalike_audiences')->where('id', $id)->first();

        if ($audience->isBuilding()) {
            return response()->json([
                'success' => false,
                'message' => 'Audience is already building',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $job = $this->lookalikeService->buildAudience($audience);

        return response()->json([
            'success' => true,
            'message' => 'Audience build started',
            'data' => [
                'job' => $job,
                'audience' => $audience->fresh()->load('creator'),
            ],
        ]);
    }

    public function matches(Request $request, int $id): JsonResponse
    {
        $audience = DB::table('lookalike_audiences')->where('id', $id)->first();
        $perPage = $request->integer('per_page', 50);

        $matches = $this->lookalikeService->getMatches($audience, $perPage);

        return response()->json([
            'success' => true,
            'data' => $matches->items(),
            'meta' => [
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
            ],
        ]);
    }

    public function export(Request $request, int $id): JsonResponse
    {
        $audience = DB::table('lookalike_audiences')->where('id', $id)->first();

        if (!$audience->isReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Audience must be ready before export',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'destination' => 'required|string|in:google_ads,facebook,linkedin,csv',
        ]);

        $exportData = $this->lookalikeService->exportAudience(
            $audience,
            $validated['destination'],
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Audience exported successfully',
            'data' => [
                'records_exported' => count($exportData),
                'destination' => $validated['destination'],
            ],
        ]);
    }

    public function sourceTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LookalikeAudience::getSourceTypes(),
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LookalikeAudience::getStatuses(),
        ]);
    }

    public function criteriaTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LookalikeAudience::getCriteriaTypes(),
        ]);
    }

    public function exportDestinations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LookalikeExportLog::getDestinations(),
        ]);
    }
}
