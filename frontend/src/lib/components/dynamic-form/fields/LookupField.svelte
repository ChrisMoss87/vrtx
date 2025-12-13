<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Popover from '$lib/components/ui/popover';
	import { Badge } from '$lib/components/ui/badge';
	import { Search, Check, X, Loader2 } from 'lucide-svelte';
	import type { FieldSettings, FieldOption } from '$lib/api/modules';
	import { searchLookup, type LookupResult } from '$lib/api/lookups';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { onMount } from 'svelte';

	interface Props {
		value: number | number[] | string | string[];
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: FieldSettings;
		options?: FieldOption[];
		onchange: (value: number | number[] | string | string[]) => void;
	}

	let {
		value = $bindable(''),
		error,
		disabled = false,
		placeholder = 'Search...',
		required,
		settings,
		options = [],
		onchange
	}: Props = $props();

	let open = $state(false);
	let searchQuery = $state('');
	let isLoading = $state(false);
	let searchResults = $state<LookupResult[]>([]);
	let searchTimeout: ReturnType<typeof setTimeout> | null = null;
	let modules = $state<Module[]>([]);

	// Get lookup configuration from settings
	const lookupConfig = settings?.lookup_configuration;
	const isMultiple = lookupConfig?.relationship_type === 'many_to_many';
	const relatedModuleId = lookupConfig?.related_module_id || settings?.related_module_id;
	const displayField = lookupConfig?.display_field || settings?.display_field || 'name';

	// Resolve related module API name
	let relatedModuleApiName = $state<string>('');

	onMount(async () => {
		if (relatedModuleId) {
			try {
				const loadedModules = await modulesApi.getActive();
				modules = loadedModules;
				const relatedModule = loadedModules.find((m: Module) => m.id === relatedModuleId);
				if (relatedModule) {
					relatedModuleApiName = relatedModule.api_name;
				}
			} catch (err) {
				console.error('Failed to load modules:', err);
			}
		}
	});

	const useDynamicSearch = $derived(!!relatedModuleApiName);

	// For static options (backwards compatibility)
	const filteredOptions = $derived(
		searchQuery
			? options.filter((opt) => opt.label.toLowerCase().includes(searchQuery.toLowerCase()))
			: options
	);

	// Selected items (for displaying labels)
	let selectedItems = $state<LookupResult[]>([]);

	// Load selected items when value changes
	$effect(() => {
		if (useDynamicSearch && value && relatedModuleApiName) {
			loadSelectedItems();
		}
	});

	async function loadSelectedItems() {
		const ids = normalizeIds(value);
		if (ids.length === 0 || !relatedModuleApiName) {
			selectedItems = [];
			return;
		}

		try {
			const results = await searchLookup(relatedModuleApiName, {
				selected_ids: ids,
				display_field: displayField
			});
			selectedItems = results;
		} catch (err) {
			console.error('Failed to load selected items:', err);
		}
	}

	function normalizeIds(val: number | number[] | string | string[]): number[] {
		if (!val) return [];
		if (Array.isArray(val)) {
			return val.map((v) => (typeof v === 'string' ? parseInt(v, 10) : v)).filter((v) => !isNaN(v));
		}
		const parsed = typeof val === 'string' ? parseInt(val, 10) : val;
		return isNaN(parsed) ? [] : [parsed];
	}

	async function handleSearch(query: string) {
		if (!useDynamicSearch || !relatedModuleApiName) return;

		if (searchTimeout) {
			clearTimeout(searchTimeout);
		}

		searchTimeout = setTimeout(async () => {
			isLoading = true;
			try {
				searchResults = await searchLookup(relatedModuleApiName, {
					q: query,
					display_field: displayField,
					limit: 20
				});
			} catch (err) {
				console.error('Lookup search failed:', err);
				searchResults = [];
			} finally {
				isLoading = false;
			}
		}, 300);
	}

	function selectItem(item: LookupResult) {
		if (isMultiple) {
			const currentIds = normalizeIds(value);
			if (currentIds.includes(item.id)) {
				// Remove
				const newIds = currentIds.filter((id) => id !== item.id);
				value = newIds;
				selectedItems = selectedItems.filter((i) => i.id !== item.id);
			} else {
				// Add
				value = [...currentIds, item.id];
				selectedItems = [...selectedItems, item];
			}
		} else {
			value = item.id;
			selectedItems = [item];
			open = false;
		}
		onchange(value);
		searchQuery = '';
	}

	// For static options
	function selectOption(optionValue: string) {
		if (isMultiple) {
			const currentValues = Array.isArray(value) ? (value as string[]) : [];
			if (currentValues.includes(optionValue)) {
				value = currentValues.filter((v) => v !== optionValue);
			} else {
				value = [...currentValues, optionValue];
			}
		} else {
			value = optionValue;
			open = false;
		}
		onchange(value);
		searchQuery = '';
	}

	function removeItem(itemId: number) {
		if (isMultiple) {
			const currentIds = normalizeIds(value);
			value = currentIds.filter((id) => id !== itemId);
			selectedItems = selectedItems.filter((i) => i.id !== itemId);
			onchange(value);
		}
	}

	function removeValue(val: string) {
		if (isMultiple && Array.isArray(value)) {
			value = (value as string[]).filter((v) => v !== val);
			onchange(value);
		}
	}

	function getSelectedLabels(): string[] {
		if (useDynamicSearch) {
			return selectedItems.map((i) => i.label);
		}
		const values = Array.isArray(value) ? value : [value];
		return values
			.filter((v) => v)
			.map((v) => options.find((o) => o.value === String(v))?.label || String(v));
	}

	function isSelected(id: number): boolean {
		const ids = normalizeIds(value);
		return ids.includes(id);
	}

	function isOptionSelected(optionValue: string): boolean {
		if (Array.isArray(value)) {
			return (value as (string | number)[]).map(String).includes(optionValue);
		}
		return String(value) === optionValue;
	}

	$effect(() => {
		if (searchQuery && useDynamicSearch) {
			handleSearch(searchQuery);
		}
	});

	$effect(() => {
		if (open && useDynamicSearch && searchResults.length === 0 && !searchQuery) {
			// Load initial results when opening
			handleSearch('');
		}
	});
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		{#snippet child({ props })}
			<Button
				{...props}
				variant="outline"
				role="combobox"
				aria-expanded={open}
				{disabled}
				class={`w-full justify-between font-normal ${error ? 'border-destructive' : ''}`}
			>
				<div class="flex flex-wrap gap-1 overflow-hidden">
					{#if !value || (Array.isArray(value) && value.length === 0)}
						<span class="text-muted-foreground">{placeholder}</span>
					{:else if isMultiple}
						{#each getSelectedLabels() as label, i}
							<Badge variant="secondary" class="text-xs">
								{label}
								<button
									type="button"
									onclick={(e) => {
										e.stopPropagation();
										if (useDynamicSearch) {
											const ids = normalizeIds(value);
											removeItem(ids[i]);
										} else {
											const vals = Array.isArray(value) ? (value as string[]) : [];
											removeValue(vals[i]);
										}
									}}
									class="ml-1 hover:text-destructive"
								>
									<X class="h-3 w-3" />
								</button>
							</Badge>
						{/each}
					{:else}
						<span>{getSelectedLabels()[0]}</span>
					{/if}
				</div>
				<Search class="ml-2 h-4 w-4 shrink-0 opacity-50" />
			</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-[var(--bits-popover-trigger-width)] p-0">
		<div class="flex flex-col">
			<div class="border-b p-2">
				<Input type="text" placeholder="Search..." bind:value={searchQuery} class="h-8" />
			</div>
			<div class="max-h-64 overflow-y-auto p-1">
				{#if isLoading}
					<div class="flex items-center justify-center py-6">
						<Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
					</div>
				{:else if useDynamicSearch}
					{#each searchResults as result}
						<button
							type="button"
							onclick={() => selectItem(result)}
							class="relative flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
						>
							{#if isSelected(result.id)}
								<Check class="mr-2 h-4 w-4" />
							{:else}
								<span class="mr-2 h-4 w-4"></span>
							{/if}
							{result.label}
						</button>
					{/each}
					{#if searchResults.length === 0}
						<div class="px-2 py-6 text-center text-sm text-muted-foreground">
							{searchQuery ? 'No results found.' : 'Start typing to search...'}
						</div>
					{/if}
				{:else}
					{#each filteredOptions as option}
						<button
							type="button"
							onclick={() => selectOption(option.value)}
							class="relative flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
						>
							{#if isOptionSelected(option.value)}
								<Check class="mr-2 h-4 w-4" />
							{:else}
								<span class="mr-2 h-4 w-4"></span>
							{/if}
							{option.label}
						</button>
					{/each}
					{#if filteredOptions.length === 0}
						<div class="px-2 py-6 text-center text-sm text-muted-foreground">No results found.</div>
					{/if}
				{/if}
			</div>
		</div>
	</Popover.Content>
</Popover.Root>
