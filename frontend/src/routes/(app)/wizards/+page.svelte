<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Badge } from '$lib/components/ui/badge';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import {
		Plus,
		Search,
		MoreHorizontal,
		Pencil,
		Copy,
		Trash2,
		Blocks,
		Power,
		PowerOff,
		GripVertical
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getWizards,
		deleteWizard,
		duplicateWizard,
		toggleWizardActive,
		type Wizard
	} from '$lib/api/wizards';
	import { getActiveModules, type Module } from '$lib/api/modules';

	let wizards = $state<Wizard[]>([]);
	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let filterType = $state<string>('all');
	let filterModule = $state<string>('all');
	let deleteDialogOpen = $state(false);
	let wizardToDelete = $state<Wizard | null>(null);

	const filteredWizards = $derived(() => {
		return wizards.filter((wizard) => {
			const matchesSearch =
				wizard.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
				wizard.description?.toLowerCase().includes(searchQuery.toLowerCase());
			const matchesType = filterType === 'all' || wizard.type === filterType;
			const matchesModule =
				filterModule === 'all' ||
				(filterModule === 'standalone' && !wizard.module) ||
				wizard.module?.id.toString() === filterModule;
			return matchesSearch && matchesType && matchesModule;
		});
	});

	async function loadData() {
		loading = true;
		try {
			const [wizardsData, modulesData] = await Promise.all([getWizards(), getActiveModules()]);
			wizards = wizardsData;
			modules = modulesData;
		} catch (error) {
			console.error('Failed to load wizards:', error);
			toast.error('Failed to load wizards');
		} finally {
			loading = false;
		}
	}

	async function handleDuplicate(wizard: Wizard) {
		try {
			const duplicated = await duplicateWizard(wizard.id);
			wizards = [...wizards, duplicated];
			toast.success(`Wizard "${duplicated.name}" created`);
		} catch (error) {
			console.error('Failed to duplicate wizard:', error);
			toast.error('Failed to duplicate wizard');
		}
	}

	async function handleToggleActive(wizard: Wizard) {
		try {
			const updated = await toggleWizardActive(wizard.id);
			wizards = wizards.map((w) => (w.id === updated.id ? updated : w));
			toast.success(updated.is_active ? 'Wizard activated' : 'Wizard deactivated');
		} catch (error) {
			console.error('Failed to toggle wizard status:', error);
			toast.error('Failed to update wizard status');
		}
	}

	function confirmDelete(wizard: Wizard) {
		wizardToDelete = wizard;
		deleteDialogOpen = true;
	}

	async function handleDelete() {
		if (!wizardToDelete) return;
		try {
			await deleteWizard(wizardToDelete.id);
			wizards = wizards.filter((w) => w.id !== wizardToDelete!.id);
			toast.success('Wizard deleted');
		} catch (error) {
			console.error('Failed to delete wizard:', error);
			toast.error('Failed to delete wizard');
		} finally {
			deleteDialogOpen = false;
			wizardToDelete = null;
		}
	}

	function getTypeLabel(type: string): string {
		switch (type) {
			case 'record_creation':
				return 'Record Creation';
			case 'record_edit':
				return 'Record Edit';
			case 'standalone':
				return 'Standalone';
			default:
				return type;
		}
	}

	function getTypeBadgeVariant(
		type: string
	): 'default' | 'secondary' | 'destructive' | 'outline' | null | undefined {
		switch (type) {
			case 'record_creation':
				return 'default';
			case 'record_edit':
				return 'secondary';
			case 'standalone':
				return 'outline';
			default:
				return 'secondary';
		}
	}

	onMount(() => {
		loadData();
	});
</script>

<div class="container mx-auto py-8">
	<!-- Header -->
	<div class="mb-8 flex items-center justify-between">
		<div>
			<div class="flex items-center gap-2">
				<Blocks class="h-6 w-6 text-primary" />
				<h1 class="text-3xl font-bold">Wizards</h1>
			</div>
			<p class="mt-1 text-muted-foreground">
				Create and manage multi-step form wizards for your modules
			</p>
		</div>
		<Button onclick={() => goto('/wizards/create')}>
			<Plus class="mr-2 h-4 w-4" />
			Create Wizard
		</Button>
	</div>

	<!-- Filters -->
	<div class="mb-6 flex flex-wrap items-center gap-4">
		<div class="relative flex-1 max-w-sm">
			<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
			<Input bind:value={searchQuery} placeholder="Search wizards..." class="pl-10" />
		</div>

		<Select.Root type="single" bind:value={filterType}>
			<Select.Trigger class="w-[180px]">
				{filterType === 'all' ? 'All Types' : getTypeLabel(filterType)}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="all">All Types</Select.Item>
				<Select.Item value="record_creation">Record Creation</Select.Item>
				<Select.Item value="record_edit">Record Edit</Select.Item>
				<Select.Item value="standalone">Standalone</Select.Item>
			</Select.Content>
		</Select.Root>

		<Select.Root type="single" bind:value={filterModule}>
			<Select.Trigger class="w-[180px]">
				{filterModule === 'all'
					? 'All Modules'
					: filterModule === 'standalone'
						? 'Standalone'
						: modules.find((m) => m.id.toString() === filterModule)?.name || 'Module'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="all">All Modules</Select.Item>
				<Select.Item value="standalone">Standalone</Select.Item>
				{#each modules as module}
					<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Wizards Grid -->
	{#if loading}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each Array(6) as _}
				<Card.Root class="animate-pulse">
					<Card.Header>
						<div class="h-6 bg-muted rounded w-3/4"></div>
						<div class="h-4 bg-muted rounded w-1/2 mt-2"></div>
					</Card.Header>
					<Card.Content>
						<div class="h-4 bg-muted rounded w-full"></div>
						<div class="h-4 bg-muted rounded w-2/3 mt-2"></div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{:else if filteredWizards().length === 0}
		<Card.Root class="py-12">
			<Card.Content class="text-center">
				<Blocks class="mx-auto h-12 w-12 text-muted-foreground/50" />
				<h3 class="mt-4 text-lg font-semibold">No wizards found</h3>
				<p class="mt-2 text-sm text-muted-foreground">
					{searchQuery || filterType !== 'all' || filterModule !== 'all'
						? 'Try adjusting your filters'
						: 'Get started by creating your first wizard'}
				</p>
				{#if !searchQuery && filterType === 'all' && filterModule === 'all'}
					<Button class="mt-4" onclick={() => goto('/wizards/create')}>
						<Plus class="mr-2 h-4 w-4" />
						Create Wizard
					</Button>
				{/if}
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each filteredWizards() as wizard}
				<Card.Root
					class="group relative transition-shadow hover:shadow-md {!wizard.is_active
						? 'opacity-60'
						: ''}"
				>
					<Card.Header class="pb-3">
						<div class="flex items-start justify-between">
							<div class="flex-1 min-w-0">
								<Card.Title class="flex items-center gap-2">
									<span class="truncate">{wizard.name}</span>
									{#if wizard.is_default}
										<Badge variant="secondary" class="text-xs">Default</Badge>
									{/if}
								</Card.Title>
								<Card.Description class="mt-1 line-clamp-2">
									{wizard.description || 'No description'}
								</Card.Description>
							</div>
							<DropdownMenu.Root>
								<DropdownMenu.Trigger>
									<Button variant="ghost" size="icon" class="h-8 w-8">
										<MoreHorizontal class="h-4 w-4" />
									</Button>
								</DropdownMenu.Trigger>
								<DropdownMenu.Content align="end">
									<DropdownMenu.Item onclick={() => goto(`/wizards/${wizard.id}/edit`)}>
										<Pencil class="mr-2 h-4 w-4" />
										Edit
									</DropdownMenu.Item>
									<DropdownMenu.Item onclick={() => handleDuplicate(wizard)}>
										<Copy class="mr-2 h-4 w-4" />
										Duplicate
									</DropdownMenu.Item>
									<DropdownMenu.Item onclick={() => handleToggleActive(wizard)}>
										{#if wizard.is_active}
											<PowerOff class="mr-2 h-4 w-4" />
											Deactivate
										{:else}
											<Power class="mr-2 h-4 w-4" />
											Activate
										{/if}
									</DropdownMenu.Item>
									<DropdownMenu.Separator />
									<DropdownMenu.Item
										class="text-destructive"
										onclick={() => confirmDelete(wizard)}
									>
										<Trash2 class="mr-2 h-4 w-4" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.Content>
							</DropdownMenu.Root>
						</div>
					</Card.Header>
					<Card.Content>
						<div class="flex flex-wrap gap-2 mb-3">
							<Badge variant={getTypeBadgeVariant(wizard.type)}>
								{getTypeLabel(wizard.type)}
							</Badge>
							{#if wizard.module}
								<Badge variant="outline">{wizard.module.name}</Badge>
							{/if}
							{#if !wizard.is_active}
								<Badge variant="destructive">Inactive</Badge>
							{/if}
						</div>

						<div class="flex items-center gap-4 text-sm text-muted-foreground">
							<span>{wizard.step_count} step{wizard.step_count !== 1 ? 's' : ''}</span>
							<span>{wizard.field_count} field{wizard.field_count !== 1 ? 's' : ''}</span>
						</div>

						{#if wizard.creator}
							<div class="mt-3 text-xs text-muted-foreground">
								Created by {wizard.creator.name}
							</div>
						{/if}
					</Card.Content>

					<!-- Click overlay for edit -->
					<button
						class="absolute inset-0 z-0 cursor-pointer"
						onclick={() => goto(`/wizards/${wizard.id}/edit`)}
						aria-label="Edit {wizard.name}"
					></button>
				</Card.Root>
			{/each}
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Wizard</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{wizardToDelete?.name}"? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete} class="bg-destructive text-destructive-foreground">
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
