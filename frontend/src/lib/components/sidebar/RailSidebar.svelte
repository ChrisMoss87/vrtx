<script lang="ts">
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { cn } from '$lib/utils';
	import { Badge } from '$lib/components/ui/badge';
	import { type Module } from '$lib/api/modules';
	import { modulesStore, favoritesStore } from '$lib/stores/modules';
	import { getIconComponent } from '$lib/utils/icons';
	import { permissions, hasPermission } from '$lib/stores/permissions';
	import { license } from '$lib/stores/license';
	import { NotificationBell } from '$lib/components/notifications';
	import { authStore } from '$lib/stores/auth.svelte';
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
		User,
		LogOut,
		FileText, Waypoints
	} from 'lucide-svelte';

	// Types
	interface NavItem {
		title: string;
		url: string;
		icon?: any;
		badge?: string | number;
		permission?: string;
	}

	interface NavCategory {
		id: string;
		title: string;
		icon: any;
		permission?: string;
		featureFlag?: string;
		items: NavItem[];
	}

	// State
	let hoveredCategory = $state<string | null>(null);
	let hoveredCategoryTop = $state<number>(0);
	let hoverTimeout = $state<ReturnType<typeof setTimeout> | null>(null);

	// Subscribe to stores
	let modules = $state<Module[]>([]);
	let favorites = $state<string[]>([]);

	// Keep local state in sync with stores
	$effect(() => {
		const unsubModules = modulesStore.subscribe(value => {
			modules = value;
		});
		const unsubFavorites = favoritesStore.subscribe(value => {
			favorites = value;
		});
		return () => {
			unsubModules();
			unsubFavorites();
		};
	});

	// Build navigation categories
	const getNavCategories = (modules: Module[], favorites: string[]): NavCategory[] => [
		{
			id: 'home',
			title: 'Home',
			icon: Home,
			items: [
				{ title: 'Dashboard', url: '/dashboard', icon: Home },
				{ title: 'Activity Feed', url: '/activity' }
			]
		},
		{
			id: 'crm',
			title: 'CRM',
			icon: Briefcase,
			permission: 'modules.view',
			items: [
				{ title: 'Module Manager', url: '/modules' },
				{ title: 'Create Module', url: '/modules/create-builder' },
				{ title: 'Module Settings', url: '/settings/modules' },
				...modules.map((m) => ({
					title: m.name,
					url: `/records/${m.api_name}`,
					icon: getIconComponent(m.icon),
					starred: favorites.includes(m.api_name)
				}))
			]
		},
		{
			id: 'automation',
			title: 'Automation',
			icon: Waypoints,
			permission: 'workflows.view',
			items: [
				{ title: 'Workflows', url: '/admin/workflows' },
				{ title: 'Wizards', url: '/wizards' },
				{ title: 'Blueprints', url: '/admin/blueprints' },
				{ title: 'Pending Approvals', url: '/approvals' },
				{ title: 'Approval Rules', url: '/admin/approval-rules' },
				{ title: 'Cadences', url: '/marketing/cadences' },
				{ title: 'Playbooks', url: '/playbooks' }
			]
		},
		{
			id: 'analytics',
			title: 'Analytics',
			icon: BarChart3,
			permission: 'reports.view',
			items: [
				{ title: 'Reports', url: '/reports' },
				{ title: 'Dashboards', url: '/dashboards' },
				{ title: 'Forecasts', url: '/forecasts' },
				{ title: 'Quotas & Goals', url: '/quotas' },
				{ title: 'Goals', url: '/goals' },
				{ title: 'Revenue Graph', url: '/graph' }
			]
		},
		{
			id: 'communication',
			title: 'Communication',
			icon: Mail,
			items: [
				{ title: 'Email', url: '/email' },
				{ title: 'Shared Inbox', url: '/shared-inbox' },
				{ title: 'Scheduling', url: '/settings/scheduling' },
				{ title: 'Meetings', url: '/meetings' },
				{ title: 'Video Meetings', url: '/video-meetings' },
				{ title: 'Live Chat', url: '/live-chat' },
				{ title: 'Team Chat', url: '/team-chat' },
				{ title: 'Calls', url: '/calls' },
				{ title: 'WhatsApp', url: '/whatsapp' },
				{ title: 'SMS', url: '/sms' }
			]
		},
		{
			id: 'sales',
			title: 'Sales',
			icon: TrendingUp,
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
			permission: 'campaigns.view',
			items: [
				{ title: 'Campaigns', url: '/marketing/campaigns' },
				{ title: 'Landing Pages', url: '/landing-pages' },
				{ title: 'Web Forms', url: '/admin/web-forms' },
				{ title: 'A/B Testing', url: '/ab-tests' },
				{ title: 'Lookalike Audiences', url: '/lookalike-audiences' },
				{ title: 'Email Templates', url: '/admin/workflow-email-templates' }
			]
		},
		{
			id: 'cms',
			title: 'Content',
			icon: FileText,
			items: [
				{ title: 'Pages', url: '/cms/pages' },
				{ title: 'Forms', url: '/cms/forms' },
				{ title: 'Media Library', url: '/cms/media' },
				{ title: 'Categories', url: '/cms/categories' },
				{ title: 'Tags', url: '/cms/tags' },
				{ title: 'Menus', url: '/cms/menus' }
			]
		},
		{
			id: 'success',
			title: 'Customer Success',
			icon: Headphones,
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
			permission: 'ai.view',
			featureFlag: 'ai.sidebar',
			items: [
				{ title: 'AI Settings', url: '/admin/ai' },
				{ title: 'Documents', url: '/documents' },
				{ title: 'Document Templates', url: '/admin/document-templates' },
				{ title: 'Recordings', url: '/recordings' }
			]
		},
		{
			id: 'settings',
			title: 'Settings',
			icon: Settings,
			permission: 'settings.view',
			items: [
				{ title: 'General', url: '/settings' },
				{ title: 'Preferences', url: '/settings/preferences' },
				{ title: 'Notifications', url: '/settings/notifications' },
				{ title: 'Users', url: '/settings/users' },
				{ title: 'Roles & Permissions', url: '/settings/roles' },
				{ title: 'Modules', url: '/settings/modules' },
				{ title: 'Billing & Plugins', url: '/settings/billing' },
				{ title: 'Integrations', url: '/settings/integrations' },
				{ title: 'Audit Logs', url: '/settings/audit-logs' }
			]
		},
		{
			id: 'dev',
			title: 'Dev Tools',
			icon: Code,
			permission: 'settings.edit',
			items: [
				{ title: 'Sidebar Demo', url: '/sidebar-demo' },
				{ title: 'DataTable Demo', url: '/datatable-demo' },
				{ title: 'Form Builder', url: '/test-form' },
				{ title: 'Field Types', url: '/field-types-demo' },
				{ title: 'Wizard Demo', url: '/wizard-demo' },
				{ title: 'Wizard Builder', url: '/wizard-builder-demo' },
				{ title: 'Step Types Demo', url: '/step-types-demo' },
				{ title: 'Conditional Wizard', url: '/conditional-wizard-demo' },
				{ title: 'Draft Demo', url: '/draft-demo' },
				{ title: 'Editor Demo', url: '/editor-demo' }
			]
		}
	];

	// Filter by permissions and feature flags
	const filteredCategories = $derived.by(() => {
		const allCategories = getNavCategories(modules, favorites);
		return allCategories
			.filter((cat) => {
				// Check permission
				if (cat.permission && !hasPermission(cat.permission)) return false;
				// Check feature flag
				if (cat.featureFlag && !license.hasFeature(cat.featureFlag)) return false;
				return true;
			})
			.map((cat) => ({
				...cat,
				items: cat.items.filter((item) => !item.permission || hasPermission(item.permission))
			}));
	});

	// Check if current path matches
	function isActive(url: string): boolean {
		return $page.url.pathname === url || $page.url.pathname.startsWith(url + '/');
	}

	function isCategoryActive(cat: NavCategory): boolean {
		return cat.items.some((item) => isActive(item.url));
	}

	// Hover handlers with delay
	function handleMouseEnter(catId: string, event: MouseEvent) {
		if (hoverTimeout) clearTimeout(hoverTimeout);
		const target = event.currentTarget as HTMLElement;
		const rect = target.getBoundingClientRect();
		hoveredCategoryTop = rect.top;
		hoveredCategory = catId;
	}

	function handleMouseLeave() {
		hoverTimeout = setTimeout(() => {
			hoveredCategory = null;
		}, 150);
	}

	function handleFlyoutEnter() {
		if (hoverTimeout) clearTimeout(hoverTimeout);
	}

	onMount(async () => {
		try {
			// Load modules from store (will fetch from API if needed)
			await modulesStore.load();
		} catch (error) {
			console.error('Failed to load modules:', error);
		}
	});
</script>

<aside class="flex h-screen">
	<!-- Icon Rail -->
	<div class="w-14 bg-slate-900 flex flex-col shrink-0">
		<!-- Logo -->
		<div class="h-14 flex items-center justify-center">
			<a
				href="/dashboard"
				class="w-9 h-9 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-lg"
			>
				V
			</a>
		</div>

		<!-- Navigation -->
		<div class="flex-1 overflow-y-auto py-2">
			{#each filteredCategories as cat}
				{@const Icon = cat.icon}
				<!-- svelte-ignore a11y_interactive_supports_focus -->
				<div role="menuitem" onmouseenter={(e) => handleMouseEnter(cat.id, e)} onmouseleave={handleMouseLeave}>
					<button
						class={cn(
							'w-full h-11 flex items-center justify-center transition-all duration-150',
							hoveredCategory === cat.id
								? 'bg-slate-700 text-white'
								: isCategoryActive(cat)
									? 'text-violet-400 bg-slate-800'
									: 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
						)}
					>
						<Icon class="h-5 w-5" />
					</button>
				</div>
			{/each}
		</div>

		<!-- User Section -->
		<div class="border-t border-slate-700 py-2">
			<button class="w-full h-11 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
				<Search class="h-5 w-5" />
			</button>
			<div class="w-full h-11 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
				<NotificationBell userId={authStore.user?.id} />
			</div>
			<button class="w-full h-11 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
				<User class="h-5 w-5" />
			</button>
		</div>
	</div>

	<!-- Flyout Panel (rendered with fixed position to avoid clipping) -->
	{#each filteredCategories as cat}
		{#if hoveredCategory === cat.id}
			{@const Icon = cat.icon}
			<!-- svelte-ignore a11y_no_static_element_interactions -->
			<div
				role="menu"
				class="fixed left-14 z-50"
				style="top: {hoveredCategoryTop}px;"
				onmouseenter={handleFlyoutEnter}
				onmouseleave={handleMouseLeave}
			>
				<div class="w-60 bg-slate-800 border border-slate-700 rounded-r-lg shadow-2xl overflow-hidden">
					<!-- Header -->
					<div class="px-4 py-2.5 bg-slate-700 border-b border-slate-600">
						<div class="flex items-center gap-2">
							<Icon class="h-4 w-4 text-violet-400" />
							<span class="font-semibold text-white">{cat.title}</span>
						</div>
					</div>
					<!-- Items -->
					<div class="py-1 max-h-[calc(100vh-100px)] overflow-y-auto">
						{#each cat.items as item}
							<a
								href={item.url}
								class={cn(
									'flex items-center gap-3 px-4 py-2.5 text-sm transition-colors',
									isActive(item.url)
										? 'bg-violet-500/20 text-violet-300'
										: 'text-slate-300 hover:bg-slate-700 hover:text-white'
								)}
							>
								{#if item.icon}
									{@const ItemIcon = item.icon}
									<ItemIcon class="h-4 w-4 shrink-0" />
								{:else}
									<div class="w-4"></div>
								{/if}
								<span class="flex-1">{item.title}</span>
								{#if item.badge}
									<Badge variant="secondary" class="bg-slate-600 text-slate-200 text-xs">
										{item.badge}
									</Badge>
								{/if}
								<ChevronRight class="h-4 w-4 text-slate-500" />
							</a>
						{/each}
					</div>
				</div>
			</div>
		{/if}
	{/each}
</aside>
