<script lang="ts">
	import { cn } from '$lib/utils';
	import { User } from 'lucide-svelte';
	import type { MentionUser } from './extensions/mention';

	interface Props {
		items: MentionUser[];
		selectedIndex: number;
		onSelect: (item: MentionUser) => void;
		loading?: boolean;
		class?: string;
	}

	let { items, selectedIndex, onSelect, loading = false, class: className }: Props = $props();
</script>

<div
	class={cn(
		'mention-dropdown max-h-[300px] min-w-[200px] overflow-hidden overflow-y-auto rounded-md border bg-popover shadow-md',
		className
	)}
>
	{#if loading}
		<div class="p-3 text-center text-sm text-muted-foreground">Searching...</div>
	{:else if items.length === 0}
		<div class="p-3 text-center text-sm text-muted-foreground">No users found</div>
	{:else}
		<ul class="py-1" role="listbox">
			{#each items as item, index}
				<li
					role="option"
					aria-selected={index === selectedIndex}
					class={cn(
						'flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors',
						index === selectedIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-muted'
					)}
					onclick={() => onSelect(item)}
					onkeydown={(e) => e.key === 'Enter' && onSelect(item)}
				>
					{#if item.avatar}
						<img src={item.avatar} alt={item.name} class="h-8 w-8 rounded-full object-cover" />
					{:else}
						<div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
							<User class="h-4 w-4 text-muted-foreground" />
						</div>
					{/if}
					<div class="min-w-0 flex-1">
						<div class="truncate text-sm font-medium">{item.name}</div>
						{#if item.email}
							<div class="truncate text-xs text-muted-foreground">
								{item.email}
							</div>
						{/if}
					</div>
				</li>
			{/each}
		</ul>
	{/if}
</div>
