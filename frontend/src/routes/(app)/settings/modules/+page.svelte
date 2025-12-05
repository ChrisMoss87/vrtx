<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import { getModules, modulesApi, type Module } from '$lib/api/modules';
	import { getIconComponent } from '$lib/utils/icons';
	import GripVerticalIcon from '@lucide/svelte/icons/grip-vertical';
	import ArrowUpIcon from '@lucide/svelte/icons/arrow-up';
	import ArrowDownIcon from '@lucide/svelte/icons/arrow-down';
	import SaveIcon from '@lucide/svelte/icons/save';
	import RotateCcwIcon from '@lucide/svelte/icons/rotate-ccw';
	import SettingsIcon from '@lucide/svelte/icons/settings';
	import EyeIcon from '@lucide/svelte/icons/eye';
	import EyeOffIcon from '@lucide/svelte/icons/eye-off';

	let modules = $state<Module[]>([]);
	let originalModules = $state<Module[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let hasChanges = $state(false);

	// Drag state
	let draggedIndex = $state<number | null>(null);
	let dragOverIndex = $state<number | null>(null);

	// Check if there are unsaved changes
	$effect(() => {
		if (originalModules.length === 0) {
			hasChanges = false;
			return;
		}

		hasChanges = modules.some((mod, idx) => {
			const orig = originalModules.find((m) => m.id === mod.id);
			return !orig || orig.display_order !== idx || orig.is_active !== mod.is_active;
		});
	});

	async function loadModules() {
		try {
			loading = true;
			const loadedModules = await getModules();
			// Sort by display_order
			modules = loadedModules.sort((a, b) => a.display_order - b.display_order);
			originalModules = JSON.parse(JSON.stringify(modules));
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Failed to load modules');
		} finally {
			loading = false;
		}
	}

	function moveModule(index: number, direction: 'up' | 'down') {
		const newIndex = direction === 'up' ? index - 1 : index + 1;
		if (newIndex < 0 || newIndex >= modules.length) return;

		const newModules = [...modules];
		[newModules[index], newModules[newIndex]] = [newModules[newIndex], newModules[index]];
		modules = newModules;
	}

	function handleDragStart(index: number, e: DragEvent) {
		draggedIndex = index;
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', String(index));
		}
	}

	function handleDragOver(index: number, e: DragEvent) {
		e.preventDefault();
		if (e.dataTransfer) {
			e.dataTransfer.dropEffect = 'move';
		}
		dragOverIndex = index;
	}

	function handleDragLeave() {
		dragOverIndex = null;
	}

	function handleDrop(index: number, e: DragEvent) {
		e.preventDefault();
		if (draggedIndex === null || draggedIndex === index) {
			draggedIndex = null;
			dragOverIndex = null;
			return;
		}

		const newModules = [...modules];
		const [removed] = newModules.splice(draggedIndex, 1);
		newModules.splice(index, 0, removed);
		modules = newModules;

		draggedIndex = null;
		dragOverIndex = null;
	}

	function handleDragEnd() {
		draggedIndex = null;
		dragOverIndex = null;
	}

	function toggleActive(index: number) {
		modules = modules.map((mod, idx) =>
			idx === index ? { ...mod, is_active: !mod.is_active } : mod
		);
	}

	async function saveChanges() {
		try {
			saving = true;

			// Build the reorder payload with updated display_order
			const reorderPayload = modules.map((mod, idx) => ({
				id: mod.id,
				display_order: idx
			}));

			// Update display orders
			await modulesApi.reorder(reorderPayload);

			// Update active status for each module that changed
			for (const mod of modules) {
				const orig = originalModules.find((m) => m.id === mod.id);
				if (orig && orig.is_active !== mod.is_active) {
					await modulesApi.toggleStatus(mod.id);
				}
			}

			// Update original state
			originalModules = JSON.parse(JSON.stringify(modules.map((m, idx) => ({ ...m, display_order: idx }))));

			toast.success('Module order saved successfully');
		} catch (error) {
			console.error('Failed to save changes:', error);
			toast.error('Failed to save changes');
		} finally {
			saving = false;
		}
	}

	function resetChanges() {
		modules = JSON.parse(JSON.stringify(originalModules));
	}

	onMount(loadModules);
</script>

<svelte:head>
	<title>Module Settings | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto max-w-4xl py-8">
	<div class="mb-8 flex items-center justify-between">
		<div>
			<h1 class="text-3xl font-bold tracking-tight">Module Settings</h1>
			<p class="mt-2 text-muted-foreground">
				Configure which modules appear in the sidebar and their display order.
			</p>
		</div>
		<div class="flex items-center gap-2">
			{#if hasChanges}
				<Button variant="outline" onclick={resetChanges} disabled={saving}>
					<RotateCcwIcon class="mr-2 h-4 w-4" />
					Reset
				</Button>
				<Button onclick={saveChanges} disabled={saving}>
					{#if saving}
						<Spinner class="mr-2 h-4 w-4" />
					{:else}
						<SaveIcon class="mr-2 h-4 w-4" />
					{/if}
					Save Changes
				</Button>
			{/if}
		</div>
	</div>

	{#if loading}
		<Card.Root>
			<Card.Content class="flex items-center justify-center py-12">
				<Spinner class="h-8 w-8" />
			</Card.Content>
		</Card.Root>
	{:else if modules.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12 text-center">
				<SettingsIcon class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="text-lg font-medium">No Modules Found</h3>
				<p class="mt-1 text-sm text-muted-foreground">
					Create modules in the Module Builder to manage them here.
				</p>
				<Button variant="outline" class="mt-4" href="/modules/create-builder">
					Create Module
				</Button>
			</Card.Content>
		</Card.Root>
	{:else}
		<Card.Root>
			<Card.Header>
				<Card.Title>Module Order</Card.Title>
				<Card.Description>
					Drag modules to reorder them or use the arrow buttons. The order determines how they appear in the sidebar CRM menu.
				</Card.Description>
			</Card.Header>
			<Card.Content>
				<div class="space-y-2">
					{#each modules as module, index (module.id)}
						{@const IconComponent = getIconComponent(module.icon)}
						<div
							class="flex items-center gap-3 rounded-lg border p-3 transition-all {dragOverIndex === index ? 'border-primary bg-accent/50' : ''} {draggedIndex === index ? 'opacity-50' : ''} {!module.is_active ? 'bg-muted/50' : ''}"
							draggable="true"
							ondragstart={(e) => handleDragStart(index, e)}
							ondragover={(e) => handleDragOver(index, e)}
							ondragleave={handleDragLeave}
							ondrop={(e) => handleDrop(index, e)}
							ondragend={handleDragEnd}
							role="listitem"
						>
							<!-- Drag Handle -->
							<div class="cursor-grab text-muted-foreground hover:text-foreground">
								<GripVerticalIcon class="h-5 w-5" />
							</div>

							<!-- Position Number -->
							<Badge variant="outline" class="min-w-8 justify-center">
								{index + 1}
							</Badge>

							<!-- Module Icon -->
							<div class="flex h-9 w-9 items-center justify-center rounded-md bg-accent">
								<IconComponent class="h-5 w-5" />
							</div>

							<!-- Module Info -->
							<div class="flex-1">
								<div class="font-medium">{module.name}</div>
								<div class="text-sm text-muted-foreground">{module.api_name}</div>
							</div>

							<!-- Active Toggle -->
							<div class="flex items-center gap-2">
								{#if module.is_active}
									<EyeIcon class="h-4 w-4 text-muted-foreground" />
								{:else}
									<EyeOffIcon class="h-4 w-4 text-muted-foreground" />
								{/if}
								<Switch
									checked={module.is_active}
									onCheckedChange={() => toggleActive(index)}
								/>
							</div>

							<!-- Move Buttons -->
							<div class="flex flex-col gap-0.5">
								<Button
									variant="ghost"
									size="icon"
									class="h-6 w-6"
									disabled={index === 0}
									onclick={() => moveModule(index, 'up')}
								>
									<ArrowUpIcon class="h-3 w-3" />
								</Button>
								<Button
									variant="ghost"
									size="icon"
									class="h-6 w-6"
									disabled={index === modules.length - 1}
									onclick={() => moveModule(index, 'down')}
								>
									<ArrowDownIcon class="h-3 w-3" />
								</Button>
							</div>
						</div>
					{/each}
				</div>
			</Card.Content>
		</Card.Root>

		{#if hasChanges}
			<div class="mt-4 flex items-center justify-between rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950">
				<p class="text-sm text-yellow-800 dark:text-yellow-200">
					You have unsaved changes. Click "Save Changes" to apply them.
				</p>
				<Button size="sm" onclick={saveChanges} disabled={saving}>
					{#if saving}
						<Spinner class="mr-2 h-3 w-3" />
					{/if}
					Save
				</Button>
			</div>
		{/if}
	{/if}
</div>
