<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Bookmark, Star, Trash2, Edit, Copy, Clock } from 'lucide-svelte';
	import type { TableContext, FilterConfig } from './types';

	interface FilterTemplate {
		id: number;
		name: string;
		description?: string;
		filters: FilterConfig[];
		is_public: boolean;
		is_favorite: boolean;
		user_id?: number;
		module: string;
		created_at: string;
		updated_at: string;
	}

	interface Props {
		moduleApiName: string;
	}

	let { moduleApiName }: Props = $props();

	const table = getContext<TableContext>('table');

	// State
	let templates = $state<FilterTemplate[]>([]);
	let loading = $state(false);
	let saveDialogOpen = $state(false);
	let templateName = $state('');
	let templateDescription = $state('');
	let isPublic = $state(false);

	// Load templates on mount
	$effect(() => {
		loadTemplates();
	});

	async function loadTemplates() {
		loading = true;
		try {
			// Use local storage for filter templates (backend not yet implemented)
			const stored = localStorage.getItem(`filter-templates-${moduleApiName}`);
			if (stored) {
				templates = JSON.parse(stored);
			}
		} catch (error) {
			console.error('Failed to load filter templates:', error);
			templates = [];
		} finally {
			loading = false;
		}
	}

	async function saveCurrentAsTemplate() {
		if (!templateName.trim()) return;

		const template: FilterTemplate = {
			id: Date.now(),
			name: templateName.trim(),
			description: templateDescription.trim() || undefined,
			filters: table.state.filters,
			is_public: isPublic,
			is_favorite: false,
			module: moduleApiName,
			created_at: new Date().toISOString(),
			updated_at: new Date().toISOString()
		};

		try {
			// Save to local storage (backend not yet implemented)
			templates = [...templates, template];
			localStorage.setItem(`filter-templates-${moduleApiName}`, JSON.stringify(templates));
		} catch (error) {
			console.error('Failed to save template:', error);
		}

		// Reset form and close dialog
		templateName = '';
		templateDescription = '';
		isPublic = false;
		saveDialogOpen = false;
	}

	async function applyTemplate(template: FilterTemplate) {
		// Clear existing filters
		table.clearFilters();

		// Apply template filters
		template.filters.forEach((filter) => {
			table.updateFilter(filter);
		});
	}

	async function deleteTemplate(templateId: number) {
		if (!confirm('Are you sure you want to delete this filter template?')) return;

		try {
			// TODO: Replace with actual API call
			await fetch(`/api/filter-templates/${templateId}`, { method: 'DELETE' });
			templates = templates.filter((t) => t.id !== templateId);
		} catch (error) {
			console.error('Failed to delete template:', error);
			// Fallback to local storage
			templates = templates.filter((t) => t.id !== templateId);
			localStorage.setItem(`filter-templates-${moduleApiName}`, JSON.stringify(templates));
		}
	}

	async function toggleFavorite(template: FilterTemplate) {
		template.is_favorite = !template.is_favorite;

		try {
			// TODO: Replace with actual API call
			await fetch(`/api/filter-templates/${template.id}`, {
				method: 'PATCH',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ is_favorite: template.is_favorite })
			});
		} catch (error) {
			console.error('Failed to update template:', error);
			// Fallback to local storage
			localStorage.setItem(`filter-templates-${moduleApiName}`, JSON.stringify(templates));
		}
	}

	async function duplicateTemplate(template: FilterTemplate) {
		const duplicate: Partial<FilterTemplate> = {
			name: `${template.name} (Copy)`,
			description: template.description,
			filters: template.filters,
			is_public: false,
			is_favorite: false,
			module: moduleApiName,
			created_at: new Date().toISOString(),
			updated_at: new Date().toISOString()
		};

		try {
			// TODO: Replace with actual API call
			const response = await fetch('/api/filter-templates', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(duplicate)
			});

			if (response.ok) {
				const saved = await response.json();
				templates = [...templates, saved];
			}
		} catch (error) {
			console.error('Failed to duplicate template:', error);
			// Fallback to local storage
			const newTemplate = { ...duplicate, id: Date.now() } as FilterTemplate;
			templates = [...templates, newTemplate];
			localStorage.setItem(`filter-templates-${moduleApiName}`, JSON.stringify(templates));
		}
	}

	const favoriteTemplates = $derived(templates.filter((t) => t.is_favorite));
	const regularTemplates = $derived(templates.filter((t) => !t.is_favorite));
</script>

<div class="flex items-center gap-2">
	<!-- Saved Templates Dropdown -->
	<DropdownMenu.Root>
		<DropdownMenu.Trigger>
			<Button variant="outline" size="sm">
				<Bookmark class="mr-1 h-3 w-3" />
				Templates
				{#if templates.length > 0}
					<Badge variant="secondary" class="ml-1 h-4 px-1 text-xs">{templates.length}</Badge>
				{/if}
			</Button>
		</DropdownMenu.Trigger>
		<DropdownMenu.Content class="w-80" align="start">
			{#if templates.length === 0}
				<div class="p-4 text-center text-sm text-muted-foreground">
					<p>No saved filter templates</p>
					<p class="mt-1 text-xs">Save your current filters as a template</p>
				</div>
			{:else}
				<!-- Favorites -->
				{#if favoriteTemplates.length > 0}
					<DropdownMenu.Label>Favorites</DropdownMenu.Label>
					{#each favoriteTemplates as template}
						<DropdownMenu.Item
							onclick={() => applyTemplate(template)}
							class="flex items-start justify-between gap-2"
						>
							<div class="min-w-0 flex-1">
								<div class="flex items-center gap-1">
									<Star class="h-3 w-3 fill-yellow-400 text-yellow-400" />
									<span class="truncate font-medium">{template.name}</span>
								</div>
								{#if template.description}
									<p class="truncate text-xs text-muted-foreground">{template.description}</p>
								{/if}
								<div class="mt-1 flex items-center gap-1">
									<Badge variant="secondary" class="h-4 px-1 text-xs">
										{template.filters.length} filter{template.filters.length === 1 ? '' : 's'}
									</Badge>
									{#if template.is_public}
										<Badge variant="outline" class="h-4 px-1 text-xs">Public</Badge>
									{/if}
								</div>
							</div>
							<DropdownMenu.Sub>
								<DropdownMenu.SubTrigger class="h-6 w-6 p-0">⋮</DropdownMenu.SubTrigger>
								<DropdownMenu.SubContent>
									<DropdownMenu.Item onclick={() => toggleFavorite(template)}>
										<Star class="mr-2 h-3 w-3" />
										Unfavorite
									</DropdownMenu.Item>
									<DropdownMenu.Item onclick={() => duplicateTemplate(template)}>
										<Copy class="mr-2 h-3 w-3" />
										Duplicate
									</DropdownMenu.Item>
									<DropdownMenu.Separator />
									<DropdownMenu.Item
										onclick={() => deleteTemplate(template.id)}
										class="text-destructive"
									>
										<Trash2 class="mr-2 h-3 w-3" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.SubContent>
							</DropdownMenu.Sub>
						</DropdownMenu.Item>
					{/each}
					<DropdownMenu.Separator />
				{/if}

				<!-- Regular Templates -->
				{#if regularTemplates.length > 0}
					{#if favoriteTemplates.length > 0}
						<DropdownMenu.Label>All Templates</DropdownMenu.Label>
					{/if}
					{#each regularTemplates as template}
						<DropdownMenu.Item
							onclick={() => applyTemplate(template)}
							class="flex items-start justify-between gap-2"
						>
							<div class="min-w-0 flex-1">
								<div class="truncate font-medium">{template.name}</div>
								{#if template.description}
									<p class="truncate text-xs text-muted-foreground">{template.description}</p>
								{/if}
								<div class="mt-1 flex items-center gap-1">
									<Badge variant="secondary" class="h-4 px-1 text-xs">
										{template.filters.length} filter{template.filters.length === 1 ? '' : 's'}
									</Badge>
									{#if template.is_public}
										<Badge variant="outline" class="h-4 px-1 text-xs">Public</Badge>
									{/if}
								</div>
							</div>
							<DropdownMenu.Sub>
								<DropdownMenu.SubTrigger class="h-6 w-6 p-0">⋮</DropdownMenu.SubTrigger>
								<DropdownMenu.SubContent>
									<DropdownMenu.Item onclick={() => toggleFavorite(template)}>
										<Star class="mr-2 h-3 w-3" />
										Favorite
									</DropdownMenu.Item>
									<DropdownMenu.Item onclick={() => duplicateTemplate(template)}>
										<Copy class="mr-2 h-3 w-3" />
										Duplicate
									</DropdownMenu.Item>
									<DropdownMenu.Separator />
									<DropdownMenu.Item
										onclick={() => deleteTemplate(template.id)}
										class="text-destructive"
									>
										<Trash2 class="mr-2 h-3 w-3" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.SubContent>
							</DropdownMenu.Sub>
						</DropdownMenu.Item>
					{/each}
				{/if}
			{/if}
		</DropdownMenu.Content>
	</DropdownMenu.Root>

	<!-- Save Current Filters Button -->
	<Button
		variant="outline"
		size="sm"
		onclick={() => (saveDialogOpen = true)}
		disabled={table.state.filters.length === 0}
	>
		<Bookmark class="mr-1 h-3 w-3" />
		Save Filters
	</Button>
</div>

<!-- Save Template Dialog -->
<Dialog.Root bind:open={saveDialogOpen}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Save Filter Template</Dialog.Title>
			<Dialog.Description>
				Save your current filters ({table.state.filters.length} active) as a reusable template
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="template-name">Template Name</Label>
				<Input id="template-name" placeholder="e.g., My Open Tasks" bind:value={templateName} />
			</div>
			<div class="space-y-2">
				<Label for="template-description">Description (Optional)</Label>
				<Textarea
					id="template-description"
					placeholder="Describe what this filter template does..."
					bind:value={templateDescription}
					rows={2}
				/>
			</div>
			<div class="flex items-center gap-2">
				<input
					type="checkbox"
					id="template-public"
					bind:checked={isPublic}
					class="h-4 w-4 rounded border-input"
				/>
				<Label for="template-public" class="cursor-pointer text-sm font-normal">
					Share with team (make public)
				</Label>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (saveDialogOpen = false)}>Cancel</Button>
			<Button onclick={saveCurrentAsTemplate} disabled={!templateName.trim()}>Save Template</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
