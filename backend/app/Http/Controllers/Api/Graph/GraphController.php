<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Graph;

use App\Http\Controllers\Controller;
use App\Models\EntityRelationship;
use App\Models\GraphMetric;
use App\Services\Graph\GraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphController extends Controller
{
    public function __construct(
        protected GraphService $graphService
    ) {}

    /**
     * Get nodes for visualization.
     * GET /api/v1/graph/nodes
     */
    public function nodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_types' => 'nullable|array',
            'entity_types.*' => 'string|in:contact,company,deal,user',
            'min_revenue' => 'nullable|numeric|min:0',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $nodes = $this->graphService->getNodes([
            'entity_types' => $validated['entity_types'] ?? ['contact', 'company', 'deal'],
            'min_revenue' => $validated['min_revenue'] ?? null,
            'limit' => $validated['limit'] ?? 500,
        ]);

        return response()->json([
            'data' => $nodes,
            'meta' => [
                'count' => count($nodes),
            ],
        ]);
    }

    /**
     * Get edges (relationships) between nodes.
     * GET /api/v1/graph/edges
     */
    public function edges(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relationship_types' => 'nullable|array',
            'relationship_types.*' => 'string',
            'node_ids' => 'nullable|array',
            'node_ids.*' => 'string',
        ]);

        $edges = $this->graphService->getEdges([
            'relationship_types' => $validated['relationship_types'] ?? null,
            'node_ids' => $validated['node_ids'] ?? null,
        ]);

        return response()->json([
            'data' => $edges,
            'meta' => [
                'count' => count($edges),
            ],
        ]);
    }

    /**
     * Get neighborhood around an entity.
     * GET /api/v1/graph/neighborhood/{type}/{id}
     */
    public function neighborhood(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'depth' => 'nullable|integer|min:1|max:4',
        ]);

        if (!in_array($type, EntityRelationship::getEntityTypes())) {
            return response()->json([
                'message' => 'Invalid entity type',
            ], 422);
        }

        $neighborhood = $this->graphService->getNeighborhood(
            $type,
            $id,
            $validated['depth'] ?? 2
        );

        return response()->json([
            'data' => $neighborhood,
            'meta' => [
                'center' => "{$type}_{$id}",
                'depth' => $validated['depth'] ?? 2,
                'node_count' => count($neighborhood['nodes']),
                'edge_count' => count($neighborhood['edges']),
            ],
        ]);
    }

    /**
     * Find shortest path between two entities.
     * GET /api/v1/graph/path
     */
    public function path(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_type' => 'required|string|in:contact,company,deal,user',
            'from_id' => 'required|integer',
            'to_type' => 'required|string|in:contact,company,deal,user',
            'to_id' => 'required|integer',
        ]);

        $path = $this->graphService->findPath(
            $validated['from_type'],
            $validated['from_id'],
            $validated['to_type'],
            $validated['to_id']
        );

        if (!$path) {
            return response()->json([
                'message' => 'No path found between the specified entities',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => $path,
        ]);
    }

    /**
     * Get graph metrics for an entity.
     * GET /api/v1/graph/metrics/{type}/{id}
     */
    public function metrics(string $type, int $id): JsonResponse
    {
        if (!in_array($type, EntityRelationship::getEntityTypes())) {
            return response()->json([
                'message' => 'Invalid entity type',
            ], 422);
        }

        $metrics = GraphMetric::forEntity($type, $id)->first();

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Get relationship types.
     * GET /api/v1/graph/relationship-types
     */
    public function relationshipTypes(): JsonResponse
    {
        return response()->json([
            'data' => EntityRelationship::getRelationshipTypes(),
        ]);
    }

    /**
     * Create a relationship.
     * POST /api/v1/graph/relationships
     */
    public function createRelationship(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_entity_type' => 'required|string|in:contact,company,deal,user',
            'from_entity_id' => 'required|integer',
            'to_entity_type' => 'required|string|in:contact,company,deal,user',
            'to_entity_id' => 'required|integer',
            'relationship_type' => 'required|string',
            'strength' => 'nullable|integer|min:1|max:10',
            'metadata' => 'nullable|array',
        ]);

        try {
            $relationship = $this->graphService->createRelationship($validated);

            return response()->json([
                'data' => [
                    'id' => $relationship->id,
                    'source' => "{$relationship->from_entity_type}_{$relationship->from_entity_id}",
                    'target' => "{$relationship->to_entity_type}_{$relationship->to_entity_id}",
                    'type' => $relationship->relationship_type,
                    'strength' => $relationship->strength,
                ],
                'message' => 'Relationship created',
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'duplicate')) {
                return response()->json([
                    'message' => 'Relationship already exists',
                ], 409);
            }
            throw $e;
        }
    }

    /**
     * Delete a relationship.
     * DELETE /api/v1/graph/relationships/{id}
     */
    public function deleteRelationship(int $id): JsonResponse
    {
        $deleted = $this->graphService->deleteRelationship($id);

        if (!$deleted) {
            return response()->json([
                'message' => 'Relationship not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Relationship deleted',
        ]);
    }
}
