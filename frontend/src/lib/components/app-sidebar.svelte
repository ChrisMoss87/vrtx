<script lang="ts" module>
	import HomeIcon from '@lucide/svelte/icons/home';
	import LayoutDashboardIcon from '@lucide/svelte/icons/layout-dashboard';
	import BriefcaseIcon from '@lucide/svelte/icons/briefcase';
	import Settings2Icon from '@lucide/svelte/icons/settings-2';
	import CodeIcon from '@lucide/svelte/icons/code';
	import KanbanIcon from '@lucide/svelte/icons/kanban';
	import ZapIcon from '@lucide/svelte/icons/zap';
	import BarChart2Icon from '@lucide/svelte/icons/bar-chart-2';
	import LayoutGridIcon from '@lucide/svelte/icons/layout-grid';
	import ShieldIcon from '@lucide/svelte/icons/shield';
	import MailIcon from '@lucide/svelte/icons/mail';

	// Static navigation items (non-module based)
	const staticNavItems = [
		{
			title: 'Dashboard',
			url: '/dashboard',
			icon: HomeIcon,
			isActive: true
		},
		{
			title: 'Modules',
			url: '/modules',
			icon: LayoutDashboardIcon,
			items: [
				{
					title: 'All Modules',
					url: '/modules'
				},
				{
					title: 'Create Module',
					url: '/modules/create-builder'
				}
			]
		}
	];

	// Features navigation
	const featureNavItems = [
		{
			title: 'Pipelines',
			url: '/pipelines',
			icon: KanbanIcon,
			items: [
				{
					title: 'View Pipelines',
					url: '/pipelines'
				},
				{
					title: 'Manage Pipelines',
					url: '/admin/pipelines'
				},
				{
					title: 'Create Pipeline',
					url: '/admin/pipelines/create'
				}
			]
		},
		{
			title: 'Workflows',
			url: '/admin/workflows',
			icon: ZapIcon,
			items: [
				{
					title: 'All Workflows',
					url: '/admin/workflows'
				},
				{
					title: 'Create Workflow',
					url: '/admin/workflows/create'
				}
			]
		},
		{
			title: 'Reports',
			url: '/reports',
			icon: BarChart2Icon,
			items: [
				{
					title: 'All Reports',
					url: '/reports'
				},
				{
					title: 'Create Report',
					url: '/reports/new'
				}
			]
		},
		{
			title: 'Dashboards',
			url: '/dashboards',
			icon: LayoutGridIcon,
			items: [
				{
					title: 'All Dashboards',
					url: '/dashboards'
				},
				{
					title: 'Create Dashboard',
					url: '/dashboards/new'
				}
			]
		},
		{
			title: 'Email',
			url: '/email',
			icon: MailIcon,
			items: [
				{
					title: 'Inbox',
					url: '/email'
				},
				{
					title: 'Templates',
					url: '/email/templates'
				}
			]
		},
		{
			title: 'Settings',
			url: '/settings',
			icon: Settings2Icon,
			items: [
				{
					title: 'Module Order',
					url: '/settings/modules'
				},
				{
					title: 'Roles & Permissions',
					url: '/settings/roles'
				}
			]
		}
	];

	// Developer tools - collapsed by default, separate from main nav
	const devTools = [
		{
			title: 'Developer',
			url: '#',
			icon: CodeIcon,
			items: [
				{
					title: 'DataTable Demo',
					url: '/datatable-demo'
				},
				{
					title: 'Form Builder Test',
					url: '/test-form'
				},
				{
					title: 'Wizard Demo',
					url: '/wizard-demo'
				},
				{
					title: 'Wizard Builder Demo',
					url: '/wizard-builder-demo'
				},
				{
					title: 'Step Types Demo',
					url: '/step-types-demo'
				},
				{
					title: 'Conditional Wizard',
					url: '/conditional-wizard-demo'
				},
				{
					title: 'Draft Management',
					url: '/draft-demo'
				},
				{
					title: 'Field Types Demo',
					url: '/field-types-demo'
				},
				{
					title: 'Rich Text Editor',
					url: '/editor-demo'
				}
			]
		}
	];
</script>

<script lang="ts">
	import { onMount } from 'svelte';
	import NavMain from './nav-main.svelte';
	import NavProjects from './nav-projects.svelte';
	import NavUser from './nav-user.svelte';
	import TeamSwitcher from './team-switcher.svelte';
	import * as Sidebar from '$lib/components/ui/sidebar/index.js';
	import type { ComponentProps } from 'svelte';
	import { getActiveModules, type Module } from '$lib/api/modules';
	import { getIconComponent } from '$lib/utils/icons';

	let {
		ref = $bindable(null),
		collapsible = 'icon',
		...restProps
	}: ComponentProps<typeof Sidebar.Root> = $props();

	// State for dynamically loaded modules
	let modules = $state<Module[]>([]);
	let loading = $state(true);

	// User data (would normally come from auth context)
	const userData = {
		name: 'Bob TechCo',
		email: 'bob@techco.com',
		avatar: '/avatars/default.jpg'
	};

	const teams = [
		{
			name: 'TechCo Solutions',
			logo: BriefcaseIcon,
			plan: 'Enterprise'
		}
	];

	// Computed CRM navigation items from modules
	const crmNavItems = $derived(() => {
		if (modules.length === 0) return [];

		return [
			{
				title: 'CRM',
				url: '#',
				icon: BriefcaseIcon,
				items: modules.map((module) => ({
					title: module.name,
					url: `/records/${module.api_name}`
				}))
			}
		];
	});

	// Build module quick links for the sidebar
	const moduleQuickLinks = $derived(() => {
		return modules.slice(0, 5).map((module) => ({
			name: module.name,
			url: `/records/${module.api_name}`,
			icon: getIconComponent(module.icon)
		}));
	});

	// Combined navigation (static + dynamic modules + features)
	const mainNavItems = $derived(() => {
		return [...staticNavItems, ...crmNavItems(), ...featureNavItems];
	});

	onMount(async () => {
		try {
			modules = await getActiveModules();
			// Sort by display_order
			modules.sort((a, b) => a.display_order - b.display_order);
		} catch (error) {
			console.error('Failed to load modules for sidebar:', error);
		} finally {
			loading = false;
		}
	});
</script>

<Sidebar.Root {collapsible} {...restProps}>
	<Sidebar.Header>
		<TeamSwitcher {teams} />
	</Sidebar.Header>
	<Sidebar.Content>
		<NavMain items={mainNavItems()} />
		<NavProjects projects={moduleQuickLinks()} />
		<!-- Developer tools - separate section at bottom -->
		<NavMain items={devTools} label="Developer Tools" />
	</Sidebar.Content>
	<Sidebar.Footer>
		<NavUser user={userData} />
	</Sidebar.Footer>
	<Sidebar.Rail />
</Sidebar.Root>
