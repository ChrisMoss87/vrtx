<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import {
		Plus,
		Search,
		MoreVertical,
		Star,
		Copy,
		Trash2,
		LayoutDashboard,
		Settings,
		Users,
		Grid3X3
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { dashboardsApi, type Dashboard } from '$lib/api/dashboards';

	let dashboards = $state<Dashboard[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let deleteDialogOpen = $state(false);
	let dashboardToDelete = $state<Dashboard | null>(null);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			dashboards = await dashboardsApi.list();
		} catch (error) {
			console.error('Failed to load dashboards:', error);
			toast.error('Failed to load dashboards');
		} finally {
			loading = false;
		}
	}

	const filteredDashboards = $derived(() => {
		if (!searchQuery) return dashboards;

		const query = searchQuery.toLowerCase();
		return dashboards.filter(
			(d) =>
				d.name.toLowerCase().includes(query) || d.description?.toLowerCase().includes(query)
		);
	});

	async function handleSetDefault(dashboard: Dashboard) {
		try {
			await dashboardsApi.setDefault(dashboard.id);
			dashboards = dashboards.map((d) => ({
				...d,
				is_default: d.id === dashboard.id
			}));
			toast.success('Default dashboard updated');
		} catch (error) {
			console.error('Failed to set default:', error);
			toast.error('Failed to set default dashboard');
		}
	}

	async function handleDuplicate(dashboard: Dashboard) {
		try {
			const duplicated = await dashboardsApi.duplicate(dashboard.id);
			dashboards = [...dashboards, duplicated];
			toast.success('Dashboard duplicated');
		} catch (error) {
			console.error('Failed to duplicate dashboard:', error);
			toast.error('Failed to duplicate dashboard');
		}
	}

	async function handleDelete() {
		if (!dashboardToDelete) return;

		try {
			await dashboardsApi.delete(dashboardToDelete.id);
			dashboards = dashboards.filter((d) => d.id !== dashboardToDelete!.id);
			toast.success('Dashboard deleted');
			deleteDialogOpen = false;
			dashboardToDelete = null;
		} catch (error) {
			console.error('Failed to delete dashboard:', error);
			toast.error('Failed to delete dashboard');
		}
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleDateString();
	}
</script>

<svelte:head>
	<title>Dashboards | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Dashboards</h1>
			<p class="text-muted-foreground">Create custom dashboards with widgets and charts</p>
		</div>
		<Button onclick={() => goto('/dashboards/new')}>
			<Plus class="mr-2 h-4 w-4" />
			Create Dashboard
		</Button>
	</div>

	<!-- Search -->
	<div class="mb-6">
		<div class="relative max-w-md">
			<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
			<Input
				type="search"
				placeholder="Search dashboards..."
				class="pl-9"
				bind:value={searchQuery}
			/>
		</div>
	</div>

	<!-- Dashboards Grid -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading dashboards...</div>
		</div>
	{:else if filteredDashboards().length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<LayoutDashboard class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="mb-2 text-lg font-medium">No dashboards found</h3>
				<p class="mb-4 text-muted-foreground">
					{searchQuery
						? 'Try adjusting your search'
						: 'Create your first dashboard to visualize your data'}
				</p>
				{#if !searchQuery}
					<Button onclick={() => goto('/dashboards/new')}>
						<Plus class="mr-2 h-4 w-4" />
						Create Dashboard
					</Button>
				{/if}
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each filteredDashboards() as dashboard (dashboard.id)}
				<Card.Root
					class="cursor-pointer transition-shadow hover:shadow-md"
					onclick={() => goto(`/dashboards/${dashboard.id}`)}
				>
					<Card.Header class="pb-3">
						<div class="flex items-start justify-between">
							<div class="flex items-center gap-3">
								<div
									class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
								>
									<LayoutDashboard class="h-5 w-5 text-primary" />
								</div>
								<div>
									<Card.Title class="text-base">{dashboard.name}</Card.Title>
									{#if dashboard.user}
										<p class="text-sm text-muted-foreground">
											by {dashboard.user.name}
										</p>
									{/if}
								</div>
							</div>
							<DropdownMenu.Root>
								<DropdownMenu.Trigger>
									{#snippet child({ props })}
										<Button
											variant="ghost"
											size="icon"
											class="h-8 w-8"
											onclick={(e) => e.stopPropagation()}
											{...props}
										>
											<MoreVertical class="h-4 w-4" />
										</Button>
									{/snippet}
								</DropdownMenu.Trigger>
								<DropdownMenu.Content align="end">
									<DropdownMenu.Item onclick={() => goto(`/dashboards/${dashboard.id}`)}>
										<LayoutDashboard class="mr-2 h-4 w-4" />
										View
									</DropdownMenu.Item>
									<DropdownMenu.Item
										onclick={() => goto(`/dashboards/${dashboard.id}?edit=true`)}
									>
										<Settings class="mr-2 h-4 w-4" />
										Edit
									</DropdownMenu.Item>
									<DropdownMenu.Separator />
									{#if !dashboard.is_default}
										<DropdownMenu.Item
											onclick={(e) => {
												e.stopPropagation();
												handleSetDefault(dashboard);
											}}
										>
											<Star class="mr-2 h-4 w-4" />
											Set as Default
										</DropdownMenu.Item>
									{/if}
									<DropdownMenu.Item
										onclick={(e) => {
											e.stopPropagation();
											handleDuplicate(dashboard);
										}}
									>
										<Copy class="mr-2 h-4 w-4" />
										Duplicate
									</DropdownMenu.Item>
									<DropdownMenu.Separator />
									<DropdownMenu.Item
										class="text-destructive focus:text-destructive"
										onclick={(e) => {
											e.stopPropagation();
											dashboardToDelete = dashboard;
											deleteDialogOpen = true;
										}}
									>
										<Trash2 class="mr-2 h-4 w-4" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.Content>
							</DropdownMenu.Root>
						</div>
					</Card.Header>
					<Card.Content>
						{#if dashboard.description}
							<p class="mb-3 text-sm text-muted-foreground line-clamp-2">
								{dashboard.description}
							</p>
						{/if}
						<div class="flex flex-wrap items-center gap-2">
							{#if dashboard.is_default}
								<Badge variant="default">
									<Star class="mr-1 h-3 w-3" />
									Default
								</Badge>
							{/if}
							{#if dashboard.is_public}
								<Badge variant="outline">
									<Users class="mr-1 h-3 w-3" />
									Public
								</Badge>
							{/if}
							<Badge variant="secondary">
								<Grid3X3 class="mr-1 h-3 w-3" />
								{dashboard.widgets_count || 0} widgets
							</Badge>
						</div>
						<div class="mt-3 text-xs text-muted-foreground">
							Updated {formatDate(dashboard.updated_at)}
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Dashboard</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{dashboardToDelete?.name}"? This will also delete all
				widgets on this dashboard. This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete}>Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
