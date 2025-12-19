<script lang="ts">
	import type { Cadence, CadenceStatus } from '$lib/api/cadences';
	import {
		getCadences,
		getCadenceStatuses,
		deleteCadence,
		duplicateCadence,
		activateCadence,
		pauseCadence,
		archiveCadence
	} from '$lib/api/cadences';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as Select from '$lib/components/ui/select';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Badge } from '$lib/components/ui/badge';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import {
		Plus,
		Search,
		MoreHorizontal,
		Eye,
		Edit,
		Copy,
		Trash2,
		Play,
		Pause,
		Archive,
		Workflow,
		Users
	} from 'lucide-svelte';
	import { goto } from '$app/navigation';

	let loading = $state(true);
	let cadences = $state<Cadence[]>([]);
	let cadenceStatuses = $state<Record<CadenceStatus, string>>({} as Record<CadenceStatus, string>);

	let search = $state('');
	let statusFilter = $state<CadenceStatus | ''>('');

	let currentPage = $state(1);
	let totalPages = $state(1);
	let total = $state(0);

	let deleteDialogOpen = $state(false);
	let cadenceToDelete = $state<Cadence | null>(null);
	let deleting = $state(false);

	const statusColors: Record<CadenceStatus, string> = {
		draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
		active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
		paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
		archived: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
	};

	async function loadData() {
		loading = true;
		try {
			const statusesData = await getCadenceStatuses();
			cadenceStatuses = statusesData;
			await loadCadences();
		} catch (error) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load cadences');
		} finally {
			loading = false;
		}
	}

	async function loadCadences() {
		try {
			const result = await getCadences({
				search: search || undefined,
				status: statusFilter || undefined,
				page: currentPage,
				per_page: 20
			});
			cadences = result.data;
			totalPages = result.meta.last_page;
			total = result.meta.total;
		} catch (error) {
			console.error('Failed to load cadences:', error);
		}
	}

	function handleSearch() {
		currentPage = 1;
		loadCadences();
	}

	function handleStatusChange(value: string | undefined) {
		statusFilter = (value as CadenceStatus) || '';
		currentPage = 1;
		loadCadences();
	}

	async function handleDuplicate(cadence: Cadence) {
		try {
			await duplicateCadence(cadence.id);
			toast.success('Cadence duplicated');
			loadCadences();
		} catch (error) {
			console.error('Failed to duplicate:', error);
			toast.error('Failed to duplicate cadence');
		}
	}

	async function handleActivate(cadence: Cadence) {
		try {
			await activateCadence(cadence.id);
			toast.success('Cadence activated');
			loadCadences();
		} catch (error) {
			console.error('Failed to activate:', error);
			toast.error('Failed to activate cadence');
		}
	}

	async function handlePause(cadence: Cadence) {
		try {
			await pauseCadence(cadence.id);
			toast.success('Cadence paused');
			loadCadences();
		} catch (error) {
			console.error('Failed to pause:', error);
			toast.error('Failed to pause cadence');
		}
	}

	async function handleArchive(cadence: Cadence) {
		try {
			await archiveCadence(cadence.id);
			toast.success('Cadence archived');
			loadCadences();
		} catch (error) {
			console.error('Failed to archive:', error);
			toast.error('Failed to archive cadence');
		}
	}

	function confirmDelete(cadence: Cadence) {
		cadenceToDelete = cadence;
		deleteDialogOpen = true;
	}

	async function handleDelete() {
		if (!cadenceToDelete) return;

		deleting = true;
		try {
			await deleteCadence(cadenceToDelete.id);
			toast.success('Cadence deleted');
			deleteDialogOpen = false;
			cadenceToDelete = null;
			loadCadences();
		} catch (error) {
			console.error('Failed to delete:', error);
			toast.error('Failed to delete cadence');
		} finally {
			deleting = false;
		}
	}

	$effect(() => {
		loadData();
	});
</script>

<svelte:head>
	<title>Smart Cadences | VRTX</title>
</svelte:head>

<div class="container mx-auto space-y-6 p-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-3xl font-bold tracking-tight">Smart Cadences</h1>
			<p class="text-muted-foreground">Create automated outreach sequences</p>
		</div>
		<Button href="/marketing/cadences/create">
			<Plus class="mr-2 h-4 w-4" />
			New Cadence
		</Button>
	</div>

	<!-- Filters -->
	<Card.Root>
		<Card.Content class="pt-6">
			<div class="flex flex-wrap items-center gap-4">
				<div class="relative flex-1 min-w-[200px]">
					<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
					<Input
						bind:value={search}
						placeholder="Search cadences..."
						class="pl-9"
						onkeydown={(e) => e.key === 'Enter' && handleSearch()}
					/>
				</div>

				<Select.Root type="single" value={statusFilter} onValueChange={handleStatusChange}>
					<Select.Trigger class="w-[160px]">
						<span>{statusFilter ? cadenceStatuses[statusFilter] : 'All Statuses'}</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="">All Statuses</Select.Item>
						{#each Object.entries(cadenceStatuses) as [value, label]}
							<Select.Item {value}>{label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>

				<Button variant="outline" onclick={handleSearch}>Search</Button>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Cadences Table -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if cadences.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Workflow class="h-12 w-12 text-muted-foreground" />
				<h3 class="mt-4 text-lg font-medium">No cadences found</h3>
				<p class="mt-1 text-sm text-muted-foreground">
					{search || statusFilter
						? 'Try adjusting your filters'
						: 'Get started by creating your first cadence'}
				</p>
				{#if !search && !statusFilter}
					<Button class="mt-4" href="/marketing/cadences/create">
						<Plus class="mr-2 h-4 w-4" />
						Create Cadence
					</Button>
				{/if}
			</Card.Content>
		</Card.Root>
	{:else}
		<Card.Root>
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Cadence</Table.Head>
						<Table.Head>Module</Table.Head>
						<Table.Head>Status</Table.Head>
						<Table.Head class="text-right">Steps</Table.Head>
						<Table.Head class="text-right">Active</Table.Head>
						<Table.Head class="w-[50px]"></Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each cadences as cadence}
						<Table.Row class="cursor-pointer" onclick={() => goto(`/marketing/cadences/${cadence.id}`)}>
							<Table.Cell>
								<div class="flex items-center gap-3">
									<div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
										<Workflow class="h-5 w-5 text-muted-foreground" />
									</div>
									<div>
										<p class="font-medium">{cadence.name}</p>
										{#if cadence.description}
											<p class="text-sm text-muted-foreground line-clamp-1">
												{cadence.description}
											</p>
										{/if}
									</div>
								</div>
							</Table.Cell>
							<Table.Cell>
								{cadence.module?.name ?? '-'}
							</Table.Cell>
							<Table.Cell>
								<Badge class={statusColors[cadence.status]}>
									{cadenceStatuses[cadence.status]}
								</Badge>
							</Table.Cell>
							<Table.Cell class="text-right">
								{cadence.steps_count ?? 0}
							</Table.Cell>
							<Table.Cell class="text-right">
								<div class="flex items-center justify-end gap-1">
									<Users class="h-4 w-4 text-muted-foreground" />
									{cadence.active_enrollments_count ?? 0}
								</div>
							</Table.Cell>
							<Table.Cell>
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button variant="ghost" size="icon" {...props} onclick={(e) => e.stopPropagation()}>
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => goto(`/marketing/cadences/${cadence.id}`)}>
											<Eye class="mr-2 h-4 w-4" />
											View
										</DropdownMenu.Item>
										<DropdownMenu.Item onclick={() => goto(`/marketing/cadences/${cadence.id}/edit`)}>
											<Edit class="mr-2 h-4 w-4" />
											Edit
										</DropdownMenu.Item>
										<DropdownMenu.Item onclick={() => handleDuplicate(cadence)}>
											<Copy class="mr-2 h-4 w-4" />
											Duplicate
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										{#if cadence.status === 'draft' || cadence.status === 'paused'}
											<DropdownMenu.Item onclick={() => handleActivate(cadence)}>
												<Play class="mr-2 h-4 w-4" />
												Activate
											</DropdownMenu.Item>
										{/if}
										{#if cadence.status === 'active'}
											<DropdownMenu.Item onclick={() => handlePause(cadence)}>
												<Pause class="mr-2 h-4 w-4" />
												Pause
											</DropdownMenu.Item>
										{/if}
										{#if cadence.status !== 'archived'}
											<DropdownMenu.Item onclick={() => handleArchive(cadence)}>
												<Archive class="mr-2 h-4 w-4" />
												Archive
											</DropdownMenu.Item>
										{/if}
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive"
											onclick={() => confirmDelete(cadence)}
										>
											<Trash2 class="mr-2 h-4 w-4" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</Card.Root>

		<!-- Pagination -->
		{#if totalPages > 1}
			<div class="flex items-center justify-between">
				<p class="text-sm text-muted-foreground">
					Showing {(currentPage - 1) * 20 + 1} to {Math.min(currentPage * 20, total)} of {total} cadences
				</p>
				<div class="flex gap-2">
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === 1}
						onclick={() => {
							currentPage--;
							loadCadences();
						}}
					>
						Previous
					</Button>
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === totalPages}
						onclick={() => {
							currentPage++;
							loadCadences();
						}}
					>
						Next
					</Button>
				</div>
			</div>
		{/if}
	{/if}
</div>

<!-- Delete Confirmation -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Cadence</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{cadenceToDelete?.name}"? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
				onclick={handleDelete}
				disabled={deleting}
			>
				{#if deleting}
					<Spinner class="mr-2 h-4 w-4" />
				{/if}
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
