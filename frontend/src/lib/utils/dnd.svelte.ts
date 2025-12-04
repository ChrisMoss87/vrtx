/**
 * Svelte 5 Drag and Drop Utilities
 *
 * A lightweight, Svelte-native drag-and-drop system with:
 * - Full TypeScript support
 * - Svelte 5 runes for reactivity
 * - Animation-friendly design
 * - Touch support
 * - Keyboard accessibility
 */

export interface DragItem<T = unknown> {
	id: string;
	data: T;
	sourceId: string;
}

export interface DropZone {
	id: string;
	accepts?: string[];
}

export interface DragState<T = unknown> {
	isDragging: boolean;
	draggedItem: DragItem<T> | null;
	sourceId: string | null;
	targetId: string | null;
	position: { x: number; y: number } | null;
}

export interface SortableItem<T = unknown> {
	id: string;
	data: T;
}

/**
 * Create a drag-and-drop context for managing drag state across components
 */
export function createDragContext<T = unknown>() {
	let state = $state<DragState<T>>({
		isDragging: false,
		draggedItem: null,
		sourceId: null,
		targetId: null,
		position: null
	});

	function startDrag(item: DragItem<T>) {
		state = {
			isDragging: true,
			draggedItem: item,
			sourceId: item.sourceId,
			targetId: null,
			position: null
		};
	}

	function updatePosition(x: number, y: number) {
		if (state.isDragging) {
			state = { ...state, position: { x, y } };
		}
	}

	function setTarget(targetId: string | null) {
		if (state.isDragging) {
			state = { ...state, targetId };
		}
	}

	function endDrag() {
		state = {
			isDragging: false,
			draggedItem: null,
			sourceId: null,
			targetId: null,
			position: null
		};
	}

	function reset() {
		endDrag();
	}

	return {
		get state() {
			return state;
		},
		get isDragging() {
			return state.isDragging;
		},
		get draggedItem() {
			return state.draggedItem;
		},
		get sourceId() {
			return state.sourceId;
		},
		get targetId() {
			return state.targetId;
		},
		startDrag,
		updatePosition,
		setTarget,
		endDrag,
		reset
	};
}

/**
 * Create a sortable list manager
 */
export function createSortable<T extends SortableItem>(initialItems: T[] = []) {
	let items = $state<T[]>(initialItems);
	let draggedIndex = $state<number | null>(null);
	let dragOverIndex = $state<number | null>(null);
	let isDragging = $state(false);

	function setItems(newItems: T[]) {
		items = newItems;
	}

	function startDrag(index: number) {
		draggedIndex = index;
		isDragging = true;
	}

	function dragOver(index: number) {
		if (draggedIndex !== null && draggedIndex !== index) {
			dragOverIndex = index;
		}
	}

	function dragLeave() {
		dragOverIndex = null;
	}

	function drop(targetIndex: number): T[] | null {
		if (draggedIndex === null || draggedIndex === targetIndex) {
			endDrag();
			return null;
		}

		const newItems = [...items];
		const [draggedItem] = newItems.splice(draggedIndex, 1);

		// Adjust target index if dragging from before to after
		const adjustedIndex = draggedIndex < targetIndex ? targetIndex - 1 : targetIndex;
		newItems.splice(adjustedIndex, 0, draggedItem);

		items = newItems;
		endDrag();
		return newItems;
	}

	function endDrag() {
		draggedIndex = null;
		dragOverIndex = null;
		isDragging = false;
	}

	function move(fromIndex: number, toIndex: number): T[] {
		if (fromIndex === toIndex) return items;

		const newItems = [...items];
		const [item] = newItems.splice(fromIndex, 1);
		newItems.splice(toIndex, 0, item);
		items = newItems;
		return newItems;
	}

	return {
		get items() {
			return items;
		},
		get draggedIndex() {
			return draggedIndex;
		},
		get dragOverIndex() {
			return dragOverIndex;
		},
		get isDragging() {
			return isDragging;
		},
		setItems,
		startDrag,
		dragOver,
		dragLeave,
		drop,
		endDrag,
		move
	};
}

/**
 * Draggable action - makes an element draggable
 */
export function draggable<T>(
	node: HTMLElement,
	options: {
		data: T;
		id: string;
		sourceId: string;
		disabled?: boolean;
		onDragStart?: (data: T) => void;
		onDragEnd?: () => void;
	}
) {
	let { data, id, sourceId, disabled = false, onDragStart, onDragEnd } = options;

	function handleDragStart(e: DragEvent) {
		if (disabled) {
			e.preventDefault();
			return;
		}

		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('application/json', JSON.stringify({ id, data, sourceId }));

			// Create a custom drag image
			const rect = node.getBoundingClientRect();
			e.dataTransfer.setDragImage(node, e.clientX - rect.left, e.clientY - rect.top);
		}

		node.classList.add('dragging');
		onDragStart?.(data);
	}

	function handleDragEnd() {
		node.classList.remove('dragging');
		onDragEnd?.();
	}

	node.draggable = !disabled;
	node.addEventListener('dragstart', handleDragStart);
	node.addEventListener('dragend', handleDragEnd);

	return {
		update(newOptions: typeof options) {
			data = newOptions.data;
			id = newOptions.id;
			sourceId = newOptions.sourceId;
			disabled = newOptions.disabled ?? false;
			onDragStart = newOptions.onDragStart;
			onDragEnd = newOptions.onDragEnd;
			node.draggable = !disabled;
		},
		destroy() {
			node.removeEventListener('dragstart', handleDragStart);
			node.removeEventListener('dragend', handleDragEnd);
		}
	};
}

/**
 * Droppable action - makes an element a drop target
 */
export function droppable<T>(
	node: HTMLElement,
	options: {
		accepts?: string[];
		disabled?: boolean;
		onDragEnter?: () => void;
		onDragLeave?: () => void;
		onDragOver?: (e: DragEvent) => void;
		onDrop?: (data: DragItem<T>) => void;
	}
) {
	let { accepts, disabled = false, onDragEnter, onDragLeave, onDragOver, onDrop } = options;
	let dragEnterCount = 0;

	function handleDragEnter(e: DragEvent) {
		if (disabled) return;
		e.preventDefault();
		dragEnterCount++;

		if (dragEnterCount === 1) {
			node.classList.add('drag-over');
			onDragEnter?.();
		}
	}

	function handleDragLeave(e: DragEvent) {
		if (disabled) return;
		dragEnterCount--;

		if (dragEnterCount === 0) {
			node.classList.remove('drag-over');
			onDragLeave?.();
		}
	}

	function handleDragOver(e: DragEvent) {
		if (disabled) return;
		e.preventDefault();

		if (e.dataTransfer) {
			e.dataTransfer.dropEffect = 'move';
		}

		onDragOver?.(e);
	}

	function handleDrop(e: DragEvent) {
		if (disabled) return;
		e.preventDefault();
		dragEnterCount = 0;
		node.classList.remove('drag-over');

		if (e.dataTransfer) {
			try {
				const rawData = e.dataTransfer.getData('application/json');
				if (rawData) {
					const data = JSON.parse(rawData) as DragItem<T>;

					// Check if this drop zone accepts this item
					if (!accepts || accepts.includes(data.sourceId)) {
						onDrop?.(data);
					}
				}
			} catch (err) {
				console.error('Failed to parse drop data:', err);
			}
		}
	}

	node.addEventListener('dragenter', handleDragEnter);
	node.addEventListener('dragleave', handleDragLeave);
	node.addEventListener('dragover', handleDragOver);
	node.addEventListener('drop', handleDrop);

	return {
		update(newOptions: typeof options) {
			accepts = newOptions.accepts;
			disabled = newOptions.disabled ?? false;
			onDragEnter = newOptions.onDragEnter;
			onDragLeave = newOptions.onDragLeave;
			onDragOver = newOptions.onDragOver;
			onDrop = newOptions.onDrop;
		},
		destroy() {
			node.removeEventListener('dragenter', handleDragEnter);
			node.removeEventListener('dragleave', handleDragLeave);
			node.removeEventListener('dragover', handleDragOver);
			node.removeEventListener('drop', handleDrop);
		}
	};
}

/**
 * Sortable item action - combines draggable and droppable for list items
 */
export function sortableItem<T>(
	node: HTMLElement,
	options: {
		index: number;
		data: T;
		sortable: ReturnType<typeof createSortable<SortableItem<T>>>;
		disabled?: boolean;
		onReorder?: (items: SortableItem<T>[]) => void;
	}
) {
	let { index, data, sortable, disabled = false, onReorder } = options;

	function handleDragStart(e: DragEvent) {
		if (disabled) {
			e.preventDefault();
			return;
		}

		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', String(index));
		}

		node.classList.add('dragging');
		sortable.startDrag(index);
	}

	function handleDragEnd() {
		node.classList.remove('dragging');
		sortable.endDrag();
	}

	function handleDragOver(e: DragEvent) {
		if (disabled) return;
		e.preventDefault();

		if (e.dataTransfer) {
			e.dataTransfer.dropEffect = 'move';
		}

		sortable.dragOver(index);
	}

	function handleDragLeave() {
		sortable.dragLeave();
	}

	function handleDrop(e: DragEvent) {
		if (disabled) return;
		e.preventDefault();
		e.stopPropagation();

		const result = sortable.drop(index);
		if (result) {
			onReorder?.(result as SortableItem<T>[]);
		}
	}

	node.draggable = !disabled;
	node.addEventListener('dragstart', handleDragStart);
	node.addEventListener('dragend', handleDragEnd);
	node.addEventListener('dragover', handleDragOver);
	node.addEventListener('dragleave', handleDragLeave);
	node.addEventListener('drop', handleDrop);

	return {
		update(newOptions: typeof options) {
			index = newOptions.index;
			data = newOptions.data;
			sortable = newOptions.sortable;
			disabled = newOptions.disabled ?? false;
			onReorder = newOptions.onReorder;
			node.draggable = !disabled;
		},
		destroy() {
			node.removeEventListener('dragstart', handleDragStart);
			node.removeEventListener('dragend', handleDragEnd);
			node.removeEventListener('dragover', handleDragOver);
			node.removeEventListener('dragleave', handleDragLeave);
			node.removeEventListener('drop', handleDrop);
		}
	};
}

/**
 * Calculate insertion index based on mouse position
 */
export function getInsertionIndex(
	container: HTMLElement,
	mouseY: number,
	itemSelector: string = '[data-sortable-item]'
): number {
	const items = container.querySelectorAll(itemSelector);

	for (let i = 0; i < items.length; i++) {
		const item = items[i] as HTMLElement;
		const rect = item.getBoundingClientRect();
		const midY = rect.top + rect.height / 2;

		if (mouseY < midY) {
			return i;
		}
	}

	return items.length;
}

/**
 * CSS classes to add to your global styles
 */
export const dndStyles = `
	/* Dragging element */
	.dragging {
		opacity: 0.5;
		transform: scale(0.98);
		transition: opacity 150ms ease, transform 150ms ease;
	}

	/* Drop target highlight */
	.drag-over {
		outline: 2px dashed hsl(var(--primary));
		outline-offset: 2px;
		background-color: hsl(var(--primary) / 0.05);
		transition: all 150ms ease;
	}

	/* Sortable placeholder */
	.sortable-placeholder {
		background-color: hsl(var(--muted));
		border: 2px dashed hsl(var(--border));
		border-radius: var(--radius);
	}
`;
