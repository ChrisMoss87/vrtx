<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Popover from '$lib/components/ui/popover';
	import { Badge } from '$lib/components/ui/badge';
	import { Loader2, Search, X, ChevronDown } from 'lucide-svelte';
	import axios from 'axios';
	import { debounce } from '../utils';
	import type { ColumnDef } from '../types';

	interface LookupRecord {
		id: string | number;
		label: string;
	}

	interface Props {
		column: ColumnDef;
		lookupModule: string;
		lookupFieldLabel?: string;
		onFilter: (recordIds: any[]) => void;
		onClear: () => void;
	}

	let { column, lookupModule, lookupFieldLabel = 'name', onFilter, onClear }: Props = $props();

	let open = $state(false);
	let searchQuery = $state('');
	let loading = $state(false);
	let results = $state<LookupRecord[]>([]);
	let selectedRecords = $state<LookupRecord[]>([]);

	// Debounced search function
	const debouncedSearch = debounce(async (query: string) => {
		if (!query.trim()) {
			results = [];
			return;
		}

		loading = true;
		try {
			const response = await axios.get(`/api/modules/${lookupModule}/records`, {
				params: {
					search: query,
					per_page: 20,
					fields: ['id', lookupFieldLabel]
				}
			});

			// Map results to LookupRecord format
			results = response.data.data.map((record: any) => ({
				id: record.id,
				label: record[lookupFieldLabel] || `Record #${record.id}`
			}));
		} catch (error) {
			console.error('Lookup search error:', error);
			results = [];
		} finally {
			loading = false;
		}
	}, 300);

	// Handle search input change
	function handleSearchChange(value: string) {
		searchQuery = value;
		debouncedSearch(value);
	}

	// Add selected record
	function selectRecord(record: LookupRecord) {
		if (!selectedRecords.find((r) => r.id === record.id)) {
			selectedRecords = [...selectedRecords, record];
		}
		searchQuery = '';
		results = [];
	}

	// Remove selected record
	function removeRecord(recordId: string | number) {
		selectedRecords = selectedRecords.filter((r) => r.id !== recordId);
	}

	// Apply filter
	function apply() {
		if (selectedRecords.length > 0) {
			onFilter(selectedRecords.map((r) => r.id));
		}
		open = false;
	}

	// Clear filter
	function clear() {
		selectedRecords = [];
		searchQuery = '';
		results = [];
		onClear();
		open = false;
	}
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		{#snippet child({ props })}
			<Button {...props} variant="outline" size="sm" class="h-8 border-dashed">
				<ChevronDown class="mr-2 h-4 w-4" />
				{column.header || column.id}
				{#if selectedRecords.length > 0}
					<div class="ml-2 flex gap-1">
						{#each selectedRecords.slice(0, 2) as record}
							<Badge variant="secondary" class="rounded-sm px-1 font-normal">
								{record.label}
							</Badge>
						{/each}
						{#if selectedRecords.length > 2}
							<Badge variant="secondary" class="rounded-sm px-1 font-normal">
								+{selectedRecords.length - 2}
							</Badge>
						{/if}
					</div>
				{/if}
			</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-80 p-0" align="start">
		<!-- Header -->
		<div class="border-b p-3">
			<div class="flex items-center justify-between">
				<Label class="text-sm font-medium">{column.header || column.id}</Label>
				{#if selectedRecords.length > 0}
					<Button variant="ghost" size="sm" onclick={clear} class="h-6 px-2 text-xs">Clear</Button>
				{/if}
			</div>
		</div>

		<!-- Search input -->
		<div class="p-3">
			<div class="relative">
				<Search class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
				<Input
					type="text"
					placeholder="Search {lookupModule}..."
					value={searchQuery}
					oninput={(e) => handleSearchChange(e.currentTarget.value)}
					class="pl-9"
				/>
				{#if loading}
					<Loader2 class="absolute top-2.5 right-2.5 h-4 w-4 animate-spin text-muted-foreground" />
				{/if}
			</div>
		</div>

		<!-- Selected records -->
		{#if selectedRecords.length > 0}
			<div class="border-t p-3">
				<Label class="mb-2 text-xs text-muted-foreground">Selected</Label>
				<div class="flex flex-wrap gap-1">
					{#each selectedRecords as record}
						<Badge variant="secondary" class="gap-1">
							{record.label}
							<button
								type="button"
								onclick={() => removeRecord(record.id)}
								class="ml-1 rounded-sm hover:bg-accent"
							>
								<X class="h-3 w-3" />
							</button>
						</Badge>
					{/each}
				</div>
			</div>
		{/if}

		<!-- Search results -->
		{#if results.length > 0}
			<div class="max-h-48 overflow-y-auto border-t">
				{#each results as record}
					<button
						type="button"
						onclick={() => selectRecord(record)}
						class="flex w-full items-center px-3 py-2 text-left text-sm hover:bg-accent {selectedRecords.find(
							(r) => r.id === record.id
						)
							? 'opacity-50'
							: ''}"
						disabled={!!selectedRecords.find((r) => r.id === record.id)}
					>
						{record.label}
					</button>
				{/each}
			</div>
		{:else if searchQuery && !loading}
			<div class="border-t p-6 text-center text-sm text-muted-foreground">No results found</div>
		{/if}

		<!-- Footer actions -->
		<div class="flex gap-2 border-t p-2">
			<Button variant="ghost" size="sm" onclick={() => (open = false)} class="flex-1">
				Cancel
			</Button>
			<Button size="sm" onclick={apply} class="flex-1" disabled={selectedRecords.length === 0}>
				Apply {selectedRecords.length > 0 ? `(${selectedRecords.length})` : ''}
			</Button>
		</div>
	</Popover.Content>
</Popover.Root>
