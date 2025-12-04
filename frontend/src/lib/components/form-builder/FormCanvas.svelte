<script lang="ts">
	import { flip } from 'svelte/animate';
	import { crossfade, fade, scale } from 'svelte/transition';
	import { quintOut } from 'svelte/easing';
	import { Plus, Settings, Trash2, GripVertical } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
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

	// Create crossfade transition for smooth field movement
	const [send, receive] = crossfade({
		duration: 250,
		easing: quintOut,
		fallback(node) {
			return scale(node, { start: 0.95, duration: 200 });
		}
	});

	// Field reordering state
	let draggedField = $state<{ blockIndex: number; fieldIndex: number } | null>(null);
	let dragOverField = $state<{ blockIndex: number; fieldIndex: number } | null>(null);

	function addBlock() {
		const newBlock: CreateBlockRequest = {
			name: `Block ${blocks.length + 1}`,
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

	function handlePaletteDrop(blockIndex: number, data: { fieldType?: FieldType }) {
		if (data.fieldType) {
			addFieldToBlock(blockIndex, data.fieldType);
		}
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
			settings: {
				additional_settings: {}
			}
		};

		// Add options for fields that require them
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

		// Auto-select the new field
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

	// Field reordering with Svelte transitions
	function handleFieldDragStart(event: DragEvent, blockIndex: number, fieldIndex: number) {
		draggedField = { blockIndex, fieldIndex };
		if (event.dataTransfer) {
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', `field-${blockIndex}-${fieldIndex}`);
		}
		// Add dragging class after a tick to allow browser to capture drag image
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
		// Only update if position changed
		if (
			dragOverField?.blockIndex !== targetBlockIndex ||
			dragOverField?.fieldIndex !== targetFieldIndex
		) {
			dragOverField = { blockIndex: targetBlockIndex, fieldIndex: targetFieldIndex };
		}
	}

	function handleFieldDrop(event: DragEvent, targetBlockIndex: number, targetFieldIndex: number) {
		event.preventDefault();
		event.stopPropagation();

		if (!draggedField) return;

		const { blockIndex: sourceBlockIndex, fieldIndex: sourceFieldIndex } = draggedField;

		// Don't do anything if dropping in same position
		if (sourceBlockIndex === targetBlockIndex && sourceFieldIndex === targetFieldIndex) {
			resetDragState();
			return;
		}

		const updatedBlocks = [...blocks];
		const sourceBlock = updatedBlocks[sourceBlockIndex];
		const targetBlock = updatedBlocks[targetBlockIndex];

		if (!sourceBlock.fields || !targetBlock.fields) {
			resetDragState();
			return;
		}

		// Remove from source
		const [movedField] = sourceBlock.fields.splice(sourceFieldIndex, 1);

		// Insert at target
		if (sourceBlockIndex === targetBlockIndex) {
			// Same block - adjust index if needed
			const adjustedIndex =
				sourceFieldIndex < targetFieldIndex ? targetFieldIndex - 1 : targetFieldIndex;
			targetBlock.fields.splice(adjustedIndex, 0, movedField);
		} else {
			// Different block
			targetBlock.fields.splice(targetFieldIndex, 0, movedField);
		}

		// Update display orders
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

	function handleFieldDragLeave() {
		dragOverField = null;
	}

	function resetDragState() {
		draggedField = null;
		dragOverField = null;
	}

	function getFieldIcon(fieldType: string) {
		const metadata = getFieldTypeMetadata(fieldType as FieldType);
		return metadata?.icon;
	}

	function getWidthClass(width: number = 100): string {
		if (width <= 25) return 'w-1/4';
		if (width <= 33) return 'w-1/3';
		if (width <= 50) return 'w-1/2';
		return 'w-full';
	}

	// Generate unique key for field based on its properties
	function getFieldKey(field: CreateFieldRequest, blockIndex: number, fieldIndex: number): string {
		return `${blockIndex}-${field.api_name || fieldIndex}`;
	}
</script>

<div
	class="form-canvas scrollbar-thin flex-1 overflow-y-auto bg-gradient-to-br from-background via-muted/20 to-background p-4 md:p-6"
>
	<div class="mx-auto max-w-5xl space-y-6">
		<!-- Blocks -->
		{#each blocks as block, blockIndex (blockIndex)}
			<Card.Root
				class="border-2 shadow-sm {selectedBlockIndex === blockIndex
					? 'border-primary shadow-primary/10'
					: 'border-border'} transition-all hover:shadow-md"
			>
				<Card.Header class="bg-card/50 pb-3">
					<div class="flex items-center justify-between gap-4">
						<div class="flex min-w-0 flex-1 items-center gap-3">
							<button
								class="shrink-0 cursor-grab rounded p-1.5 transition-colors hover:bg-accent"
								title="Drag to reorder"
							>
								<GripVertical class="h-4 w-4 text-muted-foreground" />
							</button>
							<div class="min-w-0 flex-1">
								<input
									type="text"
									bind:value={block.name}
									class="-mx-2 w-full rounded border-none bg-transparent px-2 py-1 text-lg font-semibold focus:ring-2 focus:ring-primary/20 focus:outline-none"
									placeholder="Block Name"
									data-testid="block-name-{blockIndex}"
								/>
								<p class="mt-0.5 px-2 text-sm text-muted-foreground">
									{block.type} • {block.fields?.length || 0}
									{block.fields?.length === 1 ? 'field' : 'fields'}
								</p>
							</div>
						</div>
						<div class="flex shrink-0 items-center gap-1">
							<Button
								variant="ghost"
								size="icon"
								onclick={() => onBlockSelect?.(blockIndex)}
								data-testid="block-settings-{blockIndex}"
								class="transition-colors hover:bg-primary/10"
							>
								<Settings class="h-4 w-4" />
							</Button>
							<Button
								variant="ghost"
								size="icon"
								onclick={() => removeBlock(blockIndex)}
								data-testid="block-delete-{blockIndex}"
								class="transition-colors hover:bg-destructive/10 hover:text-destructive"
							>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					</div>
				</Card.Header>

				<Card.Content class="pt-4">
					<!-- Drop Zone with droppable action -->
					<div
						class="drop-zone min-h-32 rounded-lg border-2 border-dashed p-4 {block.fields?.length
							? 'border-border bg-background'
							: 'border-muted-foreground/30 bg-muted/20 hover:border-muted-foreground/50 hover:bg-muted/30'} transition-all"
						use:droppable={{
							accepts: ['field-palette'],
							onDrop: (item) => handlePaletteDrop(blockIndex, item.data as { fieldType?: FieldType })
						}}
						data-testid="drop-zone-{blockIndex}"
					>
						{#if !block.fields || block.fields.length === 0}
							<div class="py-10 text-center text-muted-foreground" in:fade={{ duration: 150 }}>
								<div class="relative mb-3">
									<div
										class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-muted/50"
									>
										<Plus class="h-8 w-8 opacity-50" />
									</div>
								</div>
								<p class="mb-1 text-base font-medium">Drop fields here</p>
								<p class="text-sm">Drag field types from the palette to get started</p>
							</div>
						{:else}
							<!-- Fields Grid with animations -->
							<div class="flex flex-wrap gap-3">
								{#each block.fields as field, fieldIndex (getFieldKey(field, blockIndex, fieldIndex))}
									{@const FieldIcon = getFieldIcon(field.type)}
									{@const isDragging =
										draggedField?.blockIndex === blockIndex &&
										draggedField?.fieldIndex === fieldIndex}
									{@const isDragOver =
										dragOverField?.blockIndex === blockIndex &&
										dragOverField?.fieldIndex === fieldIndex}
									<div
										class="field-preview {getWidthClass(field.width)}"
										draggable="true"
										ondragstart={(e) => handleFieldDragStart(e, blockIndex, fieldIndex)}
										ondragover={(e) => handleFieldDragOver(e, blockIndex, fieldIndex)}
										ondrop={(e) => handleFieldDrop(e, blockIndex, fieldIndex)}
										ondragend={handleFieldDragEnd}
										ondragleave={handleFieldDragLeave}
										role="button"
										tabindex="0"
										animate:flip={{ duration: 250, easing: quintOut }}
										in:receive={{ key: getFieldKey(field, blockIndex, fieldIndex) }}
										out:send={{ key: getFieldKey(field, blockIndex, fieldIndex) }}
									>
										<button
											class="group w-full rounded-lg border-2 p-3.5 text-left transition-all duration-200
												{selectedBlockIndex === blockIndex && selectedFieldIndex === fieldIndex
												? 'border-primary bg-primary/5 shadow-sm'
												: 'border-border hover:border-primary/50 hover:shadow-sm'}
												{isDragging ? 'scale-95 opacity-40 shadow-lg' : ''}
												{isDragOver && !isDragging
												? 'border-primary/70 bg-primary/5 ring-2 ring-primary/20'
												: ''}"
											onclick={() => onFieldSelect?.(blockIndex, fieldIndex)}
											data-testid="field-{blockIndex}-{fieldIndex}"
										>
											<div class="flex items-start gap-3">
												<div
													class="shrink-0 cursor-grab rounded p-1 transition-colors hover:bg-accent"
													onclick={(e) => e.stopPropagation()}
													title="Drag to reorder"
													role="button"
													tabindex="0"
												>
													<GripVertical
														class="h-4 w-4 text-muted-foreground transition-colors group-hover:text-foreground"
													/>
												</div>
												<div
													class="shrink-0 rounded bg-primary/10 p-1.5 text-primary transition-all group-hover:bg-primary group-hover:text-primary-foreground"
												>
													{#if FieldIcon}
														<FieldIcon class="h-4 w-4" />
													{/if}
												</div>
												<div class="min-w-0 flex-1">
													<div class="mb-1 flex items-center gap-2">
														<span class="truncate text-sm font-medium">{field.label}</span>
														{#if field.is_required}
															<span class="text-xs font-bold text-destructive">*</span>
														{/if}
													</div>
													<p class="text-xs text-muted-foreground">
														{getFieldTypeMetadata(field.type as FieldType).label}
														{#if field.width}• {field.width}% width{/if}
													</p>
												</div>
												<Button
													variant="ghost"
													size="icon"
													class="h-7 w-7 shrink-0 transition-colors hover:bg-destructive/10 hover:text-destructive"
													onclick={(e) => {
														e.stopPropagation();
														removeField(blockIndex, fieldIndex);
													}}
													data-testid="field-delete-{blockIndex}-{fieldIndex}"
												>
													<Trash2 class="h-3.5 w-3.5" />
												</Button>
											</div>
										</button>
									</div>
								{/each}
							</div>
						{/if}
					</div>
				</Card.Content>
			</Card.Root>
		{/each}

		<!-- Add Block Button -->
		<Button
			variant="outline"
			class="group h-20 w-full border-2 border-dashed transition-all hover:border-primary hover:bg-primary/5"
			onclick={addBlock}
			data-testid="add-block"
		>
			<Plus class="mr-2 h-5 w-5 transition-transform group-hover:scale-110" />
			<span class="font-medium">Add Block</span>
		</Button>

		{#if blocks.length === 0}
			<div class="px-4 py-20 text-center">
				<div class="relative mb-6">
					<div
						class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-primary/20 to-primary/5"
					>
						<Plus class="h-12 w-12 text-primary opacity-50" />
					</div>
					<div
						class="absolute inset-0 mx-auto h-24 w-24 animate-ping rounded-full bg-primary/5"
						style="animation-duration: 3s;"
					></div>
				</div>
				<h3 class="mb-2 text-xl font-bold">Start building your form</h3>
				<p class="mx-auto mb-8 max-w-md text-muted-foreground">
					Add a block to organize your fields into logical sections. Each block can contain multiple
					fields.
				</p>
				<Button onclick={addBlock} data-testid="add-first-block" size="lg" class="shadow-lg">
					<Plus class="mr-2 h-5 w-5" />
					Create First Block
				</Button>
			</div>
		{/if}
	</div>
</div>

<style>
	.drop-zone.drag-over {
		border-color: hsl(var(--primary));
		background-color: hsl(var(--primary) / 0.05);
	}

	.field-preview {
		min-width: 200px;
	}

	/* Dragging state styles */
	.field-preview.is-dragging,
	.field-preview:has(.is-dragging) {
		opacity: 0.4;
		transform: scale(0.95);
	}

	/* Smooth transitions for field items */
	.field-preview {
		transition: transform 0.2s ease, opacity 0.2s ease;
	}
</style>
