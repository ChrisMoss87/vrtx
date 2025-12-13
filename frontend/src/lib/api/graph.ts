import { apiClient } from './client';

export interface GraphNode {
	id: string;
	entity_type: 'contact' | 'company' | 'deal' | 'user';
	entity_id: number;
	label: string;
	size: number;
	revenue?: number;
	amount?: number;
	data: Record<string, unknown>;
}

export interface GraphEdge {
	id: number;
	source: string;
	target: string;
	type: string;
	label: string;
	strength: number;
	metadata?: Record<string, unknown>;
}

export interface GraphData {
	nodes: GraphNode[];
	edges: GraphEdge[];
}

export interface PathResult {
	path: string[];
	edges: GraphEdge[];
	hops: number;
}

export interface GraphMetrics {
	entity_type: string;
	entity_id: number;
	degree_centrality: number | null;
	betweenness_centrality: number | null;
	closeness_centrality: number | null;
	cluster_id: number | null;
	total_connected_revenue: number | null;
	calculated_at: string;
}

/**
 * Get nodes for graph visualization.
 */
export async function getGraphNodes(options?: {
	entityTypes?: string[];
	minRevenue?: number;
	limit?: number;
}): Promise<GraphNode[]> {
	const params = new URLSearchParams();
	if (options?.entityTypes) {
		options.entityTypes.forEach((t) => params.append('entity_types[]', t));
	}
	if (options?.minRevenue) {
		params.set('min_revenue', options.minRevenue.toString());
	}
	if (options?.limit) {
		params.set('limit', options.limit.toString());
	}

	const response = await apiClient.get<{ data: GraphNode[] }>(`/graph/nodes?${params}`);
	return response.data;
}

/**
 * Get edges (relationships) between nodes.
 */
export async function getGraphEdges(options?: {
	relationshipTypes?: string[];
	nodeIds?: string[];
}): Promise<GraphEdge[]> {
	const params = new URLSearchParams();
	if (options?.relationshipTypes) {
		options.relationshipTypes.forEach((t) => params.append('relationship_types[]', t));
	}
	if (options?.nodeIds) {
		options.nodeIds.forEach((id) => params.append('node_ids[]', id));
	}

	const response = await apiClient.get<{ data: GraphEdge[] }>(`/graph/edges?${params}`);
	return response.data;
}

/**
 * Get neighborhood around a specific entity.
 */
export async function getNeighborhood(
	entityType: string,
	entityId: number,
	depth?: number
): Promise<GraphData> {
	const params = depth ? `?depth=${depth}` : '';
	const response = await apiClient.get<{ data: GraphData }>(
		`/graph/neighborhood/${entityType}/${entityId}${params}`
	);
	return response.data;
}

/**
 * Find shortest path between two entities.
 */
export async function findPath(
	fromType: string,
	fromId: number,
	toType: string,
	toId: number
): Promise<PathResult | null> {
	const params = new URLSearchParams({
		from_type: fromType,
		from_id: fromId.toString(),
		to_type: toType,
		to_id: toId.toString()
	});

	try {
		const response = await apiClient.get<{ data: PathResult }>(`/graph/path?${params}`);
		return response.data;
	} catch {
		return null;
	}
}

/**
 * Get metrics for an entity.
 */
export async function getGraphMetrics(
	entityType: string,
	entityId: number
): Promise<GraphMetrics | null> {
	const response = await apiClient.get<{ data: GraphMetrics | null }>(
		`/graph/metrics/${entityType}/${entityId}`
	);
	return response.data;
}

/**
 * Get available relationship types.
 */
export async function getRelationshipTypes(): Promise<Record<string, string>> {
	const response = await apiClient.get<{ data: Record<string, string> }>('/graph/relationship-types');
	return response.data;
}

/**
 * Create a new relationship.
 */
export async function createRelationship(data: {
	from_entity_type: string;
	from_entity_id: number;
	to_entity_type: string;
	to_entity_id: number;
	relationship_type: string;
	strength?: number;
	metadata?: Record<string, unknown>;
}): Promise<GraphEdge> {
	const response = await apiClient.post<{ data: GraphEdge }>('/graph/relationships', data);
	return response.data;
}

/**
 * Delete a relationship.
 */
export async function deleteRelationship(id: number): Promise<void> {
	await apiClient.delete(`/graph/relationships/${id}`);
}

/**
 * Load full graph data (nodes + edges).
 */
export async function loadGraphData(options?: {
	entityTypes?: string[];
	minRevenue?: number;
	limit?: number;
}): Promise<GraphData> {
	const nodes = await getGraphNodes(options);
	const nodeIds = nodes.map((n) => n.id);
	const edges = await getGraphEdges({ nodeIds });

	return { nodes, edges };
}
