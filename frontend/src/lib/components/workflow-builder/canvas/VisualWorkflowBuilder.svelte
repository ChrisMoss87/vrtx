<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Dialog from '$lib/components/ui/dialog';
	import { toast } from 'svelte-sonner';
	import {
		Save,
		Play,
		ArrowLeft,
		Loader2,
		LayoutGrid,
		List,
		Settings,
		Undo,
		Redo,
		ZoomIn,
		ZoomOut,
		Maximize
	} from 'lucide-svelte';
	import { nanoid } from 'nanoid';

	import NodePalette from './NodePalette.svelte';
	import WorkflowCanvas from './WorkflowCanvas.svelte';
	import NodeConfigPanel from './NodeConfigPanel.svelte';
	import TriggerConfig from '../TriggerConfig.svelte';

	import type {
		WorkflowNode,
		WorkflowEdge,
		PaletteItem,
		TriggerNodeData,
		SerializedWorkflow
	} from './types';
	import type {
		Workflow,
		WorkflowInput,
		TriggerType,
		TriggerTiming,
		TriggerConfig as TriggerConfigType,
		WorkflowStepInput
	} from '$lib/api/workflows';
	import type { Module, Field } from '$lib/api/modules';
	import { triggerTypes } from './nodeConfig';

	interface Props {
		workflow?: Workflow;
		modules: Module[];
		onSave: (data: WorkflowInput) => Promise<Workflow>;
		onCancel: () => void;
		onTest?: (workflow: Workflow) => void;
	}

	let { workflow, modules, onSave, onCancel, onTest }: Props = $props();

	const isNew = !workflow?.id;

	// Basic workflow info
	let name = $state(workflow?.name || '');
	let description = $state(workflow?.description || '');
	let moduleId = $state<number | null>(workflow?.module_id || null);
	let isActive = $state(workflow?.is_active ?? false);

	// Trigger configuration
	let triggerType = $state<TriggerType>(workflow?.trigger_type || 'record_created');
	let triggerConfig = $state<TriggerConfigType>(workflow?.trigger_config || {});
	let triggerTiming = $state<TriggerTiming>(workflow?.trigger_timing || 'all');
	let watchedFields = $state<string[]>(workflow?.watched_fields || []);

	// Advanced settings
	let priority = $state(workflow?.priority ?? 100);
	let maxExecutionsPerDay = $state<number | null>(workflow?.max_executions_per_day || null);
	let runOncePerRecord = $state(workflow?.run_once_per_record ?? false);
	let allowManualTrigger = $state(workflow?.allow_manual_trigger ?? true);
	let stopOnFirstMatch = $state(workflow?.stop_on_first_match ?? false);
	let delaySeconds = $state(workflow?.delay_seconds ?? 0);

	// Canvas state
	let nodes = $state<WorkflowNode[]>([]);
	let edges = $state<WorkflowEdge[]>([]);
	let selectedNodeId = $state<string | null>(null);

	// UI state
	let saving = $state(false);
	let testing = $state(false);
	let showSettings = $state(false);
	let showTriggerConfig = $state(false);
	let canvasRef: WorkflowCanvas;

	// Initialize canvas from workflow
	$effect(() => {
		if (workflow?.id && nodes.length === 0) {
			initializeFromWorkflow();
		} else if (!workflow?.id && nodes.length === 0) {
			initializeNewWorkflow();
		}
	});

	// Get selected module
	const selectedModule = $derived(modules.find((m) => m.id === moduleId));
	const moduleFields = $derived<Field[]>(selectedModule?.fields || []);

	// Get selected node
	const selectedNode = $derived(nodes.find((n) => n.id === selectedNodeId) || null);

	// Validation
	const isValid = $derived(() => {
		if (!name.trim()) return false;
		if (!triggerType) return false;
		const recordTriggers = ['record_created', 'record_updated', 'record_deleted', 'record_saved', 'field_changed'];
		if (recordTriggers.includes(triggerType) && !moduleId) return false;
		return true;
	});

	// Initialize new workflow with trigger node
	function initializeNewWorkflow() {
		const triggerId = `trigger-${nanoid(8)}`;
		nodes = [
			{
				id: triggerId,
				type: 'trigger',
				position: { x: 250, y: 50 },
				data: {
					label: 'When record is created',
					triggerType: 'record_created',
					triggerConfig: {}
				} as TriggerNodeData
			}
		];
		edges = [];
	}

	// Initialize from existing workflow
	function initializeFromWorkflow() {
		if (!workflow) return;

		const newNodes: WorkflowNode[] = [];
		const newEdges: WorkflowEdge[] = [];

		// Create trigger node
		const triggerId = `trigger-${nanoid(8)}`;
		const triggerInfo = triggerTypes.find((t) => t.value === workflow.trigger_type);
		newNodes.push({
			id: triggerId,
			type: 'trigger',
			position: { x: 250, y: 50 },
			data: {
				label: triggerInfo?.label || workflow.trigger_type,
				description: triggerInfo?.description,
				triggerType: workflow.trigger_type,
				triggerConfig: workflow.trigger_config || {},
				moduleId: workflow.module_id || undefined,
				moduleName: selectedModule?.name
			} as TriggerNodeData
		});

		// Create step nodes
		let prevNodeId = triggerId;
		let yPos = 200;

		for (const step of workflow.steps || []) {
			const nodeId = `action-${step.id || nanoid(8)}`;

			// Determine node type based on action
			let nodeType: 'action' | 'condition' | 'delay' = 'action';
			if (step.action_type === 'condition') {
				nodeType = 'condition';
			} else if (step.action_type === 'delay') {
				nodeType = 'delay';
			}

			newNodes.push({
				id: nodeId,
				type: nodeType,
				position: { x: 250, y: yPos },
				data: {
					label: step.name || step.action_type,
					actionType: step.action_type,
					actionConfig: step.action_config || {},
					continueOnError: step.continue_on_error,
					retryCount: step.retry_count,
					retryDelaySeconds: step.retry_delay_seconds
				}
			} as WorkflowNode);

			// Connect to previous node
			newEdges.push({
				id: `e-${prevNodeId}-${nodeId}`,
				source: prevNodeId,
				target: nodeId,
				type: 'smoothstep',
				animated: true
			});

			prevNodeId = nodeId;
			yPos += 150;
		}

		nodes = newNodes;
		edges = newEdges;
	}

	// Convert canvas to workflow steps
	function canvasToSteps(): WorkflowStepInput[] {
		const steps: WorkflowStepInput[] = [];
		const visited = new Set<string>();

		// Find the trigger node
		const triggerNode = nodes.find((n) => n.type === 'trigger');
		if (!triggerNode) return steps;

		// BFS through the graph
		const queue: string[] = [triggerNode.id];
		while (queue.length > 0) {
			const nodeId = queue.shift()!;
			if (visited.has(nodeId)) continue;
			visited.add(nodeId);

			const node = nodes.find((n) => n.id === nodeId);
			if (!node || node.type === 'trigger') {
				// Add connected nodes to queue
				const outEdges = edges.filter((e) => e.source === nodeId);
				for (const edge of outEdges) {
					queue.push(edge.target);
				}
				continue;
			}

			// Skip end nodes
			if (node.type === 'end') continue;

			// Convert node to step
			if (node.type === 'action') {
				const data = node.data as { actionType: string; actionConfig: Record<string, unknown>; label: string; continueOnError?: boolean; retryCount?: number; retryDelaySeconds?: number };
				steps.push({
					name: data.label,
					action_type: data.actionType as WorkflowStepInput['action_type'],
					action_config: data.actionConfig,
					continue_on_error: data.continueOnError ?? false,
					retry_count: data.retryCount ?? 0,
					retry_delay_seconds: data.retryDelaySeconds ?? 60
				});
			} else if (node.type === 'delay') {
				const data = node.data as { label: string; delayType: string; delayValue: number };
				steps.push({
					name: data.label,
					action_type: 'delay',
					action_config: {
						delay_type: data.delayType,
						delay_value: data.delayValue
					},
					continue_on_error: false,
					retry_count: 0,
					retry_delay_seconds: 60
				});
			} else if (node.type === 'condition') {
				const data = node.data as { label: string; conditions: unknown };
				steps.push({
					name: data.label,
					action_type: 'condition',
					action_config: {
						conditions: data.conditions
					},
					continue_on_error: false,
					retry_count: 0,
					retry_delay_seconds: 60
				});
			}

			// Add connected nodes to queue
			const outEdges = edges.filter((e) => e.source === nodeId);
			for (const edge of outEdges) {
				queue.push(edge.target);
			}
		}

		return steps;
	}

	// Handle save
	async function handleSave() {
		if (!isValid()) {
			toast.error('Please fill in all required fields');
			return;
		}

		saving = true;
		try {
			const steps = canvasToSteps();

			const data: WorkflowInput = {
				name,
				description: description || undefined,
				module_id: moduleId,
				is_active: isActive,
				priority,
				trigger_type: triggerType,
				trigger_config: triggerConfig,
				trigger_timing: triggerTiming,
				watched_fields: watchedFields.length > 0 ? watchedFields : undefined,
				stop_on_first_match: stopOnFirstMatch,
				max_executions_per_day: maxExecutionsPerDay,
				run_once_per_record: runOncePerRecord,
				allow_manual_trigger: allowManualTrigger,
				delay_seconds: delaySeconds,
				steps
			};

			await onSave(data);
			toast.success(isNew ? 'Workflow created' : 'Workflow saved');
		} catch (error) {
			console.error('Failed to save workflow:', error);
			toast.error('Failed to save workflow');
		} finally {
			saving = false;
		}
	}

	// Handle test
	async function handleTest() {
		if (!workflow || !onTest) return;
		testing = true;
		try {
			onTest(workflow);
		} finally {
			testing = false;
		}
	}

	// Handle palette item click/drag
	function handlePaletteItemClick(item: PaletteItem) {
		canvasRef?.addNode(item);
	}

	// Handle node selection
	function handleNodeSelect(nodeId: string | null) {
		selectedNodeId = nodeId;

		// Open trigger config if trigger node is selected
		if (nodeId) {
			const node = nodes.find((n) => n.id === nodeId);
			if (node?.type === 'trigger') {
				showTriggerConfig = true;
			}
		}
	}

	// Handle node update from config panel
	function handleNodeUpdate(nodeId: string, data: Partial<WorkflowNode['data']>) {
		nodes = nodes.map((n) => (n.id === nodeId ? { ...n, data: { ...n.data, ...data } } : n));
	}

	// Handle node delete from config panel
	function handleNodeDelete(nodeId: string) {
		nodes = nodes.filter((n) => n.id !== nodeId);
		edges = edges.filter((e) => e.source !== nodeId && e.target !== nodeId);
		selectedNodeId = null;
	}

	// Update trigger node when trigger config changes
	function updateTriggerNode() {
		const triggerNode = nodes.find((n) => n.type === 'trigger');
		if (!triggerNode) return;

		const triggerInfo = triggerTypes.find((t) => t.value === triggerType);
		nodes = nodes.map((n): WorkflowNode => {
			if (n.type === 'trigger') {
				return {
					...n,
					data: {
						...n.data,
						label: triggerInfo?.label || triggerType,
						description: triggerInfo?.description,
						triggerType,
						triggerConfig,
						moduleId: moduleId || undefined,
						moduleName: selectedModule?.name
					} as TriggerNodeData
				};
			}
			return n;
		});
	}

	// Watch trigger changes
	$effect(() => {
		triggerType;
		triggerConfig;
		moduleId;
		updateTriggerNode();
	});
</script>

<div class="visual-builder">
	<!-- Header -->
	<div class="builder-header">
		<div class="header-left">
			<Button variant="ghost" size="icon" onclick={onCancel}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div class="header-info">
				<Input
					bind:value={name}
					placeholder="Workflow name"
					class="header-name-input"
				/>
				<span class="header-module">
					{selectedModule?.name || 'Select module'}
				</span>
			</div>
		</div>
		<div class="header-center">
			<div class="flex items-center gap-2">
				<Switch bind:checked={isActive} />
				<span class="text-sm">{isActive ? 'Active' : 'Inactive'}</span>
			</div>
		</div>
		<div class="header-right">
			<Button variant="outline" size="icon" onclick={() => (showSettings = true)}>
				<Settings class="h-4 w-4" />
			</Button>
			{#if !isNew && onTest}
				<Button variant="outline" onclick={handleTest} disabled={testing}>
					{#if testing}
						<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					{:else}
						<Play class="mr-2 h-4 w-4" />
					{/if}
					Test
				</Button>
			{/if}
			<Button onclick={handleSave} disabled={saving || !isValid()}>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{:else}
					<Save class="mr-2 h-4 w-4" />
				{/if}
				{isNew ? 'Create' : 'Save'}
			</Button>
		</div>
	</div>

	<!-- Main content -->
	<div class="builder-content">
		<!-- Left sidebar: Node palette -->
		<div class="builder-palette">
			<NodePalette
				onItemClick={handlePaletteItemClick}
			/>
		</div>

		<!-- Center: Canvas -->
		<div class="builder-canvas">
			<WorkflowCanvas
				bind:nodes
				bind:edges
				{selectedNodeId}
				onNodeSelect={handleNodeSelect}
				onNodeDoubleClick={(nodeId) => {
					selectedNodeId = nodeId;
					const node = nodes.find((n) => n.id === nodeId);
					if (node?.type === 'trigger') {
						showTriggerConfig = true;
					}
				}}
				bind:this={canvasRef}
			/>
		</div>

		<!-- Right sidebar: Config panel -->
		<div class="builder-config">
			<NodeConfigPanel
				node={selectedNode}
				{moduleFields}
				onClose={() => (selectedNodeId = null)}
				onUpdate={handleNodeUpdate}
				onDelete={handleNodeDelete}
			/>
		</div>
	</div>
</div>

<!-- Settings Dialog -->
<Dialog.Root bind:open={showSettings}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Workflow Settings</Dialog.Title>
			<Dialog.Description>Configure workflow behavior and limits</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label>Description</Label>
				<Textarea
					bind:value={description}
					placeholder="What does this workflow do?"
					rows={3}
				/>
			</div>

			<div class="space-y-2">
				<Label>Module</Label>
				<Select.Root
					type="single"
					value={moduleId ? String(moduleId) : ''}
					onValueChange={(v) => {
						moduleId = v ? parseInt(v) : null;
						watchedFields = [];
					}}
				>
					<Select.Trigger>
						{selectedModule?.name || 'Select module'}
					</Select.Trigger>
					<Select.Content>
						{#each modules as module}
							<Select.Item value={String(module.id)}>{module.name}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<div class="space-y-2">
				<Label>Priority (lower = runs first)</Label>
				<Input type="number" min="1" max="1000" bind:value={priority} />
			</div>

			<div class="space-y-2">
				<Label>Max Executions Per Day</Label>
				<Input
					type="number"
					min="0"
					value={maxExecutionsPerDay ?? ''}
					oninput={(e) => {
						const val = parseInt(e.currentTarget.value);
						maxExecutionsPerDay = val > 0 ? val : null;
					}}
					placeholder="Unlimited"
				/>
			</div>

			<div class="space-y-2">
				<Label>Initial Delay (seconds)</Label>
				<Input type="number" min="0" bind:value={delaySeconds} />
			</div>

			<div class="flex items-center justify-between">
				<div>
					<Label>Run Once Per Record</Label>
					<p class="text-xs text-muted-foreground">Only trigger once per record</p>
				</div>
				<Switch bind:checked={runOncePerRecord} />
			</div>

			<div class="flex items-center justify-between">
				<div>
					<Label>Allow Manual Trigger</Label>
					<p class="text-xs text-muted-foreground">Allow users to run manually</p>
				</div>
				<Switch bind:checked={allowManualTrigger} />
			</div>

			<div class="flex items-center justify-between">
				<div>
					<Label>Stop on First Match</Label>
					<p class="text-xs text-muted-foreground">Stop other workflows if this runs</p>
				</div>
				<Switch bind:checked={stopOnFirstMatch} />
			</div>
		</div>

		<Dialog.Footer>
			<Button onclick={() => (showSettings = false)}>Done</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Trigger Config Dialog -->
<Dialog.Root bind:open={showTriggerConfig}>
	<Dialog.Content class="max-w-2xl max-h-[80vh] overflow-y-auto">
		<Dialog.Header>
			<Dialog.Title>Configure Trigger</Dialog.Title>
			<Dialog.Description>Define when this workflow should run</Dialog.Description>
		</Dialog.Header>

		<div class="py-4">
			<TriggerConfig
				bind:triggerType
				bind:triggerConfig
				bind:triggerTiming
				bind:watchedFields
				{moduleFields}
			/>
		</div>

		<Dialog.Footer>
			<Button onclick={() => (showTriggerConfig = false)}>Done</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<style>
	.visual-builder {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background: #f8fafc;
	}

	.builder-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 16px;
		background: white;
		border-bottom: 1px solid #e2e8f0;
		gap: 16px;
	}

	.header-left {
		display: flex;
		align-items: center;
		gap: 12px;
		flex: 1;
	}

	.header-info {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}

	:global(.header-name-input) {
		font-size: 16px;
		font-weight: 600;
		border: none;
		padding: 0;
		height: auto;
		background: transparent;
	}

	:global(.header-name-input:focus) {
		outline: none;
		box-shadow: none;
	}

	.header-module {
		font-size: 12px;
		color: #64748b;
	}

	.header-center {
		display: flex;
		align-items: center;
	}

	.header-right {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.builder-content {
		display: flex;
		flex: 1;
		overflow: hidden;
	}

	.builder-palette {
		width: 280px;
		flex-shrink: 0;
		overflow: hidden;
	}

	.builder-canvas {
		flex: 1;
		overflow: hidden;
	}

	.builder-config {
		width: 320px;
		flex-shrink: 0;
		overflow: hidden;
	}
</style>
