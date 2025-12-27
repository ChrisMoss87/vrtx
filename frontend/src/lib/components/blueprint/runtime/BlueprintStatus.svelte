<script lang="ts">
	import { onMount } from 'svelte';
	import type {
		RecordState,
		AvailableTransition,
		SLAStatus
	} from '$lib/api/blueprints';
	import * as blueprintApi from '$lib/api/blueprints';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import TransitionDialog from './TransitionDialog.svelte';
	import ArrowRightIcon from '@lucide/svelte/icons/arrow-right';
	import ClockIcon from '@lucide/svelte/icons/clock';
	import AlertTriangleIcon from '@lucide/svelte/icons/alert-triangle';
	import CheckCircleIcon from '@lucide/svelte/icons/check-circle';

	interface Props {
		recordId: number;
		moduleId?: number;
		blueprintId?: number;
		fieldId?: number;
		recordData?: Record<string, unknown>;
		compact?: boolean;
		onStateChange?: (newState: RecordState) => void;
	}

	let {
		recordId,
		moduleId,
		blueprintId,
		fieldId,
		recordData = {},
		compact = false,
		onStateChange
	}: Props = $props();

	// State
	let loading = $state(true);
	let currentState = $state<RecordState | null>(null);
	let availableTransitions = $state<AvailableTransition[]>([]);
	let slaStatus = $state<SLAStatus | null>(null);
	let blueprintInfo = $state<{ id: number; name: string; is_active: boolean } | null>(null);

	// Dialog state
	let dialogOpen = $state(false);
	let selectedTransition = $state<AvailableTransition | null>(null);

	// Load record state
	async function loadState() {
		loading = true;
		try {
			const result = await blueprintApi.getRecordState(recordId, {
				blueprint_id: blueprintId,
				module_id: moduleId,
				field_id: fieldId
			});
			blueprintInfo = result.blueprint;
			currentState = result.current_state;
			availableTransitions = result.available_transitions;
			slaStatus = result.sla_status;
		} catch (error: unknown) {
			// 404 is expected when no blueprint exists for the module - silently ignore
			const isNotFound = error && typeof error === 'object' && 'status' in error && error.status === 404;
			if (!isNotFound) {
				console.error('Failed to load blueprint state:', error);
			}
			// Reset state to show "No active blueprint" message
			blueprintInfo = null;
			currentState = null;
			availableTransitions = [];
			slaStatus = null;
		} finally {
			loading = false;
		}
	}

	// Handle transition button click
	function handleTransitionClick(transition: AvailableTransition) {
		selectedTransition = transition;
		dialogOpen = true;
	}

	// Handle transition complete
	function handleTransitionComplete(newState: { id: number; name: string }) {
		dialogOpen = false;
		selectedTransition = null;
		loadState(); // Reload state
		onStateChange?.({
			id: newState.id,
			name: newState.name,
			color: null,
			is_terminal: false,
			entered_at: new Date().toISOString()
		});
	}

	// Handle dialog close
	function handleDialogClose() {
		dialogOpen = false;
		selectedTransition = null;
	}

	// Format SLA remaining time
	function formatSLATime(seconds: number): string {
		return blueprintApi.formatRemainingTime(seconds);
	}

	// Calculate duration in current stage
	function getStageDuration(enteredAt: string | undefined): string {
		if (!enteredAt) return '';

		const entered = new Date(enteredAt);
		const now = new Date();
		const diffMs = now.getTime() - entered.getTime();

		const minutes = Math.floor(diffMs / 60000);
		const hours = Math.floor(minutes / 60);
		const days = Math.floor(hours / 24);

		if (days > 0) {
			const remainingHours = hours % 24;
			if (remainingHours > 0) {
				return `${days}d ${remainingHours}h`;
			}
			return `${days} day${days > 1 ? 's' : ''}`;
		}
		if (hours > 0) {
			const remainingMinutes = minutes % 60;
			if (remainingMinutes > 0) {
				return `${hours}h ${remainingMinutes}m`;
			}
			return `${hours} hour${hours > 1 ? 's' : ''}`;
		}
		if (minutes > 0) {
			return `${minutes} min${minutes > 1 ? 's' : ''}`;
		}
		return 'Just now';
	}

	const stageDuration = $derived(getStageDuration(currentState?.entered_at));

	onMount(() => {
		loadState();
	});
</script>

{#if loading}
	<div class="animate-pulse">
		<div class="h-8 w-32 rounded bg-muted"></div>
	</div>
{:else if blueprintInfo && blueprintInfo.is_active}
	<div class={compact ? 'inline-flex items-center gap-2' : 'space-y-3'}>
		<!-- Current Stage Badge -->
		{#if currentState}
			<div class="flex items-center gap-2">
				<Badge
					variant="outline"
					class="text-sm"
					style={currentState.color
						? `border-color: ${currentState.color}; color: ${currentState.color}; background-color: ${currentState.color}10`
						: ''}
				>
					{#if currentState.is_terminal}
						<CheckCircleIcon class="mr-1 h-3 w-3" />
					{/if}
					{currentState.name}
				</Badge>
				{#if stageDuration && !compact}
					<span class="flex items-center gap-1 text-xs text-muted-foreground">
						<ClockIcon class="h-3 w-3" />
						{stageDuration}
					</span>
				{/if}
			</div>
		{:else}
			<Badge variant="secondary" class="text-sm">No Stage</Badge>
		{/if}

		<!-- SLA Status -->
		{#if slaStatus && !compact}
			<div
				class="flex items-center gap-2 rounded-lg p-2 text-sm {slaStatus.is_breached
					? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
					: slaStatus.is_approaching
						? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
						: 'bg-muted text-muted-foreground'}"
			>
				{#if slaStatus.is_breached}
					<AlertTriangleIcon class="h-4 w-4" />
					<span>SLA Breached - {slaStatus.sla_name}</span>
				{:else}
					<ClockIcon class="h-4 w-4" />
					<span>
						{slaStatus.sla_name}: {formatSLATime(slaStatus.remaining_seconds)} remaining
					</span>
				{/if}
			</div>
		{/if}

		<!-- Available Transitions -->
		{#if availableTransitions.length > 0 && !currentState?.is_terminal}
			<div class={compact ? 'flex items-center gap-1' : 'flex flex-wrap gap-2'}>
				{#each availableTransitions as transition}
					<Button
						variant={compact ? 'ghost' : 'outline'}
						size="sm"
						onclick={() => handleTransitionClick(transition)}
						class={compact ? 'h-7 px-2' : ''}
					>
						<ArrowRightIcon class="mr-1 h-3 w-3" />
						{transition.button_label || transition.name}
						{#if transition.requires_approval}
							<span class="ml-1 text-xs text-purple-500">⚡</span>
						{/if}
					</Button>
				{/each}
			</div>
		{/if}
	</div>

	<!-- Transition Dialog -->
	<TransitionDialog
		bind:open={dialogOpen}
		{recordId}
		transition={selectedTransition}
		{recordData}
		onComplete={handleTransitionComplete}
		onClose={handleDialogClose}
	/>
{:else if !blueprintInfo?.is_active}
	<!-- Blueprint not active or not found -->
	<span class="text-sm text-muted-foreground">No active blueprint</span>
{/if}
