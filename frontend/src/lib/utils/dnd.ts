/**
 * Drag and drop utilities for form builder
 */

import { nanoid } from 'nanoid';

/**
 * Generate unique ID for draggable items
 */
export function generateId(prefix: string = 'item'): string {
	return `${prefix}_${nanoid(10)}`;
}

/**
 * Check if item is being dragged from palette
 */
export function isDragFromPalette(activeId: string): boolean {
	return activeId.startsWith('palette_');
}

/**
 * Check if item is a field
 */
export function isField(id: string): boolean {
	return id.startsWith('field_');
}

/**
 * Check if item is a block
 */
export function isBlock(id: string): boolean {
	return id.startsWith('block_');
}

/**
 * Extract field type from palette item ID
 */
export function extractFieldType(paletteId: string): string {
	return paletteId.replace('palette_', '');
}

/**
 * Calculate drop position based on over/active items
 */
export function calculateDropPosition(
	overId: string,
	activeId: string,
	items: Array<{ id: string }>
): number {
	const overIndex = items.findIndex((item) => item.id === overId);
	const activeIndex = items.findIndex((item) => item.id === activeId);

	if (overIndex === -1) return items.length;
	if (activeIndex === -1) return overIndex;

	return activeIndex < overIndex ? overIndex : overIndex + 1;
}

/**
 * Reorder array items
 */
export function reorderItems<T>(items: T[], activeIndex: number, overIndex: number): T[] {
	const result = Array.from(items);
	const [removed] = result.splice(activeIndex, 1);
	result.splice(overIndex, 0, removed);
	return result;
}

/**
 * Move item between arrays
 */
export function moveItemBetweenArrays<T>(
	sourceArray: T[],
	destArray: T[],
	sourceIndex: number,
	destIndex: number
): { source: T[]; destination: T[] } {
	const source = Array.from(sourceArray);
	const destination = Array.from(destArray);

	const [removed] = source.splice(sourceIndex, 1);
	destination.splice(destIndex, 0, removed);

	return { source, destination };
}

/**
 * Type guard for drag events
 */
export function hasDragData(
	event: any
): event is { active: { id: string }; over: { id: string } | null } {
	return event && event.active && typeof event.active.id === 'string';
}

/**
 * Get drop zone ID from container ID
 */
export function getDropZoneId(containerId: string): string {
	return `dropzone_${containerId}`;
}

/**
 * Extract container ID from drop zone ID
 */
export function extractContainerId(dropZoneId: string): string {
	return dropZoneId.replace('dropzone_', '');
}
