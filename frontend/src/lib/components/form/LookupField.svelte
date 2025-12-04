<script lang="ts">
	import { onMount } from 'svelte';
	import axios from 'axios';
	import FieldBase from './FieldBase.svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Command from '$lib/components/ui/command';
	import * as Popover from '$lib/components/ui/popover';
	import { Check, ChevronsUpDown, X, Loader2, Plus } from 'lucide-svelte';
	import { cn } from '$lib/utils';

	interface RelatedRecord {
		id: number;
		label: string;
		data: Record<string, any>;
	}

	interface Props {
		label?: string;
		name: string;
		value?: number | number[];
		relationshipId: number;
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		placeholder?: string;
		width?: 25 | 50 | 75 | 100;
		class?: string;
		multiple?: boolean;
		allowCreate?: boolean;
		onchange?: (value: number | number[] | undefined) => void;
		onCreate?: () => void;
	}

	let {
		label,
		name,
		value = $bindable(),
		relationshipId,
		description,
		error,
		required = false,
		disabled = false,
		placeholder = 'Select record...',
		width = 100,
		class: className,
		multiple = false,
		allowCreate = false,
		onchange,
		onCreate
	}: Props = $props();

	let open = $state(false);
	let searchValue = $state('');
	let records = $state<RelatedRecord[]>([]);
	let loading = $state(false);
	let selectedRecords = $state<RelatedRecord[]>([]);
	let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

	// Get selected record(s) label for display
	const selectedLabel = $derived(() => {
		if (multiple && Array.isArray(value)) {
			if (value.length === 0) return placeholder;
			const count = value.length;
			return `${count} record${count === 1 ? '' : 's'} selected`;
		} else if (value && !Array.isArray(value)) {
			const record = selectedRecords.find((r) => r.id === value);
			return record?.label || placeholder;
		}
		return placeholder;
	});

	// Fetch available records from API
	async function fetchRecords(search: string = '') {
		loading = true;
		try {
			const response = await axios.get(`/api/relationships/${relationshipId}/available`, {
				params: {
					search,
					limit: 50
				}
			});

			records = response.data.data || [];
		} catch (err) {
			console.error('Failed to fetch records:', err);
			records = [];
		} finally {
			loading = false;
		}
	}

	// Debounced search
	function handleSearchInput(newValue: string) {
		searchValue = newValue;

		if (searchDebounceTimer) {
			clearTimeout(searchDebounceTimer);
		}

		searchDebounceTimer = setTimeout(() => {
			fetchRecords(searchValue);
		}, 300);
	}

	// Handle record selection
	function selectRecord(record: RelatedRecord) {
		if (multiple) {
			const currentValue = Array.isArray(value) ? value : [];
			const index = currentValue.indexOf(record.id);

			if (index > -1) {
				// Remove from selection
				value = currentValue.filter((id) => id !== record.id);
				selectedRecords = selectedRecords.filter((r) => r.id !== record.id);
			} else {
				// Add to selection
				value = [...currentValue, record.id];
				selectedRecords = [...selectedRecords, record];
			}
		} else {
			// Single selection
			value = record.id;
			selectedRecords = [record];
			open = false;
		}

		onchange?.(value);
	}

	// Clear selection
	function clearSelection() {
		if (multiple) {
			value = [];
			selectedRecords = [];
		} else {
			value = undefined;
			selectedRecords = [];
		}
		onchange?.(value);
	}

	// Check if record is selected
	function isSelected(recordId: number): boolean {
		if (multiple && Array.isArray(value)) {
			return value.includes(recordId);
		}
		return value === recordId;
	}

	// Load initial selected records
	onMount(async () => {
		await fetchRecords();

		// Load selected record details if value is provided
		if (value) {
			const ids = Array.isArray(value) ? value : [value];
			selectedRecords = records.filter((r) => ids.includes(r.id));
		}
	});

	// Handle popover open
	function handleOpenChange(newOpen: boolean) {
		open = newOpen;
		if (newOpen && records.length === 0) {
			fetchRecords();
		}
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<Popover.Root bind:open onOpenChange={handleOpenChange}>
			<Popover.Trigger>
				<Button
					{...props}
					variant="outline"
					role="combobox"
					aria-expanded={open}
					class={cn(
						'w-full justify-between',
						!value && 'text-muted-foreground',
						error && 'border-destructive'
					)}
					{disabled}
				>
					<span class="truncate">{selectedLabel()}</span>
					<div class="flex items-center gap-1">
						{#if value && !disabled}
							<button
								type="button"
								onclick={(e) => {
									e.stopPropagation();
									clearSelection();
								}}
								class="hover:text-foreground"
							>
								<X class="h-4 w-4" />
							</button>
						{/if}
						<ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
					</div>
				</Button>
			</Popover.Trigger>
			<Popover.Content class="w-[400px] p-0" align="start">
				<Command.Root shouldFilter={false}>
					<Command.Input
						placeholder="Search records..."
						value={searchValue}
						oninput={(e) => handleSearchInput((e.target as HTMLInputElement).value)}
					/>
					<Command.List>
						{#if loading}
							<Command.Empty>
								<div class="flex items-center justify-center py-6">
									<Loader2 class="h-4 w-4 animate-spin" />
									<span class="ml-2">Loading...</span>
								</div>
							</Command.Empty>
						{:else if records.length === 0}
							<Command.Empty>
								<div class="py-6 text-center text-sm">No records found.</div>
								{#if allowCreate && onCreate}
									<Button
										variant="ghost"
										size="sm"
										onclick={() => {
											onCreate?.();
											open = false;
										}}
										class="w-full"
									>
										<Plus class="mr-2 h-4 w-4" />
										Create new
									</Button>
								{/if}
							</Command.Empty>
						{:else}
							<Command.Group>
								{#each records as record (record.id)}
									<Command.Item value={record.id.toString()} onSelect={() => selectRecord(record)}>
										<Check
											class={cn(
												'mr-2 h-4 w-4',
												isSelected(record.id) ? 'opacity-100' : 'opacity-0'
											)}
										/>
										{record.label}
									</Command.Item>
								{/each}
							</Command.Group>
						{/if}
					</Command.List>
				</Command.Root>
			</Popover.Content>
		</Popover.Root>
	{/snippet}
</FieldBase>
