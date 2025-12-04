<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { X } from 'lucide-svelte';
	import type { FilterConfig, FilterOperator } from '../types';

	interface Props {
		field: string;
		initialValue?: FilterConfig;
		onApply: (filter: FilterConfig | null) => void;
		onClose: () => void;
	}

	let { field, initialValue, onApply, onClose }: Props = $props();

	const operators: { value: FilterOperator; label: string }[] = [
		{ value: 'contains', label: 'Contains' },
		{ value: 'equals', label: 'Equals' },
		{ value: 'not_equals', label: 'Not equals' },
		{ value: 'starts_with', label: 'Starts with' },
		{ value: 'ends_with', label: 'Ends with' },
		{ value: 'is_empty', label: 'Is empty' },
		{ value: 'is_not_empty', label: 'Is not empty' }
	];

	let selectedOperator = $state<FilterOperator>(initialValue?.operator || 'contains');
	let filterValue = $state(initialValue?.value || '');

	// Check if operator requires value input
	const requiresValue = $derived(
		!['is_empty', 'is_not_empty', 'is_null', 'is_not_null'].includes(selectedOperator)
	);

	// Get the label for the currently selected operator
	const selectedLabel = $derived(
		operators.find((o) => o.value === selectedOperator)?.label || 'Contains'
	);

	function handleApply() {
		if (requiresValue && !filterValue) {
			return; // Don't apply empty filter
		}

		onApply({
			field,
			operator: selectedOperator,
			value: requiresValue ? filterValue : null
		});
		onClose();
	}

	function handleClear() {
		onApply(null);
		onClose();
	}

	function handleOperatorChange(value: string | undefined) {
		if (value) {
			selectedOperator = value as FilterOperator;
		}
	}
</script>

<div class="space-y-3 p-3">
	<div class="space-y-2">
		<label class="text-xs font-medium">Operator</label>
		<Select.Root type="single" value={selectedOperator} onValueChange={handleOperatorChange}>
			<Select.Trigger class="w-full">
				<span>{selectedLabel}</span>
			</Select.Trigger>
			<Select.Content>
				{#each operators as operator}
					<Select.Item value={operator.value}>{operator.label}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	{#if requiresValue}
		<div class="space-y-2">
			<label class="text-xs font-medium">Value</label>
			<Input
				type="text"
				bind:value={filterValue}
				placeholder="Enter value..."
				onkeydown={(e) => e.key === 'Enter' && handleApply()}
			/>
		</div>
	{/if}

	<div class="flex gap-2">
		<Button size="sm" onclick={handleApply} class="flex-1">Apply</Button>
		<Button size="sm" variant="outline" onclick={handleClear}>
			<X class="h-3 w-3" />
		</Button>
	</div>
</div>
