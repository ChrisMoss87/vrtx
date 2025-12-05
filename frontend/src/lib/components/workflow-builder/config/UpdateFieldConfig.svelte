<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { ActionType } from '$lib/api/workflows';
	import VariableInserter from '../VariableInserter.svelte';

	interface Props {
		config: Record<string, unknown>;
		actionType: ActionType;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, actionType, moduleFields = [], onConfigChange }: Props = $props();

	const isDelete = actionType === 'delete_record';

	// Local state
	let targetField = $state<string>((config.field as string) || '');
	let valueType = $state<string>((config.value_type as string) || 'static');
	let value = $state<string>((config.value as string) || '');

	function emitChange() {
		onConfigChange?.({
			field: targetField,
			value_type: valueType,
			value
		});
	}

	// Get the selected field info
	const selectedField = $derived(moduleFields.find((f) => f.api_name === targetField));
</script>

<div class="space-y-4">
	{#if isDelete}
		<h4 class="font-medium">Delete Record Configuration</h4>
		<div class="flex items-start gap-2 rounded-lg bg-destructive/10 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-destructive" />
			<div>
				<p class="text-sm font-medium text-destructive">Warning: Destructive Action</p>
				<p class="text-xs text-muted-foreground">
					This action will permanently delete the triggering record. This cannot be undone.
				</p>
			</div>
		</div>
	{:else}
		<h4 class="font-medium">Update Field Configuration</h4>

		<!-- Field Selection -->
		<div class="space-y-2">
			<Label>Field to Update</Label>
			<Select.Root
				type="single"
				value={targetField}
				onValueChange={(v) => {
					if (v) {
						targetField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{selectedField?.label || 'Select field'}
				</Select.Trigger>
				<Select.Content>
					{#each moduleFields as field}
						<Select.Item value={field.api_name}>
							<div class="flex flex-col">
								<span>{field.label}</span>
								<span class="text-xs text-muted-foreground">{field.type}</span>
							</div>
						</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>

		<!-- Value Type -->
		<div class="space-y-2">
			<Label>Value Type</Label>
			<Select.Root
				type="single"
				value={valueType}
				onValueChange={(v) => {
					if (v) {
						valueType = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{valueType === 'static'
						? 'Static Value'
						: valueType === 'field'
							? 'Copy from Field'
							: valueType === 'formula'
								? 'Formula'
								: valueType === 'current_user'
									? 'Current User'
									: valueType === 'current_date'
										? 'Current Date/Time'
										: valueType === 'increment'
											? 'Increment'
											: valueType === 'decrement'
												? 'Decrement'
												: 'Select type'}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="static">Static Value</Select.Item>
					<Select.Item value="field">Copy from Another Field</Select.Item>
					<Select.Item value="formula">Formula</Select.Item>
					<Select.Item value="current_user">Current User</Select.Item>
					<Select.Item value="current_date">Current Date/Time</Select.Item>
					{#if selectedField?.type === 'number' || selectedField?.type === 'currency' || selectedField?.type === 'percent'}
						<Select.Item value="increment">Increment by</Select.Item>
						<Select.Item value="decrement">Decrement by</Select.Item>
					{/if}
				</Select.Content>
			</Select.Root>
		</div>

		<!-- Value Input -->
		{#if valueType === 'static' || valueType === 'formula' || valueType === 'increment' || valueType === 'decrement'}
			<div class="space-y-2">
				<div class="flex items-center justify-between">
					<Label>
						{#if valueType === 'increment'}
							Increment Amount
						{:else if valueType === 'decrement'}
							Decrement Amount
						{:else}
							Value
						{/if}
					</Label>
					{#if valueType === 'static' || valueType === 'formula'}
						<VariableInserter
							fields={moduleFields}
							onInsert={(v) => {
								value = `${value}{{${v}}}`;
								emitChange();
							}}
						/>
					{/if}
				</div>
				<Input
					type={valueType === 'increment' || valueType === 'decrement' ? 'number' : 'text'}
					value={value}
					oninput={(e) => {
						value = e.currentTarget.value;
						emitChange();
					}}
					placeholder={valueType === 'formula'
						? 'e.g., {{amount}} * 1.1'
						: valueType === 'increment' || valueType === 'decrement'
							? 'e.g., 1'
							: 'Enter value'}
				/>
				{#if valueType === 'formula'}
					<p class="text-xs text-muted-foreground">
						Use field variables and basic math operations
					</p>
				{/if}
			</div>
		{/if}

		{#if valueType === 'field'}
			<div class="space-y-2">
				<Label>Source Field</Label>
				<Select.Root
					type="single"
					value={value}
					onValueChange={(v) => {
						if (v) {
							value = v;
							emitChange();
						}
					}}
				>
					<Select.Trigger>
						{moduleFields.find((f) => f.api_name === value)?.label || 'Select source field'}
					</Select.Trigger>
					<Select.Content>
						{#each moduleFields.filter((f) => f.api_name !== targetField) as field}
							<Select.Item value={field.api_name}>{field.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>
		{/if}

		{#if valueType === 'current_user' || valueType === 'current_date'}
			<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
				<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
				<p class="text-xs text-muted-foreground">
					{#if valueType === 'current_user'}
						The field will be set to the user who triggered the workflow (or system user for scheduled workflows)
					{:else}
						The field will be set to the current date/time when the workflow runs
					{/if}
				</p>
			</div>
		{/if}
	{/if}
</div>
