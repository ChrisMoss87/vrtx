<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import type {
		Blueprint,
		BlueprintState,
		BlueprintTransition
	} from '$lib/api/blueprints';
	import * as blueprintApi from '$lib/api/blueprints';
	import BlueprintCanvas from './BlueprintCanvas.svelte';
	import StateEditor from './StateEditor.svelte';
	import TransitionEditor from './TransitionEditor.svelte';
	import { Button } from '$lib/components/ui/button';
	import { toast } from 'svelte-sonner';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import SaveIcon from '@lucide/svelte/icons/save';
	import RefreshCwIcon from '@lucide/svelte/icons/refresh-cw';

	interface Field {
		id: number;
		api_name: string;
		label: string;
		type: string;
	}

	interface Props {
		blueprintId: number;
		readonly?: boolean;
		fields?: Field[];
	}

	let { blueprintId, readonly = false, fields = [] }: Props = $props();

	// Data state
	let blueprint = $state<Blueprint | null>(null);
	let states = $state<BlueprintState[]>([]);
	let transitions = $state<BlueprintTransition[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let saveTimeout: ReturnType<typeof setTimeout> | null = null;
	let pendingPositionUpdates = $state<Map<number, { x: number; y: number }>>(new Map());

	// Selection state
	let selectedStateId = $state<number | null>(null);
	let selectedTransitionId = $state<number | null>(null);

	// Derived selections
	const selectedState = $derived(states.find((s) => s.id === selectedStateId) || null);
	const selectedTransition = $derived(transitions.find((t) => t.id === selectedTransitionId) || null);

	// Load blueprint data
	async function loadBlueprint() {
		loading = true;
		try {
			blueprint = await blueprintApi.getBlueprint(blueprintId);
			states = blueprint.states || [];
			transitions = blueprint.transitions || [];
		} catch (error) {
			console.error('Failed to load blueprint:', error);
			toast.error('Failed to load blueprint');
		} finally {
			loading = false;
		}
	}

	// Save layout
	async function saveLayout() {
		if (!blueprint) return;

		saving = true;
		try {
			const layoutData: Record<string, { x: number; y: number }> = {};
			for (const state of states) {
				if (state.position_x !== null && state.position_y !== null) {
					layoutData[state.id.toString()] = {
						x: state.position_x,
						y: state.position_y
					};
				}
			}
			await blueprintApi.updateBlueprintLayout(blueprintId, layoutData);
			toast.success('Layout saved');
		} catch (error) {
			console.error('Failed to save layout:', error);
			toast.error('Failed to save layout');
		} finally {
			saving = false;
		}
	}

	// Handle state selection
	function handleSelectState(e: CustomEvent<{ state: BlueprintState }>) {
		selectedStateId = e.detail.state.id;
		selectedTransitionId = null;
	}

	// Handle transition selection
	function handleSelectTransition(e: CustomEvent<{ transition: BlueprintTransition }>) {
		selectedTransitionId = e.detail.transition.id;
		selectedStateId = null;
	}

	// Handle state move with debounced auto-save
	async function handleMoveState(e: CustomEvent<{ stateId: number; x: number; y: number }>) {
		const { stateId, x, y } = e.detail;
		const stateIndex = states.findIndex((s) => s.id === stateId);
		if (stateIndex !== -1) {
			// Update local state immediately for smooth UI
			states[stateIndex] = {
				...states[stateIndex],
				position_x: x,
				position_y: y
			};

			// Track pending updates
			pendingPositionUpdates.set(stateId, { x, y });
			pendingPositionUpdates = new Map(pendingPositionUpdates); // Trigger reactivity

			// Debounce the save
			if (saveTimeout) {
				clearTimeout(saveTimeout);
			}
			saveTimeout = setTimeout(() => {
				savePendingPositions();
			}, 500);
		}
	}

	// Save all pending position updates
	async function savePendingPositions() {
		if (pendingPositionUpdates.size === 0) return;

		const updates = Array.from(pendingPositionUpdates.entries());
		pendingPositionUpdates = new Map();

		try {
			// Update each state position in parallel
			await Promise.all(
				updates.map(([stateId, pos]) =>
					blueprintApi.updateState(blueprintId, stateId, {
						position_x: pos.x,
						position_y: pos.y
					})
				)
			);
		} catch (error) {
			console.error('Failed to save positions:', error);
			// Don't show toast for position saves to avoid spam
		}
	}

	// Handle create transition
	async function handleCreateTransition(e: CustomEvent<{ fromStateId: number; toStateId: number }>) {
		const { fromStateId, toStateId } = e.detail;

		try {
			const fromState = states.find((s) => s.id === fromStateId);
			const toState = states.find((s) => s.id === toStateId);

			const newTransition = await blueprintApi.createTransition(blueprintId, {
				from_state_id: fromStateId,
				to_state_id: toStateId,
				name: `${fromState?.name || 'Start'} → ${toState?.name || 'End'}`
			});

			transitions = [...transitions, newTransition];
			selectedTransitionId = newTransition.id;
			selectedStateId = null;
			toast.success('Transition created');
		} catch (error) {
			console.error('Failed to create transition:', error);
			toast.error('Failed to create transition');
		}
	}

	// Handle clear selection
	function handleClearSelection() {
		selectedStateId = null;
		selectedTransitionId = null;
	}

	// Add new stage
	async function addStage() {
		try {
			// Find a free position
			const maxX = Math.max(...states.map((s) => s.position_x || 0), 0);
			const newState = await blueprintApi.createState(blueprintId, {
				name: `New Stage ${states.length + 1}`,
				position_x: maxX + 200,
				position_y: 100
			});
			states = [...states, newState];
			selectedStateId = newState.id;
			selectedTransitionId = null;
			toast.success('Stage created');
		} catch (error) {
			console.error('Failed to create stage:', error);
			toast.error('Failed to create stage');
		}
	}

	// Sync stages from field
	async function syncStages() {
		try {
			const updated = await blueprintApi.syncBlueprintStates(blueprintId);
			states = updated.states || [];
			transitions = updated.transitions || [];
			toast.success('Stages synced from field options');
		} catch (error) {
			console.error('Failed to sync stages:', error);
			toast.error('Failed to sync stages');
		}
	}

	// Handle state update
	async function handleStateUpdate(updatedState: BlueprintState) {
		const index = states.findIndex((s) => s.id === updatedState.id);
		if (index !== -1) {
			states[index] = updatedState;
		}
	}

	// Handle stage delete
	async function handleStateDelete(stateId: number) {
		try {
			await blueprintApi.deleteState(blueprintId, stateId);
			states = states.filter((s) => s.id !== stateId);
			// Also remove transitions connected to this stage
			transitions = transitions.filter(
				(t) => t.from_state_id !== stateId && t.to_state_id !== stateId
			);
			selectedStateId = null;
			toast.success('Stage deleted');
		} catch (error) {
			console.error('Failed to delete stage:', error);
			toast.error('Failed to delete stage');
		}
	}

	// Handle transition update
	async function handleTransitionUpdate(updatedTransition: BlueprintTransition) {
		const index = transitions.findIndex((t) => t.id === updatedTransition.id);
		if (index !== -1) {
			transitions[index] = updatedTransition;
		}
	}

	// Handle transition delete
	async function handleTransitionDelete(transitionId: number) {
		try {
			await blueprintApi.deleteTransition(blueprintId, transitionId);
			transitions = transitions.filter((t) => t.id !== transitionId);
			selectedTransitionId = null;
			toast.success('Transition deleted');
		} catch (error) {
			console.error('Failed to delete transition:', error);
			toast.error('Failed to delete transition');
		}
	}

	onMount(() => {
		loadBlueprint();
	});

	onDestroy(() => {
		// Flush any pending position saves
		if (saveTimeout) {
			clearTimeout(saveTimeout);
		}
		if (pendingPositionUpdates.size > 0) {
			savePendingPositions();
		}
	});
</script>

<div class="flex h-full gap-4">
	<!-- Main Canvas Area -->
	<div class="flex flex-1 flex-col gap-4">
		<!-- Toolbar -->
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<h2 class="text-lg font-semibold">{blueprint?.name || 'Loading...'}</h2>
				{#if blueprint?.is_active}
					<span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
						Active
					</span>
				{:else}
					<span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
						Inactive
					</span>
				{/if}
			</div>

			{#if !readonly}
				<div class="flex items-center gap-2">
					<Button variant="outline" size="sm" onclick={syncStages}>
						<RefreshCwIcon class="mr-2 h-4 w-4" />
						Sync from Field
					</Button>
					<Button variant="outline" size="sm" onclick={addStage}>
						<PlusIcon class="mr-2 h-4 w-4" />
						Add Stage
					</Button>
					<Button size="sm" onclick={saveLayout} disabled={saving}>
						<SaveIcon class="mr-2 h-4 w-4" />
						{saving ? 'Saving...' : 'Save Layout'}
					</Button>
				</div>
			{/if}
		</div>

		<!-- Canvas -->
		<div class="flex-1">
			{#if loading}
				<div class="flex h-full items-center justify-center">
					<div class="text-muted-foreground">Loading blueprint...</div>
				</div>
			{:else}
				<BlueprintCanvas
					{states}
					{transitions}
					{selectedStateId}
					{selectedTransitionId}
					{readonly}
					on:selectState={handleSelectState}
					on:selectTransition={handleSelectTransition}
					on:moveState={handleMoveState}
					on:createTransition={handleCreateTransition}
					on:clearSelection={handleClearSelection}
				/>
			{/if}
		</div>
	</div>

	<!-- Side Panel for Editing -->
	{#if selectedState || selectedTransition}
		<div class="w-80 shrink-0 overflow-y-auto rounded-lg border bg-background p-4">
			{#if selectedState}
				<StateEditor
					nodeState={selectedState}
					{blueprintId}
					{readonly}
					onUpdate={handleStateUpdate}
					onDelete={() => handleStateDelete(selectedState.id)}
					onClose={handleClearSelection}
				/>
			{:else if selectedTransition}
				<TransitionEditor
					transition={selectedTransition}
					{states}
					{fields}
					{blueprintId}
					{readonly}
					onUpdate={handleTransitionUpdate}
					onDelete={() => handleTransitionDelete(selectedTransition.id)}
					onClose={handleClearSelection}
				/>
			{/if}
		</div>
	{/if}
</div>
