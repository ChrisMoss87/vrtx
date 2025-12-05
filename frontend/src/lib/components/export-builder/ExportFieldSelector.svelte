<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Search, GripVertical, ChevronUp, ChevronDown } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface Props {
		fields: Field[];
		selectedFields: string[];
		onselectall?: () => void;
		onclearall?: () => void;
	}

	let {
		fields,
		selectedFields = $bindable(),
		onselectall,
		onclearall
	}: Props = $props();

	let searchQuery = $state('');
	let draggedIndex = $state<number | null>(null);

	const filteredFields = $derived(
		fields.filter((f) =>
			f.label.toLowerCase().includes(searchQuery.toLowerCase()) ||
			f.api_name.toLowerCase().includes(searchQuery.toLowerCase())
		)
	);

	// Get fields in selection order
	const orderedSelectedFields = $derived(
		selectedFields
			.map((apiName) => fields.find((f) => f.api_name === apiName))
			.filter((f): f is Field => f !== undefined)
	);

	function toggleField(apiName: string) {
		if (selectedFields.includes(apiName)) {
			selectedFields = selectedFields.filter((f) => f !== apiName);
		} else {
			selectedFields = [...selectedFields, apiName];
		}
	}

	function moveField(fromIndex: number, toIndex: number) {
		if (toIndex < 0 || toIndex >= selectedFields.length) return;
		const newOrder = [...selectedFields];
		const [moved] = newOrder.splice(fromIndex, 1);
		newOrder.splice(toIndex, 0, moved);
		selectedFields = newOrder;
	}

	function handleDragStart(index: number) {
		draggedIndex = index;
	}

	function handleDragOver(e: DragEvent, index: number) {
		e.preventDefault();
		if (draggedIndex !== null && draggedIndex !== index) {
			moveField(draggedIndex, index);
			draggedIndex = index;
		}
	}

	function handleDragEnd() {
		draggedIndex = null;
	}

	function getFieldTypeColor(type: string): string {
		switch (type) {
			case 'text':
			case 'textarea':
				return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
			case 'number':
			case 'integer':
			case 'currency':
			case 'percent':
				return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
			case 'date':
			case 'datetime':
				return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
			case 'email':
			case 'url':
			case 'phone':
				return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200';
			case 'select':
			case 'multiselect':
			case 'radio':
				return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
			case 'boolean':
			case 'switch':
				return 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200';
			case 'lookup':
				return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200';
			default:
				return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
		}
	}
</script>

<div class="space-y-4">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div class="text-sm text-muted-foreground">
			{selectedFields.length} of {fields.length} fields selected
		</div>
		<div class="flex gap-2">
			<Button variant="link" size="sm" class="h-auto p-0" onclick={onselectall}>
				Select all
			</Button>
			<span class="text-muted-foreground">|</span>
			<Button variant="link" size="sm" class="h-auto p-0" onclick={onclearall}>
				Clear all
			</Button>
		</div>
	</div>

	<!-- Search -->
	<div class="relative">
		<Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
		<Input
			bind:value={searchQuery}
			placeholder="Search fields..."
			class="pl-9"
		/>
	</div>

	<!-- Two Column Layout -->
	<div class="grid gap-4 md:grid-cols-2">
		<!-- Available Fields -->
		<div class="space-y-2">
			<h4 class="text-sm font-medium">Available Fields</h4>
			<div class="border rounded-lg max-h-64 overflow-auto">
				{#each filteredFields as field}
					{@const isSelected = selectedFields.includes(field.api_name)}
					<button
						type="button"
						class="w-full flex items-center gap-3 p-3 hover:bg-muted/50 transition-colors border-b last:border-b-0 {isSelected ? 'bg-primary/5' : ''}"
						onclick={() => toggleField(field.api_name)}
					>
						<Checkbox checked={isSelected} />
						<div class="flex-1 text-left">
							<div class="font-medium text-sm">{field.label}</div>
							<div class="text-xs text-muted-foreground">{field.api_name}</div>
						</div>
						<Badge class={getFieldTypeColor(field.type)} variant="secondary">
							{field.type}
						</Badge>
					</button>
				{/each}
				{#if filteredFields.length === 0}
					<div class="p-4 text-center text-muted-foreground text-sm">
						No fields match your search
					</div>
				{/if}
			</div>
		</div>

		<!-- Selected Fields (Ordered) -->
		<div class="space-y-2">
			<h4 class="text-sm font-medium">Export Order</h4>
			<div class="border rounded-lg max-h-64 overflow-auto">
				{#if orderedSelectedFields.length === 0}
					<div class="p-4 text-center text-muted-foreground text-sm">
						Select fields to include in export
					</div>
				{:else}
					{#each orderedSelectedFields as field, index (field.api_name)}
						<div
							class="flex items-center gap-2 p-2 hover:bg-muted/50 border-b last:border-b-0"
							class:ring-2={draggedIndex === index}
							class:ring-primary={draggedIndex === index}
							draggable="true"
							ondragstart={() => handleDragStart(index)}
							ondragover={(e) => handleDragOver(e, index)}
							ondragend={handleDragEnd}
							role="listitem"
						>
							<div class="cursor-grab text-muted-foreground">
								<GripVertical class="h-4 w-4" />
							</div>
							<Badge variant="outline" class="text-xs w-6 justify-center">
								{index + 1}
							</Badge>
							<span class="flex-1 text-sm truncate">{field.label}</span>
							<div class="flex gap-1">
								<Button
									variant="ghost"
									size="icon"
									class="h-6 w-6"
									disabled={index === 0}
									onclick={() => moveField(index, index - 1)}
								>
									<ChevronUp class="h-3 w-3" />
								</Button>
								<Button
									variant="ghost"
									size="icon"
									class="h-6 w-6"
									disabled={index === orderedSelectedFields.length - 1}
									onclick={() => moveField(index, index + 1)}
								>
									<ChevronDown class="h-3 w-3" />
								</Button>
							</div>
						</div>
					{/each}
				{/if}
			</div>
			<p class="text-xs text-muted-foreground">
				Drag to reorder columns in the export file
			</p>
		</div>
	</div>
</div>
