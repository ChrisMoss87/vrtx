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
		{ value: 'equals', label: 'Equals' },
		{ value: 'not_equals', label: 'Not equals' },
		{ value: 'greater_than', label: 'Greater than' },
		{ value: 'greater_than_or_equal', label: 'Greater than or equal' },
		{ value: 'less_than', label: 'Less than' },
		{ value: 'less_than_or_equal', label: 'Less than or equal' },
		{ value: 'between', label: 'Between' },
		{ value: 'is_empty', label: 'Is empty' },
		{ value: 'is_not_empty', label: 'Is not empty' }
	];

	let selectedOperator = $state<FilterOperator>(initialValue?.operator || 'equals');
	let filterValue = $state(
		initialValue?.operator === 'between' && Array.isArray(initialValue?.value)
			? initialValue.value[0]
			: initialValue?.value || ''
	);
	let filterValueTo = $state(
		initialValue?.operator === 'between' && Array.isArray(initialValue?.value)
			? initialValue.value[1]
			: ''
	);

	const requiresValue = $derived(!['is_empty', 'is_not_empty'].includes(selectedOperator));
	const isBetween = $derived(selectedOperator === 'between');

	// Get the label for the currently selected operator
	const selectedLabel = $derived(
		operators.find((o) => o.value === selectedOperator)?.label || 'Equals'
	);

	function handleApply() {
		if (requiresValue) {
			if (isBetween) {
				if (!filterValue || !filterValueTo) return;
				onApply({
					field,
					operator: selectedOperator,
					value: [parseFloat(filterValue), parseFloat(filterValueTo)]
				});
			} else {
				if (!filterValue) return;
				onApply({
					field,
					operator: selectedOperator,
					value: parseFloat(filterValue)
				});
			}
		} else {
			onApply({
				field,
				operator: selectedOperator,
				value: null
			});
		}
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
			<label class="text-xs font-medium">
				{isBetween ? 'From' : 'Value'}
			</label>
			<Input
				type="number"
				bind:value={filterValue}
				placeholder="Enter number..."
				onkeydown={(e) => e.key === 'Enter' && handleApply()}
			/>
		</div>

		{#if isBetween}
			<div class="space-y-2">
				<label class="text-xs font-medium">To</label>
				<Input
					type="number"
					bind:value={filterValueTo}
					placeholder="Enter number..."
					onkeydown={(e) => e.key === 'Enter' && handleApply()}
				/>
			</div>
		{/if}
	{/if}

	<div class="flex gap-2">
		<Button size="sm" onclick={handleApply} class="flex-1">Apply</Button>
		<Button size="sm" variant="outline" onclick={handleClear}>
			<X class="h-3 w-3" />
		</Button>
	</div>
</div>
