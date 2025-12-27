<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { license } from '$lib/stores/license';
	import { apiClient } from '$lib/api/client';
	import { PlanBadge } from '$lib/components/billing';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import * as Tabs from '$lib/components/ui/tabs';
	import { toast } from 'svelte-sonner';
	import Search from 'lucide-svelte/icons/search';
	import Check from 'lucide-svelte/icons/check';
	import Package from 'lucide-svelte/icons/package';
	import Sparkles from 'lucide-svelte/icons/sparkles';
	import Lock from 'lucide-svelte/icons/lock';
	import ArrowLeft from 'lucide-svelte/icons/arrow-left';
	import ArrowRight from 'lucide-svelte/icons/arrow-right';
	import Settings from 'lucide-svelte/icons/settings';
	import PartyPopper from 'lucide-svelte/icons/party-popper';

	interface Plugin {
		id: number;
		slug: string;
		name: string;
		description: string;
		category: string;
		tier: string;
		pricing_model: string;
		price_monthly: number;
		price_yearly: number;
		is_active: boolean;
		features: string[];
	}

	interface Bundle {
		id: number;
		slug: string;
		name: string;
		description: string;
		price_monthly: number;
		price_yearly: number;
		discount_percentage: number;
		plugins: Plugin[];
	}

	interface NextStep {
		title: string;
		description: string;
		action: string;
		path: string | null;
	}

	interface ActivationResult {
		plugin: { name: string; slug: string; description: string; category: string };
		features_unlocked: string[];
		settings_path: string | null;
		next_steps: NextStep[];
	}

	let plugins: Plugin[] = $state([]);
	let bundles: Bundle[] = $state([]);
	let licensedPluginSlugs: string[] = $state([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let activeCategory = $state('all');

	// Activation success dialog state
	let showActivationSuccess = $state(false);
	let activationResult: ActivationResult | null = $state(null);

	// Get highlighted plugin from URL
	const highlightedPlugin = $derived($page.url.searchParams.get('highlight'));

	// Categories
	const categories = [
		{ value: 'all', label: 'All Plugins' },
		{ value: 'sales', label: 'Sales' },
		{ value: 'marketing', label: 'Marketing' },
		{ value: 'analytics', label: 'Analytics' },
		{ value: 'communication', label: 'Communication' },
		{ value: 'productivity', label: 'Productivity' },
		{ value: 'integration', label: 'Integration' }
	];

	// Tier colors
	const tierColors: Record<string, string> = {
		core: 'bg-slate-100 text-slate-700',
		starter: 'bg-blue-100 text-blue-700',
		professional: 'bg-purple-100 text-purple-700',
		business: 'bg-amber-100 text-amber-700',
		enterprise: 'bg-gradient-to-r from-indigo-500 to-purple-500 text-white'
	};

	async function loadPlugins() {
		try {
			const [pluginsRes, licensesRes, bundlesRes] = await Promise.all([
				apiClient.get<{ plugins: Plugin[]; by_category: { category: string; plugins: Plugin[] }[] }>('/billing/plugins'),
				apiClient.get<{ licenses: { plugin_slug: string }[] }>('/billing/plugins/licenses'),
				apiClient.get<{ bundles: Bundle[] }>('/billing/bundles')
			]);

			plugins = pluginsRes.plugins || [];
			licensedPluginSlugs = (licensesRes.licenses || []).map((l) => l.plugin_slug);
			bundles = bundlesRes.bundles || [];
		} catch (error) {
			console.error('Failed to load plugins:', error);
			toast.error('Failed to load plugins');
		} finally {
			loading = false;
		}
	}

	async function activatePlugin(slug: string) {
		try {
			const response = await apiClient.post<{
				message: string;
				plugin: ActivationResult['plugin'];
				features_unlocked: string[];
				settings_path: string | null;
				next_steps: NextStep[];
			}>(`/billing/plugins/${slug}/activate`);

			licensedPluginSlugs = [...licensedPluginSlugs, slug];
			await license.load(); // Refresh license state

			// Show activation success dialog
			activationResult = {
				plugin: response.plugin,
				features_unlocked: response.features_unlocked || [],
				settings_path: response.settings_path,
				next_steps: response.next_steps || []
			};
			showActivationSuccess = true;
		} catch (error: any) {
			toast.error(error.message || 'Failed to activate plugin');
		}
	}

	function closeActivationDialog() {
		showActivationSuccess = false;
		activationResult = null;
	}

	function goToSettings() {
		if (activationResult?.settings_path) {
			goto(activationResult.settings_path);
			closeActivationDialog();
		}
	}

	async function deactivatePlugin(slug: string) {
		try {
			await apiClient.delete(`/billing/plugins/${slug}`);
			licensedPluginSlugs = licensedPluginSlugs.filter((s) => s !== slug);
			await license.load(); // Refresh license state
			toast.success('Plugin deactivated');
		} catch (error: any) {
			toast.error(error.message || 'Failed to deactivate plugin');
		}
	}

	// Filter plugins
	const filteredPlugins = $derived.by(() => {
		let result = plugins;

		if (activeCategory !== 'all') {
			result = result.filter((p) => p.category === activeCategory);
		}

		if (searchQuery) {
			const query = searchQuery.toLowerCase();
			result = result.filter(
				(p) =>
					p.name.toLowerCase().includes(query) ||
					p.description.toLowerCase().includes(query)
			);
		}

		return result;
	});

	// Check if plugin is licensed
	function isLicensed(slug: string): boolean {
		return licensedPluginSlugs.includes(slug) || $license.plugins.includes(slug);
	}

	// Check if user can activate (meets plan requirements)
	function canActivate(plugin: Plugin): boolean {
		// Check if current plan meets tier requirement
		return license.hasPlan(plugin.tier);
	}

	onMount(() => {
		loadPlugins();
	});
</script>

<svelte:head>
	<title>Plugins & Add-ons | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6 max-w-6xl">
	<!-- Header -->
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" href="/settings/billing">
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div class="flex-1">
			<h1 class="text-2xl font-bold tracking-tight">Plugins & Add-ons</h1>
			<p class="text-muted-foreground">Extend your CRM with powerful add-ons</p>
		</div>
		<PlanBadge showStatus={false} />
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-pulse">Loading plugins...</div>
		</div>
	{:else}
		<Tabs.Root value="plugins">
			<Tabs.List>
				<Tabs.Trigger value="plugins">Individual Plugins</Tabs.Trigger>
				<Tabs.Trigger value="bundles">Bundles</Tabs.Trigger>
				<Tabs.Trigger value="active">Active ({licensedPluginSlugs.length})</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="plugins" class="space-y-6 pt-4">
				<!-- Search and Filters -->
				<div class="flex flex-col sm:flex-row gap-4">
					<div class="relative flex-1">
						<Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
						<Input
							bind:value={searchQuery}
							placeholder="Search plugins..."
							class="pl-10"
						/>
					</div>
					<div class="flex gap-2 flex-wrap">
						{#each categories as category}
							<Button
								variant={activeCategory === category.value ? 'default' : 'outline'}
								size="sm"
								onclick={() => (activeCategory = category.value)}
							>
								{category.label}
							</Button>
						{/each}
					</div>
				</div>

				<!-- Plugins Grid -->
				<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
					{#each filteredPlugins as plugin (plugin.id)}
						{@const licensed = isLicensed(plugin.slug)}
						{@const canActivatePlugin = canActivate(plugin)}
						<Card.Root
							class="relative {highlightedPlugin === plugin.slug
								? 'ring-2 ring-primary'
								: ''}"
						>
							{#if licensed}
								<div class="absolute top-3 right-3">
									<span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
										<Check class="h-3 w-3" />
										Active
									</span>
								</div>
							{/if}
							<Card.Header class="pb-2">
								<div class="flex items-start justify-between">
									<div>
										<Card.Title class="text-lg">{plugin.name}</Card.Title>
										<span class="inline-block mt-1 text-xs rounded-full px-2 py-0.5 {tierColors[plugin.tier] || tierColors.core}">
											{plugin.tier}
										</span>
									</div>
								</div>
							</Card.Header>
							<Card.Content class="pb-2">
								<p class="text-sm text-muted-foreground line-clamp-2">
									{plugin.description}
								</p>
								<div class="mt-3">
									<p class="text-2xl font-bold">
										${Number(plugin.price_monthly) || 0}
										<span class="text-sm font-normal text-muted-foreground">/mo</span>
									</p>
									{#if Number(plugin.price_yearly) > 0 && Number(plugin.price_monthly) > 0}
										<p class="text-xs text-muted-foreground">
											or ${plugin.price_yearly}/year (save {Math.round((1 - Number(plugin.price_yearly) / (Number(plugin.price_monthly) * 12)) * 100)}%)
										</p>
									{/if}
								</div>
							</Card.Content>
							<Card.Footer class="pt-2">
								{#if licensed}
									<Button
										variant="outline"
										size="sm"
										class="w-full"
										onclick={() => deactivatePlugin(plugin.slug)}
									>
										Deactivate
									</Button>
								{:else if canActivatePlugin}
									<Button
										size="sm"
										class="w-full"
										onclick={() => activatePlugin(plugin.slug)}
									>
										<Sparkles class="mr-2 h-4 w-4" />
										Activate
									</Button>
								{:else}
									<Button
										variant="secondary"
										size="sm"
										class="w-full"
										href="/settings/billing/upgrade"
									>
										<Lock class="mr-2 h-4 w-4" />
										Requires {plugin.tier}
									</Button>
								{/if}
							</Card.Footer>
						</Card.Root>
					{:else}
						<div class="col-span-full text-center py-12 text-muted-foreground">
							No plugins found matching your criteria
						</div>
					{/each}
				</div>
			</Tabs.Content>

			<Tabs.Content value="bundles" class="space-y-6 pt-4">
				<p class="text-muted-foreground">
					Save money by purchasing plugin bundles. Get 17-25% off compared to individual pricing.
				</p>

				<div class="grid gap-6 md:grid-cols-2">
					{#each bundles as bundle (bundle.id)}
						<Card.Root class="relative overflow-hidden">
							<div class="absolute top-0 right-0 bg-green-500 text-white text-xs px-3 py-1 rounded-bl-lg font-medium">
								Save {bundle.discount_percentage}%
							</div>
							<Card.Header>
								<Card.Title>{bundle.name}</Card.Title>
								<Card.Description>{bundle.description}</Card.Description>
							</Card.Header>
							<Card.Content>
								<div class="mb-4">
									<p class="text-3xl font-bold">
										${Number(bundle.price_monthly) || 0}
										<span class="text-sm font-normal text-muted-foreground">/mo</span>
									</p>
									<p class="text-sm text-muted-foreground">
										or ${Number(bundle.price_yearly) || 0}/year
									</p>
								</div>

								<div class="space-y-2">
									<p class="text-sm font-medium">Includes:</p>
									<div class="flex flex-wrap gap-1">
										{#each bundle.plugins as plugin}
											<span class="inline-flex items-center rounded-full bg-muted px-2 py-1 text-xs">
												{plugin.name}
											</span>
										{/each}
									</div>
								</div>
							</Card.Content>
							<Card.Footer>
								<Button class="w-full">
									<Package class="mr-2 h-4 w-4" />
									Get Bundle
								</Button>
							</Card.Footer>
						</Card.Root>
					{:else}
						<div class="col-span-full text-center py-12 text-muted-foreground">
							No bundles available
						</div>
					{/each}
				</div>
			</Tabs.Content>

			<Tabs.Content value="active" class="space-y-6 pt-4">
				{#if licensedPluginSlugs.length === 0}
					<Card.Root>
						<Card.Content class="py-12 text-center">
							<Package class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
							<h3 class="text-lg font-semibold">No Active Plugins</h3>
							<p class="text-muted-foreground mb-4">
								You haven't activated any plugins yet. Browse the catalog to get started.
							</p>
							<Button onclick={() => (activeCategory = 'all')}>
								Browse Plugins
							</Button>
						</Card.Content>
					</Card.Root>
				{:else}
					<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
						{#each plugins.filter((p) => isLicensed(p.slug)) as plugin (plugin.id)}
							<Card.Root>
								<Card.Header class="pb-2">
									<div class="flex items-center justify-between">
										<Card.Title class="text-lg">{plugin.name}</Card.Title>
										<span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
											<Check class="h-3 w-3" />
											Active
										</span>
									</div>
								</Card.Header>
								<Card.Content class="pb-2">
									<p class="text-sm text-muted-foreground">
										{plugin.description}
									</p>
									<p class="mt-2 text-sm">
										<span class="font-medium">${Number(plugin.price_monthly) || 0}</span>/month
									</p>
								</Card.Content>
								<Card.Footer class="pt-2">
									<Button
										variant="outline"
										size="sm"
										class="w-full"
										onclick={() => deactivatePlugin(plugin.slug)}
									>
										Deactivate
									</Button>
								</Card.Footer>
							</Card.Root>
						{/each}
					</div>

					<Card.Root>
						<Card.Content class="py-4">
							<div class="flex items-center justify-between">
								<div>
									<p class="font-medium">Total Monthly Cost</p>
									<p class="text-sm text-muted-foreground">
										{licensedPluginSlugs.length} active plugin(s)
									</p>
								</div>
								<p class="text-2xl font-bold">
									${plugins
										.filter((p) => isLicensed(p.slug))
										.reduce((sum, p) => sum + (Number(p.price_monthly) || 0), 0)
										.toFixed(2)}
									<span class="text-sm font-normal text-muted-foreground">/mo</span>
								</p>
							</div>
						</Card.Content>
					</Card.Root>
				{/if}
			</Tabs.Content>
		</Tabs.Root>
	{/if}
</div>

<!-- Activation Success Dialog -->
<Dialog.Root bind:open={showActivationSuccess}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
				<PartyPopper class="h-6 w-6 text-green-600" />
			</div>
			<Dialog.Title class="text-center">Plugin Activated!</Dialog.Title>
			<Dialog.Description class="text-center">
				{#if activationResult?.plugin}
					<span class="font-medium">{activationResult.plugin.name}</span> is now active on your account.
				{/if}
			</Dialog.Description>
		</Dialog.Header>

		{#if activationResult}
			<div class="space-y-4">
				<!-- Features Unlocked -->
				{#if activationResult.features_unlocked.length > 0}
					<div class="rounded-lg border bg-muted/50 p-4">
						<h4 class="mb-2 text-sm font-medium flex items-center gap-2">
							<Sparkles class="h-4 w-4 text-amber-500" />
							Features Unlocked
						</h4>
						<ul class="space-y-1">
							{#each activationResult.features_unlocked as feature}
								<li class="flex items-center gap-2 text-sm text-muted-foreground">
									<Check class="h-3 w-3 text-green-500" />
									{feature}
								</li>
							{/each}
						</ul>
					</div>
				{/if}

				<!-- Next Steps -->
				{#if activationResult.next_steps.length > 0}
					<div class="space-y-3">
						<h4 class="text-sm font-medium">Next Steps</h4>
						{#each activationResult.next_steps as step}
							<div class="rounded-lg border p-3">
								<p class="font-medium text-sm">{step.title}</p>
								<p class="text-xs text-muted-foreground mt-1">{step.description}</p>
							</div>
						{/each}
					</div>
				{/if}
			</div>
		{/if}

		<Dialog.Footer class="flex-col sm:flex-row gap-2">
			<Button variant="outline" onclick={closeActivationDialog} class="w-full sm:w-auto">
				Done
			</Button>
			{#if activationResult?.settings_path}
				<Button onclick={goToSettings} class="w-full sm:w-auto">
					<Settings class="mr-2 h-4 w-4" />
					Configure Now
					<ArrowRight class="ml-2 h-4 w-4" />
				</Button>
			{/if}
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
