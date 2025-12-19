import type { Node, Edge } from '@xyflow/svelte';
import type { ActionType, TriggerType, WorkflowConditions } from '$lib/api/workflows';

// Node types for the workflow canvas
export type WorkflowNodeType = 'trigger' | 'action' | 'condition' | 'delay' | 'end';

// Base data shared by all nodes - extends Record for SvelteFlow compatibility
export interface BaseNodeData extends Record<string, unknown> {
	label: string;
	description?: string;
}

// Trigger node data
export interface TriggerNodeData extends BaseNodeData {
	triggerType: TriggerType;
	triggerConfig: Record<string, unknown>;
	moduleId?: number;
	moduleName?: string;
}

// Action node data
export interface ActionNodeData extends BaseNodeData {
	actionType: ActionType;
	actionConfig: Record<string, unknown>;
	continueOnError?: boolean;
	retryCount?: number;
	retryDelaySeconds?: number;
}

// Condition node data
export interface ConditionNodeData extends BaseNodeData {
	conditions: WorkflowConditions | null;
	trueBranch?: string; // Node ID for true path
	falseBranch?: string; // Node ID for false path
}

// Delay node data
export interface DelayNodeData extends BaseNodeData {
	delayType: 'seconds' | 'minutes' | 'hours' | 'days' | 'until_time' | 'until_date';
	delayValue: number;
	delayTime?: string;
}

// End node data
export interface EndNodeData extends BaseNodeData {
	endType: 'success' | 'failure' | 'stop';
}

// Union type for all node data
export type WorkflowNodeData =
	| TriggerNodeData
	| ActionNodeData
	| ConditionNodeData
	| DelayNodeData
	| EndNodeData;

// Custom node type
export type WorkflowNode = Node<WorkflowNodeData, WorkflowNodeType>;

// Custom edge type - extends Record for SvelteFlow compatibility
export interface WorkflowEdgeData extends Record<string, unknown> {
	label?: string;
	condition?: 'true' | 'false';
}

export type WorkflowEdge = Edge<WorkflowEdgeData>;

// Canvas state
export interface CanvasState {
	nodes: WorkflowNode[];
	edges: WorkflowEdge[];
	selectedNodeId: string | null;
	zoom: number;
	viewport: { x: number; y: number };
}

// Node palette item
export interface PaletteItem {
	type: WorkflowNodeType;
	actionType?: ActionType;
	label: string;
	description: string;
	icon: string;
	category: string;
	color: string;
}

// Serialized workflow for API
export interface SerializedWorkflow {
	nodes: Array<{
		id: string;
		type: WorkflowNodeType;
		position: { x: number; y: number };
		data: WorkflowNodeData;
	}>;
	edges: Array<{
		id: string;
		source: string;
		target: string;
		sourceHandle?: string;
		targetHandle?: string;
		data?: WorkflowEdgeData;
	}>;
}
