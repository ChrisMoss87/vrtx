<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Check, X, Loader2, Pencil, ChevronDown, Search } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import type { ColumnDef, FilterOption } from './types';
	import { recordsApi } from '$lib/api/records';
	import { Badge } from '$lib/components/ui/badge';
	import * as Popover from '$lib/components/ui/popover';
	import * as Command from '$lib/components/ui/command';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Checkbox } from '$lib/components/ui/checkbox';

	interface Props {
		value: any;
		row: any;
		column: ColumnDef;
		moduleApiName: string;
		onUpdate?: (recordId: string, field: string, value: any) => Promise<void>;
		editable?: boolean;
	}

	let {
		value = $bindable(),
		row,
		column,
		moduleApiName,
		onUpdate,
		editable = true
	}: Props = $props();

	let isEditing = $state(false);
	let editValue = $state(value);
	let isSaving = $state(false);
	let error = $state<string | null>(null);
	let isHovered = $state(false);
	let cellRef: HTMLDivElement | undefined;
	let inputRef: HTMLInputElement | undefined;
	let selectOpen = $state(false);
	let searchQuery = $state('');

	// Get options for select/multiselect fields
	const options = $derived(column.options || column.filterOptions || []);

	// Format value for display
	const displayValue = $derived.by(() => {
		if (value === null || value === undefined) return '';
		if (column.format) return column.format(value, row);
		if (column.type === 'date') return new Date(value).toLocaleDateString();
		if (column.type === 'datetime') return new Date(value).toLocaleString();
		if (column.type === 'boolean') return value ? 'Yes' : 'No';
		if (column.type === 'number' || column.type === 'decimal') return String(value);

		// For select fields, show the label instead of value
		if (column.type === 'select' || column.type === 'radio') {
			const option = options.find((opt: FilterOption) => opt.value === value);
			return option?.label || String(value);
		}

		// For multiselect, show all labels
		if (column.type === 'multiselect') {
			if (!Array.isArray(value)) return '';
			return value
				.map((v: any) => {
					const option = options.find((opt: FilterOption) => opt.value === v);
					return option?.label || String(v);
				})
				.join(', ');
		}

		return String(value);
	});

	// Get input type based on column type
	const inputType = $derived.by(() => {
		switch (column.type) {
			case 'email':
				return 'email';
			case 'number':
			case 'decimal':
				return 'number';
			case 'url':
				return 'url';
			case 'phone':
				return 'tel';
			case 'date':
				return 'date';
			case 'datetime':
				return 'datetime-local';
			default:
				return 'text';
		}
	});

	// Check if field type is editable inline
	const isEditableType = $derived.by(() => {
		const editableTypes = [
			'text',
			'email',
			'phone',
			'url',
			'number',
			'decimal',
			'date',
			'datetime',
			'select',
			'radio',
			'multiselect',
			'boolean',
			'toggle',
			'checkbox'
		];
		return editableTypes.includes(column.type || 'text');
	});

	// Check if this is a select-based type
	const isSelectType = $derived(column.type === 'select' || column.type === 'radio');
	const isMultiSelectType = $derived(column.type === 'multiselect');
	const isBooleanType = $derived(
		column.type === 'boolean' || column.type === 'toggle' || column.type === 'checkbox'
	);

	// Filter options based on search query
	const filteredOptions = $derived.by(() => {
		if (!searchQuery) return options;
		return options.filter((opt: FilterOption) =>
			opt.label.toLowerCase().includes(searchQuery.toLowerCase())
		);
	});

	function startEditing() {
		if (!editable || !isEditableType) return;
		isEditing = true;
		editValue = value;
		searchQuery = '';
		error = null;

		// For select types, open the dropdown
		if (isSelectType || isMultiSelectType) {
			selectOpen = true;
		} else {
			// Focus input after DOM update
			setTimeout(() => inputRef?.focus(), 0);
		}
	}

	// Handle select value change
	function handleSelectChange(newValue: string | undefined) {
		if (newValue !== undefined) {
			editValue = newValue;
			selectOpen = false;
			save();
		}
	}

	// Handle multiselect toggle
	function handleMultiSelectToggle(optionValue: any) {
		const currentValues = Array.isArray(editValue) ? [...editValue] : [];
		const index = currentValues.indexOf(optionValue);

		if (index > -1) {
			currentValues.splice(index, 1);
		} else {
			currentValues.push(optionValue);
		}

		editValue = currentValues;
	}

	// Handle boolean toggle
	function handleBooleanToggle() {
		editValue = !editValue;
		save();
	}

	// Close select dropdown and cancel if clicking outside
	function handleSelectOpenChange(open: boolean) {
		selectOpen = open;
		if (!open && isEditing) {
			// For multiselect, save when closing
			if (isMultiSelectType) {
				save();
			} else {
				cancel();
			}
		}
	}

	function handleDoubleClick() {
		startEditing();
	}

	function handleKeydownOnCell(e: KeyboardEvent) {
		if (!editable || !isEditableType) return;
		// Enter or F2 to start editing (common spreadsheet pattern)
		if (e.key === 'Enter' || e.key === 'F2') {
			e.preventDefault();
			startEditing();
		}
	}

	function cancel() {
		isEditing = false;
		editValue = value;
		error = null;
	}

	async function save() {
		if (editValue === value) {
			cancel();
			return;
		}

		isSaving = true;
		error = null;

		try {
			// Convert value based on type
			let convertedValue = editValue;
			if (column.type === 'number') {
				convertedValue = parseInt(editValue, 10);
			} else if (column.type === 'decimal') {
				convertedValue = parseFloat(editValue);
			}

			if (onUpdate) {
				await onUpdate(row.id, column.accessorKey, convertedValue);
			} else {
				// Extract field name from accessorKey (e.g., "data.first_name" -> "first_name")
				const fieldName = column.accessorKey.startsWith('data.')
					? column.accessorKey.slice(5)
					: column.accessorKey;

				// Use the records API for inline editing
				await recordsApi.updateField(moduleApiName, row.id, fieldName, convertedValue);
			}

			value = convertedValue;
			isEditing = false;
			toast.success('Updated successfully');
		} catch (err) {
			error = err instanceof Error ? err.message : 'An error occurred';
			toast.error(error);
		} finally {
			isSaving = false;
		}
	}

	function handleKeydown(e: KeyboardEvent) {
		if (e.key === 'Enter') {
			e.preventDefault();
			save();
		} else if (e.key === 'Escape') {
			e.preventDefault();
			cancel();
		}
	}

	function handleBlur() {
		// Small delay to allow click on save/cancel buttons
		setTimeout(() => {
			if (isEditing && !isSaving) {
				save();
			}
		}, 150);
	}
</script>

{#if isEditing}
	<!-- Edit mode -->
	<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
	<div
		class="flex items-center gap-1"
		role="group"
		aria-label="Edit {column.header}"
		onclick={(e) => e.stopPropagation()}
		onkeydown={(e) => e.stopPropagation()}
	>
		{#if isSelectType}
			<!-- Select dropdown -->
			<Select.Root
				type="single"
				value={editValue}
				onValueChange={handleSelectChange}
				open={selectOpen}
				onOpenChange={handleSelectOpenChange}
			>
				<Select.Trigger class="h-8 min-w-[150px]">
					{#if editValue}
						{@const selectedOption = options.find((opt) => opt.value === editValue)}
						<span>{selectedOption?.label || editValue}</span>
					{:else}
						<span class="text-muted-foreground">Select...</span>
					{/if}
				</Select.Trigger>
				<Select.Content>
					{#each options as option (option.value)}
						<Select.Item value={option.value}>{option.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		{:else if isMultiSelectType}
			<!-- Multiselect dropdown -->
			<Popover.Root bind:open={selectOpen} onOpenChange={handleSelectOpenChange}>
				<Popover.Trigger>
					<button
						type="button"
						class="flex h-8 min-w-[150px] items-center justify-between rounded-md border bg-background px-3 py-2 text-sm ring-offset-background hover:bg-accent"
					>
						<span class="truncate">
							{#if Array.isArray(editValue) && editValue.length > 0}
								{editValue.length} selected
							{:else}
								<span class="text-muted-foreground">Select...</span>
							{/if}
						</span>
						<ChevronDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
					</button>
				</Popover.Trigger>
				<Popover.Content class="w-[200px] p-0" align="start">
					<div class="border-b p-2">
						<div class="relative">
							<Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
							<Input
								bind:value={searchQuery}
								placeholder="Search..."
								class="h-8 pl-8"
							/>
						</div>
					</div>
					<ScrollArea class="h-[200px]">
						<div class="p-1">
							{#each filteredOptions as option (option.value)}
								{@const isSelected = Array.isArray(editValue) && editValue.includes(option.value)}
								<button
									type="button"
									onclick={() => handleMultiSelectToggle(option.value)}
									class="flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
								>
									<Checkbox checked={isSelected} />
									<span class="truncate">{option.label}</span>
								</button>
							{/each}
							{#if filteredOptions.length === 0}
								<div class="py-6 text-center text-sm text-muted-foreground">
									No options found
								</div>
							{/if}
						</div>
					</ScrollArea>
					<div class="flex justify-end border-t p-2">
						<button
							type="button"
							onclick={() => {
								selectOpen = false;
								save();
							}}
							class="inline-flex items-center justify-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
						>
							Done
						</button>
					</div>
				</Popover.Content>
			</Popover.Root>
		{:else}
			<!-- Standard text input -->
			<Input
				bind:ref={inputRef}
				bind:value={editValue}
				type={inputType}
				onkeydown={handleKeydown}
				onblur={handleBlur}
				disabled={isSaving}
				class="h-8 w-full min-w-[120px] {error
					? 'border-destructive focus-visible:ring-destructive'
					: ''}"
				aria-invalid={error ? 'true' : undefined}
				aria-describedby={error ? `error-${row.id}-${column.id}` : undefined}
				aria-label="Edit {column.header}"
			/>
		{/if}

		{#if !isSelectType && !isMultiSelectType}
			<div class="flex items-center gap-0.5">
				{#if isSaving}
					<div class="p-1" aria-label="Saving...">
						<Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
					</div>
				{:else}
					<button
						type="button"
						onclick={save}
						class="rounded p-1 transition-colors hover:bg-green-100 focus:ring-2 focus:ring-green-500 focus:ring-offset-1 focus:outline-none dark:hover:bg-green-900/30"
						title="Save (Enter)"
						aria-label="Save changes"
					>
						<Check class="h-4 w-4 text-green-600" />
					</button>
					<button
						type="button"
						onclick={cancel}
						class="rounded p-1 transition-colors hover:bg-red-100 focus:ring-2 focus:ring-red-500 focus:ring-offset-1 focus:outline-none dark:hover:bg-red-900/30"
						title="Cancel (Esc)"
						aria-label="Cancel editing"
					>
						<X class="h-4 w-4 text-destructive" />
					</button>
				{/if}
			</div>
		{/if}
	</div>
	{#if error}
		<p id="error-{row.id}-{column.id}" class="mt-1 text-xs text-destructive" role="alert">
			{error}
		</p>
	{/if}
{:else if isBooleanType && editable}
	<!-- Boolean toggle (single click to toggle) -->
	<button
		type="button"
		onclick={handleBooleanToggle}
		class="flex items-center gap-2 rounded px-1.5 py-0.5 transition-colors hover:bg-accent/50"
		aria-label="Toggle {column.header}"
	>
		{#if isSaving}
			<Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
		{:else}
			<div
				class="flex h-5 w-5 items-center justify-center rounded border-2 transition-colors {value
					? 'border-primary bg-primary text-primary-foreground'
					: 'border-muted-foreground/30'}"
			>
				{#if value}
					<Check class="h-3 w-3" />
				{/if}
			</div>
			<span class="text-sm">{value ? 'Yes' : 'No'}</span>
		{/if}
	</button>
{:else}
	<!-- Display mode -->
	<div
		bind:this={cellRef}
		ondblclick={handleDoubleClick}
		onkeydown={handleKeydownOnCell}
		onmouseenter={() => (isHovered = true)}
		onmouseleave={() => (isHovered = false)}
		role={editable && isEditableType ? 'button' : undefined}
		tabindex={editable && isEditableType ? 0 : undefined}
		aria-label={editable && isEditableType
			? `${column.header}: ${displayValue || 'empty'}. Press Enter or double-click to edit.`
			: undefined}
		class="group relative flex items-center gap-2 truncate transition-colors {editable &&
		isEditableType
			? '-mx-1.5 -my-0.5 cursor-pointer rounded px-1.5 py-0.5 hover:bg-accent/50 focus:bg-accent/50 focus:ring-2 focus:ring-primary focus:ring-offset-1 focus:outline-none'
			: ''}"
	>
		<span class="truncate">
			{#if displayValue}
				{displayValue}
			{:else}
				<span class="text-muted-foreground/50 italic">Empty</span>
			{/if}
		</span>
		{#if editable && isEditableType}
			<span
				class="flex-shrink-0 opacity-0 transition-opacity group-hover:opacity-100 group-focus:opacity-100"
				aria-hidden="true"
			>
				<Pencil class="h-3 w-3 text-muted-foreground" />
			</span>
		{/if}
	</div>
{/if}
