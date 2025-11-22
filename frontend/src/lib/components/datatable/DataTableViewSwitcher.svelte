<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Check, ChevronDown, Plus, Save, Star, StarOff } from 'lucide-svelte';
	import { onMount } from 'svelte';

	interface TableView {
		id: number;
		name: string;
		description?: string;
		is_default: boolean;
		is_public: boolean;
		user_id: number;
		filters?: any;
		sorting?: any;
		column_visibility?: any;
		column_order?: any;
		column_widths?: any;
		page_size?: number;
	}

	interface Props {
		module: string;
		currentView?: TableView | null;
		defaultViewId?: number | null;
		onViewChange?: (view: TableView | null) => void;
		onSaveView?: () => void;
		onCreateView?: () => void;
	}

	let {
		module,
		currentView = $bindable(null),
		defaultViewId,
		onViewChange,
		onSaveView,
		onCreateView
	}: Props = $props();

	let views = $state<TableView[]>([]);
	let loading = $state(false);

	async function loadViews() {
		loading = true;
		try {
			const response = await fetch(`/api/table-views?module=${encodeURIComponent(module)}`);
			if (response.ok) {
				views = await response.json();
				// If no current view, try to set the default one
				if (!currentView && views.length > 0) {
					// First try user's default view preference
					if (defaultViewId) {
						const userDefaultView = views.find((v) => v.id === defaultViewId);
						if (userDefaultView) {
							selectView(userDefaultView);
							return;
						}
					}
					// Otherwise try view marked as default
					const defaultView = views.find((v) => v.is_default);
					if (defaultView) {
						selectView(defaultView);
					}
				}
			}
		} catch (error) {
			console.error('Failed to load views:', error);
		} finally {
			loading = false;
		}
	}

	function selectView(view: TableView | null) {
		currentView = view;
		onViewChange?.(view);
	}

	async function duplicateView(view: TableView) {
		try {
			const response = await fetch(`/api/table-views/${view.id}/duplicate`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
				}
			});

			if (response.ok) {
				await loadViews();
			}
		} catch (error) {
			console.error('Failed to duplicate view:', error);
		}
	}

	async function deleteView(view: TableView) {
		if (!confirm(`Are you sure you want to delete the view "${view.name}"?`)) {
			return;
		}

		try {
			const response = await fetch(`/api/table-views/${view.id}`, {
				method: 'DELETE',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
				}
			});

			if (response.ok) {
				if (currentView?.id === view.id) {
					selectView(null);
				}
				await loadViews();
			}
		} catch (error) {
			console.error('Failed to delete view:', error);
		}
	}

	async function setAsDefaultView(view: TableView) {
		try {
			const response = await fetch('/api/user/preferences/default-view', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
				},
				body: JSON.stringify({
					module: module,
					view_id: view.id
				})
			});

			if (response.ok) {
				console.log(`View "${view.name}" set as default`);
			}
		} catch (error) {
			console.error('Failed to set default view:', error);
		}
	}

	async function clearDefaultView() {
		try {
			const response = await fetch('/api/user/preferences/default-view', {
				method: 'DELETE',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
				},
				body: JSON.stringify({
					module: module
				})
			});

			if (response.ok) {
				console.log('Default view cleared');
			}
		} catch (error) {
			console.error('Failed to clear default view:', error);
		}
	}

	onMount(() => {
		loadViews();
	});
</script>

<DropdownMenu.Root>
	<DropdownMenu.Trigger asChild>
		{#snippet child({ props })}
			<Button {...props} variant="outline" class="w-[200px] justify-between">
				<span class="truncate">
					{#if currentView}
						{currentView.name}
					{:else}
						All Records
					{/if}
				</span>
				<ChevronDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
			</Button>
		{/snippet}
	</DropdownMenu.Trigger>
	<DropdownMenu.Content class="w-[250px]" align="start">
		<DropdownMenu.Group>
			<DropdownMenu.Label>Views</DropdownMenu.Label>
			<DropdownMenu.Item on:click={() => selectView(null)}>
				<div class="flex items-center justify-between w-full">
					<span>All Records</span>
					{#if !currentView}
						<Check class="h-4 w-4" />
					{/if}
				</div>
			</DropdownMenu.Item>
		</DropdownMenu.Group>

		{#if views.length > 0}
			<DropdownMenu.Separator />
			<DropdownMenu.Group>
				{#each views as view (view.id)}
					<DropdownMenu.Item on:click={() => selectView(view)}>
						<div class="flex items-center justify-between w-full">
							<div class="flex items-center gap-2 min-w-0">
								{#if view.is_default}
									<Star class="h-3 w-3 fill-current shrink-0" />
								{/if}
								<span class="truncate">{view.name}</span>
								{#if view.is_public}
									<span class="text-xs text-muted-foreground shrink-0">(Public)</span>
								{/if}
							</div>
							{#if currentView?.id === view.id}
								<Check class="h-4 w-4 shrink-0" />
							{/if}
						</div>
					</DropdownMenu.Item>
				{/each}
			</DropdownMenu.Group>
		{/if}

		<DropdownMenu.Separator />
		<DropdownMenu.Group>
			{#if currentView}
				<DropdownMenu.Item on:click={onSaveView}>
					<Save class="mr-2 h-4 w-4" />
					<span>Update Current View</span>
				</DropdownMenu.Item>
				<DropdownMenu.Item on:click={() => currentView && duplicateView(currentView)}>
					<Plus class="mr-2 h-4 w-4" />
					<span>Duplicate View</span>
				</DropdownMenu.Item>
				<DropdownMenu.Separator />
				<DropdownMenu.Item on:click={() => currentView && setAsDefaultView(currentView)}>
					<Star class="mr-2 h-4 w-4" />
					<span>Set as Default</span>
				</DropdownMenu.Item>
				<DropdownMenu.Separator />
				<DropdownMenu.Item
					class="text-destructive"
					on:click={() => currentView && deleteView(currentView)}
				>
					Delete View
				</DropdownMenu.Item>
			{:else}
				<DropdownMenu.Item on:click={onCreateView}>
					<Plus class="mr-2 h-4 w-4" />
					<span>Save as New View</span>
				</DropdownMenu.Item>
			{/if}
		</DropdownMenu.Group>
	</DropdownMenu.Content>
</DropdownMenu.Root>
