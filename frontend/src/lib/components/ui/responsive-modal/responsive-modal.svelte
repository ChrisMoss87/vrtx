<script lang="ts">
	import {
		Root as DialogRoot,
		Content as DialogContent,
		Header as DialogHeader,
		Title as DialogTitle,
		Description as DialogDescription
	} from '$lib/components/ui/dialog';
	import {
		Root as DrawerRoot,
		Content as DrawerContent,
		Header as DrawerHeader,
		Title as DrawerTitle,
		Description as DrawerDescription,
		Footer as DrawerFooter,
		Close as DrawerClose
	} from '$lib/components/ui/drawer';
	import { onMount } from 'svelte';
	import type { Snippet } from 'svelte';

	interface Props {
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
		children?: Snippet;
		title?: string;
		description?: string;
	}

	let { open = $bindable(false), onOpenChange, children, title, description }: Props = $props();

	let isDesktop = $state(false);

	onMount(() => {
		const mediaQuery = window.matchMedia('(min-width: 768px)');
		isDesktop = mediaQuery.matches;

		const handler = (e: MediaQueryListEvent) => {
			isDesktop = e.matches;
		};

		mediaQuery.addEventListener('change', handler);
		return () => mediaQuery.removeEventListener('change', handler);
	});

	function handleOpenChange(value: boolean) {
		open = value;
		onOpenChange?.(value);
	}
</script>

{#if isDesktop}
	<DialogRoot {open} onOpenChange={handleOpenChange}>
		<DialogContent class="sm:max-w-[425px]">
			{#if title || description}
				<DialogHeader>
					{#if title}
						<DialogTitle>{title}</DialogTitle>
					{/if}
					{#if description}
						<DialogDescription>{description}</DialogDescription>
					{/if}
				</DialogHeader>
			{/if}
			{#if children}
				{@render children()}
			{/if}
		</DialogContent>
	</DialogRoot>
{:else}
	<DrawerRoot {open} onOpenChange={handleOpenChange}>
		<DrawerContent>
			{#if title || description}
				<DrawerHeader class="text-left">
					{#if title}
						<DrawerTitle>{title}</DrawerTitle>
					{/if}
					{#if description}
						<DrawerDescription>{description}</DrawerDescription>
					{/if}
				</DrawerHeader>
			{/if}
			{#if children}
				<div class="px-4">
					{@render children()}
				</div>
			{/if}
			<DrawerFooter class="pt-2">
				<DrawerClose />
			</DrawerFooter>
		</DrawerContent>
	</DrawerRoot>
{/if}
