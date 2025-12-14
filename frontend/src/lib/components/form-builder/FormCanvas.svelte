<script lang="ts">
	import { flip } from 'svelte/animate';
	import { fade, slide } from 'svelte/transition';
	import { quintOut } from 'svelte/easing';
	import { Plus, Settings, Trash2, GripVertical, ChevronDown, ChevronRight } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { getFieldTypeMetadata, type FieldType } from '$lib/constants/fieldTypes';
	import type { CreateBlockRequest, CreateFieldRequest } from '$lib/api/modules';
	import { droppable } from '$lib/utils/dnd.svelte';

	interface Props {
		blocks: CreateBlockRequest[];
		onBlocksChange: (blocks: CreateBlockRequest[]) => void;
		onFieldSelect?: (blockIndex: number, fieldIndex: number) => void;
		onBlockSelect?: (blockIndex: number) => void;
		selectedBlockIndex?: number;
		selectedFieldIndex?: number;
	}

	let {
		blocks = $bindable([]),
		onBlocksChange,
		onFieldSelect,
		onBlockSelect,
		selectedBlockIndex = -1,
		selectedFieldIndex = -1
	}: Props = $props();

	// Collapsed blocks state
	let collapsedBlocks = $state<Set<number>>(new Set());

	// Section (block) reordering state
	let draggedBlockIndex = $state<number | null>(null);
	let dragOverBlockIndex = $state<number | null>(null);
	let dragOverBlockPosition = $state<'before' | 'after' | null>(null);

	// Field reordering state
	let draggedField = $state<{ blockIndex: number; fieldIndex: number } | null>(null);
	let dragOverField = $state<{
		blockIndex: number;
		fieldIndex: number;
		position: 'before' | 'after';
	} | null>(null);

	// Palette drag state
	let isDraggingFromPalette = $state(false);
	let insertAtBlockIndex = $state<number | null>(null);

	// Track if any drag is active
	let isDragging = $derived(draggedField !== null || isDraggingFromPalette);
	let isDraggingBlock = $derived(draggedBlockIndex !== null);

	function toggleBlockCollapse(blockIndex: number) {
		const newCollapsed = new Set(collapsedBlocks);
		if (newCollapsed.has(blockIndex)) {
			newCollapsed.delete(blockIndex);
		} else {
			newCollapsed.add(blockIndex);
		}
		collapsedBlocks = newCollapsed;
	}

	function addBlock() {
		const newBlock: CreateBlockRequest = {
			name: `Section ${blocks.length + 1}`,
			type: 'section',
			display_order: blocks.length,
			settings: { columns: 2, collapsible: false },
			fields: []
		};
		onBlocksChange([...blocks, newBlock]);
	}

	function removeBlock(blockIndex: number) {
		onBlocksChange(blocks.filter((_, i) => i !== blockIndex));
	}

	// Section drag handlers
	function handleBlockDragStart(event: DragEvent, blockIndex: number) {
		draggedBlockIndex = blockIndex;
		if (event.dataTransfer) {
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', `block-${blockIndex}`);
		}
	}

	function handleBlockDragOver(event: DragEvent, targetBlockIndex: number) {
		event.preventDefault();
		if (event.dataTransfer) {
			event.dataTransfer.dropEffect = 'move';
		}

		if (draggedBlockIndex === null || draggedBlockIndex === targetBlockIndex) return;

		const target = event.currentTarget as HTMLElement;
		const rect = target.getBoundingClientRect();
		const midY = rect.top + rect.height / 2;
		const position: 'before' | 'after' = event.clientY < midY ? 'before' : 'after';

		if (dragOverBlockIndex !== targetBlockIndex || dragOverBlockPosition !== position) {
			dragOverBlockIndex = targetBlockIndex;
			dragOverBlockPosition = position;
		}
	}

	function handleBlockDrop(event: DragEvent, targetBlockIndex: number) {
		event.preventDefault();
		event.stopPropagation();

		if (draggedBlockIndex === null || draggedBlockIndex === targetBlockIndex) {
			resetBlockDragState();
			return;
		}

		const position = dragOverBlockPosition || 'after';
		let actualTargetIndex = position === 'after' ? targetBlockIndex + 1 : targetBlockIndex;

		// Adjust target index if moving down
		if (draggedBlockIndex < actualTargetIndex) {
			actualTargetIndex--;
		}

		const updatedBlocks = [...blocks];
		const [movedBlock] = updatedBlocks.splice(draggedBlockIndex, 1);
		updatedBlocks.splice(actualTargetIndex, 0, movedBlock);

		// Update display_order for all blocks
		updatedBlocks.forEach((block, idx) => {
			block.display_order = idx;
		});

		onBlocksChange(updatedBlocks);
		resetBlockDragState();
	}

	function handleBlockDragEnd() {
		resetBlockDragState();
	}

	function resetBlockDragState() {
		draggedBlockIndex = null;
		dragOverBlockIndex = null;
		dragOverBlockPosition = null;
	}

	function handlePaletteDrop(blockIndex: number, data: { fieldType?: FieldType }) {
		isDraggingFromPalette = false;
		insertAtBlockIndex = null;
		if (data.fieldType) {
			addFieldToBlock(blockIndex, data.fieldType);
		}
	}

	function handlePaletteDragEnter(blockIndex: number) {
		isDraggingFromPalette = true;
		insertAtBlockIndex = blockIndex;
	}

	function handlePaletteDragLeave() {
		setTimeout(() => {
			if (insertAtBlockIndex !== null) {
				insertAtBlockIndex = null;
			}
		}, 50);
	}

	function generateApiName(label: string): string {
		return (
			label
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '_')
				.replace(/^_|_$/g, '') || 'field'
		);
	}

	function addFieldToBlock(blockIndex: number, fieldType: FieldType) {
		const metadata = getFieldTypeMetadata(fieldType);
		const block = blocks[blockIndex];
		const fieldCount = block.fields?.length || 0;
		const label = `New ${metadata.label}`;

		const newField: CreateFieldRequest = {
			label,
			api_name: generateApiName(label) + '_' + (fieldCount + 1),
			type: fieldType,
			display_order: fieldCount,
			width: metadata.defaultWidth,
			is_required: false,
			is_unique: false,
			is_searchable: true,
			is_filterable: true,
			is_sortable: true,
			is_mass_updatable: fieldType !== 'formula',
			settings: { additional_settings: {} }
		};

		if (metadata.requiresOptions) {
			newField.options = [
				{ label: 'Option 1', value: 'option_1', display_order: 0 },
				{ label: 'Option 2', value: 'option_2', display_order: 1 }
			];
		}

		const updatedBlocks = [...blocks];
		updatedBlocks[blockIndex] = {
			...block,
			fields: [...(block.fields || []), newField]
		};
		onBlocksChange(updatedBlocks);

		if (onFieldSelect) {
			onFieldSelect(blockIndex, fieldCount);
		}
	}

	function removeField(blockIndex: number, fieldIndex: number) {
		const updatedBlocks = [...blocks];
		updatedBlocks[blockIndex] = {
			...updatedBlocks[blockIndex],
			fields: updatedBlocks[blockIndex].fields?.filter((_, i) => i !== fieldIndex) || []
		};
		onBlocksChange(updatedBlocks);
	}

	function handleFieldDragStart(event: DragEvent, blockIndex: number, fieldIndex: number) {
		draggedField = { blockIndex, fieldIndex };
		if (event.dataTransfer) {
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', `field-${blockIndex}-${fieldIndex}`);
		}
		requestAnimationFrame(() => {
			const target = event.target as HTMLElement;
			target.classList.add('is-dragging');
		});
	}

	function handleFieldDragOver(
		event: DragEvent,
		targetBlockIndex: number,
		targetFieldIndex: number
	) {
		event.preventDefault();
		if (event.dataTransfer) {
			event.dataTransfer.dropEffect = 'move';
		}

		if (!draggedField) {
			isDraggingFromPalette = true;
			insertAtBlockIndex = targetBlockIndex;
		}

		const target = event.currentTarget as HTMLElement;
		const rect = target.getBoundingClientRect();
		const midY = rect.top + rect.height / 2;
		const position: 'before' | 'after' = event.clientY < midY ? 'before' : 'after';

		if (
			dragOverField?.blockIndex !== targetBlockIndex ||
			dragOverField?.fieldIndex !== targetFieldIndex ||
			dragOverField?.position !== position
		) {
			dragOverField = { blockIndex: targetBlockIndex, fieldIndex: targetFieldIndex, position };
		}
	}

	function handleFieldDrop(event: DragEvent, targetBlockIndex: number, targetFieldIndex: number) {
		event.preventDefault();
		event.stopPropagation();

		if (event.dataTransfer) {
			try {
				const rawData = event.dataTransfer.getData('application/json');
				if (rawData) {
					const parsed = JSON.parse(rawData);
					if (parsed.sourceId === 'field-palette' && parsed.data?.fieldType) {
						addFieldToBlock(targetBlockIndex, parsed.data.fieldType);
						resetDragState();
						return;
					}
				}
			} catch {
				// Continue with field reorder
			}
		}

		if (!draggedField) return;

		const { blockIndex: sourceBlockIndex, fieldIndex: sourceFieldIndex } = draggedField;
		const dropPosition = dragOverField?.position || 'after';
		let actualTargetIndex = dropPosition === 'after' ? targetFieldIndex + 1 : targetFieldIndex;

		if (sourceBlockIndex === targetBlockIndex) {
			if (sourceFieldIndex === actualTargetIndex || sourceFieldIndex === actualTargetIndex - 1) {
				resetDragState();
				return;
			}
		}

		const updatedBlocks = [...blocks];
		const sourceBlock = updatedBlocks[sourceBlockIndex];
		const targetBlock = updatedBlocks[targetBlockIndex];

		if (!sourceBlock.fields || !targetBlock.fields) {
			resetDragState();
			return;
		}

		const [movedField] = sourceBlock.fields.splice(sourceFieldIndex, 1);

		if (sourceBlockIndex === targetBlockIndex && sourceFieldIndex < actualTargetIndex) {
			actualTargetIndex--;
		}

		targetBlock.fields.splice(actualTargetIndex, 0, movedField);

		sourceBlock.fields.forEach((field, idx) => {
			field.display_order = idx;
		});
		if (sourceBlockIndex !== targetBlockIndex) {
			targetBlock.fields.forEach((field, idx) => {
				field.display_order = idx;
			});
		}

		onBlocksChange(updatedBlocks);
		resetDragState();
	}

	function handleFieldDragEnd(event: DragEvent) {
		const target = event.target as HTMLElement;
		target.classList.remove('is-dragging');
		resetDragState();
	}

	function handleFieldDragLeave(event: DragEvent) {
		const relatedTarget = event.relatedTarget as HTMLElement | null;
		const currentTarget = event.currentTarget as HTMLElement;

		if (!relatedTarget || !currentTarget.contains(relatedTarget)) {
			dragOverField = null;
		}
	}

	function resetDragState() {
		draggedField = null;
		dragOverField = null;
		isDraggingFromPalette = false;
		insertAtBlockIndex = null;
	}

	function getFieldIcon(fieldType: string) {
		const metadata = getFieldTypeMetadata(fieldType as FieldType);
		return metadata?.icon;
	}

	// Max 3 columns using 6-column grid
	function getWidthClass(width: number = 100): string {
		if (width <= 33) return 'col-span-2'; // 1/3
		if (width <= 50) return 'col-span-3'; // 1/2
		if (width <= 66) return 'col-span-4'; // 2/3
		return 'col-span-6'; // full
	}

	function getFieldKey(field: CreateFieldRequest, blockIndex: number, fieldIndex: number): string {
		return `${blockIndex}-${field.api_name || fieldIndex}`;
	}
</script>

<div class="form-canvas flex-1 overflow-y-auto bg-muted/30 p-4 md:p-6">
	<div class="mx-auto max-w-3xl space-y-4">
		{#each blocks as block, blockIndex (blockIndex)}
			{@const isCollapsed = collapsedBlocks.has(blockIndex)}
			{@const isDropTarget = insertAtBlockIndex === blockIndex}
			{@const isSelected = selectedBlockIndex === blockIndex}
			{@const isBlockDragging = draggedBlockIndex === blockIndex}
			{@const isBlockDropBefore = dragOverBlockIndex === blockIndex && dragOverBlockPosition === 'before'}
			{@const isBlockDropAfter = dragOverBlockIndex === blockIndex && dragOverBlockPosition === 'after'}

			<!-- Drop indicator before block -->
			{#if isBlockDropBefore && !isBlockDragging}
				<div class="block-drop-indicator"></div>
			{/if}

			<div
				class="block-container rounded-lg border bg-card shadow-sm transition-all duration-200
					{isSelected ? 'ring-2 ring-primary ring-offset-2' : ''}
					{isDropTarget ? 'ring-2 ring-primary border-primary' : ''}
					{isBlockDragging ? 'opacity-50 scale-[0.98]' : ''}"
				draggable="true"
				ondragstart={(e) => handleBlockDragStart(e, blockIndex)}
				ondragover={(e) => handleBlockDragOver(e, blockIndex)}
				ondrop={(e) => handleBlockDrop(e, blockIndex)}
				ondragend={handleBlockDragEnd}
				role="listitem"
			>
				<!-- Block Header -->
				<div class="flex items-center gap-2 border-b px-4 py-3">
					<!-- Drag Handle -->
					<div class="cursor-grab active:cursor-grabbing shrink-0 rounded p-1 hover:bg-accent">
						<GripVertical class="h-4 w-4 text-muted-foreground" />
					</div>

					<button
						type="button"
						class="shrink-0 rounded p-1 hover:bg-accent"
						onclick={() => toggleBlockCollapse(blockIndex)}
					>
						{#if isCollapsed}
							<ChevronRight class="h-4 w-4 text-muted-foreground" />
						{:else}
							<ChevronDown class="h-4 w-4 text-muted-foreground" />
						{/if}
					</button>

					<input
						type="text"
						bind:value={block.name}
						class="flex-1 bg-transparent text-base font-semibold focus:outline-none"
						placeholder="Section Name"
						ondragstart={(e) => e.stopPropagation()}
						draggable="false"
					/>

					<span class="text-xs text-muted-foreground">
						{block.fields?.length || 0} fields
					</span>

					<Button
						variant="ghost"
						size="icon"
						class="h-8 w-8"
						onclick={() => onBlockSelect?.(blockIndex)}
					>
						<Settings class="h-4 w-4" />
					</Button>

					<Button
						variant="ghost"
						size="icon"
						class="h-8 w-8 hover:bg-destructive/10 hover:text-destructive"
						onclick={() => removeBlock(blockIndex)}
					>
						<Trash2 class="h-4 w-4" />
					</Button>
				</div>

				<!-- Block Content -->
				{#if !isCollapsed}
					<div
						class="p-4 transition-all duration-200 min-h-[80px]
							{isDropTarget ? 'bg-primary/5' : ''}"
						use:droppable={{
							accepts: ['field-palette'],
							onDragEnter: () => handlePaletteDragEnter(blockIndex),
							onDragLeave: handlePaletteDragLeave,
							onDrop: (item) =>
								handlePaletteDrop(blockIndex, item.data as { fieldType?: FieldType })
						}}
						transition:slide={{ duration: 150 }}
					>
						{#if !block.fields || block.fields.length === 0}
							<!-- Empty State -->
							<div
								class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed py-10 text-center transition-all duration-200
									{isDropTarget ? 'border-primary bg-primary/10 scale-[1.02]' : 'border-muted-foreground/30'}"
							>
								<div class="rounded-full p-3 mb-2 {isDropTarget ? 'bg-primary/20' : 'bg-muted'}">
									<Plus
										class="h-6 w-6 {isDropTarget ? 'text-primary' : 'text-muted-foreground'}"
									/>
								</div>
								<p class="text-sm font-medium {isDropTarget ? 'text-primary' : 'text-muted-foreground'}">
									{isDropTarget ? 'Release to add field' : 'Drag fields here'}
								</p>
							</div>
						{:else}
							<!-- Fields Grid - Max 3 columns -->
							<!-- svelte-ignore a11y_no_static_element_interactions -->
							<div
								class="grid grid-cols-6 gap-3"
								role="list"
								ondragover={(e) => {
									e.preventDefault();
									if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
									isDraggingFromPalette = true;
									insertAtBlockIndex = blockIndex;
								}}
								ondrop={(e) => {
									if (e.dataTransfer) {
										try {
											const rawData = e.dataTransfer.getData('application/json');
											if (rawData) {
												const parsed = JSON.parse(rawData);
												if (parsed.sourceId === 'field-palette' && parsed.data?.fieldType) {
													e.preventDefault();
													e.stopPropagation();
													addFieldToBlock(blockIndex, parsed.data.fieldType);
													resetDragState();
												}
											}
										} catch {
											// Ignore
										}
									}
								}}
							>
								{#each block.fields as field, fieldIndex (getFieldKey(field, blockIndex, fieldIndex))}
									{@const FieldIcon = getFieldIcon(field.type)}
									{@const isFieldDragging =
										draggedField?.blockIndex === blockIndex &&
										draggedField?.fieldIndex === fieldIndex}
									{@const isFieldSelected =
										selectedBlockIndex === blockIndex && selectedFieldIndex === fieldIndex}
									{@const isDropBefore =
										dragOverField?.blockIndex === blockIndex &&
										dragOverField?.fieldIndex === fieldIndex &&
										dragOverField?.position === 'before' &&
										!isFieldDragging}
									{@const isDropAfter =
										dragOverField?.blockIndex === blockIndex &&
										dragOverField?.fieldIndex === fieldIndex &&
										dragOverField?.position === 'after' &&
										!isFieldDragging}

									<div
										class="field-item relative {getWidthClass(field.width)}
											{isFieldDragging ? 'opacity-30 scale-95' : ''}
											{isDropBefore ? 'drop-before' : ''}
											{isDropAfter ? 'drop-after' : ''}"
										draggable="true"
										ondragstart={(e) => handleFieldDragStart(e, blockIndex, fieldIndex)}
										ondragover={(e) => handleFieldDragOver(e, blockIndex, fieldIndex)}
										ondrop={(e) => handleFieldDrop(e, blockIndex, fieldIndex)}
										ondragend={handleFieldDragEnd}
										ondragleave={(e) => handleFieldDragLeave(e)}
										role="listitem"
										animate:flip={{ duration: 200, easing: quintOut }}
									>
										<!-- svelte-ignore a11y_click_events_have_key_events -->
										<div
											class="group flex w-full items-center gap-2 rounded-md border bg-background p-2.5 text-left transition-all duration-150 cursor-pointer
												{isFieldSelected ? 'border-primary bg-primary/5 shadow-sm' : 'border-border hover:border-primary/50 hover:shadow-sm'}"
											onclick={() => onFieldSelect?.(blockIndex, fieldIndex)}
											role="button"
											tabindex="0"
										>
											<div class="cursor-grab active:cursor-grabbing" onclick={(e) => e.stopPropagation()}>
												<GripVertical class="h-4 w-4 text-muted-foreground/40 group-hover:text-muted-foreground" />
											</div>

											<div class="flex min-w-0 flex-1 items-center gap-2">
												{#if FieldIcon}
													<FieldIcon class="h-4 w-4 shrink-0 text-primary" />
												{/if}
												<div class="min-w-0 flex-1">
													<div class="flex items-center gap-1">
														<span class="truncate text-sm font-medium">{field.label}</span>
														{#if field.is_required}
															<span class="text-destructive">*</span>
														{/if}
													</div>
													<span class="text-xs text-muted-foreground">
														{getFieldTypeMetadata(field.type as FieldType).label}
													</span>
												</div>
											</div>

											<button
												type="button"
												class="shrink-0 rounded p-1 opacity-0 transition-opacity hover:bg-destructive/10 hover:text-destructive group-hover:opacity-100"
												onclick={(e) => {
													e.stopPropagation();
													removeField(blockIndex, fieldIndex);
												}}
											>
												<Trash2 class="h-3.5 w-3.5" />
											</button>
										</div>
									</div>
								{/each}
							</div>

							<!-- Always show drop zone at bottom when dragging -->
							{#if isDragging}
								<div
									class="mt-3 flex items-center justify-center gap-2 rounded-lg border-2 border-dashed py-4 transition-all duration-200
										{isDropTarget ? 'border-primary bg-primary/10' : 'border-muted-foreground/30'}"
									transition:fade={{ duration: 100 }}
								>
									<Plus class="h-4 w-4 {isDropTarget ? 'text-primary' : 'text-muted-foreground'}" />
									<span class="text-sm {isDropTarget ? 'text-primary font-medium' : 'text-muted-foreground'}">
										Drop here to add
									</span>
								</div>
							{/if}
						{/if}
					</div>
				{/if}
			</div>

			<!-- Drop indicator after block -->
			{#if isBlockDropAfter && !isBlockDragging}
				<div class="block-drop-indicator"></div>
			{/if}
		{/each}

		<!-- Add Block -->
		<button
			type="button"
			class="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-muted-foreground/25 py-6 text-muted-foreground transition-all hover:border-primary hover:bg-primary/5 hover:text-primary"
			onclick={addBlock}
		>
			<Plus class="h-5 w-5" />
			<span class="font-medium">Add Section</span>
		</button>

		{#if blocks.length === 0}
			<div class="py-12 text-center">
				<div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
					<Plus class="h-8 w-8 text-muted-foreground" />
				</div>
				<h3 class="mb-2 text-lg font-semibold">Start building your form</h3>
				<p class="mb-6 text-sm text-muted-foreground">
					Add a section to organize your fields
				</p>
				<Button onclick={addBlock}>
					<Plus class="mr-2 h-4 w-4" />
					Create First Section
				</Button>
			</div>
		{/if}
	</div>
</div>

<style>
	.field-item {
		min-width: 0;
		transition: transform 0.2s ease, opacity 0.2s ease, margin 0.2s ease;
	}

	/* Fields move apart to show drop zone */
	.field-item.drop-before {
		margin-top: 52px;
	}

	.field-item.drop-after {
		margin-bottom: 52px;
	}

	/* Drop indicator placeholder - shows where field will go */
	.field-item.drop-before::before,
	.field-item.drop-after::after {
		content: 'Drop here';
		position: absolute;
		left: 0;
		right: 0;
		height: 48px;
		background: hsl(var(--primary) / 0.1);
		border: 2px dashed hsl(var(--primary) / 0.5);
		border-radius: 6px;
		z-index: 10;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 12px;
		font-weight: 500;
		color: hsl(var(--primary));
	}

	.field-item.drop-before::before {
		top: -54px;
	}

	.field-item.drop-after::after {
		bottom: -54px;
	}

	/* Block drop indicator - shows where section will go */
	.block-drop-indicator {
		height: 4px;
		border-radius: 2px;
		background: hsl(var(--primary));
		margin: -2px 0;
		animation: pulse 0.8s ease-in-out infinite;
	}

	@keyframes pulse {
		0%, 100% { opacity: 0.7; }
		50% { opacity: 1; }
	}

	/* Dragging states */
	.field-item.is-dragging {
		opacity: 0.3;
	}

	/* Responsive - stack on mobile */
	@media (max-width: 640px) {
		.col-span-2,
		.col-span-3,
		.col-span-4 {
			grid-column: span 6;
		}
	}
</style>
