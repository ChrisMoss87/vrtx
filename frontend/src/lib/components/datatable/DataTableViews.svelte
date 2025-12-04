<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Eye, Plus, Save, Trash2, Star, Users } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import type { TableContext } from './types';
	import type { ModuleView } from '$lib/api/views';
	import { getViews, createView, updateView, deleteView, getDefaultView } from '$lib/api/views';

	interface Props {
		moduleApiName: string;
	}

	let { moduleApiName }: Props = $props();

	const table = getContext<TableContext>('table');

	let views = $state<ModuleView[]>([]);
	let currentView = $state<ModuleView | null>(null);
	let showSaveDialog = $state(false);
	let isLoadingViews = $state(false);

	// Save view form state
	let viewName = $state('');
	let viewDescription = $state('');
	let saveAsDefault = $state(false);
	let shareWithTeam = $state(false);

	// Load views on mount
	$effect(() => {
		loadViews();
	});

	async function loadViews() {
		try {
			isLoadingViews = true;
			views = await getViews(moduleApiName);
		} catch (error: any) {
			console.error('Failed to load views:', error);
			toast.error('Failed to load views');
		} finally {
			isLoadingViews = false;
		}
	}

	async function loadView(view: ModuleView) {
		try {
			currentView = view;
			await table.loadView(view);
			toast.success(`Loaded view: ${view.name}`);
		} catch (error: any) {
			console.error('Failed to load view:', error);
			toast.error('Failed to load view');
		}
	}

	async function loadDefaultView() {
		try {
			const { view, module_defaults } = await getDefaultView(moduleApiName);

			if (view) {
				currentView = view;
				await table.loadView(view);
				toast.success(`Loaded default view: ${view.name}`);
			} else if (module_defaults) {
				// Apply module defaults
				table.state.filters = module_defaults.filters || [];
				table.state.sorting = module_defaults.sorting || [];
				if (module_defaults.column_visibility) {
					table.state.columnVisibility = module_defaults.column_visibility;
				}
				table.state.pagination.perPage = module_defaults.page_size || 50;
				await table.refresh();
				currentView = null;
				toast.success('Loaded module defaults');
			} else {
				currentView = null;
				toast.info('No default view set');
			}
		} catch (error: any) {
			console.error('Failed to load default view:', error);
			toast.error('Failed to load default view');
		}
	}

	// Track if we're creating a new view or updating existing
	let isCreatingNew = $state(false);

	function openSaveDialog(createNew: boolean = false) {
		isCreatingNew = createNew;

		// Pre-fill with current view name if editing (not creating new)
		if (currentView && !createNew) {
			viewName = currentView.name;
			viewDescription = currentView.description || '';
			saveAsDefault = currentView.is_default;
			shareWithTeam = currentView.is_shared;
		} else {
			viewName = '';
			viewDescription = '';
			saveAsDefault = false;
			shareWithTeam = false;
		}
		showSaveDialog = true;
	}

	async function handleSaveView() {
		if (!viewName.trim()) {
			toast.error('Please enter a view name');
			return;
		}

		try {
			const viewData = {
				name: viewName,
				description: viewDescription || undefined,
				filters: table.state.filters,
				sorting: table.state.sorting,
				column_visibility: table.state.columnVisibility,
				column_order: table.state.columnOrder,
				column_widths: table.state.columnWidths,
				page_size: table.state.pagination.perPage,
				is_default: saveAsDefault,
				is_shared: shareWithTeam
			};

			if (currentView && !isCreatingNew) {
				// Update existing view
				const updated = await updateView(moduleApiName, currentView.id, viewData);
				currentView = updated;
				toast.success('View updated successfully');
			} else {
				// Create new view
				const created = await createView(moduleApiName, viewData);
				currentView = created;
				toast.success('View saved successfully');
			}

			await loadViews();
			showSaveDialog = false;
		} catch (error: any) {
			console.error('Failed to save view:', error);
			toast.error(error.response?.data?.message || 'Failed to save view');
		}
	}

	async function handleDeleteView(view: ModuleView) {
		if (!confirm(`Are you sure you want to delete the view "${view.name}"?`)) {
			return;
		}

		try {
			await deleteView(moduleApiName, view.id);

			if (currentView?.id === view.id) {
				currentView = null;
			}

			await loadViews();
			toast.success('View deleted successfully');
		} catch (error: any) {
			console.error('Failed to delete view:', error);
			toast.error('Failed to delete view');
		}
	}

	function resetToDefault() {
		currentView = null;
		// Reset to module defaults
		loadDefaultView();
	}
</script>

<div class="flex items-center gap-2">
	<!-- Views Dropdown -->
	<DropdownMenu.Root>
		<DropdownMenu.Trigger>
			{#snippet child({ props })}
				<Button variant="outline" size="sm" {...props}>
					<Eye class="mr-2 h-4 w-4" />
					{currentView ? currentView.name : 'Default View'}
				</Button>
			{/snippet}
		</DropdownMenu.Trigger>
		<DropdownMenu.Content align="start" class="w-56">
			<DropdownMenu.Label>Views</DropdownMenu.Label>
			<DropdownMenu.Separator />

			<!-- Default View -->
			<DropdownMenu.Item onclick={loadDefaultView}>
				<Star class="mr-2 h-4 w-4" />
				Default View
			</DropdownMenu.Item>

			{#if views.length > 0}
				<DropdownMenu.Separator />
				<DropdownMenu.Label class="text-xs text-muted-foreground">My Views</DropdownMenu.Label>

				{#each views.filter((v) => !v.is_shared) as view (view.id)}
					<DropdownMenu.Item
						class="flex items-center justify-between"
						onclick={() => loadView(view)}
					>
						<span class="flex items-center">
							<Eye class="mr-2 h-4 w-4" />
							{view.name}
							{#if view.is_default}
								<Star class="ml-2 h-3 w-3 fill-current text-yellow-500" />
							{/if}
						</span>
					</DropdownMenu.Item>
				{/each}

				{#if views.some((v) => v.is_shared)}
					<DropdownMenu.Separator />
					<DropdownMenu.Label class="text-xs text-muted-foreground">Shared Views</DropdownMenu.Label
					>

					{#each views.filter((v) => v.is_shared) as view (view.id)}
						<DropdownMenu.Item onclick={() => loadView(view)}>
							<Users class="mr-2 h-4 w-4" />
							{view.name}
						</DropdownMenu.Item>
					{/each}
				{/if}
			{/if}

			<DropdownMenu.Separator />
			<DropdownMenu.Item onclick={() => openSaveDialog(true)}>
				<Plus class="mr-2 h-4 w-4" />
				Save as New View...
			</DropdownMenu.Item>

			{#if currentView}
				<DropdownMenu.Item onclick={() => openSaveDialog(false)}>
					<Save class="mr-2 h-4 w-4" />
					Update "{currentView.name}"
				</DropdownMenu.Item>

				<DropdownMenu.Item
					class="text-destructive"
					onclick={() => currentView && handleDeleteView(currentView)}
				>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete View
				</DropdownMenu.Item>
			{/if}
		</DropdownMenu.Content>
	</DropdownMenu.Root>

	{#if currentView}
		<Button variant="ghost" size="sm" onclick={resetToDefault}>Reset to Default</Button>
	{/if}
</div>

<!-- Save View Dialog -->
<Dialog.Root bind:open={showSaveDialog}>
	<Dialog.Content class="sm:max-w-[425px]">
		<Dialog.Header>
			<Dialog.Title>{currentView && !isCreatingNew ? 'Update View' : 'Save New View'}</Dialog.Title>
			<Dialog.Description>
				{currentView && !isCreatingNew
					? 'Update the current view settings'
					: 'Save the current table configuration as a new view'}
			</Dialog.Description>
		</Dialog.Header>

		<div class="grid gap-4 py-4">
			<div class="grid gap-2">
				<Label for="view-name">View Name</Label>
				<Input id="view-name" bind:value={viewName} placeholder="My Custom View" />
			</div>

			<div class="grid gap-2">
				<Label for="view-description">Description (optional)</Label>
				<Textarea
					id="view-description"
					bind:value={viewDescription}
					placeholder="Describe this view..."
					rows={3}
				/>
			</div>

			<div class="flex items-center space-x-2">
				<Checkbox id="default-view" bind:checked={saveAsDefault} />
				<Label for="default-view" class="text-sm font-normal">Set as my default view</Label>
			</div>

			<div class="flex items-center space-x-2">
				<Checkbox id="share-view" bind:checked={shareWithTeam} />
				<Label for="share-view" class="text-sm font-normal">Share with team</Label>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showSaveDialog = false)}>Cancel</Button>
			<Button onclick={handleSaveView}>
				{currentView && !isCreatingNew ? 'Update View' : 'Save View'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
