<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { X, Plus, Info } from 'lucide-svelte';
	import type {
		TriggerType,
		TriggerTiming,
		TriggerConfig,
		FieldChangeType
	} from '$lib/api/workflows';
	import type { Field } from '$lib/api/modules';

	interface Props {
		triggerType: TriggerType;
		triggerConfig: TriggerConfig;
		triggerTiming: TriggerTiming;
		watchedFields: string[];
		moduleFields?: Field[];
		onTriggerTypeChange?: (type: TriggerType) => void;
		onTriggerConfigChange?: (config: TriggerConfig) => void;
		onTriggerTimingChange?: (timing: TriggerTiming) => void;
		onWatchedFieldsChange?: (fields: string[]) => void;
	}

	let {
		triggerType = $bindable(),
		triggerConfig = $bindable(),
		triggerTiming = $bindable(),
		watchedFields = $bindable(),
		moduleFields = [],
		onTriggerTypeChange,
		onTriggerConfigChange,
		onTriggerTimingChange,
		onWatchedFieldsChange
	}: Props = $props();

	// Trigger type options with descriptions
	const triggerTypes: { value: TriggerType; label: string; description: string; category: string }[] = [
		{
			value: 'record_created',
			label: 'When a record is created',
			description: 'Triggers when a new record is created in the module',
			category: 'Record Events'
		},
		{
			value: 'record_updated',
			label: 'When a record is updated',
			description: 'Triggers when an existing record is modified',
			category: 'Record Events'
		},
		{
			value: 'record_saved',
			label: 'When a record is saved (create or update)',
			description: 'Triggers on both record creation and updates',
			category: 'Record Events'
		},
		{
			value: 'record_deleted',
			label: 'When a record is deleted',
			description: 'Triggers when a record is removed',
			category: 'Record Events'
		},
		{
			value: 'field_changed',
			label: 'When a field value changes',
			description: 'Triggers when specific field(s) change value',
			category: 'Field Events'
		},
		{
			value: 'related_created',
			label: 'When a related record is created',
			description: 'Triggers when a related record is created (e.g., new task on a deal)',
			category: 'Related Events'
		},
		{
			value: 'related_updated',
			label: 'When a related record is updated',
			description: 'Triggers when a related record is modified',
			category: 'Related Events'
		},
		{
			value: 'record_converted',
			label: 'When a record is converted',
			description: 'Triggers when a record is converted (e.g., Lead to Contact)',
			category: 'Record Events'
		},
		{
			value: 'time_based',
			label: 'On a schedule',
			description: 'Triggers at scheduled times (cron or relative to field date)',
			category: 'Scheduled'
		},
		{
			value: 'webhook',
			label: 'When a webhook is received',
			description: 'Triggers when an external system sends data via webhook',
			category: 'External'
		},
		{
			value: 'manual',
			label: 'Manual trigger only',
			description: 'Only runs when manually triggered by a user',
			category: 'Manual'
		}
	];

	const timingOptions: { value: TriggerTiming; label: string }[] = [
		{ value: 'all', label: 'On create and update' },
		{ value: 'create_only', label: 'Only on create' },
		{ value: 'update_only', label: 'Only on update' }
	];

	const changeTypeOptions: { value: FieldChangeType; label: string; description: string }[] = [
		{ value: 'any', label: 'Any change', description: 'Triggers when the field value changes to anything' },
		{ value: 'from_value', label: 'Changes from specific value', description: 'Triggers when the field changes FROM a specific value' },
		{ value: 'to_value', label: 'Changes to specific value', description: 'Triggers when the field changes TO a specific value' },
		{ value: 'from_to', label: 'Changes from X to Y', description: 'Triggers when the field changes from one specific value to another' }
	];

	const relativeUnitOptions = [
		{ value: 'hours', label: 'Hours' },
		{ value: 'days', label: 'Days' },
		{ value: 'weeks', label: 'Weeks' },
		{ value: 'months', label: 'Months' }
	];

	// Group triggers by category for the dropdown
	const groupedTriggers = $derived(() => {
		const groups: Record<string, typeof triggerTypes> = {};
		for (const trigger of triggerTypes) {
			if (!groups[trigger.category]) {
				groups[trigger.category] = [];
			}
			groups[trigger.category].push(trigger);
		}
		return groups;
	});

	// Check if timing is applicable for current trigger
	const showTiming = $derived(
		['record_saved', 'field_changed'].includes(triggerType)
	);

	// Check if field selection is needed
	const showFieldSelection = $derived(
		triggerType === 'field_changed'
	);

	// Check if time config is needed
	const showTimeConfig = $derived(
		triggerType === 'time_based'
	);

	// Get date fields for relative scheduling
	const dateFields = $derived(
		moduleFields.filter((f) => ['date', 'datetime'].includes(f.type))
	);

	function handleTriggerTypeChange(value: string) {
		const newType = value as TriggerType;
		triggerType = newType;
		onTriggerTypeChange?.(newType);

		// Reset config when trigger type changes
		triggerConfig = {};
		onTriggerConfigChange?.({});
		watchedFields = [];
		onWatchedFieldsChange?.([]);
	}

	function handleTimingChange(value: string) {
		const newTiming = value as TriggerTiming;
		triggerTiming = newTiming;
		onTriggerTimingChange?.(newTiming);
	}

	function addWatchedField(fieldApiName: string) {
		if (!watchedFields.includes(fieldApiName)) {
			watchedFields = [...watchedFields, fieldApiName];
			onWatchedFieldsChange?.(watchedFields);
		}
	}

	function removeWatchedField(fieldApiName: string) {
		watchedFields = watchedFields.filter((f) => f !== fieldApiName);
		onWatchedFieldsChange?.(watchedFields);
	}

	function updateConfig<K extends keyof TriggerConfig>(key: K, value: TriggerConfig[K]) {
		triggerConfig = { ...triggerConfig, [key]: value };
		onTriggerConfigChange?.(triggerConfig);
	}
</script>

<div class="space-y-6">
	<!-- Trigger Type Selection -->
	<div class="space-y-2">
		<Label>When should this workflow run?</Label>
		<Select.Root type="single" value={triggerType} onValueChange={handleTriggerTypeChange}>
			<Select.Trigger class="w-full">
				{triggerTypes.find((t) => t.value === triggerType)?.label || 'Select a trigger'}
			</Select.Trigger>
			<Select.Content>
				{#each Object.entries(groupedTriggers()) as [category, triggers]}
					<Select.Group>
						<Select.GroupHeading>{category}</Select.GroupHeading>
						{#each triggers as trigger}
							<Select.Item value={trigger.value}>
								<div class="flex flex-col">
									<span>{trigger.label}</span>
									<span class="text-xs text-muted-foreground">{trigger.description}</span>
								</div>
							</Select.Item>
						{/each}
					</Select.Group>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Trigger Timing (for applicable triggers) -->
	{#if showTiming}
		<div class="space-y-2">
			<Label>Trigger timing</Label>
			<Select.Root type="single" value={triggerTiming} onValueChange={handleTimingChange}>
				<Select.Trigger class="w-full">
					{timingOptions.find((t) => t.value === triggerTiming)?.label || 'Select timing'}
				</Select.Trigger>
				<Select.Content>
					{#each timingOptions as option}
						<Select.Item value={option.value}>{option.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

	<!-- Field Changed Configuration -->
	{#if showFieldSelection}
		<Card.Root>
			<Card.Header class="pb-3">
				<Card.Title class="text-base">Field Change Detection</Card.Title>
				<Card.Description>
					Select which fields to watch and how to detect changes
				</Card.Description>
			</Card.Header>
			<Card.Content class="space-y-4">
				<!-- Watched Fields -->
				<div class="space-y-2">
					<Label>Fields to watch</Label>
					<div class="flex flex-wrap gap-2">
						{#each watchedFields as fieldApiName}
							{@const field = moduleFields.find((f) => f.api_name === fieldApiName)}
							<Badge variant="secondary" class="gap-1">
								{field?.label || fieldApiName}
								<button
									type="button"
									onclick={() => removeWatchedField(fieldApiName)}
									class="ml-1 hover:text-destructive"
								>
									<X class="h-3 w-3" />
								</button>
							</Badge>
						{/each}
					</div>
					<Select.Root
						type="single"
						value=""
						onValueChange={(value) => value && addWatchedField(value)}
					>
						<Select.Trigger class="w-full">
							<Plus class="mr-2 h-4 w-4" />
							Add field to watch
						</Select.Trigger>
						<Select.Content>
							{#each moduleFields.filter((f) => !watchedFields.includes(f.api_name)) as field}
								<Select.Item value={field.api_name}>{field.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<!-- Change Type -->
				<div class="space-y-2">
					<Label>Change type</Label>
					<Select.Root
						type="single"
						value={triggerConfig.change_type || 'any'}
						onValueChange={(value) => updateConfig('change_type', value as FieldChangeType)}
					>
						<Select.Trigger class="w-full">
							{changeTypeOptions.find((c) => c.value === (triggerConfig.change_type || 'any'))?.label}
						</Select.Trigger>
						<Select.Content>
							{#each changeTypeOptions as option}
								<Select.Item value={option.value}>
									<div class="flex flex-col">
										<span>{option.label}</span>
										<span class="text-xs text-muted-foreground">{option.description}</span>
									</div>
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<!-- From Value -->
				{#if triggerConfig.change_type === 'from_value' || triggerConfig.change_type === 'from_to'}
					<div class="space-y-2">
						<Label>From value</Label>
						<Input
							value={String(triggerConfig.from_value || '')}
							oninput={(e) => updateConfig('from_value', e.currentTarget.value)}
							placeholder="Enter the value the field must change FROM"
						/>
					</div>
				{/if}

				<!-- To Value -->
				{#if triggerConfig.change_type === 'to_value' || triggerConfig.change_type === 'from_to'}
					<div class="space-y-2">
						<Label>To value</Label>
						<Input
							value={String(triggerConfig.to_value || '')}
							oninput={(e) => updateConfig('to_value', e.currentTarget.value)}
							placeholder="Enter the value the field must change TO"
						/>
					</div>
				{/if}
			</Card.Content>
		</Card.Root>
	{/if}

	<!-- Time-based Configuration -->
	{#if showTimeConfig}
		<Card.Root>
			<Card.Header class="pb-3">
				<Card.Title class="text-base">Schedule Configuration</Card.Title>
				<Card.Description>
					Configure when this workflow should run
				</Card.Description>
			</Card.Header>
			<Card.Content class="space-y-4">
				<!-- Schedule Type -->
				<div class="space-y-2">
					<Label>Schedule type</Label>
					<Select.Root
						type="single"
						value={triggerConfig.schedule_type || 'cron'}
						onValueChange={(value) => updateConfig('schedule_type', value as 'cron' | 'relative' | 'specific_date')}
					>
						<Select.Trigger class="w-full">
							{triggerConfig.schedule_type === 'relative'
								? 'Relative to a date field'
								: triggerConfig.schedule_type === 'specific_date'
									? 'On a specific date'
									: 'Cron schedule'}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="cron">Cron schedule (recurring)</Select.Item>
							<Select.Item value="relative">Relative to a date field</Select.Item>
							<Select.Item value="specific_date">On a specific date</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>

				<!-- Relative Schedule Config -->
				{#if triggerConfig.schedule_type === 'relative'}
					<div class="grid gap-4 sm:grid-cols-2">
						<div class="space-y-2">
							<Label>Date field</Label>
							<Select.Root
								type="single"
								value={triggerConfig.relative_field || ''}
								onValueChange={(value) => updateConfig('relative_field', value)}
							>
								<Select.Trigger>
									{dateFields.find((f) => f.api_name === triggerConfig.relative_field)?.label || 'Select field'}
								</Select.Trigger>
								<Select.Content>
									{#each dateFields as field}
										<Select.Item value={field.api_name}>{field.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>

						<div class="space-y-2">
							<Label>Offset</Label>
							<div class="flex gap-2">
								<Input
									type="number"
									value={String(triggerConfig.relative_offset || 0)}
									oninput={(e) => updateConfig('relative_offset', parseInt(e.currentTarget.value) || 0)}
									class="w-24"
								/>
								<Select.Root
									type="single"
									value={triggerConfig.relative_unit || 'days'}
									onValueChange={(value) => updateConfig('relative_unit', value as 'hours' | 'days' | 'weeks' | 'months')}
								>
									<Select.Trigger class="flex-1">
										{relativeUnitOptions.find((u) => u.value === (triggerConfig.relative_unit || 'days'))?.label}
									</Select.Trigger>
									<Select.Content>
										{#each relativeUnitOptions as unit}
											<Select.Item value={unit.value}>{unit.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>
							<p class="text-xs text-muted-foreground">
								Use negative numbers for before the date, positive for after
							</p>
						</div>
					</div>
				{/if}
			</Card.Content>
		</Card.Root>
	{/if}

	<!-- Info about the selected trigger -->
	{#if triggerTypes.find((t) => t.value === triggerType)}
		{@const selectedTrigger = triggerTypes.find((t) => t.value === triggerType)}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-sm text-muted-foreground">{selectedTrigger?.description}</p>
		</div>
	{/if}
</div>
