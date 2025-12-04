<script lang="ts">
	import { cn } from '$lib/utils';
	import {
		Heading1,
		Heading2,
		Heading3,
		List,
		ListOrdered,
		Quote,
		Code,
		Minus,
		Table,
		Image
	} from 'lucide-svelte';
	import type { SlashCommand } from './extensions/slashCommands';

	interface Props {
		items: SlashCommand[];
		selectedIndex: number;
		onSelect: (item: SlashCommand) => void;
		class?: string;
	}

	let { items, selectedIndex, onSelect, class: className }: Props = $props();

	// Group items by category
	let groupedItems = $derived(() => {
		const groups: Record<string, SlashCommand[]> = {};
		for (const item of items) {
			const category = item.category || 'Other';
			if (!groups[category]) {
				groups[category] = [];
			}
			groups[category].push(item);
		}
		return groups;
	});

	// Flatten for index calculation
	let flatItems = $derived(items);

	function getIcon(iconName?: string) {
		switch (iconName) {
			case 'heading-1':
				return Heading1;
			case 'heading-2':
				return Heading2;
			case 'heading-3':
				return Heading3;
			case 'list':
				return List;
			case 'list-ordered':
				return ListOrdered;
			case 'quote':
				return Quote;
			case 'code':
				return Code;
			case 'minus':
				return Minus;
			case 'table':
				return Table;
			case 'image':
				return Image;
			default:
				return null;
		}
	}
</script>

<div
	class={cn(
		'command-menu max-h-[350px] min-w-[280px] overflow-hidden overflow-y-auto rounded-md border bg-popover shadow-lg',
		className
	)}
>
	{#if items.length === 0}
		<div class="p-3 text-center text-sm text-muted-foreground">No commands found</div>
	{:else}
		<div class="py-1">
			{#each Object.entries(groupedItems()) as [category, categoryItems]}
				<div class="px-3 py-1.5">
					<div class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
						{category}
					</div>
				</div>
				{#each categoryItems as item}
					{@const globalIndex = flatItems.indexOf(item)}
					{@const IconComponent = getIcon(item.icon)}
					<button
						type="button"
						class={cn(
							'flex w-full items-center gap-3 px-3 py-2 text-left transition-colors',
							globalIndex === selectedIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-muted'
						)}
						onclick={() => onSelect(item)}
					>
						<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-muted">
							{#if IconComponent}
								<IconComponent class="h-5 w-5" />
							{:else}
								<span class="text-sm font-medium">/</span>
							{/if}
						</div>
						<div class="min-w-0 flex-1">
							<div class="text-sm font-medium">{item.title}</div>
							<div class="truncate text-xs text-muted-foreground">
								{item.description}
							</div>
						</div>
					</button>
				{/each}
			{/each}
		</div>
	{/if}
</div>
