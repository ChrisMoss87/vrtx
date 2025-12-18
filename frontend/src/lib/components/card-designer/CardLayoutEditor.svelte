<script lang="ts">
	import type { CardLayout, CardField, CardFieldDisplayType } from '$lib/types/kanban-card-config';
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import { cn } from '$lib/utils';
	import {
		GripVertical,
		Plus,
		Trash2,
		Type,
		Heading1,
		Tag,
		DollarSign,
		AlignLeft,
		Minimize2
	} from 'lucide-svelte';
	import type { ComponentType } from 'svelte';

	interface Props {
		layout: CardLayout;
		availableFields: Array<{ api_name: string; label: string; type: string }>;
		onchange: (layout: CardLayout) => void;
		class?: string;
	}

	let { layout, availableFields, onchange, class: className }: Props = $props();

	// Drag and drop state
	let draggedIndex = $state<number | null>(null);
	let dragOverIndex = $state<number | null>(null);

	// Display type icons
	const displayTypeIcons: Record<CardFieldDisplayType, ComponentType> = {
		title: Heading1,
		subtitle: Type,
		badge: Tag,
		value: DollarSign,
		text: AlignLeft,
		small: Minimize2
	};

	const displayTypeLabels: Record<CardFieldDisplayType, string> = {
		title: 'Title',
		subtitle: 'Subtitle',
		badge: 'Badge',
		value: 'Value (Large)',
		text: 'Text',
		small: 'Small Text'
	};

	function addField() {
		const unusedFields = availableFields.filter(
			(f) => !layout.fields.some((lf) => lf.fieldApiName === f.api_name)
		);

		if (unusedFields.length === 0) return;

		const newField: CardField = {
			fieldApiName: unusedFields[0].api_name,
			displayAs: 'text',
			showLabel: false
		};

		onchange({
			...layout,
			fields: [...layout.fields, newField]
		});
	}

	function removeField(index: number) {
		const newFields = layout.fields.filter((_, i) => i !== index);
		onchange({
			...layout,
			fields: newFields
		});
	}

	function updateField(index: number, updates: Partial<CardField>) {
		const newFields = layout.fields.map((f, i) => (i === index ? { ...f, ...updates } : f));
		onchange({
			...layout,
			fields: newFields
		});
	}

	function toggleShowFieldLabels(checked: boolean) {
		onchange({
			...layout,
			showFieldLabels: checked
		});
	}

	// Drag handlers
	function handleDragStart(index: number) {
		draggedIndex = index;
	}

	function handleDragOver(e: DragEvent, index: number) {
		e.preventDefault();
		if (draggedIndex !== null && draggedIndex !== index) {
			dragOverIndex = index;
		}
	}

	function handleDrop(targetIndex: number) {
		if (draggedIndex === null || draggedIndex === targetIndex) {
			resetDragState();
			return;
		}

		const newFields = [...layout.fields];
		const [movedItem] = newFields.splice(draggedIndex, 1);
		newFields.splice(targetIndex, 0, movedItem);

		onchange({
			...layout,
			fields: newFields
		});

		resetDragState();
	}

	function resetDragState() {
		draggedIndex = null;
		dragOverIndex = null;
	}

	function getFieldLabel(apiName: string): string {
		return availableFields.find((f) => f.api_name === apiName)?.label || apiName;
	}

	const unusedFieldsCount = $derived(
		availableFields.filter((f) => !layout.fields.some((lf) => lf.fieldApiName === f.api_name))
			.length
	);
</script>

<div class={cn('space-y-4', className)}>
	<div class="flex items-center justify-between">
		<div>
			<h3 class="text-lg font-semibold">Card Layout</h3>
			<p class="text-sm text-muted-foreground">
				Add and arrange fields to display on kanban cards
			</p>
		</div>
		<Button
			size="sm"
			variant="outline"
			onclick={addField}
			disabled={unusedFieldsCount === 0}
			class="gap-2"
		>
			<Plus class="h-4 w-4" />
			Add Field
		</Button>
	</div>

	<!-- Global field label toggle -->
	<div class="flex items-center justify-between rounded-lg border bg-muted/50 p-3">
		<div class="space-y-0.5">
			<Label class="text-sm font-medium">Show Field Labels</Label>
			<p class="text-xs text-muted-foreground">Display field labels on all cards by default</p>
		</div>
		<Switch
			checked={layout.showFieldLabels || false}
			onCheckedChange={(checked) => toggleShowFieldLabels(checked)}
		/>
	</div>

	<!-- Field list -->
	{#if layout.fields.length > 0}
		<div class="space-y-2">
			{#each layout.fields as field, index (field.fieldApiName)}
				{@const isDragging = draggedIndex === index}
				{@const isDragOver = dragOverIndex === index}
				{@const Icon = displayTypeIcons[field.displayAs]}
				<div
					role="listitem"
					draggable="true"
					ondragstart={() => handleDragStart(index)}
					ondragover={(e) => handleDragOver(e, index)}
					ondrop={() => handleDrop(index)}
					ondragend={resetDragState}
					class="flex items-center gap-3 rounded-lg border bg-card p-3 transition-all
						{isDragging ? 'opacity-50 scale-95' : ''}
						{isDragOver ? 'border-primary ring-2 ring-primary/20' : ''}"
				>
					<!-- Drag handle -->
					<div class="cursor-grab active:cursor-grabbing text-muted-foreground">
						<GripVertical class="h-4 w-4" />
					</div>

					<!-- Field selector -->
					<div class="flex-1 grid grid-cols-2 gap-3">
						<div class="space-y-1">
							<Label class="text-xs text-muted-foreground">Field</Label>
							<Select.Root
								type="single"
								value={field.fieldApiName}
								onValueChange={(val) => val && updateField(index, { fieldApiName: val })}
							>
								<Select.Trigger class="h-9">
									<span class="text-sm">{getFieldLabel(field.fieldApiName)}</span>
								</Select.Trigger>
								<Select.Content>
									{#each availableFields as availField}
										<Select.Item value={availField.api_name}>
											{availField.label}
											<span class="ml-2 text-xs text-muted-foreground">
												({availField.type})
											</span>
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>

						<div class="space-y-1">
							<Label class="text-xs text-muted-foreground">Display As</Label>
							<Select.Root
								type="single"
								value={field.displayAs}
								onValueChange={(val) =>
									val && updateField(index, { displayAs: val as CardFieldDisplayType })}
							>
								<Select.Trigger class="h-9">
									<Icon class="mr-2 h-4 w-4" />
									<span class="text-sm">{displayTypeLabels[field.displayAs]}</span>
								</Select.Trigger>
								<Select.Content>
									{#each Object.entries(displayTypeLabels) as [value, label]}
										{@const TypeIcon = displayTypeIcons[value as CardFieldDisplayType]}
										<Select.Item value={value}>
											<div class="flex items-center gap-2">
												<TypeIcon class="h-4 w-4" />
												{label}
											</div>
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
					</div>

					<!-- Show label toggle (overrides global setting) -->
					<div class="flex items-center gap-2">
						<Label class="text-xs text-muted-foreground">Label</Label>
						<Switch
							checked={field.showLabel || false}
							onCheckedChange={(checked) => updateField(index, { showLabel: checked })}
							class="scale-75"
						/>
					</div>

					<!-- Remove button -->
					<Button
						size="icon"
						variant="ghost"
						onclick={() => removeField(index)}
						class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
					>
						<Trash2 class="h-4 w-4" />
					</Button>
				</div>
			{/each}
		</div>
	{:else}
		<div class="rounded-lg border-2 border-dashed bg-muted/20 p-8 text-center">
			<p class="text-sm text-muted-foreground mb-3">No fields added yet</p>
			<Button size="sm" variant="outline" onclick={addField} disabled={unusedFieldsCount === 0}>
				<Plus class="mr-2 h-4 w-4" />
				Add Your First Field
			</Button>
		</div>
	{/if}

	{#if unusedFieldsCount === 0 && layout.fields.length < availableFields.length}
		<div class="rounded-lg bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 p-3">
			<p class="text-xs text-blue-700 dark:text-blue-300">
				All available fields have been added to the card layout.
			</p>
		</div>
	{/if}

	<div class="text-xs text-muted-foreground">
		<p class="font-medium mb-1">Display Type Guide:</p>
		<ul class="space-y-0.5 ml-4">
			<li><strong>Title:</strong> Large, bold text for main heading</li>
			<li><strong>Subtitle:</strong> Medium text for secondary info</li>
			<li><strong>Badge:</strong> Colored badge/pill for status or category</li>
			<li><strong>Value:</strong> Large, highlighted text for important numbers</li>
			<li><strong>Text:</strong> Regular text for general info</li>
			<li><strong>Small:</strong> Compact text for less important details</li>
		</ul>
	</div>
</div>
