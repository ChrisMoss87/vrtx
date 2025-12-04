<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { X } from 'lucide-svelte';
	import type { FilterConfig, FilterOption } from '../types';
	import { cn } from '$lib/utils';

	interface Props {
		field: string;
		options: FilterOption[];
		initialValue?: FilterConfig;
		onApply: (filter: FilterConfig | null) => void;
		onClose: () => void;
	}

	let { field, options, initialValue, onApply, onClose }: Props = $props();

	let selectedValues = $state<any[]>(
		initialValue?.operator === 'in' && Array.isArray(initialValue.value) ? initialValue.value : []
	);
	let searchValue = $state('');

	const filteredOptions = $derived(
		options.filter((option) => option.label.toLowerCase().includes(searchValue.toLowerCase()))
	);

	function toggleOption(value: any) {
		const index = selectedValues.indexOf(value);
		if (index > -1) {
			selectedValues = selectedValues.filter((v) => v !== value);
		} else {
			selectedValues = [...selectedValues, value];
		}
	}

	function selectAll() {
		selectedValues = filteredOptions.map((o) => o.value);
	}

	function clearAll() {
		selectedValues = [];
	}

	function handleApply() {
		if (selectedValues.length === 0) {
			onApply(null);
		} else {
			onApply({
				field,
				operator: 'in',
				value: selectedValues
			});
		}
		onClose();
	}

	function handleClear() {
		onApply(null);
		onClose();
	}
</script>

<div class="w-[280px] space-y-3 p-3">
	<div class="space-y-2">
		<Input type="search" bind:value={searchValue} placeholder="Search options..." class="h-8" />
	</div>

	<div class="flex gap-2">
		<Button variant="ghost" size="sm" onclick={selectAll} class="h-7 flex-1">Select all</Button>
		<Button variant="ghost" size="sm" onclick={clearAll} class="h-7 flex-1">Clear all</Button>
	</div>

	<div class="max-h-[200px] space-y-1 overflow-y-auto">
		{#if filteredOptions.length === 0}
			<p class="py-4 text-center text-sm text-muted-foreground">No options found</p>
		{:else}
			{#each filteredOptions as option (option.value)}
				<label
					class="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 hover:bg-accent"
				>
					<Checkbox
						checked={selectedValues.includes(option.value)}
						onCheckedChange={() => toggleOption(option.value)}
					/>
					<span class="flex-1 text-sm">{option.label}</span>
					{#if option.count !== undefined}
						<span class="text-xs text-muted-foreground">({option.count})</span>
					{/if}
				</label>
			{/each}
		{/if}
	</div>

	{#if selectedValues.length > 0}
		<div class="text-xs text-muted-foreground">
			{selectedValues.length} selected
		</div>
	{/if}

	<div class="flex gap-2">
		<Button size="sm" onclick={handleApply} class="flex-1">Apply</Button>
		<Button size="sm" variant="outline" onclick={handleClear}>
			<X class="h-3 w-3" />
		</Button>
	</div>
</div>
