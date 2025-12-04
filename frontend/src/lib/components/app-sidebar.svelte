<script lang="ts" module>
	import HomeIcon from '@lucide/svelte/icons/home';
	import LayoutDashboardIcon from '@lucide/svelte/icons/layout-dashboard';
	import PackageIcon from '@lucide/svelte/icons/package';
	import UsersIcon from '@lucide/svelte/icons/users';
	import BriefcaseIcon from '@lucide/svelte/icons/briefcase';
	import Settings2Icon from '@lucide/svelte/icons/settings-2';
	import CodeIcon from '@lucide/svelte/icons/code';
	import TrendingUpIcon from '@lucide/svelte/icons/trending-up';

	// VRTX CRM Data
	const data = {
		user: {
			name: 'Bob TechCo',
			email: 'bob@techco.com',
			avatar: '/avatars/default.jpg'
		},
		teams: [
			{
				name: 'TechCo Solutions',
				logo: BriefcaseIcon,
				plan: 'Enterprise'
			}
		],
		navMain: [
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
			},
			{
				title: 'CRM',
				url: '#',
				icon: BriefcaseIcon,
				items: [
					{
						title: 'Contacts',
						url: '/records/contacts'
					},
					{
						title: 'Deals',
						url: '/records/deals'
					},
					{
						title: 'Products',
						url: '/records/products'
					}
				]
			},
			{
				title: 'Settings',
				url: '/settings',
				icon: Settings2Icon
			}
		],
		// Developer tools - collapsed by default, separate from main nav
		devTools: [
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
		],
		projects: [
			{
				name: 'Contacts',
				url: '/records/contacts',
				icon: UsersIcon
			},
			{
				name: 'Deals',
				url: '/records/deals',
				icon: TrendingUpIcon
			},
			{
				name: 'Products',
				url: '/records/products',
				icon: PackageIcon
			}
		]
	};
</script>

<script lang="ts">
	import NavMain from './nav-main.svelte';
	import NavProjects from './nav-projects.svelte';
	import NavUser from './nav-user.svelte';
	import TeamSwitcher from './team-switcher.svelte';
	import * as Sidebar from '$lib/components/ui/sidebar/index.js';
	import type { ComponentProps } from 'svelte';

	let {
		ref = $bindable(null),
		collapsible = 'icon',
		...restProps
	}: ComponentProps<typeof Sidebar.Root> = $props();
</script>

<Sidebar.Root {collapsible} {...restProps}>
	<Sidebar.Header>
		<TeamSwitcher teams={data.teams} />
	</Sidebar.Header>
	<Sidebar.Content>
		<NavMain items={data.navMain} />
		<NavProjects projects={data.projects} />
		<!-- Developer tools - separate section at bottom -->
		<NavMain items={data.devTools} label="Developer Tools" />
	</Sidebar.Content>
	<Sidebar.Footer>
		<NavUser user={data.user} />
	</Sidebar.Footer>
	<Sidebar.Rail />
</Sidebar.Root>
