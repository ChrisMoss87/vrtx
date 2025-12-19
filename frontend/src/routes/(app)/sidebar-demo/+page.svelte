<script lang="ts">
	import { cn } from '$lib/utils';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Separator } from '$lib/components/ui/separator';
	import * as Tabs from '$lib/components/ui/tabs';
	import {
		Home,
		Briefcase,
		Users,
		Building2,
		Target,
		BarChart3,
		Mail,
		Zap,
		Settings,
		ChevronRight,
		ChevronDown,
		Search,
		Plus,
		LayoutGrid,
		TrendingUp,
		FileText,
		Calendar,
		MessageSquare,
		Megaphone,
		Sparkles,
		Globe,
		Phone,
		Video,
		Headphones,
		PanelLeftClose,
		PanelLeft,
		Menu,
		X,
		Star,
		Clock,
		Bell
	} from 'lucide-svelte';

	let activeStyle = $state('style1');
	let expandedSection = $state<string | null>('crm');
	let hoveredCategory = $state<string | null>(null);
	let sidebarCollapsed = $state(false);

	// Sample navigation data
	const modules = [
		{ name: 'Leads', icon: Target, count: 142 },
		{ name: 'Contacts', icon: Users, count: 1893 },
		{ name: 'Accounts', icon: Building2, count: 456 },
		{ name: 'Deals', icon: Briefcase, count: 89 }
	];

	const categories = [
		{
			id: 'home',
			name: 'Home',
			icon: Home,
			items: [
				{ name: 'Dashboard', url: '#' },
				{ name: 'Activity Feed', url: '#' }
			]
		},
		{
			id: 'crm',
			name: 'CRM',
			icon: Briefcase,
			items: modules.map((m) => ({ name: m.name, url: '#', icon: m.icon, count: m.count }))
		},
		{
			id: 'automation',
			name: 'Automation',
			icon: Zap,
			items: [
				{ name: 'Workflows', url: '#' },
				{ name: 'Blueprints', url: '#' },
				{ name: 'Approval Rules', url: '#' }
			]
		},
		{
			id: 'analytics',
			name: 'Analytics',
			icon: BarChart3,
			items: [
				{ name: 'Reports', url: '#' },
				{ name: 'Dashboards', url: '#' },
				{ name: 'Forecasts', url: '#' }
			]
		},
		{
			id: 'communication',
			name: 'Communication',
			icon: Mail,
			items: [
				{ name: 'Email', url: '#' },
				{ name: 'Live Chat', url: '#' },
				{ name: 'Calls', url: '#' }
			]
		},
		{
			id: 'marketing',
			name: 'Marketing',
			icon: Megaphone,
			items: [
				{ name: 'Campaigns', url: '#' },
				{ name: 'Landing Pages', url: '#' },
				{ name: 'Web Forms', url: '#' }
			]
		},
		{
			id: 'settings',
			name: 'Settings',
			icon: Settings,
			items: [
				{ name: 'General', url: '#' },
				{ name: 'Users', url: '#' },
				{ name: 'Roles', url: '#' }
			]
		}
	];

	function toggleSection(id: string) {
		expandedSection = expandedSection === id ? null : id;
	}
</script>

<div class="space-y-6">
	<div>
		<h1 class="text-2xl font-bold">Sidebar Design Options</h1>
		<p class="text-muted-foreground">Choose a sidebar style for the application</p>
	</div>

	<Tabs.Root bind:value={activeStyle}>
		<Tabs.List class="grid w-full grid-cols-5">
			<Tabs.Trigger value="style1">Style 1: Rail</Tabs.Trigger>
			<Tabs.Trigger value="style2">Style 2: Notion</Tabs.Trigger>
			<Tabs.Trigger value="style3">Style 3: Linear</Tabs.Trigger>
			<Tabs.Trigger value="style4">Style 4: Slack</Tabs.Trigger>
			<Tabs.Trigger value="style5">Style 5: Collapsible</Tabs.Trigger>
		</Tabs.List>

		<!-- Style 1: Rail-style with hover flyout -->
		<Tabs.Content value="style1" class="mt-4">
			<div class="rounded-lg border bg-card overflow-hidden">
				<div class="p-4 border-b bg-muted/50">
					<h3 class="font-semibold">Style 1: Rail-style Hover Flyout</h3>
					<p class="text-sm text-muted-foreground">
						Icon rail with hover-triggered flyout panels. Categories expand on hover.
					</p>
				</div>
				<div class="flex h-[500px]">
					<!-- Icon Rail -->
					<div class="w-14 bg-slate-900 flex flex-col">
						<div class="h-14 flex items-center justify-center border-b border-slate-700">
							<div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-primary-foreground font-bold">
								V
							</div>
						</div>
						<div class="flex-1 py-2">
							{#each categories as cat}
								{@const Icon = cat.icon}
								<div
									class="relative"
									role="menuitem"
									onmouseenter={() => (hoveredCategory = cat.id)}
									onmouseleave={() => (hoveredCategory = null)}
								>
									<button
										class={cn(
											'w-full h-11 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors',
											hoveredCategory === cat.id && 'bg-slate-800 text-white'
										)}
									>
										<Icon class="h-5 w-5" />
									</button>
									<!-- Flyout -->
									{#if hoveredCategory === cat.id}
										<div class="absolute left-full top-0 ml-0 w-56 bg-slate-800 border border-slate-700 rounded-r-lg shadow-xl z-50">
											<div class="p-3 border-b border-slate-700">
												<span class="font-medium text-white">{cat.name}</span>
											</div>
											<div class="py-1">
												{#each cat.items as item}
													<a
														href={item.url}
														class="flex items-center gap-2 px-3 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white"
													>
														{#if item.icon}
															{@const ItemIcon = item.icon}
															<ItemIcon class="h-4 w-4" />
														{/if}
														<span>{item.name}</span>
														{#if item.count}
															<Badge variant="secondary" class="ml-auto text-xs">{item.count}</Badge>
														{/if}
													</a>
												{/each}
											</div>
										</div>
									{/if}
								</div>
							{/each}
						</div>
					</div>
					<!-- Main Content Area -->
					<div class="flex-1 bg-background p-6">
						<div class="text-center text-muted-foreground">
							<p>Hover over icons to see flyout menus</p>
						</div>
					</div>
				</div>
			</div>
		</Tabs.Content>

		<!-- Style 2: Notion-style collapsible -->
		<Tabs.Content value="style2" class="mt-4">
			<div class="rounded-lg border bg-card overflow-hidden">
				<div class="p-4 border-b bg-muted/50">
					<h3 class="font-semibold">Style 2: Notion-style Collapsible Sections</h3>
					<p class="text-sm text-muted-foreground">
						Full sidebar with collapsible sections. Clean and minimal.
					</p>
				</div>
				<div class="flex h-[500px]">
					<!-- Sidebar -->
					<div class="w-64 bg-stone-50 dark:bg-stone-900 border-r flex flex-col">
						<!-- Header -->
						<div class="h-12 flex items-center px-3 gap-2">
							<div class="w-6 h-6 rounded bg-gradient-to-br from-orange-400 to-pink-500"></div>
							<span class="font-semibold text-sm">TechCo Workspace</span>
							<ChevronDown class="h-4 w-4 ml-auto text-muted-foreground" />
						</div>
						<!-- Search -->
						<div class="px-3 pb-2">
							<button class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-muted-foreground hover:bg-stone-200 dark:hover:bg-stone-800 rounded">
								<Search class="h-4 w-4" />
								<span>Search</span>
								<kbd class="ml-auto text-xs bg-stone-200 dark:bg-stone-700 px-1.5 py-0.5 rounded">⌘K</kbd>
							</button>
						</div>
						<Separator />
						<!-- Navigation -->
						<ScrollArea class="flex-1">
							<div class="p-2">
								{#each categories as cat}
									{@const Icon = cat.icon}
									<div class="mb-1">
										<button
											class="w-full flex items-center gap-2 px-2 py-1.5 text-sm hover:bg-stone-200 dark:hover:bg-stone-800 rounded group"
											onclick={() => toggleSection(cat.id)}
										>
											<ChevronRight
												class={cn(
													'h-3 w-3 text-muted-foreground transition-transform',
													expandedSection === cat.id && 'rotate-90'
												)}
											/>
											<Icon class="h-4 w-4 text-muted-foreground" />
											<span>{cat.name}</span>
											<Plus class="h-3 w-3 ml-auto opacity-0 group-hover:opacity-100 text-muted-foreground" />
										</button>
										{#if expandedSection === cat.id}
											<div class="ml-5 mt-1 space-y-0.5">
												{#each cat.items as item}
													<a
														href={item.url}
														class="flex items-center gap-2 px-2 py-1 text-sm text-muted-foreground hover:bg-stone-200 dark:hover:bg-stone-800 rounded"
													>
														<span class="w-4 h-4 flex items-center justify-center text-xs">•</span>
														<span>{item.name}</span>
													</a>
												{/each}
											</div>
										{/if}
									</div>
								{/each}
							</div>
						</ScrollArea>
						<!-- Footer -->
						<div class="p-2 border-t">
							<button class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-muted-foreground hover:bg-stone-200 dark:hover:bg-stone-800 rounded">
								<Plus class="h-4 w-4" />
								<span>New page</span>
							</button>
						</div>
					</div>
					<!-- Main Content -->
					<div class="flex-1 bg-background p-6">
						<div class="text-center text-muted-foreground">
							<p>Click sections to expand/collapse</p>
						</div>
					</div>
				</div>
			</div>
		</Tabs.Content>

		<!-- Style 3: Linear-style minimal -->
		<Tabs.Content value="style3" class="mt-4">
			<div class="rounded-lg border bg-card overflow-hidden">
				<div class="p-4 border-b bg-muted/50">
					<h3 class="font-semibold">Style 3: Linear-style Minimal</h3>
					<p class="text-sm text-muted-foreground">
						Ultra-clean sidebar with subtle hover states. Focus on content.
					</p>
				</div>
				<div class="flex h-[500px]">
					<!-- Sidebar -->
					<div class="w-56 bg-background border-r flex flex-col">
						<!-- Header -->
						<div class="h-12 flex items-center px-4 border-b">
							<div class="flex items-center gap-2">
								<div class="w-5 h-5 rounded bg-violet-500"></div>
								<span class="font-medium text-sm">TechCo</span>
							</div>
						</div>
						<!-- Quick Actions -->
						<div class="p-2 space-y-0.5">
							<a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-md hover:bg-muted">
								<Search class="h-4 w-4 text-muted-foreground" />
								<span>Search</span>
							</a>
							<a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-md hover:bg-muted">
								<Bell class="h-4 w-4 text-muted-foreground" />
								<span>Inbox</span>
								<Badge variant="secondary" class="ml-auto">3</Badge>
							</a>
						</div>
						<Separator class="my-2" />
						<!-- Main Nav -->
						<ScrollArea class="flex-1 px-2">
							<div class="space-y-1">
								<div class="px-3 py-2">
									<span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Workspace</span>
								</div>
								{#each categories.slice(0, 4) as cat}
									{@const Icon = cat.icon}
									<a
										href="#"
										class={cn(
											'flex items-center gap-3 px-3 py-2 text-sm rounded-md hover:bg-muted transition-colors',
											cat.id === 'crm' && 'bg-violet-500/10 text-violet-600 dark:text-violet-400'
										)}
									>
										<Icon class="h-4 w-4" />
										<span>{cat.name}</span>
									</a>
								{/each}
								<div class="px-3 py-2 pt-4">
									<span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Modules</span>
								</div>
								{#each modules as mod}
									{@const Icon = mod.icon}
									<a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-md hover:bg-muted">
										<Icon class="h-4 w-4 text-muted-foreground" />
										<span>{mod.name}</span>
										<span class="ml-auto text-xs text-muted-foreground">{mod.count}</span>
									</a>
								{/each}
							</div>
						</ScrollArea>
						<!-- User -->
						<div class="p-2 border-t">
							<button class="w-full flex items-center gap-3 px-3 py-2 text-sm rounded-md hover:bg-muted">
								<div class="w-6 h-6 rounded-full bg-gradient-to-br from-violet-400 to-purple-500"></div>
								<span>John Doe</span>
								<Settings class="h-4 w-4 ml-auto text-muted-foreground" />
							</button>
						</div>
					</div>
					<!-- Main Content -->
					<div class="flex-1 bg-muted/30 p-6">
						<div class="text-center text-muted-foreground">
							<p>Clean, minimal design with clear hierarchy</p>
						</div>
					</div>
				</div>
			</div>
		</Tabs.Content>

		<!-- Style 4: Slack-style with channels -->
		<Tabs.Content value="style4" class="mt-4">
			<div class="rounded-lg border bg-card overflow-hidden">
				<div class="p-4 border-b bg-muted/50">
					<h3 class="font-semibold">Style 4: Slack-style Channels</h3>
					<p class="text-sm text-muted-foreground">
						Two-panel approach with workspace switcher and channel-like navigation.
					</p>
				</div>
				<div class="flex h-[500px]">
					<!-- Workspace Rail -->
					<div class="w-16 bg-slate-800 flex flex-col items-center py-3 gap-2">
						<button class="w-10 h-10 rounded-lg bg-emerald-500 flex items-center justify-center text-white font-bold text-lg">
							T
						</button>
						<div class="w-8 h-0.5 bg-slate-600 rounded my-1"></div>
						<button class="w-10 h-10 rounded-lg bg-slate-700 flex items-center justify-center text-slate-400 hover:text-white">
							<Plus class="h-5 w-5" />
						</button>
					</div>
					<!-- Channel Sidebar -->
					<div class="w-60 bg-slate-800 flex flex-col">
						<div class="h-12 flex items-center px-4 border-b border-slate-700">
							<span class="font-semibold text-white">TechCo CRM</span>
							<ChevronDown class="h-4 w-4 ml-1 text-slate-400" />
						</div>
						<ScrollArea class="flex-1">
							<div class="p-2">
								<!-- Starred -->
								<div class="mb-3">
									<button class="flex items-center gap-1 px-2 py-1 text-xs text-slate-400 hover:text-white w-full">
										<ChevronDown class="h-3 w-3" />
										<Star class="h-3 w-3" />
										<span>Starred</span>
									</button>
									<div class="mt-1 space-y-0.5">
										<a href="#" class="flex items-center gap-2 px-4 py-1 text-sm text-slate-300 hover:bg-slate-700 rounded">
											<span class="text-slate-500">#</span>
											<span>hot-leads</span>
										</a>
										<a href="#" class="flex items-center gap-2 px-4 py-1 text-sm text-slate-300 hover:bg-slate-700 rounded">
											<span class="text-slate-500">#</span>
											<span>deals-closing</span>
										</a>
									</div>
								</div>
								<!-- Modules -->
								<div class="mb-3">
									<button class="flex items-center gap-1 px-2 py-1 text-xs text-slate-400 hover:text-white w-full">
										<ChevronDown class="h-3 w-3" />
										<LayoutGrid class="h-3 w-3" />
										<span>Modules</span>
									</button>
									<div class="mt-1 space-y-0.5">
										{#each modules as mod}
											<a href="#" class="flex items-center gap-2 px-4 py-1 text-sm text-slate-300 hover:bg-slate-700 rounded">
												<span class="text-slate-500">#</span>
												<span>{mod.name.toLowerCase()}</span>
												{#if mod.count > 100}
													<Badge variant="secondary" class="ml-auto text-xs bg-slate-700">{mod.count}</Badge>
												{/if}
											</a>
										{/each}
									</div>
								</div>
								<!-- Tools -->
								<div class="mb-3">
									<button class="flex items-center gap-1 px-2 py-1 text-xs text-slate-400 hover:text-white w-full">
										<ChevronDown class="h-3 w-3" />
										<Zap class="h-3 w-3" />
										<span>Tools</span>
									</button>
									<div class="mt-1 space-y-0.5">
										<a href="#" class="flex items-center gap-2 px-4 py-1 text-sm text-slate-300 hover:bg-slate-700 rounded">
											<BarChart3 class="h-4 w-4 text-slate-500" />
											<span>Reports</span>
										</a>
										<a href="#" class="flex items-center gap-2 px-4 py-1 text-sm text-slate-300 hover:bg-slate-700 rounded">
											<Mail class="h-4 w-4 text-slate-500" />
											<span>Email</span>
										</a>
										<a href="#" class="flex items-center gap-2 px-4 py-1 text-sm text-slate-300 hover:bg-slate-700 rounded">
											<Calendar class="h-4 w-4 text-slate-500" />
											<span>Calendar</span>
										</a>
									</div>
								</div>
							</div>
						</ScrollArea>
					</div>
					<!-- Main Content -->
					<div class="flex-1 bg-background p-6">
						<div class="text-center text-muted-foreground">
							<p>Workspace + channel pattern familiar to Slack users</p>
						</div>
					</div>
				</div>
			</div>
		</Tabs.Content>

		<!-- Style 5: Collapsible-style tabs -->
		<Tabs.Content value="style5" class="mt-4">
			<div class="rounded-lg border bg-card overflow-hidden">
				<div class="p-4 border-b bg-muted/50">
					<h3 class="font-semibold">Style 5: Collapsible-style Icon Tabs</h3>
					<p class="text-sm text-muted-foreground">
						Vertical icon tabs with expandable panel. Click to expand, click again to collapse.
					</p>
				</div>
				<div class="flex h-[500px]">
					<!-- Icon Tabs -->
					<div class="w-12 bg-zinc-100 dark:bg-zinc-900 border-r flex flex-col">
						<div class="flex-1 flex flex-col items-center py-2 gap-1">
							{#each categories.slice(0, 6) as cat, i}
								{@const Icon = cat.icon}
								<button
									class={cn(
										'w-9 h-9 rounded-lg flex items-center justify-center transition-colors',
										expandedSection === cat.id
											? 'bg-primary text-primary-foreground'
											: 'text-muted-foreground hover:bg-zinc-200 dark:hover:bg-zinc-800'
									)}
									onclick={() => toggleSection(cat.id)}
								>
									<Icon class="h-5 w-5" />
								</button>
							{/each}
						</div>
						<div class="pb-2 flex flex-col items-center gap-1">
							<Separator class="w-6 my-2" />
							<button class="w-9 h-9 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-zinc-200 dark:hover:bg-zinc-800">
								<Settings class="h-5 w-5" />
							</button>
						</div>
					</div>
					<!-- Expandable Panel -->
					{#if expandedSection}
						{@const activeCat = categories.find((c) => c.id === expandedSection)}
						<div class="w-56 bg-background border-r flex flex-col">
							<div class="h-10 flex items-center justify-between px-3 border-b">
								<span class="font-medium text-sm">{activeCat?.name}</span>
								<button
									class="w-6 h-6 rounded hover:bg-muted flex items-center justify-center"
									onclick={() => (expandedSection = null)}
								>
									<X class="h-4 w-4" />
								</button>
							</div>
							<ScrollArea class="flex-1">
								<div class="p-2 space-y-1">
									{#each activeCat?.items || [] as item}
										<a
											href={item.url}
											class="flex items-center gap-2 px-3 py-2 text-sm rounded-md hover:bg-muted"
										>
											{#if item.icon}
												{@const ItemIcon = item.icon}
												<ItemIcon class="h-4 w-4 text-muted-foreground" />
											{/if}
											<span>{item.name}</span>
											{#if item.count}
												<Badge variant="outline" class="ml-auto text-xs">{item.count}</Badge>
											{/if}
										</a>
									{/each}
								</div>
							</ScrollArea>
							<div class="p-2 border-t">
								<Button variant="ghost" size="sm" class="w-full justify-start">
									<Plus class="h-4 w-4 mr-2" />
									Add new
								</Button>
							</div>
						</div>
					{/if}
					<!-- Main Content -->
					<div class="flex-1 bg-muted/20 p-6">
						<div class="text-center text-muted-foreground">
							<p>Click icons to toggle panel. Click X or same icon to close.</p>
						</div>
					</div>
				</div>
			</div>
		</Tabs.Content>
	</Tabs.Root>

	<!-- Summary -->
	<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-8">
		<div class="p-4 border rounded-lg">
			<h4 class="font-semibold mb-2">Style 1: Rail</h4>
			<ul class="text-sm text-muted-foreground space-y-1">
				<li>+ Compact icon rail</li>
				<li>+ Hover flyouts (fast access)</li>
				<li>+ Good for many modules</li>
				<li>- Flyouts can be finicky</li>
			</ul>
		</div>
		<div class="p-4 border rounded-lg">
			<h4 class="font-semibold mb-2">Style 2: Notion</h4>
			<ul class="text-sm text-muted-foreground space-y-1">
				<li>+ Familiar accordion pattern</li>
				<li>+ Full text always visible</li>
				<li>+ Easy to scan</li>
				<li>- Takes more horizontal space</li>
			</ul>
		</div>
		<div class="p-4 border rounded-lg">
			<h4 class="font-semibold mb-2">Style 3: Linear</h4>
			<ul class="text-sm text-muted-foreground space-y-1">
				<li>+ Ultra clean and minimal</li>
				<li>+ Clear visual hierarchy</li>
				<li>+ Professional feel</li>
				<li>- Less information density</li>
			</ul>
		</div>
		<div class="p-4 border rounded-lg">
			<h4 class="font-semibold mb-2">Style 4: Slack</h4>
			<ul class="text-sm text-muted-foreground space-y-1">
				<li>+ Workspace switching</li>
				<li>+ Familiar to many users</li>
				<li>+ Channel-like organization</li>
				<li>- Two-column takes space</li>
			</ul>
		</div>
		<div class="p-4 border rounded-lg">
			<h4 class="font-semibold mb-2">Style 5: Collapsible</h4>
			<ul class="text-sm text-muted-foreground space-y-1">
				<li>+ Icon tabs are compact</li>
				<li>+ Click to expand (stable)</li>
				<li>+ Panel can be dismissed</li>
				<li>- Requires click to navigate</li>
			</ul>
		</div>
		<div class="p-4 border rounded-lg bg-primary/5">
			<h4 class="font-semibold mb-2">Recommendation</h4>
			<p class="text-sm text-muted-foreground">
				For a CRM with many modules, <strong>Style 5 (Collapsible)</strong> or <strong>Style 2 (Notion)</strong>
				work best. They handle complexity without overwhelming users.
			</p>
		</div>
	</div>
</div>
