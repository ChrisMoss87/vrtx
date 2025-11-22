<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Check, X, Loader2 } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import type { ColumnDef } from './types';

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
	let inputElement: HTMLInputElement | undefined;

	// Format value for display
	const displayValue = $derived.by(() => {
		if (value === null || value === undefined) return '';
		if (column.format) return column.format(value, row);
		if (column.type === 'date') return new Date(value).toLocaleDateString();
		if (column.type === 'datetime') return new Date(value).toLocaleString();
		if (column.type === 'boolean') return value ? 'Yes' : 'No';
		if (column.type === 'number' || column.type === 'decimal') return String(value);
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
			'datetime'
		];
		return editableTypes.includes(column.type || 'text');
	});

	function handleDoubleClick() {
		if (!editable || !isEditableType) return;
		isEditing = true;
		editValue = value;
		error = null;
		// Focus input after DOM update
		setTimeout(() => inputElement?.focus(), 0);
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
				// Default API call
				const response = await fetch(`/api/${moduleApiName}/records/${row.id}`, {
					method: 'PATCH',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
					},
					body: JSON.stringify({
						[column.accessorKey]: convertedValue
					})
				});

				if (!response.ok) {
					const data = await response.json();
					throw new Error(data.message || 'Failed to update record');
				}
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
	<div class="flex items-center gap-1" onclick={(e) => e.stopPropagation()}>
		<Input
			bind:this={inputElement}
			bind:value={editValue}
			type={inputType}
			onkeydown={handleKeydown}
			onblur={handleBlur}
			disabled={isSaving}
			class="h-8 w-full min-w-[120px] {error ? 'border-destructive' : ''}"
			aria-invalid={error ? 'true' : 'false'}
		/>
		<div class="flex items-center gap-0.5">
			{#if isSaving}
				<Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
			{:else}
				<button
					type="button"
					onclick={save}
					class="rounded p-1 hover:bg-accent"
					title="Save (Enter)"
					aria-label="Save"
				>
					<Check class="h-4 w-4 text-green-600" />
				</button>
				<button
					type="button"
					onclick={cancel}
					class="rounded p-1 hover:bg-accent"
					title="Cancel (Esc)"
					aria-label="Cancel"
				>
					<X class="h-4 w-4 text-destructive" />
				</button>
			{/if}
		</div>
	</div>
	{#if error}
		<p class="mt-1 text-xs text-destructive">{error}</p>
	{/if}
{:else}
	<!-- Display mode -->
	<div
		ondblclick={handleDoubleClick}
		class="truncate {editable && isEditableType ? 'cursor-text hover:bg-accent/50 rounded px-1 -mx-1' : ''}"
		title={editable && isEditableType ? 'Double-click to edit' : undefined}
	>
		{displayValue}
	</div>
{/if}
