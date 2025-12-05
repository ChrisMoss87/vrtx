<script lang="ts">
	import { flip } from 'svelte/animate';
	import { quintOut } from 'svelte/easing';
	import type { StageInput } from '$lib/api/pipelines';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { cn } from '$lib/utils';
	import GripVertical from 'lucide-svelte/icons/grip-vertical';
	import Pencil from 'lucide-svelte/icons/pencil';
	import Trash2 from 'lucide-svelte/icons/trash-2';
	import Trophy from 'lucide-svelte/icons/trophy';
	import XCircle from 'lucide-svelte/icons/x-circle';

	interface Props {
		stages: StageInput[];
		onEdit: (stage: StageInput, index: number) => void;
		onDelete: (index: number) => void;
		onReorder: (fromIndex: number, toIndex: number) => void;
		class?: string;
	}

	let { stages, onEdit, onDelete, onReorder, class: className }: Props = $props();

	let draggedIndex = $state<number | null>(null);
	let dragOverIndex = $state<number | null>(null);

	function handleDragStart(e: DragEvent, index: number) {
		draggedIndex = index;
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', String(index));
		}
		// Add dragging class after a tick
		requestAnimationFrame(() => {
			const target = e.target as HTMLElement;
			target.classList.add('is-dragging');
		});
	}

	function handleDragOver(e: DragEvent, index: number) {
		e.preventDefault();
		if (e.dataTransfer) {
			e.dataTransfer.dropEffect = 'move';
		}
		if (dragOverIndex !== index) {
			dragOverIndex = index;
		}
	}

	function handleDragLeave() {
		dragOverIndex = null;
	}

	function handleDrop(e: DragEvent, toIndex: number) {
		e.preventDefault();
		if (draggedIndex !== null && draggedIndex !== toIndex) {
			onReorder(draggedIndex, toIndex);
		}
		resetDragState();
	}

	function handleDragEnd(e: DragEvent) {
		const target = e.target as HTMLElement;
		target.classList.remove('is-dragging');
		resetDragState();
	}

	function resetDragState() {
		draggedIndex = null;
		dragOverIndex = null;
	}

	// Generate unique key for stage
	function getStageKey(stage: StageInput, index: number): string {
		return stage.id ? String(stage.id) : `stage-${index}-${stage.name}`;
	}
</script>

<div class={cn('space-y-2', className)}>
	{#each stages as stage, index (getStageKey(stage, index))}
		{@const isDragging = draggedIndex === index}
		{@const isDragOver = dragOverIndex === index && draggedIndex !== index}
		<div
			class={cn(
				'stage-item flex items-center gap-3 rounded-lg border bg-card p-3 transition-all duration-200',
				isDragging && 'scale-[0.98] opacity-50 shadow-lg',
				isDragOver && 'border-2 border-primary ring-2 ring-primary/20'
			)}
			draggable="true"
			ondragstart={(e) => handleDragStart(e, index)}
			ondragover={(e) => handleDragOver(e, index)}
			ondragleave={handleDragLeave}
			ondrop={(e) => handleDrop(e, index)}
			ondragend={handleDragEnd}
			role="listitem"
			animate:flip={{ duration: 250, easing: quintOut }}
		>
			<!-- Drag Handle -->
			<div class="cursor-grab text-muted-foreground active:cursor-grabbing">
				<GripVertical class="h-5 w-5" />
			</div>

			<!-- Color Indicator -->
			<div class="h-6 w-6 rounded-full" style="background-color: {stage.color || '#6b7280'}"></div>

			<!-- Stage Info -->
			<div class="flex-1">
				<div class="flex items-center gap-2">
					<span class="font-medium">{stage.name}</span>
					{#if stage.is_won_stage}
						<Badge variant="outline" class="border-green-500 text-green-600">
							<Trophy class="mr-1 h-3 w-3" />
							Won
						</Badge>
					{/if}
					{#if stage.is_lost_stage}
						<Badge variant="outline" class="border-red-500 text-red-600">
							<XCircle class="mr-1 h-3 w-3" />
							Lost
						</Badge>
					{/if}
				</div>
				{#if stage.probability !== undefined && stage.probability > 0}
					<span class="text-sm text-muted-foreground">{stage.probability}% probability</span>
				{/if}
			</div>

			<!-- Actions -->
			<div class="flex items-center gap-1">
				<Button variant="ghost" size="sm" onclick={() => onEdit(stage, index)}>
					<Pencil class="h-4 w-4" />
				</Button>
				<Button
					variant="ghost"
					size="sm"
					class="text-destructive hover:text-destructive"
					onclick={() => onDelete(index)}
				>
					<Trash2 class="h-4 w-4" />
				</Button>
			</div>
		</div>
	{/each}
</div>
