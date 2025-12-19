<script lang="ts">
	import { onMount } from 'svelte';
	import { Input } from '$lib/components/ui/input';
	import { Button } from '$lib/components/ui/button';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import {
		Search,
		Users,
		TrendingUp,
		Heart,
		Database,
		Clock,
		MessageSquare,
		Sparkles,
		X
	} from 'lucide-svelte';
	import {
		getWorkflowTemplates,
		type WorkflowTemplate,
		type TemplateCategory,
		type TemplateDifficulty
	} from '$lib/api/workflows';
	import TemplateCard from './TemplateCard.svelte';

	interface Props {
		onSelectTemplate?: (template: WorkflowTemplate) => void;
		onClose?: () => void;
	}

	let { onSelectTemplate, onClose }: Props = $props();

	let templates = $state<WorkflowTemplate[]>([]);
	let categories = $state<Record<string, string>>({});
	let difficultyLevels = $state<Record<string, string>>({});
	let loading = $state(true);
	let error = $state<string | null>(null);

	let searchQuery = $state('');
	let selectedCategory = $state<TemplateCategory | 'all'>('all');
	let selectedDifficulty = $state<TemplateDifficulty | 'all'>('all');
	let previewTemplate = $state<WorkflowTemplate | null>(null);

	const categoryIcons: Record<string, typeof Users> = {
		lead: Users,
		deal: TrendingUp,
		customer: Heart,
		data: Database,
		productivity: Clock,
		communication: MessageSquare
	};

	let filteredTemplates = $derived(() => {
		let filtered = templates;

		if (searchQuery) {
			const query = searchQuery.toLowerCase();
			filtered = filtered.filter(
				(t) =>
					t.name.toLowerCase().includes(query) || t.description.toLowerCase().includes(query)
			);
		}

		if (selectedCategory !== 'all') {
			filtered = filtered.filter((t) => t.category === selectedCategory);
		}

		if (selectedDifficulty !== 'all') {
			filtered = filtered.filter((t) => t.difficulty === selectedDifficulty);
		}

		return filtered;
	});

	async function loadTemplates() {
		loading = true;
		error = null;
		try {
			const result = await getWorkflowTemplates();
			templates = result.templates;
			categories = result.categories;
			difficultyLevels = result.difficulty_levels;
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load templates';
		} finally {
			loading = false;
		}
	}

	function handleSelect(template: WorkflowTemplate) {
		onSelectTemplate?.(template);
	}

	function handlePreview(template: WorkflowTemplate) {
		previewTemplate = template;
	}

	function closePreview() {
		previewTemplate = null;
	}

	onMount(() => {
		loadTemplates();
	});
</script>

<div class="flex h-full flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b px-6 py-4">
		<div>
			<h2 class="text-lg font-semibold">Workflow Templates</h2>
			<p class="text-sm text-muted-foreground">
				Choose from {templates.length} pre-built automation templates
			</p>
		</div>
		{#if onClose}
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		{/if}
	</div>

	<!-- Search and filters -->
	<div class="border-b px-6 py-4">
		<div class="flex flex-wrap gap-4">
			<div class="relative flex-1">
				<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<Input
					placeholder="Search templates..."
					class="pl-9"
					bind:value={searchQuery}
				/>
			</div>

			<select
				class="rounded-md border px-3 py-2 text-sm"
				bind:value={selectedDifficulty}
			>
				<option value="all">All difficulties</option>
				{#each Object.entries(difficultyLevels) as [value, label]}
					<option {value}>{label}</option>
				{/each}
			</select>
		</div>
	</div>

	<!-- Category tabs -->
	<div class="flex-1 overflow-hidden">
		<Tabs.Root bind:value={selectedCategory} class="flex h-full flex-col">
			<div class="border-b px-6">
				<Tabs.List class="h-12">
					<Tabs.Trigger value="all" class="gap-2">
						<Sparkles class="h-4 w-4" />
						All
					</Tabs.Trigger>
					{#each Object.entries(categories) as [key, label]}
						{@const Icon = categoryIcons[key] || Sparkles}
						<Tabs.Trigger value={key} class="gap-2">
							<Icon class="h-4 w-4" />
							{label}
						</Tabs.Trigger>
					{/each}
				</Tabs.List>
			</div>

			<div class="flex-1 overflow-y-auto p-6">
				{#if loading}
					<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
						{#each Array(6) as _}
							<div class="space-y-3">
								<Skeleton class="h-32 w-full" />
								<Skeleton class="h-4 w-3/4" />
								<Skeleton class="h-4 w-1/2" />
							</div>
						{/each}
					</div>
				{:else if error}
					<div class="flex flex-col items-center justify-center py-12">
						<p class="text-destructive">{error}</p>
						<Button variant="outline" class="mt-4" onclick={loadTemplates}>
							Try again
						</Button>
					</div>
				{:else if filteredTemplates().length === 0}
					<div class="flex flex-col items-center justify-center py-12">
						<p class="text-muted-foreground">No templates found</p>
						{#if searchQuery || selectedCategory !== 'all' || selectedDifficulty !== 'all'}
							<Button
								variant="outline"
								class="mt-4"
								onclick={() => {
									searchQuery = '';
									selectedCategory = 'all';
									selectedDifficulty = 'all';
								}}
							>
								Clear filters
							</Button>
						{/if}
					</div>
				{:else}
					<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
						{#each filteredTemplates() as template (template.id)}
							<TemplateCard
								{template}
								onSelect={handleSelect}
								onPreview={handlePreview}
							/>
						{/each}
					</div>
				{/if}
			</div>
		</Tabs.Root>
	</div>
</div>

<!-- Preview modal -->
{#if previewTemplate}
	<div
		class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
		onclick={closePreview}
		onkeydown={(e) => e.key === 'Escape' && closePreview()}
		role="dialog"
		aria-modal="true"
		tabindex="-1"
	>
		<div
			class="relative max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl"
			onclick={(e) => e.stopPropagation()}
			role="document"
		>
			<Button
				variant="ghost"
				size="icon"
				class="absolute right-4 top-4"
				onclick={closePreview}
			>
				<X class="h-4 w-4" />
			</Button>

			<h3 class="text-xl font-semibold">{previewTemplate.name}</h3>

			<div class="mt-2 flex flex-wrap gap-2">
				<Badge>{previewTemplate.category}</Badge>
				<Badge variant="outline">{previewTemplate.difficulty}</Badge>
				{#if previewTemplate.estimated_time_saved_hours}
					<Badge variant="secondary">
						Saves ~{previewTemplate.estimated_time_saved_hours}h/month
					</Badge>
				{/if}
			</div>

			<p class="mt-4 text-muted-foreground">{previewTemplate.description}</p>

			{#if previewTemplate.required_modules?.length}
				<div class="mt-4">
					<h4 class="text-sm font-medium">Required Modules</h4>
					<div class="mt-1 flex flex-wrap gap-1">
						{#each previewTemplate.required_modules as mod}
							<Badge variant="outline">{mod}</Badge>
						{/each}
					</div>
				</div>
			{/if}

			{#if previewTemplate.variable_mappings && Object.keys(previewTemplate.variable_mappings).length > 0}
				<div class="mt-4">
					<h4 class="text-sm font-medium">Configuration Required</h4>
					<ul class="mt-2 space-y-2">
						{#each Object.entries(previewTemplate.variable_mappings) as [key, config]}
							<li class="text-sm">
								<span class="font-medium">{config.label}</span>
								{#if config.description}
									<span class="text-muted-foreground"> - {config.description}</span>
								{/if}
							</li>
						{/each}
					</ul>
				</div>
			{/if}

			<div class="mt-6 flex gap-2">
				<Button variant="outline" class="flex-1" onclick={closePreview}>
					Cancel
				</Button>
				<Button
					class="flex-1"
					disabled={!previewTemplate.is_compatible}
					onclick={() => {
						handleSelect(previewTemplate!);
						closePreview();
					}}
				>
					Use This Template
				</Button>
			</div>
		</div>
	</div>
{/if}
