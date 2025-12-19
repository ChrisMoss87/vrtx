<script lang="ts">
	import { page } from '$app/stores';
	import { cn } from '$lib/utils';
	import { Tooltip } from '$lib/components/ui/tooltip';
	import * as TooltipPrimitive from '$lib/components/ui/tooltip';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import SidebarDrawer from './SidebarDrawer.svelte';
	import {
		Home,
		LayoutGrid,
		Briefcase,
		Zap,
		BarChart3,
		Mail,
		TrendingUp,
		FileText,
		Sparkles,
		Globe,
		Megaphone,
		MessageSquare,
		Settings,
		Code,
		ChevronLeft,
		ChevronRight
	} from 'lucide-svelte';

	interface NavCategory {
		id: string;
		title: string;
		icon: any;
		permission?: string;
		items: {
			title: string;
			url: string;
			icon?: any;
			permission?: string;
			items?: {
				title: string;
				url: string;
				permission?: string;
			}[];
		}[];
	}

	interface Props {
		categories: NavCategory[];
		modules?: { name: string; url: string; icon?: any }[];
		collapsed?: boolean;
		onToggleCollapse?: () => void;
	}

	let { categories, modules = [], collapsed = false, onToggleCollapse }: Props = $props();

	let activeDrawer = $state<string | null>(null);

	function toggleDrawer(categoryId: string) {
		if (activeDrawer === categoryId) {
			activeDrawer = null;
		} else {
			activeDrawer = categoryId;
		}
	}

	function closeDrawer() {
		activeDrawer = null;
	}

	function isActive(categoryId: string): boolean {
		const category = categories.find((c) => c.id === categoryId);
		if (!category) return false;

		return category.items.some(
			(item) =>
				$page.url.pathname === item.url ||
				$page.url.pathname.startsWith(item.url + '/') ||
				item.items?.some(
					(sub) => $page.url.pathname === sub.url || $page.url.pathname.startsWith(sub.url + '/')
				)
		);
	}

	const activeCategory = $derived(categories.find((c) => c.id === activeDrawer));
</script>

<div class="flex h-full">
	<!-- Icon Rail -->
	<div
		class="flex h-full w-[52px] flex-col border-r bg-sidebar"
	>
		<!-- Logo/Brand -->
		<div class="flex h-14 items-center justify-center border-b">
			<a href="/dashboard" class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
				<span class="text-lg font-bold">V</span>
			</a>
		</div>

		<!-- Navigation Icons -->
		<ScrollArea class="flex-1">
			<div class="flex flex-col items-center gap-1 py-2">
				{#each categories as category}
					{@const Icon = category.icon}
					<TooltipPrimitive.Root>
						<TooltipPrimitive.Trigger>
							<button
								class={cn(
									'flex h-10 w-10 items-center justify-center rounded-lg transition-colors',
									activeDrawer === category.id
										? 'bg-primary text-primary-foreground'
										: isActive(category.id)
											? 'bg-primary/10 text-primary'
											: 'text-muted-foreground hover:bg-muted hover:text-foreground'
								)}
								onclick={() => toggleDrawer(category.id)}
							>
								<Icon class="h-5 w-5" />
							</button>
						</TooltipPrimitive.Trigger>
						<TooltipPrimitive.Content side="right" class="z-[100]">
							{category.title}
						</TooltipPrimitive.Content>
					</TooltipPrimitive.Root>
				{/each}
			</div>
		</ScrollArea>

		<!-- Bottom Actions -->
		<div class="flex flex-col items-center gap-1 border-t py-2">
			<TooltipPrimitive.Root>
				<TooltipPrimitive.Trigger>
					<button
						class="flex h-10 w-10 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
						onclick={onToggleCollapse}
					>
						{#if collapsed}
							<ChevronRight class="h-5 w-5" />
						{:else}
							<ChevronLeft class="h-5 w-5" />
						{/if}
					</button>
				</TooltipPrimitive.Trigger>
				<TooltipPrimitive.Content side="right" class="z-[100]">
					{collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
				</TooltipPrimitive.Content>
			</TooltipPrimitive.Root>
		</div>
	</div>

	<!-- Expandable Drawer -->
	{#if activeCategory}
		<SidebarDrawer
			open={!!activeDrawer}
			title={activeCategory.title}
			icon={activeCategory.icon}
			items={activeCategory.items}
			onClose={closeDrawer}
		/>
	{/if}
</div>
