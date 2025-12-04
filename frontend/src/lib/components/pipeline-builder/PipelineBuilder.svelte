<script lang="ts">
	import type { Pipeline, Stage, StageInput } from '$lib/api/pipelines';
	import { createPipeline, updatePipeline } from '$lib/api/pipelines';
	import StageEditor from './StageEditor.svelte';
	import StageList from './StageList.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import { cn } from '$lib/utils';
	import Plus from 'lucide-svelte/icons/plus';

	interface Module {
		id: number;
		name: string;
		api_name: string;
		fields?: Array<{ api_name: string; label: string; type: string }>;
	}

	interface Props {
		pipeline?: Pipeline;
		modules: Module[];
		onSave?: (pipeline: Pipeline) => void;
		onCancel?: () => void;
		class?: string;
	}

	let { pipeline, modules, onSave, onCancel, class: className }: Props = $props();

	// Form state
	let name = $state(pipeline?.name || '');
	let moduleId = $state(pipeline?.module_id?.toString() || '');
	let stageFieldApiName = $state(pipeline?.stage_field_api_name || '');
	let isActive = $state(pipeline?.is_active ?? true);
	let stages = $state<StageInput[]>(
		pipeline?.stages?.map((s) => ({
			id: s.id,
			name: s.name,
			color: s.color,
			probability: s.probability,
			display_order: s.display_order,
			is_won_stage: s.is_won_stage,
			is_lost_stage: s.is_lost_stage,
			settings: s.settings
		})) || []
	);

	let saving = $state(false);
	let editingStage = $state<StageInput | null>(null);
	let showStageEditor = $state(false);

	// Get selected module
	let selectedModule = $derived(modules.find((m) => m.id.toString() === moduleId));

	// Get stage-compatible fields from selected module
	let stageFields = $derived(
		selectedModule?.fields?.filter(
			(f) => f.type === 'select' || f.type === 'picklist' || f.type === 'status'
		) || []
	);

	function addStage() {
		editingStage = {
			name: '',
			color: '#6b7280',
			probability: 0,
			is_won_stage: false,
			is_lost_stage: false
		};
		showStageEditor = true;
	}

	function editStage(stage: StageInput, index: number) {
		editingStage = { ...stage, _index: index } as StageInput & { _index: number };
		showStageEditor = true;
	}

	function saveStage(stage: StageInput) {
		const stageWithIndex = stage as StageInput & { _index?: number };
		if (stageWithIndex._index !== undefined) {
			// Update existing
			stages[stageWithIndex._index] = stage;
			stages = [...stages];
		} else {
			// Add new
			stages = [...stages, stage];
		}
		showStageEditor = false;
		editingStage = null;
	}

	function deleteStage(index: number) {
		stages = stages.filter((_, i) => i !== index);
	}

	function reorderStages(fromIndex: number, toIndex: number) {
		const newStages = [...stages];
		const [removed] = newStages.splice(fromIndex, 1);
		newStages.splice(toIndex, 0, removed);
		stages = newStages;
	}

	async function handleSubmit() {
		if (!name.trim()) {
			toast.error('Pipeline name is required');
			return;
		}

		if (!moduleId) {
			toast.error('Please select a module');
			return;
		}

		if (stages.length === 0) {
			toast.error('Please add at least one stage');
			return;
		}

		saving = true;

		try {
			const data = {
				name,
				module_id: parseInt(moduleId),
				stage_field_api_name: stageFieldApiName || undefined,
				is_active: isActive,
				stages: stages.map((s, i) => ({
					...s,
					display_order: i
				}))
			};

			let result: Pipeline;
			if (pipeline?.id) {
				result = await updatePipeline(pipeline.id, data);
				toast.success('Pipeline updated successfully');
			} else {
				result = await createPipeline(data);
				toast.success('Pipeline created successfully');
			}

			onSave?.(result);
		} catch (error: any) {
			console.error('Failed to save pipeline:', error);
			toast.error(error.message || 'Failed to save pipeline');
		} finally {
			saving = false;
		}
	}
</script>

<div class={cn('space-y-6', className)}>
	<!-- Pipeline Details -->
	<div class="space-y-4">
		<h3 class="text-lg font-semibold">Pipeline Details</h3>

		<div class="grid gap-4 sm:grid-cols-2">
			<!-- Name -->
			<div class="space-y-2">
				<Label for="pipeline-name">Pipeline Name *</Label>
				<Input id="pipeline-name" bind:value={name} placeholder="Enter pipeline name" />
			</div>

			<!-- Module -->
			<div class="space-y-2">
				<Label for="pipeline-module">Module *</Label>
				<Select.Root type="single" bind:value={moduleId}>
					<Select.Trigger id="pipeline-module" class="w-full">
						<span>
							{#if selectedModule}
								{selectedModule.name}
							{:else}
								Select a module
							{/if}
						</span>
					</Select.Trigger>
					<Select.Content>
						{#each modules as module}
							<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<!-- Stage Field -->
			{#if selectedModule && stageFields.length > 0}
				<div class="space-y-2">
					<Label for="stage-field">Stage Field (optional)</Label>
					<Select.Root type="single" bind:value={stageFieldApiName}>
						<Select.Trigger id="stage-field" class="w-full">
							<span>
								{#if stageFieldApiName}
									{stageFields.find((f) => f.api_name === stageFieldApiName)?.label ||
										stageFieldApiName}
								{:else}
									Select field to store stage
								{/if}
							</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="">None</Select.Item>
							{#each stageFields as field}
								<Select.Item value={field.api_name}>{field.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
					<p class="text-xs text-muted-foreground">
						Link a select field from your module to automatically track stages
					</p>
				</div>
			{/if}

			<!-- Active Status -->
			<div class="flex items-center space-x-2 self-end">
				<Switch id="pipeline-active" bind:checked={isActive} />
				<Label for="pipeline-active">Active</Label>
			</div>
		</div>
	</div>

	<!-- Stages -->
	<div class="space-y-4">
		<div class="flex items-center justify-between">
			<h3 class="text-lg font-semibold">Stages</h3>
			<Button onclick={addStage} size="sm">
				<Plus class="mr-2 h-4 w-4" />
				Add Stage
			</Button>
		</div>

		{#if stages.length === 0}
			<div class="rounded-lg border border-dashed bg-muted/50 p-8 text-center">
				<p class="text-muted-foreground">No stages added yet. Click "Add Stage" to create one.</p>
			</div>
		{:else}
			<StageList {stages} onEdit={editStage} onDelete={deleteStage} onReorder={reorderStages} />
		{/if}
	</div>

	<!-- Actions -->
	<div class="flex justify-end gap-2 border-t pt-4">
		{#if onCancel}
			<Button variant="outline" onclick={onCancel} disabled={saving}>Cancel</Button>
		{/if}
		<Button onclick={handleSubmit} disabled={saving}>
			{#if saving}
				<Spinner class="mr-2 h-4 w-4" />
			{/if}
			{pipeline?.id ? 'Update Pipeline' : 'Create Pipeline'}
		</Button>
	</div>
</div>

<!-- Stage Editor Dialog -->
{#if showStageEditor}
	<StageEditor
		stage={editingStage}
		open={showStageEditor}
		onSave={saveStage}
		onClose={() => {
			showStageEditor = false;
			editingStage = null;
		}}
	/>
{/if}
