<script lang="ts">
	import { page } from '$app/stores';
	import { fly } from 'svelte/transition';
	import { X, ChevronRight } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { cn } from '$lib/utils';

	interface NavItem {
		title: string;
		url: string;
		icon?: any;
		permission?: string;
		items?: {
			title: string;
			url: string;
			permission?: string;
		}[];
	}

	interface Props {
		open: boolean;
		title: string;
		icon?: any;
		items: NavItem[];
		onClose: () => void;
	}

	let { open, title, icon: Icon, items, onClose }: Props = $props();

	function isActive(url: string): boolean {
		return $page.url.pathname === url || $page.url.pathname.startsWith(url + '/');
	}

	function handleKeydown(e: KeyboardEvent) {
		if (e.key === 'Escape') {
			onClose();
		}
	}
</script>

<svelte:window onkeydown={handleKeydown} />

{#if open}
	<!-- Backdrop -->
	<button
		class="fixed inset-0 z-40 bg-black/20 backdrop-blur-sm lg:hidden"
		onclick={onClose}
		aria-label="Close drawer"
	></button>

	<!-- Drawer Panel -->
	<div
		class="fixed left-[52px] top-0 z-50 h-full w-64 border-r bg-background shadow-lg"
		transition:fly={{ x: -20, duration: 200 }}
	>
		<!-- Header -->
		<div class="flex h-14 items-center justify-between border-b px-4">
			<div class="flex items-center gap-2">
				{#if Icon}
					<Icon class="h-5 w-5 text-primary" />
				{/if}
				<span class="font-semibold">{title}</span>
			</div>
			<Button variant="ghost" size="icon" class="h-8 w-8" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<!-- Content -->
		<ScrollArea class="h-[calc(100vh-3.5rem)]">
			<div class="p-2">
				{#each items as item}
					{#if item.items && item.items.length > 0}
						<!-- Group with sub-items -->
						<div class="mb-2">
							<a
								href={item.url}
								class={cn(
									'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
									isActive(item.url)
										? 'bg-primary/10 text-primary'
										: 'text-muted-foreground hover:bg-muted hover:text-foreground'
								)}
								onclick={onClose}
							>
								{#if item.icon}
									{@const ItemIcon = item.icon}
									<ItemIcon class="h-4 w-4" />
								{/if}
								<span>{item.title}</span>
							</a>
							<div class="ml-4 mt-1 space-y-1 border-l pl-3">
								{#each item.items as subItem}
									<a
										href={subItem.url}
										class={cn(
											'block rounded-md px-3 py-1.5 text-sm transition-colors',
											isActive(subItem.url)
												? 'bg-primary/10 text-primary font-medium'
												: 'text-muted-foreground hover:bg-muted hover:text-foreground'
										)}
										onclick={onClose}
									>
										{subItem.title}
									</a>
								{/each}
							</div>
						</div>
					{:else}
						<!-- Single item -->
						<a
							href={item.url}
							class={cn(
								'flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors',
								isActive(item.url)
									? 'bg-primary/10 text-primary font-medium'
									: 'text-muted-foreground hover:bg-muted hover:text-foreground'
							)}
							onclick={onClose}
						>
							{#if item.icon}
								{@const ItemIcon = item.icon}
								<ItemIcon class="h-4 w-4" />
							{/if}
							<span>{item.title}</span>
							<ChevronRight class="ml-auto h-4 w-4 opacity-50" />
						</a>
					{/if}
				{/each}
			</div>
		</ScrollArea>
	</div>
{/if}
