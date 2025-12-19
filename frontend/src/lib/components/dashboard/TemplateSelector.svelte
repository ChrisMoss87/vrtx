<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Check, LayoutGrid, BarChart3, Target, Users, FileText, Plus } from 'lucide-svelte';
	import {
		dashboardTemplatesApi,
		type DashboardTemplate,
		type DashboardTemplateCategory
	} from '$lib/api/dashboards';

	interface Props {
		selected?: DashboardTemplate | null;
		onSelect?: (template: DashboardTemplate | null) => void;
	}

	let { selected = $bindable(null), onSelect }: Props = $props();

	let templates = $state<DashboardTemplate[]>([]);
	let categories = $state<DashboardTemplateCategory[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let activeCategory = $state('all');
	let searchQuery = $state('');

	const categoryIcons: Record<string, typeof LayoutGrid> = {
		sales: BarChart3,
		marketing: Target,
		executive: Users,
		custom: FileText,
		blank: Plus
	};

	// Filtered templates based on category and search
	let filteredTemplates = $derived.by(() => {
		let result = templates;

		// Filter by category
		if (activeCategory !== 'all') {
			result = result.filter((t) => t.category === activeCategory);
		}

		// Filter by search
		if (searchQuery.trim()) {
			const query = searchQuery.toLowerCase();
			result = result.filter(
				(t) =>
					t.name.toLowerCase().includes(query) ||
					(t.description && t.description.toLowerCase().includes(query))
			);
		}

		return result;
	});

	// Load templates and categories on mount
	$effect(() => {
		loadData();
	});

	async function loadData() {
		loading = true;
		error = null;
		try {
			const [templatesData, categoriesData] = await Promise.all([
				dashboardTemplatesApi.list(),
				dashboardTemplatesApi.getCategories()
			]);
			templates = templatesData;
			categories = categoriesData;
		} catch (e) {
			console.error('Failed to load templates:', e);
			error = 'Failed to load templates';
		} finally {
			loading = false;
		}
	}

	function handleSelect(template: DashboardTemplate | null) {
		selected = template;
		onSelect?.(template);
	}

	function getCategoryIcon(category: string) {
		return categoryIcons[category] || LayoutGrid;
	}
</script>

<div class="space-y-4">
	<!-- Search -->
	<div class="flex items-center gap-2">
		<Input placeholder="Search templates..." bind:value={searchQuery} class="max-w-xs" />
	</div>

	<!-- Category Tabs -->
	<Tabs.Root bind:value={activeCategory}>
		<Tabs.List>
			<Tabs.Trigger value="all">All</Tabs.Trigger>
			{#each categories as category}
				<Tabs.Trigger value={category.value}>{category.label}</Tabs.Trigger>
			{/each}
		</Tabs.List>
	</Tabs.Root>

	<!-- Loading State -->
	{#if loading}
		<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
			{#each Array(6) as _}
				<div class="rounded-lg border p-4">
					<Skeleton class="mb-3 h-32 w-full" />
					<Skeleton class="mb-2 h-5 w-3/4" />
					<Skeleton class="h-4 w-full" />
				</div>
			{/each}
		</div>
	{:else if error}
		<!-- Error State -->
		<div class="rounded-lg border border-destructive/50 bg-destructive/10 p-6 text-center">
			<p class="text-destructive">{error}</p>
			<Button variant="outline" size="sm" class="mt-2" onclick={loadData}>Try Again</Button>
		</div>
	{:else if filteredTemplates.length === 0}
		<!-- Empty State -->
		<div class="rounded-lg border border-dashed p-8 text-center">
			<LayoutGrid class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
			<p class="text-muted-foreground">No templates found</p>
		</div>
	{:else}
		<!-- Template Grid -->
		<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
			<!-- Blank Dashboard Option -->
			<button
				type="button"
				class="group relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 transition-colors hover:border-primary hover:bg-muted/50 {selected ===
				null
					? 'border-primary bg-primary/5 ring-2 ring-primary'
					: ''}"
				onclick={() => handleSelect(null)}
			>
				{#if selected === null}
					<div class="absolute right-2 top-2">
						<div class="flex h-5 w-5 items-center justify-center rounded-full bg-primary">
							<Check class="h-3 w-3 text-primary-foreground" />
						</div>
					</div>
				{/if}
				<Plus
					class="mb-3 h-10 w-10 text-muted-foreground transition-colors group-hover:text-primary"
				/>
				<h3 class="font-medium">Blank Dashboard</h3>
				<p class="mt-1 text-center text-sm text-muted-foreground">
					Start from scratch with an empty dashboard
				</p>
			</button>

			<!-- Template Cards -->
			{#each filteredTemplates as template (template.id)}
				{@const isSelected = selected?.id === template.id}
				{@const CategoryIcon = getCategoryIcon(template.category)}
				<button
					type="button"
					class="group relative flex flex-col rounded-lg border-2 p-4 text-left transition-colors hover:border-primary hover:bg-muted/50 {isSelected
						? 'border-primary bg-primary/5 ring-2 ring-primary'
						: 'border-border'}"
					onclick={() => handleSelect(template)}
				>
					{#if isSelected}
						<div class="absolute right-2 top-2">
							<div class="flex h-5 w-5 items-center justify-center rounded-full bg-primary">
								<Check class="h-3 w-3 text-primary-foreground" />
							</div>
						</div>
					{/if}

					<!-- Preview/Thumbnail -->
					<div
						class="mb-3 flex h-32 items-center justify-center rounded-md bg-muted/50 transition-colors group-hover:bg-muted"
					>
						{#if template.thumbnail}
							<img
								src={template.thumbnail}
								alt={template.name}
								class="h-full w-full rounded-md object-cover"
							/>
						{:else}
							<CategoryIcon class="h-12 w-12 text-muted-foreground" />
						{/if}
					</div>

					<!-- Info -->
					<div class="flex-1">
						<div class="mb-1 flex items-center gap-2">
							<h3 class="font-medium">{template.name}</h3>
							<Badge variant="secondary" class="text-xs">
								{template.widgets_count} widgets
							</Badge>
						</div>
						{#if template.description}
							<p class="line-clamp-2 text-sm text-muted-foreground">
								{template.description}
							</p>
						{/if}
					</div>
				</button>
			{/each}
		</div>
	{/if}
</div>
