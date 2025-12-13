<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Tabs from '$lib/components/ui/tabs';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import {
		Eye,
		Smartphone,
		Monitor,
		Tablet,
		Save,
		Undo,
		Redo,
		Settings,
		Palette,
		Layers
	} from 'lucide-svelte';
	import PageElementLibrary from './PageElementLibrary.svelte';
	import PageElementRenderer from './PageElementRenderer.svelte';
	import PageElementEditor from './PageElementEditor.svelte';
	import PageStylesEditor from './PageStylesEditor.svelte';
	import type { PageElement, PageStyles, PageSettings, SeoSettings } from '$lib/api/landing-pages';
	import { generateElementId } from '$lib/api/landing-pages';

	interface Props {
		content: PageElement[];
		styles: PageStyles;
		settings: PageSettings;
		seoSettings: SeoSettings;
		onChange: (data: {
			content: PageElement[];
			styles: PageStyles;
			settings: PageSettings;
			seoSettings: SeoSettings;
		}) => void;
		onSave: () => void;
		saving?: boolean;
	}

	let { content, styles, settings, seoSettings, onChange, onSave, saving = false }: Props = $props();

	let selectedElementId = $state<string | null>(null);
	let viewportSize = $state<'desktop' | 'tablet' | 'mobile'>('desktop');
	let leftPanel = $state<'elements' | 'layers'>('elements');
	let rightPanel = $state<'properties' | 'styles'>('properties');
	let undoStack = $state<PageElement[][]>([]);
	let redoStack = $state<PageElement[][]>([]);

	const selectedElement = $derived(
		selectedElementId ? findElementById(content, selectedElementId) : null
	);

	const viewportWidth = $derived(
		viewportSize === 'desktop' ? '100%' : viewportSize === 'tablet' ? '768px' : '375px'
	);

	function findElementById(elements: PageElement[], id: string): PageElement | null {
		for (const el of elements) {
			if (el.id === id) return el;
			if (el.children) {
				const found = findElementById(el.children, id);
				if (found) return found;
			}
		}
		return null;
	}

	function saveToHistory() {
		undoStack = [...undoStack, structuredClone(content)];
		redoStack = [];
		if (undoStack.length > 50) undoStack = undoStack.slice(-50);
	}

	function undo() {
		if (undoStack.length === 0) return;
		const previous = undoStack[undoStack.length - 1];
		undoStack = undoStack.slice(0, -1);
		redoStack = [...redoStack, structuredClone(content)];
		onChange({ content: previous, styles, settings, seoSettings });
	}

	function redo() {
		if (redoStack.length === 0) return;
		const next = redoStack[redoStack.length - 1];
		redoStack = redoStack.slice(0, -1);
		undoStack = [...undoStack, structuredClone(content)];
		onChange({ content: next, styles, settings, seoSettings });
	}

	function handleAddElement(element: PageElement) {
		saveToHistory();
		onChange({
			content: [...content, element],
			styles,
			settings,
			seoSettings
		});
		selectedElementId = element.id;
	}

	function handleUpdateElement(id: string, props: Record<string, unknown>) {
		saveToHistory();
		const newContent = updateElementById(content, id, props);
		onChange({ content: newContent, styles, settings, seoSettings });
	}

	function updateElementById(
		elements: PageElement[],
		id: string,
		props: Record<string, unknown>
	): PageElement[] {
		return elements.map((el) => {
			if (el.id === id) {
				return { ...el, props };
			}
			if (el.children) {
				return { ...el, children: updateElementById(el.children, id, props) };
			}
			return el;
		});
	}

	function handleDeleteElement(id: string) {
		saveToHistory();
		const newContent = deleteElementById(content, id);
		onChange({ content: newContent, styles, settings, seoSettings });
		if (selectedElementId === id) selectedElementId = null;
	}

	function deleteElementById(elements: PageElement[], id: string): PageElement[] {
		return elements
			.filter((el) => el.id !== id)
			.map((el) => ({
				...el,
				children: el.children ? deleteElementById(el.children, id) : undefined
			}));
	}

	function handleMoveElement(id: string, direction: 'up' | 'down') {
		saveToHistory();
		const index = content.findIndex((el) => el.id === id);
		if (index === -1) return;

		const newIndex = direction === 'up' ? index - 1 : index + 1;
		if (newIndex < 0 || newIndex >= content.length) return;

		const newContent = [...content];
		[newContent[index], newContent[newIndex]] = [newContent[newIndex], newContent[index]];
		onChange({ content: newContent, styles, settings, seoSettings });
	}

	function handleDuplicateElement(id: string) {
		const element = findElementById(content, id);
		if (!element) return;

		saveToHistory();
		const duplicated = duplicateElement(element);
		const index = content.findIndex((el) => el.id === id);
		const newContent = [...content.slice(0, index + 1), duplicated, ...content.slice(index + 1)];
		onChange({ content: newContent, styles, settings, seoSettings });
		selectedElementId = duplicated.id;
	}

	function duplicateElement(element: PageElement): PageElement {
		return {
			...element,
			id: generateElementId(),
			children: element.children?.map(duplicateElement)
		};
	}

	function handleStylesChange(newStyles: PageStyles) {
		onChange({ content, styles: newStyles, settings, seoSettings });
	}
</script>

<div class="flex h-full flex-col">
	<!-- Toolbar -->
	<div class="flex items-center justify-between border-b px-4 py-2">
		<div class="flex items-center gap-2">
			<Button variant="outline" size="sm" onclick={undo} disabled={undoStack.length === 0}>
				<Undo class="h-4 w-4" />
			</Button>
			<Button variant="outline" size="sm" onclick={redo} disabled={redoStack.length === 0}>
				<Redo class="h-4 w-4" />
			</Button>
		</div>

		<div class="flex items-center gap-1 rounded-lg border p-1">
			<Button
				variant={viewportSize === 'desktop' ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => (viewportSize = 'desktop')}
			>
				<Monitor class="h-4 w-4" />
			</Button>
			<Button
				variant={viewportSize === 'tablet' ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => (viewportSize = 'tablet')}
			>
				<Tablet class="h-4 w-4" />
			</Button>
			<Button
				variant={viewportSize === 'mobile' ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => (viewportSize = 'mobile')}
			>
				<Smartphone class="h-4 w-4" />
			</Button>
		</div>

		<div class="flex items-center gap-2">
			<Button variant="outline" size="sm">
				<Eye class="mr-1 h-4 w-4" />
				Preview
			</Button>
			<Button size="sm" onclick={onSave} disabled={saving}>
				<Save class="mr-1 h-4 w-4" />
				{saving ? 'Saving...' : 'Save'}
			</Button>
		</div>
	</div>

	<!-- Main editor area -->
	<div class="flex flex-1 overflow-hidden">
		<!-- Left panel -->
		<div class="w-64 flex-shrink-0 border-r">
			<Tabs.Root bind:value={leftPanel} class="flex h-full flex-col">
				<Tabs.List class="border-b px-2">
					<Tabs.Trigger value="elements" class="flex-1 gap-1">
						<Layers class="h-4 w-4" />
						Elements
					</Tabs.Trigger>
					<Tabs.Trigger value="layers" class="flex-1 gap-1">
						<Settings class="h-4 w-4" />
						Layers
					</Tabs.Trigger>
				</Tabs.List>
				<Tabs.Content value="elements" class="flex-1 overflow-hidden">
					<PageElementLibrary onAddElement={handleAddElement} />
				</Tabs.Content>
				<Tabs.Content value="layers" class="flex-1 overflow-hidden">
					<ScrollArea class="h-full">
						<div class="p-3">
							{#if content.length === 0}
								<p class="text-muted-foreground text-center text-sm">No elements yet</p>
							{:else}
								<div class="space-y-1">
									{#each content as element, i}
										<button
											class="hover:bg-muted w-full rounded-md px-3 py-2 text-left text-sm transition-colors"
											class:bg-muted={selectedElementId === element.id}
											onclick={() => (selectedElementId = element.id)}
										>
											<span class="font-medium">{element.type}</span>
											<span class="text-muted-foreground ml-2">#{i + 1}</span>
										</button>
									{/each}
								</div>
							{/if}
						</div>
					</ScrollArea>
				</Tabs.Content>
			</Tabs.Root>
		</div>

		<!-- Canvas -->
		<div class="bg-muted/30 flex-1 overflow-auto p-8">
			<div
				class="mx-auto min-h-full bg-white shadow-lg transition-all"
				style="max-width: {viewportWidth}"
			>
				{#if content.length === 0}
					<div class="flex h-96 items-center justify-center">
						<div class="text-center">
							<p class="text-muted-foreground mb-2">Your page is empty</p>
							<p class="text-muted-foreground text-sm">
								Add elements from the left panel to get started
							</p>
						</div>
					</div>
				{:else}
					{#each content as element}
						<PageElementRenderer
							{element}
							{styles}
							isEditing={true}
							isSelected={selectedElementId === element.id}
							onSelect={() => (selectedElementId = element.id)}
						/>
					{/each}
				{/if}
			</div>
		</div>

		<!-- Right panel -->
		<div class="w-72 flex-shrink-0 border-l">
			<Tabs.Root bind:value={rightPanel} class="flex h-full flex-col">
				<Tabs.List class="border-b px-2">
					<Tabs.Trigger value="properties" class="flex-1 gap-1">
						<Settings class="h-4 w-4" />
						Properties
					</Tabs.Trigger>
					<Tabs.Trigger value="styles" class="flex-1 gap-1">
						<Palette class="h-4 w-4" />
						Styles
					</Tabs.Trigger>
				</Tabs.List>
				<Tabs.Content value="properties" class="flex-1 overflow-hidden">
					<PageElementEditor
						element={selectedElement}
						onUpdate={handleUpdateElement}
						onDelete={handleDeleteElement}
						onMoveUp={(id) => handleMoveElement(id, 'up')}
						onMoveDown={(id) => handleMoveElement(id, 'down')}
						onDuplicate={handleDuplicateElement}
					/>
				</Tabs.Content>
				<Tabs.Content value="styles" class="flex-1 overflow-hidden">
					<PageStylesEditor {styles} onChange={handleStylesChange} />
				</Tabs.Content>
			</Tabs.Root>
		</div>
	</div>
</div>
