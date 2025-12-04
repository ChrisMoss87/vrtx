/**
 * Form builder state management
 */

import { writable, derived } from 'svelte/store';
import type { FormBuilderState, Module, Block, Field } from '$lib/types/module-builder';
import { generateId } from '$lib/utils/dnd';

const initialState: FormBuilderState = {
	module: {
		name: '',
		singularName: '',
		apiName: '',
		isActive: true,
		settings: {},
		displayOrder: 0,
		blocks: [],
		fields: []
	},
	isDragging: false,
	history: [],
	historyIndex: -1
};

function createFormBuilderStore() {
	const { subscribe, set, update } = writable<FormBuilderState>(initialState);

	return {
		subscribe,

		/**
		 * Initialize with existing module data
		 */
		initialize(module: Module) {
			set({
				module,
				history: [module],
				historyIndex: 0,
				isDragging: false
			});
		},

		/**
		 * Reset to empty state
		 */
		reset() {
			set(initialState);
		},

		/**
		 * Add to history (for undo/redo)
		 */
		pushHistory(module: Module) {
			update((state) => {
				const newHistory = state.history.slice(0, state.historyIndex + 1);
				newHistory.push(module);

				return {
					...state,
					module,
					history: newHistory,
					historyIndex: newHistory.length - 1
				};
			});
		},

		/**
		 * Undo last action
		 */
		undo() {
			update((state) => {
				if (state.historyIndex > 0) {
					const newIndex = state.historyIndex - 1;
					return {
						...state,
						module: state.history[newIndex],
						historyIndex: newIndex
					};
				}
				return state;
			});
		},

		/**
		 * Redo last undone action
		 */
		redo() {
			update((state) => {
				if (state.historyIndex < state.history.length - 1) {
					const newIndex = state.historyIndex + 1;
					return {
						...state,
						module: state.history[newIndex],
						historyIndex: newIndex
					};
				}
				return state;
			});
		},

		/**
		 * Update module metadata
		 */
		updateModule(updates: Partial<Module>) {
			update((state) => {
				const newModule = { ...state.module, ...updates };
				return {
					...state,
					module: newModule
				};
			});
		},

		/**
		 * Add a new block
		 */
		addBlock(block: Omit<Block, 'id' | 'displayOrder'>) {
			update((state) => {
				const newBlock: Block = {
					...block,
					id: generateId('block'),
					displayOrder: state.module.blocks.length,
					fields: []
				};

				const newModule = {
					...state.module,
					blocks: [...state.module.blocks, newBlock]
				};

				const newHistory = state.history.slice(0, state.historyIndex + 1);
				newHistory.push(newModule);

				return {
					...state,
					module: newModule,
					history: newHistory,
					historyIndex: newHistory.length - 1
				};
			});
		},

		/**
		 * Update a block
		 */
		updateBlock(blockId: string, updates: Partial<Block>) {
			update((state) => {
				const newModule = {
					...state.module,
					blocks: state.module.blocks.map((block) =>
						block.id === blockId ? { ...block, ...updates } : block
					)
				};

				return {
					...state,
					module: newModule
				};
			});
		},

		/**
		 * Delete a block
		 */
		deleteBlock(blockId: string) {
			update((state) => {
				const newModule = {
					...state.module,
					blocks: state.module.blocks.filter((block) => block.id !== blockId),
					fields: state.module.fields.filter((field) => field.blockId !== blockId)
				};

				const newHistory = state.history.slice(0, state.historyIndex + 1);
				newHistory.push(newModule);

				return {
					...state,
					module: newModule,
					history: newHistory,
					historyIndex: newHistory.length - 1
				};
			});
		},

		/**
		 * Add a new field
		 */
		addField(field: Omit<Field, 'id' | 'displayOrder'>) {
			update((state) => {
				const newField: Field = {
					...field,
					id: generateId('field'),
					displayOrder: state.module.fields.length
				};

				const newModule = {
					...state.module,
					fields: [...state.module.fields, newField]
				};

				const newHistory = state.history.slice(0, state.historyIndex + 1);
				newHistory.push(newModule);

				return {
					...state,
					module: newModule,
					selectedFieldId: newField.id,
					history: newHistory,
					historyIndex: newHistory.length - 1
				};
			});
		},

		/**
		 * Update a field
		 */
		updateField(fieldId: string, updates: Partial<Field>) {
			update((state) => {
				const newModule = {
					...state.module,
					fields: state.module.fields.map((field) =>
						field.id === fieldId ? { ...field, ...updates } : field
					)
				};

				return {
					...state,
					module: newModule
				};
			});
		},

		/**
		 * Delete a field
		 */
		deleteField(fieldId: string) {
			update((state) => {
				const newModule = {
					...state.module,
					fields: state.module.fields.filter((field) => field.id !== fieldId)
				};

				const newHistory = state.history.slice(0, state.historyIndex + 1);
				newHistory.push(newModule);

				return {
					...state,
					module: newModule,
					selectedFieldId: undefined,
					history: newHistory,
					historyIndex: newHistory.length - 1
				};
			});
		},

		/**
		 * Reorder fields
		 */
		reorderFields(fields: Field[]) {
			update((state) => {
				const newModule = {
					...state.module,
					fields: fields.map((field, index) => ({
						...field,
						displayOrder: index
					}))
				};

				return {
					...state,
					module: newModule
				};
			});
		},

		/**
		 * Reorder blocks
		 */
		reorderBlocks(blocks: Block[]) {
			update((state) => {
				const newModule = {
					...state.module,
					blocks: blocks.map((block, index) => ({
						...block,
						displayOrder: index
					}))
				};

				return {
					...state,
					module: newModule
				};
			});
		},

		/**
		 * Select a field for editing
		 */
		selectField(fieldId: string | undefined) {
			update((state) => ({
				...state,
				selectedFieldId: fieldId,
				selectedBlockId: undefined
			}));
		},

		/**
		 * Select a block for editing
		 */
		selectBlock(blockId: string | undefined) {
			update((state) => ({
				...state,
				selectedBlockId: blockId,
				selectedFieldId: undefined
			}));
		},

		/**
		 * Set dragging state
		 */
		setDragging(isDragging: boolean) {
			update((state) => ({
				...state,
				isDragging
			}));
		}
	};
}

export const formBuilder = createFormBuilderStore();

/**
 * Derived stores
 */
export const selectedField = derived(formBuilder, ($formBuilder) =>
	$formBuilder.selectedFieldId
		? $formBuilder.module.fields.find((f) => f.id === $formBuilder.selectedFieldId)
		: undefined
);

export const selectedBlock = derived(formBuilder, ($formBuilder) =>
	$formBuilder.selectedBlockId
		? $formBuilder.module.blocks.find((b) => b.id === $formBuilder.selectedBlockId)
		: undefined
);

export const canUndo = derived(formBuilder, ($formBuilder) => $formBuilder.historyIndex > 0);

export const canRedo = derived(
	formBuilder,
	($formBuilder) => $formBuilder.historyIndex < $formBuilder.history.length - 1
);

export const fieldsByBlock = derived(formBuilder, ($formBuilder) => {
	const grouped: Record<string, Field[]> = {
		unassigned: []
	};

	$formBuilder.module.fields.forEach((field) => {
		const blockId = field.blockId || 'unassigned';
		if (!grouped[blockId]) {
			grouped[blockId] = [];
		}
		grouped[blockId].push(field);
	});

	// Sort by display order
	Object.keys(grouped).forEach((blockId) => {
		grouped[blockId].sort((a, b) => a.displayOrder - b.displayOrder);
	});

	return grouped;
});
