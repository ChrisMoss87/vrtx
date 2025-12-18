<script lang="ts">
	import {
		SvelteFlow,
		Background,
		Controls,
		MiniMap,
		type Node,
		type Edge,
		type Connection,
		type NodeTypes,
		useSvelteFlow,
		MarkerType
	} from '@xyflow/svelte';
	import '@xyflow/svelte/dist/style.css';

	import { nanoid } from 'nanoid';
	import TriggerNode from './nodes/TriggerNode.svelte';
	import ActionNode from './nodes/ActionNode.svelte';
	import ConditionNode from './nodes/ConditionNode.svelte';
	import DelayNode from './nodes/DelayNode.svelte';
	import EndNode from './nodes/EndNode.svelte';
	import type {
		WorkflowNode,
		WorkflowEdge,
		PaletteItem,
		WorkflowNodeType,
		TriggerNodeData,
		ActionNodeData,
		ConditionNodeData,
		DelayNodeData,
		EndNodeData
	} from './types';
	import type { ActionType } from '$lib/api/workflows';
	import { getActionInfo } from './nodeConfig';

	interface Props {
		nodes: WorkflowNode[];
		edges: WorkflowEdge[];
		selectedNodeId?: string | null;
		onNodesChange?: (nodes: WorkflowNode[]) => void;
		onEdgesChange?: (edges: WorkflowEdge[]) => void;
		onNodeSelect?: (nodeId: string | null) => void;
		onNodeDoubleClick?: (nodeId: string) => void;
	}

	let {
		nodes = $bindable([]),
		edges = $bindable([]),
		selectedNodeId = null,
		onNodesChange,
		onEdgesChange,
		onNodeSelect,
		onNodeDoubleClick
	}: Props = $props();

	// Node types mapping
	const nodeTypes: NodeTypes = {
		trigger: TriggerNode,
		action: ActionNode,
		condition: ConditionNode,
		delay: DelayNode,
		end: EndNode
	};

	// Default edge style
	const defaultEdgeOptions = {
		type: 'smoothstep',
		animated: true,
		style: 'stroke: #94a3b8; stroke-width: 2px;',
		markerEnd: {
			type: MarkerType.ArrowClosed,
			color: '#94a3b8'
		}
	};

	// Handle node changes
	function handleNodesChange(changes: unknown[]) {
		// SvelteFlow will update nodes internally
		onNodesChange?.(nodes);
	}

	// Handle edge changes
	function handleEdgesChange(changes: unknown[]) {
		onEdgesChange?.(edges);
	}

	// Handle new connection
	function handleConnect(connection: Connection) {
		if (!connection.source || !connection.target) return;

		const newEdge: WorkflowEdge = {
			id: `e-${connection.source}-${connection.target}-${nanoid(6)}`,
			source: connection.source,
			target: connection.target,
			sourceHandle: connection.sourceHandle || undefined,
			targetHandle: connection.targetHandle || undefined,
			type: 'smoothstep',
			animated: true,
			markerEnd: {
				type: MarkerType.ArrowClosed,
				color: '#94a3b8'
			}
		};

		// Add condition label for condition node edges
		const sourceNode = nodes.find((n) => n.id === connection.source);
		if (sourceNode?.type === 'condition' && connection.sourceHandle) {
			newEdge.data = {
				label: connection.sourceHandle === 'true' ? 'Yes' : 'No',
				condition: connection.sourceHandle as 'true' | 'false'
			};
			newEdge.style =
				connection.sourceHandle === 'true'
					? 'stroke: #22c55e; stroke-width: 2px;'
					: 'stroke: #ef4444; stroke-width: 2px;';
			newEdge.markerEnd = {
				type: MarkerType.ArrowClosed,
				color: connection.sourceHandle === 'true' ? '#22c55e' : '#ef4444'
			};
		}

		edges = [...edges, newEdge];
		onEdgesChange?.(edges);
	}

	// Handle node selection
	function handleSelectionChange({ nodes: selectedNodes }: { nodes: Node[] }) {
		const selectedId = selectedNodes.length > 0 ? selectedNodes[0].id : null;
		onNodeSelect?.(selectedId);
	}

	// Handle node double click
	function handleNodeDoubleClick(event: MouseEvent, node: Node) {
		onNodeDoubleClick?.(node.id);
	}

	// Handle drop from palette
	function handleDragOver(event: DragEvent) {
		event.preventDefault();
		event.dataTransfer!.dropEffect = 'copy';
	}

	function handleDrop(event: DragEvent) {
		event.preventDefault();

		const data = event.dataTransfer?.getData('application/workflow-node');
		if (!data) return;

		const item: PaletteItem = JSON.parse(data);

		// Get the flow instance to convert screen coordinates to flow coordinates
		const bounds = (event.target as HTMLElement).getBoundingClientRect();
		const position = {
			x: event.clientX - bounds.left - 100,
			y: event.clientY - bounds.top - 50
		};

		addNode(item, position);
	}

	// Add a new node
	export function addNode(item: PaletteItem, position?: { x: number; y: number }) {
		const id = `node-${nanoid(8)}`;
		const pos = position || { x: 250, y: nodes.length * 150 + 100 };

		let newNode: WorkflowNode;

		switch (item.type) {
			case 'action':
				const actionInfo = item.actionType ? getActionInfo(item.actionType) : null;
				newNode = {
					id,
					type: 'action',
					position: pos,
					data: {
						label: item.label,
						description: actionInfo?.description,
						actionType: item.actionType!,
						actionConfig: {},
						continueOnError: false,
						retryCount: 0
					} as ActionNodeData
				};
				break;

			case 'condition':
				newNode = {
					id,
					type: 'condition',
					position: pos,
					data: {
						label: 'Condition',
						description: 'Branch based on conditions',
						conditions: { logic: 'and', groups: [] }
					} as ConditionNodeData
				};
				break;

			case 'delay':
				newNode = {
					id,
					type: 'delay',
					position: pos,
					data: {
						label: 'Wait',
						delayType: 'minutes',
						delayValue: 5
					} as DelayNodeData
				};
				break;

			case 'end':
				newNode = {
					id,
					type: 'end',
					position: pos,
					data: {
						label: 'End',
						endType: 'success'
					} as EndNodeData
				};
				break;

			default:
				return;
		}

		nodes = [...nodes, newNode];

		// Auto-connect to last node if there's only one unconnected node
		autoConnectNode(newNode);

		onNodesChange?.(nodes);
		onNodeSelect?.(id);
	}

	// Auto-connect new node to previous node
	function autoConnectNode(newNode: WorkflowNode) {
		// Find nodes that have no outgoing edges (excluding end nodes and condition nodes with both branches)
		const nodesWithoutOutgoing = nodes.filter((n) => {
			if (n.id === newNode.id) return false;
			if (n.type === 'end') return false;

			const outgoingEdges = edges.filter((e) => e.source === n.id);

			if (n.type === 'condition') {
				// Condition nodes need both true and false branches
				return outgoingEdges.length < 2;
			}

			return outgoingEdges.length === 0;
		});

		// If there's exactly one such node, connect it
		if (nodesWithoutOutgoing.length === 1) {
			const sourceNode = nodesWithoutOutgoing[0];
			const newEdge: WorkflowEdge = {
				id: `e-${sourceNode.id}-${newNode.id}-${nanoid(6)}`,
				source: sourceNode.id,
				target: newNode.id,
				type: 'smoothstep',
				animated: true,
				markerEnd: {
					type: MarkerType.ArrowClosed,
					color: '#94a3b8'
				}
			};

			edges = [...edges, newEdge];
			onEdgesChange?.(edges);
		}
	}

	// Delete selected node
	export function deleteSelectedNode() {
		if (!selectedNodeId) return;

		// Don't delete trigger node
		const selectedNode = nodes.find((n) => n.id === selectedNodeId);
		if (selectedNode?.type === 'trigger') return;

		// Remove node
		nodes = nodes.filter((n) => n.id !== selectedNodeId);

		// Remove connected edges
		edges = edges.filter((e) => e.source !== selectedNodeId && e.target !== selectedNodeId);

		onNodesChange?.(nodes);
		onEdgesChange?.(edges);
		onNodeSelect?.(null);
	}

	// Keyboard shortcuts
	function handleKeyDown(event: KeyboardEvent) {
		if (event.key === 'Delete' || event.key === 'Backspace') {
			// Don't delete if focused on an input
			if (
				document.activeElement?.tagName === 'INPUT' ||
				document.activeElement?.tagName === 'TEXTAREA'
			) {
				return;
			}
			deleteSelectedNode();
		}
	}
</script>

<svelte:window onkeydown={handleKeyDown} />

<div
	class="workflow-canvas"
	ondragover={handleDragOver}
	ondrop={handleDrop}
	role="application"
	aria-label="Workflow canvas"
>
	<SvelteFlow
		{nodes}
		{edges}
		{nodeTypes}
		defaultEdgeOptions={defaultEdgeOptions}
		fitView
	>
		<Background gap={15} />
		<Controls />
		<MiniMap
			nodeColor={(node) => {
				switch (node.type) {
					case 'trigger':
						return '#10b981';
					case 'action':
						return '#3b82f6';
					case 'condition':
						return '#f59e0b';
					case 'delay':
						return '#6366f1';
					case 'end':
						return '#64748b';
					default:
						return '#94a3b8';
				}
			}}
		/>
	</SvelteFlow>
</div>

<style>
	.workflow-canvas {
		width: 100%;
		height: 100%;
		background: #fafafa;
	}

	:global(.svelte-flow) {
		background: #fafafa;
	}

	:global(.svelte-flow__edge-path) {
		stroke-width: 2;
	}

	:global(.svelte-flow__edge.selected .svelte-flow__edge-path) {
		stroke: #3b82f6;
		stroke-width: 3;
	}

	:global(.svelte-flow__controls) {
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		border-radius: 8px;
		border: 1px solid #e2e8f0;
	}

	:global(.svelte-flow__controls-button) {
		border: none;
		background: white;
	}

	:global(.svelte-flow__controls-button:hover) {
		background: #f8fafc;
	}

	:global(.svelte-flow__minimap) {
		background: white;
		border-radius: 8px;
		border: 1px solid #e2e8f0;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}
</style>
