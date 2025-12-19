<script lang="ts">
	import { onMount } from 'svelte';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { getModules, getModuleById, type Module, type Field } from '$lib/api/modules';
	import type { WidgetConfig } from '$lib/api/dashboards';

	interface Props {
		config: WidgetConfig;
		onUpdate: (config: WidgetConfig) => void;
	}

	let { config, onUpdate }: Props = $props();

	let modules = $state<Module[]>([]);
	let fields = $state<Field[]>([]);
	let loading = $state(true);

	let selectedModuleId = $state<string>(config.module_id?.toString() || '');
	let selectedAggregation = $state<string>(config.aggregation || 'count');
	let selectedField = $state<string>(config.field || '');

	const aggregations = [
		{ value: 'count', label: 'Count' },
		{ value: 'sum', label: 'Sum' },
		{ value: 'avg', label: 'Average' },
		{ value: 'min', label: 'Minimum' },
		{ value: 'max', label: 'Maximum' }
	];

	onMount(async () => {
		try {
			modules = await getModules();
			if (selectedModuleId) {
				await loadFields(parseInt(selectedModuleId));
			}
		} catch (error) {
			console.error('Failed to load modules:', error);
		} finally {
			loading = false;
		}
	});

	async function loadFields(moduleId: number) {
		try {
			const module = await getModuleById(moduleId);
			fields = module.blocks?.flatMap((block) => block.fields || []) || [];
		} catch (error) {
			console.error('Failed to load fields:', error);
			fields = [];
		}
	}

	function handleModuleChange(value: string) {
		selectedModuleId = value;
		selectedField = '';
		if (value) {
			loadFields(parseInt(value));
		} else {
			fields = [];
		}
		emitUpdate();
	}

	function handleAggregationChange(value: string) {
		selectedAggregation = value;
		if (value === 'count') {
			selectedField = '';
		}
		emitUpdate();
	}

	function handleFieldChange(value: string) {
		selectedField = value;
		emitUpdate();
	}

	function emitUpdate() {
		onUpdate({
			...config,
			module_id: selectedModuleId ? parseInt(selectedModuleId) : undefined,
			aggregation: selectedAggregation,
			field: selectedField || undefined
		});
	}

	const numericFields = $derived(
		fields.filter((f) =>
			['integer', 'decimal', 'currency', 'percent', 'number'].includes(f.type)
		)
	);

	const needsField = $derived(
		['sum', 'avg', 'min', 'max'].includes(selectedAggregation)
	);
</script>

<div class="space-y-4">
	<div class="space-y-2">
		<Label>Module</Label>
		<Select.Root type="single" value={selectedModuleId} onValueChange={handleModuleChange}>
			<Select.Trigger class="w-full">
				{selectedModuleId
					? modules.find((m) => m.id.toString() === selectedModuleId)?.name || 'Select module'
					: 'Select a module'}
			</Select.Trigger>
			<Select.Content>
				{#each modules as module}
					<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
		<p class="text-xs text-muted-foreground">Choose the module to aggregate data from</p>
	</div>

	<div class="space-y-2">
		<Label>Aggregation</Label>
		<Select.Root
			type="single"
			value={selectedAggregation}
			onValueChange={handleAggregationChange}
		>
			<Select.Trigger class="w-full">
				{aggregations.find((a) => a.value === selectedAggregation)?.label || 'Select aggregation'}
			</Select.Trigger>
			<Select.Content>
				{#each aggregations as agg}
					<Select.Item value={agg.value}>{agg.label}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
		<p class="text-xs text-muted-foreground">How to calculate the KPI value</p>
	</div>

	{#if needsField}
		<div class="space-y-2">
			<Label>Field</Label>
			<Select.Root
				type="single"
				value={selectedField}
				onValueChange={handleFieldChange}
				disabled={!selectedModuleId || numericFields.length === 0}
			>
				<Select.Trigger class="w-full">
					{selectedField
						? numericFields.find((f) => f.api_name === selectedField)?.label || 'Select field'
						: numericFields.length === 0
							? 'No numeric fields available'
							: 'Select a field'}
				</Select.Trigger>
				<Select.Content>
					{#each numericFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			<p class="text-xs text-muted-foreground">
				Select a numeric field to aggregate (required for sum, avg, min, max)
			</p>
		</div>
	{/if}
</div>
