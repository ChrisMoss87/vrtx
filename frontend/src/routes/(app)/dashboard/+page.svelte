<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { authStore } from '$lib/stores/auth.svelte';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import {
		Users,
		TrendingUp,
		Package,
		LayoutDashboard,
		ArrowRight,
		Plus,
		Loader2,
		AlertCircle
	} from 'lucide-svelte';

	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	// Module stats with counts (would come from API in production)
	let stats = $state<{ name: string; apiName: string; count: number; icon: typeof Users }[]>([]);

	onMount(async () => {
		try {
			const allModules = await modulesApi.getActive();
			modules = allModules;

			// Create stats from active modules
			stats = allModules.slice(0, 4).map((mod) => ({
				name: mod.name,
				apiName: mod.api_name,
				count: 0, // Would be fetched from a counts endpoint
				icon: getIconForModule(mod.api_name)
			}));
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load dashboard data';
		} finally {
			loading = false;
		}
	});

	function getIconForModule(apiName: string): typeof Users {
		const icons: Record<string, typeof Users> = {
			contacts: Users,
			deals: TrendingUp,
			products: Package
		};
		return icons[apiName] || LayoutDashboard;
	}

	function navigateToModule(apiName: string) {
		goto(`/records/${apiName}`);
	}
</script>

<svelte:head>
	<title>Dashboard - VRTX CRM</title>
</svelte:head>

<div class="space-y-8">
	<!-- Header -->
	<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
		<div>
			<h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>
			<p class="text-muted-foreground">
				Welcome back{authStore.user?.name ? `, ${authStore.user.name}` : ''}. Here's an overview of
				your CRM.
			</p>
		</div>
		<Button onclick={() => goto('/modules/create-builder')}>
			<Plus class="mr-2 h-4 w-4" />
			New Module
		</Button>
	</div>

	<!-- Stats Grid -->
	{#if loading}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
			{#each [1, 2, 3, 4] as _}
				<Card.Root>
					<Card.Header class="flex flex-row items-center justify-between space-y-0 pb-2">
						<Skeleton class="h-4 w-24" />
						<Skeleton class="h-4 w-4 rounded" />
					</Card.Header>
					<Card.Content>
						<Skeleton class="h-8 w-16" />
						<Skeleton class="mt-2 h-3 w-32" />
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{:else if error}
		<Card.Root class="border-destructive/50 bg-destructive/5">
			<Card.Content class="flex items-center gap-4 pt-6">
				<AlertCircle class="h-8 w-8 text-destructive" />
				<div>
					<p class="font-medium text-destructive">Failed to load dashboard</p>
					<p class="text-sm text-muted-foreground">{error}</p>
				</div>
			</Card.Content>
		</Card.Root>
	{:else if stats.length > 0}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
			{#each stats as stat}
				{@const Icon = stat.icon}
				<Card.Root
					class="cursor-pointer transition-colors hover:bg-accent/50"
					onclick={() => navigateToModule(stat.apiName)}
				>
					<Card.Header class="flex flex-row items-center justify-between space-y-0 pb-2">
						<Card.Title class="text-sm font-medium">{stat.name}</Card.Title>
						<Icon class="h-4 w-4 text-muted-foreground" />
					</Card.Header>
					<Card.Content>
						<div class="text-2xl font-bold">{stat.count}</div>
						<p class="text-xs text-muted-foreground">
							View all {stat.name.toLowerCase()}
						</p>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{:else}
		<!-- No modules yet -->
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12 text-center">
				<div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
					<LayoutDashboard class="h-8 w-8 text-muted-foreground" />
				</div>
				<h3 class="mt-4 font-semibold">No modules yet</h3>
				<p class="mt-2 max-w-sm text-sm text-muted-foreground">
					Get started by creating your first module to manage your data.
				</p>
				<Button class="mt-4" onclick={() => goto('/modules/create-builder')}>
					<Plus class="mr-2 h-4 w-4" />
					Create your first module
				</Button>
			</Card.Content>
		</Card.Root>
	{/if}

	<!-- Quick Actions -->
	<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
		<Card.Root>
			<Card.Header>
				<Card.Title>Quick Actions</Card.Title>
				<Card.Description>Common tasks to get you started</Card.Description>
			</Card.Header>
			<Card.Content class="grid gap-2">
				<Button variant="outline" class="justify-start" onclick={() => goto('/modules')}>
					<LayoutDashboard class="mr-2 h-4 w-4" />
					Manage Modules
					<ArrowRight class="ml-auto h-4 w-4" />
				</Button>
				<Button variant="outline" class="justify-start" onclick={() => goto('/records/contacts')}>
					<Users class="mr-2 h-4 w-4" />
					View Contacts
					<ArrowRight class="ml-auto h-4 w-4" />
				</Button>
				<Button variant="outline" class="justify-start" onclick={() => goto('/records/deals')}>
					<TrendingUp class="mr-2 h-4 w-4" />
					View Deals
					<ArrowRight class="ml-auto h-4 w-4" />
				</Button>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Header>
				<Card.Title>Active Modules</Card.Title>
				<Card.Description>Your configured CRM modules</Card.Description>
			</Card.Header>
			<Card.Content>
				{#if loading}
					<div class="space-y-2">
						{#each [1, 2, 3] as _}
							<Skeleton class="h-8 w-full" />
						{/each}
					</div>
				{:else if modules.length > 0}
					<div class="space-y-2">
						{#each modules.slice(0, 5) as mod}
							<Button
								variant="ghost"
								class="w-full justify-start"
								onclick={() => navigateToModule(mod.api_name)}
							>
								<LayoutDashboard class="mr-2 h-4 w-4" />
								{mod.name}
							</Button>
						{/each}
						{#if modules.length > 5}
							<Button variant="link" class="w-full" onclick={() => goto('/modules')}>
								View all {modules.length} modules
							</Button>
						{/if}
					</div>
				{:else}
					<p class="text-sm text-muted-foreground">No active modules</p>
				{/if}
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Header>
				<Card.Title>Recent Activity</Card.Title>
				<Card.Description>Your latest CRM activity</Card.Description>
			</Card.Header>
			<Card.Content>
				<p class="text-sm text-muted-foreground">
					Activity tracking coming soon. You'll see your recent actions here.
				</p>
			</Card.Content>
		</Card.Root>
	</div>
</div>
