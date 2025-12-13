<script lang="ts" module>
	import HomeIcon from '@lucide/svelte/icons/home';
	import LayoutDashboardIcon from '@lucide/svelte/icons/layout-dashboard';
	import BriefcaseIcon from '@lucide/svelte/icons/briefcase';
	import Settings2Icon from '@lucide/svelte/icons/settings-2';
	import CodeIcon from '@lucide/svelte/icons/code';
	import WorkflowIcon from '@lucide/svelte/icons/workflow';
	import ZapIcon from '@lucide/svelte/icons/zap';
	import BarChart2Icon from '@lucide/svelte/icons/bar-chart-2';
	import LayoutGridIcon from '@lucide/svelte/icons/layout-grid';
	import MailIcon from '@lucide/svelte/icons/mail';
	import ClipboardCheckIcon from '@lucide/svelte/icons/clipboard-check';
	import TrendingUpIcon from '@lucide/svelte/icons/trending-up';
	import FileTextIcon from '@lucide/svelte/icons/file-text';
	import CalendarDaysIcon from '@lucide/svelte/icons/calendar-days';
	import ReceiptIcon from '@lucide/svelte/icons/receipt';
	import TargetIcon from '@lucide/svelte/icons/target';
	import MessageSquareIcon from '@lucide/svelte/icons/message-square';
	import MegaphoneIcon from '@lucide/svelte/icons/megaphone';
	import SmartphoneIcon from '@lucide/svelte/icons/smartphone';
	import UsersIcon from '@lucide/svelte/icons/users';
	import InboxIcon from '@lucide/svelte/icons/inbox';
	import PhoneIcon from '@lucide/svelte/icons/phone';
	import NetworkIcon from '@lucide/svelte/icons/network';
	import SwordsIcon from '@lucide/svelte/icons/swords';
	import HandshakeIcon from '@lucide/svelte/icons/handshake';
	import VideoIcon from '@lucide/svelte/icons/video';
	import PlugIcon from '@lucide/svelte/icons/plug';
	import HistoryIcon from '@lucide/svelte/icons/history';
	import PanelsTopLeftIcon from '@lucide/svelte/icons/panels-top-left';
	import FlaskConicalIcon from '@lucide/svelte/icons/flask-conical';

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

	// Core CRM Features - always available
	const coreFeatureNavItems = [
		{
			title: 'Blueprints',
			url: '/admin/blueprints',
			icon: WorkflowIcon,
			items: [
				{
					title: 'All Blueprints',
					url: '/admin/blueprints'
				},
				{
					title: 'Create Blueprint',
					url: '/admin/blueprints/create'
				},
				{
					title: 'Pending Approvals',
					url: '/approvals'
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
			title: 'Web Forms',
			url: '/admin/web-forms',
			icon: FileTextIcon,
			items: [
				{
					title: 'All Forms',
					url: '/admin/web-forms'
				},
				{
					title: 'Create Form',
					url: '/admin/web-forms/create'
				}
			]
		}
	];

	// Sales & Revenue Features
	const salesNavItems = [
		{
			title: 'Forecasts',
			url: '/forecasts',
			icon: TrendingUpIcon,
			items: [
				{
					title: 'Overview',
					url: '/forecasts'
				},
				{
					title: 'Manage Quotas',
					url: '/forecasts/quotas'
				},
				{
					title: 'Scenarios',
					url: '/forecasts/scenarios'
				}
			]
		},
		{
			title: 'Quotas & Goals',
			url: '/quotas',
			icon: TargetIcon,
			items: [
				{
					title: 'My Progress',
					url: '/quotas'
				},
				{
					title: 'Goals',
					url: '/goals'
				}
			]
		},
		{
			title: 'Quotes & Invoices',
			url: '/quotes',
			icon: ReceiptIcon,
			items: [
				{
					title: 'Quotes',
					url: '/quotes'
				},
				{
					title: 'Invoices',
					url: '/invoices'
				}
			]
		},
		{
			title: 'Deal Rooms',
			url: '/deal-rooms',
			icon: HandshakeIcon,
			items: [
				{
					title: 'All Rooms',
					url: '/deal-rooms'
				}
			]
		},
		{
			title: 'Competitors',
			url: '/competitors',
			icon: SwordsIcon,
			items: [
				{
					title: 'Battlecards',
					url: '/competitors'
				}
			]
		}
	];

	// Analytics & Reporting
	const analyticsNavItems = [
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
			title: 'Revenue Graph',
			url: '/graph',
			icon: NetworkIcon
		}
	];

	// Communication Hub - Core Email + Scheduling
	const communicationNavItems = [
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
			title: 'Scheduling',
			url: '/settings/scheduling',
			icon: CalendarDaysIcon,
			items: [
				{
					title: 'Scheduling Pages',
					url: '/settings/scheduling'
				},
				{
					title: 'Availability',
					url: '/settings/scheduling/availability'
				},
				{
					title: 'Meeting Types',
					url: '/settings/scheduling/meetings'
				}
			]
		}
	];

	// Plugin/Integration Features - These should be conditional based on enabled plugins
	// TODO: Make these dynamically load based on tenant's enabled plugins
	const pluginNavItems = [
		{
			title: 'Marketing',
			url: '/marketing/campaigns',
			icon: MegaphoneIcon,
			items: [
				{
					title: 'Campaigns',
					url: '/marketing/campaigns'
				},
				{
					title: 'Landing Pages',
					url: '/landing-pages'
				},
				{
					title: 'Cadences',
					url: '/marketing/cadences'
				},
				{
					title: 'A/B Testing',
					url: '/ab-tests'
				},
				{
					title: 'Lookalike Audiences',
					url: '/lookalike-audiences'
				},
				{
					title: 'Email Templates',
					url: '/admin/workflow-email-templates'
				}
			]
		},
		{
			title: 'Live Chat',
			url: '/live-chat',
			icon: MessageSquareIcon,
			items: [
				{
					title: 'Conversations',
					url: '/live-chat'
				},
				{
					title: 'Widgets',
					url: '/live-chat?tab=widgets'
				},
				{
					title: 'Canned Responses',
					url: '/live-chat?tab=canned'
				}
			]
		},
		{
			title: 'WhatsApp',
			url: '/whatsapp',
			icon: SmartphoneIcon,
			items: [
				{
					title: 'Inbox',
					url: '/whatsapp'
				},
				{
					title: 'Templates',
					url: '/whatsapp?tab=templates'
				},
				{
					title: 'Connections',
					url: '/whatsapp?tab=connections'
				}
			]
		},
		{
			title: 'SMS',
			url: '/sms',
			icon: MessageSquareIcon,
			items: [
				{
					title: 'Campaigns',
					url: '/sms'
				},
				{
					title: 'Templates',
					url: '/sms?tab=templates'
				},
				{
					title: 'Opt-Outs',
					url: '/sms?tab=opt-outs'
				},
				{
					title: 'Connections',
					url: '/sms?tab=connections'
				}
			]
		},
		{
			title: 'Team Chat',
			url: '/team-chat',
			icon: UsersIcon,
			items: [
				{
					title: 'Connections',
					url: '/team-chat'
				},
				{
					title: 'Notifications',
					url: '/team-chat?tab=notifications'
				}
			]
		},
		{
			title: 'Shared Inbox',
			url: '/shared-inbox',
			icon: InboxIcon
		},
		{
			title: 'Call Center',
			url: '/calls',
			icon: PhoneIcon,
			items: [
				{
					title: 'Call History',
					url: '/calls'
				},
				{
					title: 'Dialer',
					url: '/calls?tab=dialer'
				},
				{
					title: 'Queues',
					url: '/calls?tab=queues'
				},
				{
					title: 'Providers',
					url: '/calls?tab=providers'
				}
			]
		},
		{
			title: 'Video Meetings',
			url: '/video-meetings',
			icon: VideoIcon,
			items: [
				{
					title: 'All Meetings',
					url: '/video-meetings'
				},
				{
					title: 'Providers',
					url: '/video-meetings?tab=providers'
				},
				{
					title: 'Meeting Intelligence',
					url: '/meetings'
				}
			]
		}
	];

	// Process Recording / Automation Tools
	const automationNavItems = [
		{
			title: 'Process Recorder',
			url: '/recordings',
			icon: HistoryIcon,
			items: [
				{
					title: 'Recordings',
					url: '/recordings'
				}
			]
		}
	];

	// Customer Success Features
	import GlobeIcon from '@lucide/svelte/icons/globe';
	import HeadphonesIcon from '@lucide/svelte/icons/headphones';
	import BookOpenIcon from '@lucide/svelte/icons/book-open';

	const customerSuccessNavItems = [
		{
			title: 'Customer Portal',
			url: '/admin/portal',
			icon: GlobeIcon,
			items: [
				{
					title: 'Manage Portal',
					url: '/admin/portal'
				}
			]
		},
		{
			title: 'Support',
			url: '/support',
			icon: HeadphonesIcon,
			items: [
				{
					title: 'Tickets',
					url: '/support'
				},
				{
					title: 'Knowledge Base',
					url: '/support/knowledge-base'
				},
				{
					title: 'Settings',
					url: '/admin/support'
				}
			]
		}
	];

	// Settings
	const settingsNavItems = [
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
				},
				{
					title: 'Billing & Plugins',
					url: '/settings/billing'
				},
				{
					title: 'Integrations',
					url: '/settings/integrations'
				}
			]
		}
	];

	// Developer tools - ADMIN ONLY - should be gated by role
	// TODO: Move to /admin/dev-tools/ and add permission check
	const devTools = [
		{
			title: 'Dev Tools',
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
				},
				{
					title: 'Timeline Demo',
					url: '/timeline-demo'
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
	import { license } from '$lib/stores/license';

	let {
		ref = $bindable(null),
		collapsible = 'icon',
		...restProps
	}: ComponentProps<typeof Sidebar.Root> = $props();

	// State for dynamically loaded modules
	let modules = $state<Module[]>([]);
	let loading = $state(true);

	// TODO: Load from user context/permissions
	let isAdmin = $state(true); // For now, show dev tools to everyone

	// Plugin to navigation item mapping
	const pluginToNavMap: Record<string, string> = {
		'marketing-automation': 'Marketing',
		'live-chat': 'Live Chat',
		'whatsapp-integration': 'WhatsApp',
		'sms-automation': 'SMS',
		'team-chat-integration': 'Team Chat',
		'shared-inboxes': 'Shared Inbox',
		'call-recording': 'Call Center',
		'video-conferencing': 'Video Meetings'
	};

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

	// Filter plugin nav items based on enabled plugins from license
	const activePluginNavItems = $derived(() => {
		const enabledPlugins = $license.plugins;

		// If no plugins are enabled yet (still loading or free tier), show all but disabled
		if ($license.loading) {
			return pluginNavItems;
		}

		// Filter based on which plugins are licensed
		return pluginNavItems.filter((item) => {
			// Find the plugin slug for this nav item
			const pluginSlug = Object.entries(pluginToNavMap).find(
				([_, navTitle]) => navTitle === item.title
			)?.[0];

			// If we can't match it to a plugin, show it (core features)
			if (!pluginSlug) return true;

			// Show if the plugin is licensed
			return enabledPlugins.includes(pluginSlug);
		});
	});

	// Combined navigation (organized by category)
	const mainNavItems = $derived(() => {
		return [
			...staticNavItems,
			...crmNavItems(),
			...coreFeatureNavItems,
			...salesNavItems,
			...analyticsNavItems,
			...communicationNavItems,
			...customerSuccessNavItems,
			...automationNavItems,
			...settingsNavItems
		];
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
		<!-- Core Navigation -->
		<NavMain items={mainNavItems()} />

		<!-- Module Quick Links -->
		<NavProjects projects={moduleQuickLinks()} />

		<!-- Plugin/Integration Features (Communication Channels) -->
		{#if activePluginNavItems().length > 0}
			<NavMain items={activePluginNavItems()} label="Channels & Integrations" />
		{/if}

		<!-- Developer tools - Only show if admin -->
		{#if isAdmin}
			<NavMain items={devTools} label="Admin Tools" />
		{/if}
	</Sidebar.Content>
	<Sidebar.Footer>
		<NavUser user={userData} />
	</Sidebar.Footer>
	<Sidebar.Rail />
</Sidebar.Root>
