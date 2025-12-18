<script lang="ts">
	import { Search, Sparkles } from 'lucide-svelte';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import {
		FIELD_TYPES,
		FIELD_CATEGORIES,
		searchFieldTypes,
		getFieldTypesByCategory,
		type FieldType,
		type FieldCategory
	} from '$lib/constants/fieldTypes';
	import { draggable } from '$lib/utils/dnd.svelte';

	let searchQuery = $state('');
	let selectedCategory = $state<FieldCategory | 'all'>('all');

	// Filter field types based on search and category
	let filteredFieldTypes = $derived.by(() => {
		if (searchQuery.trim()) {
			return searchFieldTypes(searchQuery);
		}

		if (selectedCategory === 'all') {
			return Object.values(FIELD_TYPES);
		}

		return getFieldTypesByCategory(selectedCategory);
	});

	// Get sorted categories
	const categories = Object.entries(FIELD_CATEGORIES)
		.sort(([, a], [, b]) => a.order - b.order)
		.map(([key, value]) => ({ key: key as FieldCategory, ...value }));

	function getDraggableOptions(fieldType: FieldType) {
		return {
			data: { fieldType },
			id: `field-type-${fieldType}`,
			sourceId: 'field-palette'
		};
	}
</script>

<div class="field-palette flex h-full flex-col bg-background">
	<!-- Header with Search -->
	<div class="space-y-4 border-b p-4">
		<div>
			<div class="mb-1 flex items-center gap-2">
				<Sparkles class="h-5 w-5 text-primary" />
				<h3 class="text-lg font-semibold">Field Types</h3>
			</div>
			<p class="text-xs text-muted-foreground">{filteredFieldTypes.length} available fields</p>
		</div>

		<!-- Search -->
		<div class="relative">
			<Search
				class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
			/>
			<Input
				type="text"
				placeholder="Search fields..."
				bind:value={searchQuery}
				class="h-9 pl-9"
				data-testid="field-search"
			/>
		</div>
	</div>

	<!-- Category Tabs -->
	{#if !searchQuery}
		<ScrollArea orientation="horizontal" class="border-b">
			<div class="flex min-w-max gap-1 p-2">
				<button
					class="rounded-md px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-all {selectedCategory ===
					'all'
						? 'bg-primary text-primary-foreground'
						: 'hover:bg-accent'}"
					onclick={() => (selectedCategory = 'all')}
					data-testid="category-all"
				>
					All
				</button>
				{#each categories as category}
					<button
						class="rounded-md px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-all {selectedCategory ===
						category.key
							? 'bg-primary text-primary-foreground'
							: 'hover:bg-accent'}"
						onclick={() => (selectedCategory = category.key)}
						data-testid="category-{category.key}"
					>
						{category.label}
					</button>
				{/each}
			</div>
		</ScrollArea>
	{/if}

	<!-- Field Type List -->
	<ScrollArea class="flex-1">
		<div class="flex flex-col gap-1.5 p-3">
			{#each filteredFieldTypes as fieldType (fieldType.value)}
				{@const Icon = fieldType.icon}
				<div
					class="field-type-card group flex cursor-grab items-center gap-3 rounded-lg border bg-card p-2.5 text-left transition-all hover:border-primary hover:bg-accent active:cursor-grabbing"
					role="button"
					tabindex="0"
					use:draggable={getDraggableOptions(fieldType.value)}
					data-testid="field-type-{fieldType.value}"
				>
					<div
						class="shrink-0 rounded-md bg-primary/10 p-1.5 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
					>
						<Icon class="h-4 w-4" />
					</div>
					<div class="min-w-0 flex-1">
						<div class="text-sm font-medium leading-tight">{fieldType.label}</div>
						<p class="truncate text-[11px] text-muted-foreground leading-tight">
							{fieldType.description}
						</p>
					</div>
					{#if fieldType.isAdvanced}
						<Badge variant="secondary" class="h-5 shrink-0 px-1.5 text-[10px]">Pro</Badge>
					{/if}
				</div>
			{/each}
		</div>

		{#if filteredFieldTypes.length === 0}
			<div class="px-4 py-12 text-center">
				<div
					class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted/50"
				>
					<Search class="h-6 w-6 text-muted-foreground" />
				</div>
				<p class="mb-1 text-sm font-medium">No fields found</p>
				<p class="text-xs text-muted-foreground">Try a different search term</p>
			</div>
		{/if}
	</ScrollArea>

	<!-- Footer Tip -->
	<div class="border-t bg-muted/30 p-3">
		<div class="flex items-start gap-2 text-xs text-muted-foreground">
			<span class="text-sm">💡</span>
			<p>Drag fields to canvas to add them</p>
		</div>
	</div>
</div>

<style>
	.field-palette {
		width: 280px;
		max-width: 100%;
	}

	.field-type-card:active {
		transform: scale(0.98);
	}
</style>
