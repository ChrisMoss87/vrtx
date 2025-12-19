<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Models\EntityRelationship;
use App\Models\GraphMetric;
use App\Models\Module;
use App\Models\ModuleRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GraphService
{
    /**
     * Get nodes for graph visualization.
     */
    public function getNodes(array $options = []): array
    {
        $entityTypes = $options['entity_types'] ?? ['contact', 'company', 'deal'];
        $minRevenue = $options['min_revenue'] ?? null;
        $limit = $options['limit'] ?? 500;
        $nodeIds = $options['node_ids'] ?? null;

        $nodes = [];

        foreach ($entityTypes as $entityType) {
            $entityNodes = $this->getNodesOfType($entityType, $minRevenue, $limit, $nodeIds);
            $nodes = array_merge($nodes, $entityNodes);
        }

        // Sort by importance/size and limit
        usort($nodes, fn($a, $b) => ($b['size'] ?? 0) <=> ($a['size'] ?? 0));

        return array_slice($nodes, 0, $limit);
    }

    /**
     * Get nodes of a specific type.
     */
    protected function getNodesOfType(
        string $entityType,
        ?float $minRevenue,
        int $limit,
        ?array $nodeIds
    ): array {
        $nodes = [];

        switch ($entityType) {
            case 'contact':
                $module = Module::where('api_name', 'contacts')->first();
                if (!$module) break;

                $query = ModuleRecord::where('module_id', $module->id)
                    ->select(['id', 'data'])
                    ->limit($limit);

                if ($nodeIds) {
                    $query->whereIn('id', $nodeIds);
                }

                foreach ($query->cursor() as $record) {
                    $nodes[] = [
                        'id' => "contact_{$record->id}",
                        'entity_type' => 'contact',
                        'entity_id' => $record->id,
                        'label' => $record->data['name'] ?? $record->data['first_name'] ?? 'Contact',
                        'size' => $this->getContactSize($record),
                        'data' => [
                            'email' => $record->data['email'] ?? null,
                            'title' => $record->data['title'] ?? null,
                            'company' => $record->data['company'] ?? null,
                        ],
                    ];
                }
                break;

            case 'company':
                $module = Module::where('api_name', 'accounts')
                    ->orWhere('api_name', 'companies')
                    ->first();
                if (!$module) break;

                $query = ModuleRecord::where('module_id', $module->id)
                    ->select(['id', 'data'])
                    ->limit($limit);

                if ($nodeIds) {
                    $query->whereIn('id', $nodeIds);
                }

                foreach ($query->cursor() as $record) {
                    $revenue = (float) ($record->data['annual_revenue'] ?? $record->data['revenue'] ?? 0);
                    if ($minRevenue && $revenue < $minRevenue) continue;

                    $nodes[] = [
                        'id' => "company_{$record->id}",
                        'entity_type' => 'company',
                        'entity_id' => $record->id,
                        'label' => $record->data['name'] ?? 'Company',
                        'size' => $this->getCompanySize($record),
                        'revenue' => $revenue,
                        'data' => [
                            'industry' => $record->data['industry'] ?? null,
                            'website' => $record->data['website'] ?? null,
                            'employees' => $record->data['employees'] ?? null,
                        ],
                    ];
                }
                break;

            case 'deal':
                $module = Module::where('api_name', 'deals')
                    ->orWhere('api_name', 'opportunities')
                    ->first();
                if (!$module) break;

                $query = ModuleRecord::where('module_id', $module->id)
                    ->select(['id', 'data'])
                    ->limit($limit);

                if ($nodeIds) {
                    $query->whereIn('id', $nodeIds);
                }

                foreach ($query->cursor() as $record) {
                    $amount = (float) ($record->data['amount'] ?? $record->data['value'] ?? 0);
                    if ($minRevenue && $amount < $minRevenue) continue;

                    $nodes[] = [
                        'id' => "deal_{$record->id}",
                        'entity_type' => 'deal',
                        'entity_id' => $record->id,
                        'label' => $record->data['name'] ?? $record->data['deal_name'] ?? 'Deal',
                        'size' => $this->getDealSize($record),
                        'amount' => $amount,
                        'data' => [
                            'stage' => $record->data['stage'] ?? null,
                            'probability' => $record->data['probability'] ?? null,
                            'close_date' => $record->data['close_date'] ?? null,
                        ],
                    ];
                }
                break;

            case 'user':
                $users = \App\Models\User::select(['id', 'name', 'email'])->limit($limit);

                if ($nodeIds) {
                    $users->whereIn('id', $nodeIds);
                }

                foreach ($users->cursor() as $user) {
                    $nodes[] = [
                        'id' => "user_{$user->id}",
                        'entity_type' => 'user',
                        'entity_id' => $user->id,
                        'label' => $user->name,
                        'size' => 20,
                        'data' => [
                            'email' => $user->email,
                        ],
                    ];
                }
                break;
        }

        return $nodes;
    }

    /**
     * Get edges (relationships) between nodes.
     */
    public function getEdges(array $options = []): array
    {
        $relationshipTypes = $options['relationship_types'] ?? null;
        $nodeIds = $options['node_ids'] ?? null;

        $query = EntityRelationship::query();

        if ($relationshipTypes) {
            $query->whereIn('relationship_type', $relationshipTypes);
        }

        if ($nodeIds) {
            // Filter to edges where both endpoints are in the node list
            $parsedIds = $this->parseNodeIds($nodeIds);

            $query->where(function ($q) use ($parsedIds) {
                foreach ($parsedIds as $type => $ids) {
                    $q->orWhere(function ($q2) use ($type, $ids) {
                        $q2->where('from_entity_type', $type)
                            ->whereIn('from_entity_id', $ids);
                    });
                }
            })->where(function ($q) use ($parsedIds) {
                foreach ($parsedIds as $type => $ids) {
                    $q->orWhere(function ($q2) use ($type, $ids) {
                        $q2->where('to_entity_type', $type)
                            ->whereIn('to_entity_id', $ids);
                    });
                }
            });
        }

        return $query->get()->map(function ($rel) {
            return [
                'id' => $rel->id,
                'source' => "{$rel->from_entity_type}_{$rel->from_entity_id}",
                'target' => "{$rel->to_entity_type}_{$rel->to_entity_id}",
                'type' => $rel->relationship_type,
                'label' => EntityRelationship::getRelationshipTypes()[$rel->relationship_type] ?? $rel->relationship_type,
                'strength' => $rel->strength,
                'metadata' => $rel->metadata,
            ];
        })->toArray();
    }

    /**
     * Get neighborhood around a specific entity.
     */
    public function getNeighborhood(string $entityType, int $entityId, int $depth = 2): array
    {
        $visitedNodes = ["$entityType_{$entityId}"];
        $nodes = [];
        $edges = [];
        $currentLayer = [['type' => $entityType, 'id' => $entityId]];

        for ($d = 0; $d < $depth; $d++) {
            $nextLayer = [];

            foreach ($currentLayer as $entity) {
                // Get relationships from this entity
                $rels = EntityRelationship::involving($entity['type'], $entity['id'])->get();

                foreach ($rels as $rel) {
                    // Add edge
                    $edges[] = [
                        'id' => $rel->id,
                        'source' => "{$rel->from_entity_type}_{$rel->from_entity_id}",
                        'target' => "{$rel->to_entity_type}_{$rel->to_entity_id}",
                        'type' => $rel->relationship_type,
                        'label' => EntityRelationship::getRelationshipTypes()[$rel->relationship_type] ?? $rel->relationship_type,
                        'strength' => $rel->strength,
                    ];

                    // Check other endpoint
                    $otherType = $rel->from_entity_type === $entity['type'] && $rel->from_entity_id === $entity['id']
                        ? $rel->to_entity_type
                        : $rel->from_entity_type;
                    $otherId = $rel->from_entity_type === $entity['type'] && $rel->from_entity_id === $entity['id']
                        ? $rel->to_entity_id
                        : $rel->from_entity_id;
                    $otherNodeId = "{$otherType}_{$otherId}";

                    if (!in_array($otherNodeId, $visitedNodes)) {
                        $visitedNodes[] = $otherNodeId;
                        $nextLayer[] = ['type' => $otherType, 'id' => $otherId];
                    }
                }
            }

            $currentLayer = $nextLayer;
        }

        // Get node data for all visited nodes
        $parsedIds = $this->parseNodeIds($visitedNodes);
        foreach ($parsedIds as $type => $ids) {
            $typeNodes = $this->getNodesOfType($type, null, count($ids) + 10, $ids);
            $nodes = array_merge($nodes, $typeNodes);
        }

        return [
            'nodes' => $nodes,
            'edges' => $this->deduplicateEdges($edges),
        ];
    }

    /**
     * Find shortest path between two entities using BFS.
     */
    public function findPath(
        string $fromType,
        int $fromId,
        string $toType,
        int $toId
    ): ?array {
        $start = "{$fromType}_{$fromId}";
        $target = "{$toType}_{$toId}";

        if ($start === $target) {
            return ['path' => [$start], 'edges' => []];
        }

        // BFS
        $visited = [$start => null]; // node => previous node
        $queue = [['node' => $start, 'type' => $fromType, 'id' => $fromId]];
        $edgeMap = []; // node -> edge used to reach it

        while (!empty($queue)) {
            $current = array_shift($queue);
            $currentNode = $current['node'];

            // Get relationships
            $rels = EntityRelationship::involving($current['type'], $current['id'])->get();

            foreach ($rels as $rel) {
                $otherType = $rel->from_entity_type === $current['type'] && $rel->from_entity_id === $current['id']
                    ? $rel->to_entity_type
                    : $rel->from_entity_type;
                $otherId = $rel->from_entity_type === $current['type'] && $rel->from_entity_id === $current['id']
                    ? $rel->to_entity_id
                    : $rel->from_entity_id;
                $otherNode = "{$otherType}_{$otherId}";

                if (!isset($visited[$otherNode])) {
                    $visited[$otherNode] = $currentNode;
                    $edgeMap[$otherNode] = [
                        'id' => $rel->id,
                        'source' => "{$rel->from_entity_type}_{$rel->from_entity_id}",
                        'target' => "{$rel->to_entity_type}_{$rel->to_entity_id}",
                        'type' => $rel->relationship_type,
                        'label' => EntityRelationship::getRelationshipTypes()[$rel->relationship_type] ?? $rel->relationship_type,
                    ];

                    if ($otherNode === $target) {
                        // Reconstruct path
                        $path = [$target];
                        $edges = [$edgeMap[$target]];
                        $node = $target;

                        while ($visited[$node] !== null) {
                            $node = $visited[$node];
                            array_unshift($path, $node);
                            if (isset($edgeMap[$node])) {
                                array_unshift($edges, $edgeMap[$node]);
                            }
                        }

                        return [
                            'path' => $path,
                            'edges' => $edges,
                            'hops' => count($path) - 1,
                        ];
                    }

                    $queue[] = ['node' => $otherNode, 'type' => $otherType, 'id' => $otherId];
                }
            }
        }

        return null; // No path found
    }

    /**
     * Create a new relationship.
     */
    public function createRelationship(array $data): EntityRelationship
    {
        return EntityRelationship::create([
            'from_entity_type' => $data['from_entity_type'],
            'from_entity_id' => $data['from_entity_id'],
            'to_entity_type' => $data['to_entity_type'],
            'to_entity_id' => $data['to_entity_id'],
            'relationship_type' => $data['relationship_type'],
            'strength' => $data['strength'] ?? 5,
            'metadata' => $data['metadata'] ?? [],
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Delete a relationship.
     */
    public function deleteRelationship(int $id): bool
    {
        return EntityRelationship::destroy($id) > 0;
    }

    /**
     * Parse node IDs from strings like "contact_123".
     */
    protected function parseNodeIds(array $nodeIds): array
    {
        $result = [];

        foreach ($nodeIds as $nodeId) {
            $parts = explode('_', $nodeId, 2);
            if (count($parts) === 2) {
                $type = $parts[0];
                $id = (int) $parts[1];
                if (!isset($result[$type])) {
                    $result[$type] = [];
                }
                $result[$type][] = $id;
            }
        }

        return $result;
    }

    /**
     * Deduplicate edges.
     */
    protected function deduplicateEdges(array $edges): array
    {
        $seen = [];
        $result = [];

        foreach ($edges as $edge) {
            if (!isset($seen[$edge['id']])) {
                $seen[$edge['id']] = true;
                $result[] = $edge;
            }
        }

        return $result;
    }

    /**
     * Calculate contact node size based on activity/influence.
     */
    protected function getContactSize(ModuleRecord $record): int
    {
        // Base size + bonuses for influence indicators
        $size = 15;

        if (!empty($record->data['title'])) {
            $title = strtolower($record->data['title']);
            if (str_contains($title, 'ceo') || str_contains($title, 'founder')) {
                $size += 20;
            } elseif (str_contains($title, 'vp') || str_contains($title, 'director')) {
                $size += 10;
            } elseif (str_contains($title, 'manager')) {
                $size += 5;
            }
        }

        return $size;
    }

    /**
     * Calculate company node size based on revenue.
     */
    protected function getCompanySize(ModuleRecord $record): int
    {
        $revenue = (float) ($record->data['annual_revenue'] ?? $record->data['revenue'] ?? 0);

        if ($revenue >= 100000000) return 50;
        if ($revenue >= 10000000) return 40;
        if ($revenue >= 1000000) return 30;
        if ($revenue >= 100000) return 25;

        return 20;
    }

    /**
     * Calculate deal node size based on amount.
     */
    protected function getDealSize(ModuleRecord $record): int
    {
        $amount = (float) ($record->data['amount'] ?? $record->data['value'] ?? 0);

        if ($amount >= 1000000) return 45;
        if ($amount >= 100000) return 35;
        if ($amount >= 50000) return 28;
        if ($amount >= 10000) return 22;

        return 18;
    }
}
