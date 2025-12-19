<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import { Input } from '$lib/components/ui/input';
	import {
		Search,
		ChevronDown,
		Mail,
		Bell,
		FilePlus,
		FileEdit,
		PenLine,
		Trash2,
		Link,
		UserPlus,
		CheckSquare,
		ArrowRight,
		Tag,
		Webhook,
		GitBranch,
		Clock,
		CircleStop,
		GripVertical,
		Cog
	} from 'lucide-svelte';
	import { buildPaletteItems, groupPaletteItems } from './nodeConfig';
	import type { PaletteItem } from './types';

	interface Props {
		onDragStart?: (item: PaletteItem, event: DragEvent) => void;
		onItemClick?: (item: PaletteItem) => void;
	}

	let { onDragStart, onItemClick }: Props = $props();

	const allItems = buildPaletteItems();
	const groupedItems = groupPaletteItems(allItems);

	let searchQuery = $state('');
	let openCategories = $state<Set<string>>(new Set(Object.keys(groupedItems)));

	const filteredGroups = $derived(() => {
		if (!searchQuery.trim()) return groupedItems;

		const query = searchQuery.toLowerCase();
		const filtered: Record<string, PaletteItem[]> = {};

		for (const [category, items] of Object.entries(groupedItems)) {
			const matchingItems = items.filter(
				(item) =>
					item.label.toLowerCase().includes(query) ||
					item.description.toLowerCase().includes(query)
			);
			if (matchingItems.length > 0) {
				filtered[category] = matchingItems;
			}
		}

		return filtered;
	});

	const iconMap: Record<string, typeof Cog> = {
		Mail,
		Bell,
		FilePlus,
		FileEdit,
		PenLine,
		Trash2,
		Link,
		UserPlus,
		CheckSquare,
		ArrowRight,
		Tag,
		TagOff: Tag,
		Webhook,
		GitBranch,
		Clock,
		CircleStop
	};

	function getIcon(iconName: string) {
		return iconMap[iconName] || Cog;
	}

	function handleDragStart(item: PaletteItem, event: DragEvent) {
		event.dataTransfer?.setData('application/workflow-node', JSON.stringify(item));
		event.dataTransfer!.effectAllowed = 'copy';
		onDragStart?.(item, event);
	}

	function toggleCategory(category: string) {
		const newSet = new Set(openCategories);
		if (newSet.has(category)) {
			newSet.delete(category);
		} else {
			newSet.add(category);
		}
		openCategories = newSet;
	}
</script>

<div class="node-palette">
	<div class="palette-header">
		<h3 class="palette-title">Actions</h3>
		<p class="palette-description">Drag to canvas or click to add</p>
	</div>

	<div class="palette-search">
		<div class="search-input">
			<Search class="search-icon h-4 w-4" />
			<Input
				type="text"
				placeholder="Search actions..."
				bind:value={searchQuery}
				class="pl-9"
			/>
		</div>
	</div>

	<div class="palette-content">
		{#each Object.entries(filteredGroups()) as [category, items]}
			<Collapsible.Root open={openCategories.has(category)}>
				<Collapsible.Trigger
					class="category-header"
					onclick={() => toggleCategory(category)}
				>
					<span class="category-name">{category}</span>
					<ChevronDown
						class="category-chevron h-4 w-4 transition-transform {!openCategories.has(category) ? 'rotate-180' : ''}"
					/>
				</Collapsible.Trigger>
				<Collapsible.Content class="category-content">
					{#each items as item}
						{@const Icon = getIcon(item.icon)}
						<button
							class="palette-item"
							draggable="true"
							ondragstart={(e) => handleDragStart(item, e)}
							onclick={() => onItemClick?.(item)}
							style="--item-color: {item.color}"
						>
							<div class="item-drag-handle">
								<GripVertical class="h-3 w-3" />
							</div>
							<div class="item-icon" style="background-color: {item.color}">
								<Icon class="h-4 w-4" />
							</div>
							<div class="item-info">
								<span class="item-label">{item.label}</span>
								<span class="item-description">{item.description}</span>
							</div>
						</button>
					{/each}
				</Collapsible.Content>
			</Collapsible.Root>
		{/each}

		{#if Object.keys(filteredGroups()).length === 0}
			<div class="no-results">
				<p>No actions found</p>
				<Button variant="ghost" size="sm" onclick={() => (searchQuery = '')}>
					Clear search
				</Button>
			</div>
		{/if}
	</div>
</div>

<style>
	.node-palette {
		display: flex;
		flex-direction: column;
		height: 100%;
		background: white;
		border-right: 1px solid #e2e8f0;
	}

	.palette-header {
		padding: 16px;
		border-bottom: 1px solid #e2e8f0;
	}

	.palette-title {
		font-size: 14px;
		font-weight: 600;
		color: #1e293b;
		margin: 0 0 4px 0;
	}

	.palette-description {
		font-size: 12px;
		color: #64748b;
		margin: 0;
	}

	.palette-search {
		padding: 12px 16px;
		border-bottom: 1px solid #e2e8f0;
	}

	.search-input {
		position: relative;
	}

	.search-input :global(.search-icon) {
		position: absolute;
		left: 12px;
		top: 50%;
		transform: translateY(-50%);
		color: #94a3b8;
		pointer-events: none;
	}

	.palette-content {
		flex: 1;
		overflow-y: auto;
		padding: 8px;
	}

	:global(.category-header) {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		padding: 8px 12px;
		font-size: 11px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		color: #64748b;
		background: none;
		border: none;
		cursor: pointer;
		border-radius: 6px;
	}

	:global(.category-header:hover) {
		background: #f8fafc;
	}

	.category-name {
		flex: 1;
		text-align: left;
	}

	:global(.category-content) {
		padding: 4px 0;
	}

	.palette-item {
		display: flex;
		align-items: center;
		gap: 10px;
		width: 100%;
		padding: 10px 12px;
		background: white;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		margin-bottom: 6px;
		cursor: grab;
		transition: all 0.15s ease;
		text-align: left;
	}

	.palette-item:hover {
		border-color: var(--item-color, #3b82f6);
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
	}

	.palette-item:active {
		cursor: grabbing;
	}

	.item-drag-handle {
		color: #cbd5e1;
		flex-shrink: 0;
	}

	.item-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 32px;
		height: 32px;
		border-radius: 6px;
		color: white;
		flex-shrink: 0;
	}

	.item-info {
		flex: 1;
		min-width: 0;
	}

	.item-label {
		display: block;
		font-size: 13px;
		font-weight: 500;
		color: #1e293b;
	}

	.item-description {
		display: block;
		font-size: 11px;
		color: #94a3b8;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.no-results {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 32px 16px;
		color: #64748b;
	}

	.no-results p {
		margin: 0 0 8px 0;
		font-size: 13px;
	}
</style>
