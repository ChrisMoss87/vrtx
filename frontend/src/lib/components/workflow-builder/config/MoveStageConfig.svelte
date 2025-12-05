<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let pipelineId = $state<number | null>((config.pipeline_id as number) || null);
	let stageId = $state<number | null>((config.stage_id as number) || null);
	let moveType = $state<string>((config.move_type as string) || 'specific');

	function emitChange() {
		onConfigChange?.({
			pipeline_id: pipelineId,
			stage_id: stageId,
			move_type: moveType
		});
	}

	// Mock pipelines/stages - in real app would fetch from API
	const mockPipelines = [
		{
			id: 1,
			name: 'Sales Pipeline',
			stages: [
				{ id: 1, name: 'Lead' },
				{ id: 2, name: 'Qualified' },
				{ id: 3, name: 'Proposal' },
				{ id: 4, name: 'Negotiation' },
				{ id: 5, name: 'Closed Won' },
				{ id: 6, name: 'Closed Lost' }
			]
		},
		{
			id: 2,
			name: 'Support Pipeline',
			stages: [
				{ id: 7, name: 'New' },
				{ id: 8, name: 'In Progress' },
				{ id: 9, name: 'Resolved' },
				{ id: 10, name: 'Closed' }
			]
		}
	];

	const selectedPipeline = $derived(mockPipelines.find((p) => p.id === pipelineId));
</script>

<div class="space-y-4">
	<h4 class="font-medium">Move Stage Configuration</h4>

	<!-- Move Type -->
	<div class="space-y-2">
		<Label>Move Type</Label>
		<Select.Root
			type="single"
			value={moveType}
			onValueChange={(v) => {
				if (v) {
					moveType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{moveType === 'specific'
					? 'To Specific Stage'
					: moveType === 'next'
						? 'To Next Stage'
						: moveType === 'previous'
							? 'To Previous Stage'
							: 'Select move type'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="specific">To Specific Stage</Select.Item>
				<Select.Item value="next">To Next Stage</Select.Item>
				<Select.Item value="previous">To Previous Stage</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	{#if moveType === 'specific'}
		<!-- Pipeline Selection -->
		<div class="space-y-2">
			<Label>Pipeline</Label>
			<Select.Root
				type="single"
				value={pipelineId ? String(pipelineId) : ''}
				onValueChange={(v) => {
					pipelineId = v ? parseInt(v) : null;
					stageId = null; // Reset stage when pipeline changes
					emitChange();
				}}
			>
				<Select.Trigger>
					{selectedPipeline?.name || 'Select pipeline'}
				</Select.Trigger>
				<Select.Content>
					{#each mockPipelines as pipeline}
						<Select.Item value={String(pipeline.id)}>{pipeline.name}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>

		<!-- Stage Selection -->
		{#if selectedPipeline}
			<div class="space-y-2">
				<Label>Target Stage</Label>
				<Select.Root
					type="single"
					value={stageId ? String(stageId) : ''}
					onValueChange={(v) => {
						stageId = v ? parseInt(v) : null;
						emitChange();
					}}
				>
					<Select.Trigger>
						{selectedPipeline.stages.find((s) => s.id === stageId)?.name || 'Select stage'}
					</Select.Trigger>
					<Select.Content>
						{#each selectedPipeline.stages as stage}
							<Select.Item value={String(stage.id)}>{stage.name}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>
		{/if}
	{/if}

	<!-- Info based on move type -->
	{#if moveType === 'next'}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The record will be moved to the next stage in its current pipeline.
				If the record is already in the last stage, no action will be taken.
			</p>
		</div>
	{:else if moveType === 'previous'}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The record will be moved to the previous stage in its current pipeline.
				If the record is already in the first stage, no action will be taken.
			</p>
		</div>
	{:else}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				Select a pipeline and stage to move the record to.
				The record must be in a module that supports pipelines.
			</p>
		</div>
	{/if}
</div>
