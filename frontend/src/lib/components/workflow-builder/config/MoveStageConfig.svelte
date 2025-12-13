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
	let fieldApiName = $state<string | null>((config.field_api_name as string) || null);
	let targetValue = $state<string | null>((config.target_value as string) || null);
	let moveType = $state<string>((config.move_type as string) || 'specific');

	function emitChange() {
		onConfigChange?.({
			field_api_name: fieldApiName,
			target_value: targetValue,
			move_type: moveType
		});
	}

	// Get fields that can be used for stage movement (select, radio with options)
	const stageFields = $derived(
		moduleFields.filter(
			(f) => (f.type === 'select' || f.type === 'radio') && f.options && f.options.length > 0
		)
	);

	const selectedField = $derived(stageFields.find((f) => f.api_name === fieldApiName));
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
					? 'To Specific Value'
					: moveType === 'next'
						? 'To Next Option'
						: moveType === 'previous'
							? 'To Previous Option'
							: 'Select move type'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="specific">To Specific Value</Select.Item>
				<Select.Item value="next">To Next Option</Select.Item>
				<Select.Item value="previous">To Previous Option</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Field Selection -->
	<div class="space-y-2">
		<Label>Stage Field</Label>
		{#if stageFields.length === 0}
			<p class="text-sm text-muted-foreground">
				No select or radio fields available in this module.
			</p>
		{:else}
			<Select.Root
				type="single"
				value={fieldApiName || ''}
				onValueChange={(v) => {
					fieldApiName = v || null;
					targetValue = null; // Reset value when field changes
					emitChange();
				}}
			>
				<Select.Trigger>
					{selectedField?.label || 'Select field'}
				</Select.Trigger>
				<Select.Content>
					{#each stageFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		{/if}
	</div>

	{#if moveType === 'specific' && selectedField}
		<!-- Target Value Selection -->
		<div class="space-y-2">
			<Label>Target Value</Label>
			<Select.Root
				type="single"
				value={targetValue || ''}
				onValueChange={(v) => {
					targetValue = v || null;
					emitChange();
				}}
			>
				<Select.Trigger>
					{selectedField.options?.find((o) => o.value === targetValue)?.label || 'Select value'}
				</Select.Trigger>
				<Select.Content>
					{#each selectedField.options || [] as option}
						<Select.Item value={option.value}>{option.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

	<!-- Info based on move type -->
	{#if moveType === 'next'}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The record will be moved to the next option in the field's option list.
				If the record is already at the last option, no action will be taken.
			</p>
		</div>
	{:else if moveType === 'previous'}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The record will be moved to the previous option in the field's option list.
				If the record is already at the first option, no action will be taken.
			</p>
		</div>
	{:else}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				Select a field and target value to update the record.
				The field must be a select or radio type with defined options.
			</p>
		</div>
	{/if}
</div>
