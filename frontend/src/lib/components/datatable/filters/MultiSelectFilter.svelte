<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Label } from '$lib/components/ui/label';
	import * as Popover from '$lib/components/ui/popover';
	import { Badge } from '$lib/components/ui/badge';
	import { Check, X, ChevronDown } from 'lucide-svelte';
	import type { ColumnDef } from '../types';

	interface Props {
		column: ColumnDef;
		options: { label: string; value: any }[];
		onFilter: (values: any[]) => void;
		onClear: () => void;
	}

	let { column, options, onFilter, onClear }: Props = $props();

	let selectedValues = $state<any[]>([]);
	let open = $state(false);

	// Handle checkbox change
	function toggleValue(value: any) {
		if (selectedValues.includes(value)) {
			selectedValues = selectedValues.filter((v) => v !== value);
		} else {
			selectedValues = [...selectedValues, value];
		}
	}

	// Apply filter
	function apply() {
		if (selectedValues.length > 0) {
			onFilter(selectedValues);
		}
		open = false;
	}

	// Clear filter
	function clear() {
		selectedValues = [];
		onClear();
		open = false;
	}

	// Select all
	function selectAll() {
		selectedValues = options.map((opt) => opt.value);
	}

	// Deselect all
	function deselectAll() {
		selectedValues = [];
	}

	// Get selected labels for display
	const selectedLabels = $derived(
		options.filter((opt) => selectedValues.includes(opt.value)).map((opt) => opt.label)
	);
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		{#snippet child({ props })}
			<Button {...props} variant="outline" size="sm" class="h-8 border-dashed">
				<ChevronDown class="mr-2 h-4 w-4" />
				{column.header || column.id}
				{#if selectedValues.length > 0}
					<div class="ml-2 flex gap-1">
						{#each selectedLabels.slice(0, 2) as label}
							<Badge variant="secondary" class="rounded-sm px-1 font-normal">
								{label}
							</Badge>
						{/each}
						{#if selectedLabels.length > 2}
							<Badge variant="secondary" class="rounded-sm px-1 font-normal">
								+{selectedLabels.length - 2}
							</Badge>
						{/if}
					</div>
				{/if}
			</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-64 p-0" align="start">
		<div class="border-b p-2">
			<div class="flex items-center justify-between">
				<Label class="text-sm font-medium">{column.header || column.id}</Label>
				{#if selectedValues.length > 0}
					<Button variant="ghost" size="sm" onclick={clear} class="h-6 px-2 text-xs">Clear</Button>
				{/if}
			</div>
		</div>

		<div class="max-h-64 overflow-y-auto p-2">
			<!-- Select/Deselect all -->
			<div class="mb-2 flex gap-2">
				<Button variant="ghost" size="sm" onclick={selectAll} class="h-7 flex-1 text-xs">
					Select all
				</Button>
				<Button variant="ghost" size="sm" onclick={deselectAll} class="h-7 flex-1 text-xs">
					Deselect all
				</Button>
			</div>

			<!-- Options list -->
			<div class="space-y-1">
				{#each options as option}
					{@const isChecked = selectedValues.includes(option.value)}
					<div class="flex items-center space-x-2 rounded-sm px-2 py-1.5 hover:bg-accent">
						<Checkbox
							id={`filter-${column.id}-${option.value}`}
							checked={isChecked}
							onCheckedChange={() => toggleValue(option.value)}
						/>
						<Label
							for={`filter-${column.id}-${option.value}`}
							class="flex-1 cursor-pointer text-sm font-normal"
						>
							{option.label}
						</Label>
						{#if isChecked}
							<Check class="h-4 w-4 text-primary" />
						{/if}
					</div>
				{/each}
			</div>
		</div>

		<!-- Footer actions -->
		<div class="flex gap-2 border-t p-2">
			<Button variant="ghost" size="sm" onclick={() => (open = false)} class="flex-1">
				Cancel
			</Button>
			<Button size="sm" onclick={apply} class="flex-1">
				Apply {selectedValues.length > 0 ? `(${selectedValues.length})` : ''}
			</Button>
		</div>
	</Popover.Content>
</Popover.Root>
