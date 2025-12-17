<script lang="ts">
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { fly } from 'svelte/transition';
	import { cn } from '$lib/utils';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import { Separator } from '$lib/components/ui/separator';
	import { getActiveModules, type Module } from '$lib/api/modules';
	import { getIconComponent } from '$lib/utils/icons';
	import { permissions, hasPermission } from '$lib/stores/permissions';
	import {
		Home,
		Briefcase,
		Zap,
		BarChart3,
		Mail,
		TrendingUp,
		Megaphone,
		Settings,
		Sparkles,
		Headphones,
		Code,
		ChevronRight,
		Search,
		Plus,
		X,
		PanelLeftClose,
		User,
		Bell,
		Star
	} from 'lucide-svelte';

	// Types
	interface NavItem {
		title: string;
		url: string;
		icon?: any;
		badge?: string | number;
		starred?: boolean;
		permission?: string;
	}

	interface NavCategory {
		id: string;
		title: string;
		icon: any;
		color?: string;
		permission?: string;
		items: NavItem[];
	}

	// State
	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let activePanel = $state<string | null>(null);
	let searchQuery = $state('');

	// Build navigation categories
	const getNavCategories = (modules: Module[]): NavCategory[] => [
		{
			id: 'home',
			title: 'Home',
			icon: Home,
			color: 'text-blue-500',
			items: [
				{ title: 'Dashboard', url: '/dashboard', icon: Home },
				{ title: 'Activity Feed', url: '/activity' },
				{ title: 'Notifications', url: '/notifications', icon: Bell, badge: 3 }
			]
		},
		{
			id: 'crm',
			title: 'CRM',
			icon: Briefcase,
			color: 'text-emerald-500',
			permission: 'modules.view',
			items: [
				{ title: 'All Modules', url: '/modules' },
				...modules.map((m) => ({
					title: m.name,
					url: `/records/${m.api_name}`,
					icon: getIconComponent(m.icon),
					starred: ['leads', 'deals'].includes(m.api_name)
				}))
			]
		},
		{
			id: 'automation',
			title: 'Automation',
			icon: Zap,
			color: 'text-amber-500',
			permission: 'workflows.view',
			items: [
				{ title: 'Workflows', url: '/admin/workflows' },
				{ title: 'Blueprints', url: '/admin/blueprints' },
				{ title: 'Approval Rules', url: '/admin/approval-rules' },
				{ title: 'Cadences', url: '/marketing/cadences' },
				{ title: 'Playbooks', url: '/playbooks' }
			]
		},
		{
			id: 'analytics',
			title: 'Analytics',
			icon: BarChart3,
			color: 'text-violet-500',
			permission: 'reports.view',
			items: [
				{ title: 'Reports', url: '/reports', starred: true },
				{ title: 'Dashboards', url: '/dashboards', starred: true },
				{ title: 'Forecasts', url: '/forecasts' },
				{ title: 'Quotas & Goals', url: '/quotas' },
				{ title: 'Revenue Graph', url: '/graph' }
			]
		},
		{
			id: 'communication',
			title: 'Communication',
			icon: Mail,
			color: 'text-sky-500',
			items: [
				{ title: 'Email', url: '/email', starred: true },
				{ title: 'Scheduling', url: '/settings/scheduling' },
				{ title: 'Live Chat', url: '/live-chat' },
				{ title: 'Calls', url: '/calls' },
				{ title: 'WhatsApp', url: '/whatsapp' },
				{ title: 'SMS', url: '/sms' }
			]
		},
		{
			id: 'sales',
			title: 'Sales',
			icon: TrendingUp,
			color: 'text-green-500',
			items: [
				{ title: 'Quotes', url: '/quotes' },
				{ title: 'Invoices', url: '/invoices' },
				{ title: 'Proposals', url: '/proposals' },
				{ title: 'E-Signatures', url: '/signatures' },
				{ title: 'Deal Rooms', url: '/deal-rooms' },
				{ title: 'Competitors', url: '/competitors' }
			]
		},
		{
			id: 'marketing',
			title: 'Marketing',
			icon: Megaphone,
			color: 'text-pink-500',
			permission: 'campaigns.view',
			items: [
				{ title: 'Campaigns', url: '/marketing/campaigns' },
				{ title: 'Landing Pages', url: '/landing-pages' },
				{ title: 'Web Forms', url: '/admin/web-forms' },
				{ title: 'A/B Testing', url: '/ab-tests' },
				{ title: 'Email Templates', url: '/admin/workflow-email-templates' }
			]
		},
		{
			id: 'success',
			title: 'Customer Success',
			icon: Headphones,
			color: 'text-orange-500',
			items: [
				{ title: 'Support Tickets', url: '/support' },
				{ title: 'Knowledge Base', url: '/support/knowledge-base' },
				{ title: 'Customer Portal', url: '/admin/portal' },
				{ title: 'Renewals', url: '/renewals' }
			]
		},
		{
			id: 'ai',
			title: 'AI & Tools',
			icon: Sparkles,
			color: 'text-purple-500',
			permission: 'ai.view',
			items: [
				{ title: 'AI Settings', url: '/admin/ai' },
				{ title: 'Document Templates', url: '/admin/document-templates' },
				{ title: 'Recordings', url: '/recordings' }
			]
		},
		{
			id: 'settings',
			title: 'Settings',
			icon: Settings,
			color: 'text-slate-500',
			permission: 'settings.view',
			items: [
				{ title: 'General', url: '/settings' },
				{ title: 'Preferences', url: '/settings/preferences', starred: true },
				{ title: 'Users', url: '/settings/users' },
				{ title: 'Roles & Permissions', url: '/settings/roles' },
				{ title: 'Billing & Plugins', url: '/settings/billing' },
				{ title: 'Integrations', url: '/settings/integrations' },
				{ title: 'API Keys', url: '/admin/api-keys' },
				{ title: 'Webhooks', url: '/admin/webhooks' },
				{ title: 'Audit Logs', url: '/admin/audit-logs' }
			]
		},
		{
			id: 'dev',
			title: 'Dev Tools',
			icon: Code,
			color: 'text-cyan-500',
			permission: 'settings.edit',
			items: [
				{ title: 'Sidebar Demo', url: '/sidebar-demo' },
				{ title: 'DataTable Demo', url: '/datatable-demo' },
				{ title: 'Form Builder', url: '/test-form' },
				{ title: 'Field Types', url: '/field-types-demo' },
				{ title: 'Wizard Demo', url: '/wizard-demo' }
			]
		}
	];

	// Filter by permissions
	const filteredCategories = $derived.by(() => {
		const allCategories = getNavCategories(modules);
		return allCategories
			.filter((cat) => !cat.permission || hasPermission(cat.permission))
			.map((cat) => ({
				...cat,
				items: cat.items.filter((item) => !item.permission || hasPermission(item.permission))
			}));
	});

	// Get active category
	const activeCategoryData = $derived(filteredCategories.find((c) => c.id === activePanel));

	// Filtered items based on search
	const filteredItems = $derived.by(() => {
		if (!activeCategoryData) return [];
		if (!searchQuery.trim()) return activeCategoryData.items;
		const query = searchQuery.toLowerCase();
		return activeCategoryData.items.filter((item) => item.title.toLowerCase().includes(query));
	});

	// Check if current path matches
	function isActive(url: string): boolean {
		return $page.url.pathname === url || $page.url.pathname.startsWith(url + '/');
	}

	function isCategoryActive(cat: NavCategory): boolean {
		return cat.items.some((item) => isActive(item.url));
	}

	// Toggle panel
	function togglePanel(catId: string) {
		console.log('togglePanel called with:', catId, 'current:', activePanel);
		if (activePanel === catId) {
			activePanel = null;
		} else {
			activePanel = catId;
			searchQuery = '';
		}
		console.log('activePanel now:', activePanel);
	}

	function closePanel() {
		activePanel = null;
		searchQuery = '';
	}

	// Keyboard handler
	function handleKeydown(e: KeyboardEvent) {
		if (e.key === 'Escape' && activePanel) {
			closePanel();
		}
	}

	onMount(async () => {
		try {
			modules = await getActiveModules();
			modules.sort((a, b) => a.display_order - b.display_order);
		} catch (error) {
			console.error('Failed to load modules:', error);
		} finally {
			loading = false;
		}
	});
</script>

<svelte:window onkeydown={handleKeydown} />

<aside class="flex h-screen">
	<!-- Icon Tabs Rail -->
	<div class="w-12 bg-zinc-50 dark:bg-zinc-900 border-r flex flex-col shrink-0">
		<!-- Logo -->
		<div class="h-12 flex items-center justify-center">
			<a
				href="/dashboard"
				class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-md hover:shadow-lg transition-shadow"
			>
				V
			</a>
		</div>

		<!-- Navigation Tabs -->
		<nav class="flex-1 flex flex-col items-center py-1 gap-0.5">
			{#each filteredCategories as cat}
				{@const Icon = cat.icon}
				<button
					type="button"
					class={cn(
						'w-9 h-9 rounded-lg flex items-center justify-center transition-all duration-150 cursor-pointer',
						activePanel === cat.id
							? 'bg-primary text-primary-foreground shadow-md'
							: isCategoryActive(cat)
								? 'bg-primary/10 text-primary'
								: 'text-muted-foreground hover:bg-muted hover:text-foreground'
					)}
					onclick={(e) => {
						e.preventDefault();
						e.stopPropagation();
						togglePanel(cat.id);
					}}
					title={cat.title}
				>
					<Icon class="h-[18px] w-[18px]" />
				</button>
			{/each}
		</nav>

		<!-- Bottom Actions -->
		<div class="py-2 flex flex-col items-center gap-0.5 border-t">
			<button
				class="w-9 h-9 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
				title="Search (⌘K)"
			>
				<Search class="h-[18px] w-[18px]" />
			</button>
			<button
				class="w-9 h-9 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
				title="Profile"
			>
				<User class="h-[18px] w-[18px]" />
			</button>
		</div>
	</div>

	<!-- Expandable Panel -->
	{#if activePanel && activeCategoryData}
		<div
			class="w-64 bg-background border-r flex flex-col shrink-0"
			transition:fly={{ x: -10, duration: 150 }}
		>
			<!-- Header -->
			<div class="h-12 flex items-center gap-2 px-3 border-b shrink-0">
				{#if activeCategoryData.icon}
					{@const Icon = activeCategoryData.icon}
					<div class={cn('p-1 rounded', activeCategoryData.color)}>
						<Icon class="h-4 w-4" />
					</div>
				{/if}
				<span class="font-semibold text-sm flex-1">{activeCategoryData.title}</span>
				<Button variant="ghost" size="icon" class="h-7 w-7" onclick={closePanel}>
					<X class="h-4 w-4" />
				</Button>
			</div>

			<!-- Search (for categories with many items) -->
			{#if activeCategoryData.items.length > 5}
				<div class="px-3 py-2 border-b shrink-0">
					<div class="relative">
						<Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
						<input
							type="text"
							placeholder="Search..."
							bind:value={searchQuery}
							class="w-full h-8 pl-8 pr-3 text-sm rounded-md border bg-muted/50 focus:bg-background focus:outline-none focus:ring-2 focus:ring-primary/20"
						/>
					</div>
				</div>
			{/if}

			<!-- Items -->
			<ScrollArea class="flex-1">
				<div class="p-2">
					{#if filteredItems.length > 0}
						<!-- Starred items first -->
						{@const starredItems = filteredItems.filter((i) => i.starred)}
						{@const regularItems = filteredItems.filter((i) => !i.starred)}

						{#if starredItems.length > 0 && !searchQuery}
						<div class="mb-2">
							<div class="px-2 py-1.5 text-xs font-medium text-muted-foreground flex items-center gap-1">
								<Star class="h-3 w-3" />
								Favorites
							</div>
							{#each starredItems as item}
								<a
									href={item.url}
									class={cn(
										'flex items-center gap-2 px-2 py-2 text-sm rounded-md transition-colors group',
										isActive(item.url)
											? 'bg-primary/10 text-primary font-medium'
											: 'text-foreground hover:bg-muted'
									)}
								>
									{#if item.icon}
										{@const ItemIcon = item.icon}
										<ItemIcon class="h-4 w-4 text-muted-foreground group-hover:text-foreground shrink-0" />
									{:else}
										<div class="w-4 h-4 rounded bg-muted shrink-0" />
									{/if}
									<span class="flex-1 truncate">{item.title}</span>
									{#if item.badge}
										<Badge variant="secondary" class="text-xs">{item.badge}</Badge>
									{/if}
								</a>
							{/each}
						</div>
						<Separator class="my-2" />
					{/if}

					<!-- Regular items -->
					<div>
						{#if starredItems.length > 0 && !searchQuery}
							<div class="px-2 py-1.5 text-xs font-medium text-muted-foreground">
								All Items
							</div>
						{/if}
						{#each regularItems as item}
							<a
								href={item.url}
								class={cn(
									'flex items-center gap-2 px-2 py-2 text-sm rounded-md transition-colors group',
									isActive(item.url)
										? 'bg-primary/10 text-primary font-medium'
										: 'text-foreground hover:bg-muted'
								)}
							>
								{#if item.icon}
									{@const ItemIcon = item.icon}
									<ItemIcon class="h-4 w-4 text-muted-foreground group-hover:text-foreground shrink-0" />
								{:else}
									<div class="w-4 h-4 rounded bg-muted shrink-0" />
								{/if}
								<span class="flex-1 truncate">{item.title}</span>
								{#if item.badge}
									<Badge variant="secondary" class="text-xs">{item.badge}</Badge>
								{/if}
								<ChevronRight class="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 shrink-0" />
							</a>
						{/each}
					</div>
					{:else}
						<div class="px-2 py-8 text-center text-sm text-muted-foreground">
							No items found
						</div>
					{/if}
				</div>
			</ScrollArea>

			<!-- Footer -->
			<div class="p-2 border-t shrink-0">
				<Button variant="ghost" size="sm" class="w-full justify-start text-muted-foreground hover:text-foreground">
					<Plus class="h-4 w-4 mr-2" />
					Add new
				</Button>
			</div>
		</div>
	{/if}
</aside>
