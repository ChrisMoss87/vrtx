<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Plus, GripVertical, AlertTriangle, Calendar } from 'lucide-svelte';
	import {
		createActionItem,
		updateActionItem,
		deleteActionItem,
		type DealRoomActionItem
	} from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	export let roomId: number;
	export let items: DealRoomActionItem[];
	export let onUpdate: () => void;
	export let compact = false;

	let newItemTitle = '';
	let addingItem = false;

	async function handleAddItem() {
		if (!newItemTitle.trim()) return;

		addingItem = true;
		const { error } = await tryCatch(
			createActionItem(roomId, { title: newItemTitle.trim() })
		);
		addingItem = false;

		if (error) {
			toast.error('Failed to add action item');
			return;
		}

		newItemTitle = '';
		onUpdate();
	}

	async function handleToggleComplete(item: DealRoomActionItem) {
		const newStatus = item.status === 'completed' ? 'pending' : 'completed';
		const { error } = await tryCatch(
			updateActionItem(roomId, item.id, { status: newStatus })
		);

		if (error) {
			toast.error('Failed to update item');
			return;
		}

		onUpdate();
	}

	async function handleDelete(item: DealRoomActionItem) {
		if (!confirm('Delete this action item?')) return;

		const { error } = await tryCatch(deleteActionItem(roomId, item.id));

		if (error) {
			toast.error('Failed to delete item');
			return;
		}

		onUpdate();
	}

	function formatDate(dateStr: string | null): string {
		if (!dateStr) return '';
		return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
	}

	function getPartyLabel(party: string | null): string {
		switch (party) {
			case 'seller':
				return 'Us';
			case 'buyer':
				return 'Them';
			case 'both':
				return 'Both';
			default:
				return '';
		}
	}
</script>

<div class="space-y-3">
	{#each items as item}
		<div
			class="flex items-start gap-3 p-3 rounded-lg border hover:bg-muted/50 transition-colors {item.status === 'completed'
				? 'opacity-60'
				: ''}"
		>
			<GripVertical class="h-4 w-4 text-muted-foreground mt-1 cursor-grab" />

			<Checkbox
				checked={item.status === 'completed'}
				onCheckedChange={() => handleToggleComplete(item)}
			/>

			<div class="flex-1 min-w-0">
				<div class="flex items-center gap-2">
					<span
						class="text-sm font-medium {item.status === 'completed'
							? 'line-through text-muted-foreground'
							: ''}"
					>
						{item.title}
					</span>
					{#if item.assigned_party}
						<span class="text-xs px-1.5 py-0.5 rounded bg-muted text-muted-foreground">
							{getPartyLabel(item.assigned_party)}
						</span>
					{/if}
				</div>

				{#if item.description && !compact}
					<p class="text-xs text-muted-foreground mt-1">{item.description}</p>
				{/if}

				{#if item.due_date}
					<div
						class="flex items-center gap-1 text-xs mt-1 {item.is_overdue
							? 'text-red-600'
							: 'text-muted-foreground'}"
					>
						{#if item.is_overdue}
							<AlertTriangle class="h-3 w-3" />
						{:else}
							<Calendar class="h-3 w-3" />
						{/if}
						{formatDate(item.due_date)}
					</div>
				{/if}
			</div>

			{#if !compact}
				<Button
					variant="ghost"
					size="sm"
					class="text-destructive hover:text-destructive"
					onclick={() => handleDelete(item)}
				>
					Delete
				</Button>
			{/if}
		</div>
	{/each}

	{#if items.length === 0}
		<div class="text-center py-6 text-muted-foreground text-sm">
			No action items yet. Add one below.
		</div>
	{/if}

	{#if !compact}
		<form
			onsubmit={(e) => {
				e.preventDefault();
				handleAddItem();
			}}
			class="flex gap-2 mt-4"
		>
			<Input
				bind:value={newItemTitle}
				placeholder="Add action item..."
				class="flex-1"
				disabled={addingItem}
			/>
			<Button type="submit" disabled={!newItemTitle.trim() || addingItem}>
				<Plus class="h-4 w-4" />
			</Button>
		</form>
	{/if}
</div>
