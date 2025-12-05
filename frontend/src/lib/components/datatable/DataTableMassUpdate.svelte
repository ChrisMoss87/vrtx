<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Edit, Loader2, AlertCircle, Info } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { recordsApi } from '$lib/api/records';
	import type { TableContext, ColumnDef } from './types';

	interface Props {
		moduleApiName: string;
		open?: boolean;
	}

	let { moduleApiName, open = $bindable(false) }: Props = $props();

	const table = getContext<TableContext>('table');

	let selectedField = $state<string | null>(null);
	let newValue = $state<any>('');
	let isUpdating = $state(false);

	// Get selected record IDs
	const selectedIds = $derived(
		Object.entries(table.state.rowSelection)
			.filter(([_, selected]) => selected)
			.map(([id]) => parseInt(id))
	);

	// Get editable columns (exclude non-editable types and non-mass-updatable fields)
	const editableColumns = $derived(
		table.columns.filter((col) => {
			const nonEditableTypes = ['actions', 'id'];
			// Exclude system columns, non-editable types, and fields marked as non-mass-updatable
			return (
				!nonEditableTypes.includes(col.type || '') &&
				col.id !== 'id' &&
				col.id !== 'actions' &&
				col.id !== 'created_at' &&
				col.id !== 'updated_at' &&
				col.meta?.is_mass_updatable !== false
			);
		})
	);

	// Get formula columns (for showing info message)
	const formulaColumns = $derived(
		table.columns.filter((col) => col.meta?.isFormula === true)
	);

	// Get the selected column definition
	const selectedColumn = $derived(editableColumns.find((col) => col.id === selectedField));

	function handleFieldSelect(value: string | undefined) {
		if (value) {
			selectedField = value;
			newValue = getDefaultValue(value);
		}
	}

	function getDefaultValue(fieldId: string): any {
		const column = editableColumns.find((col) => col.id === fieldId);
		if (!column) return '';

		switch (column.type) {
			case 'checkbox':
			case 'boolean':
			case 'toggle':
				return false;
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return null;
			case 'multiselect':
				return [];
			default:
				return '';
		}
	}

	async function handleUpdate() {
		if (!selectedField || selectedIds.length === 0) {
			toast.error('Please select a field and ensure records are selected');
			return;
		}

		// Extract field name from accessorKey if needed
		const column = selectedColumn;
		const fieldName = column?.accessorKey?.startsWith('data.')
			? column.accessorKey.slice(5)
			: selectedField;

		isUpdating = true;

		try {
			const result = await recordsApi.bulkUpdate(moduleApiName, selectedIds, {
				[fieldName]: newValue
			});

			toast.success(result.message);

			if (result.errors && result.errors.length > 0) {
				console.warn('Some records failed to update:', result.errors);
				toast.warning(`${result.errors.length} record(s) failed to update`);
			}

			// Refresh table data
			await table.refresh();

			// Clear selection
			table.clearSelection();

			// Close dialog
			open = false;

			// Reset form
			selectedField = null;
			newValue = '';
		} catch (err: any) {
			const message = err.response?.data?.error || err.message || 'Failed to update records';
			toast.error(message);
		} finally {
			isUpdating = false;
		}
	}

	function handleClose() {
		open = false;
		selectedField = null;
		newValue = '';
	}
</script>

<Dialog.Root
	bind:open
	onOpenChange={(isOpen) => {
		if (!isOpen) handleClose();
	}}
>
	<Dialog.Content class="sm:max-w-lg">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				<Edit class="h-5 w-5" />
				Mass Update
			</Dialog.Title>
			<Dialog.Description>
				Update {selectedIds.length} selected record{selectedIds.length === 1 ? '' : 's'} with a new value.
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-6 py-4">
			{#if selectedIds.length === 0}
				<div
					class="flex items-center gap-2 rounded-lg bg-amber-50 p-4 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
				>
					<AlertCircle class="h-5 w-5 flex-shrink-0" />
					<p class="text-sm">No records selected. Please select records from the table first.</p>
				</div>
			{:else}
				{#if formulaColumns.length > 0}
					<div
						class="flex items-center gap-2 rounded-lg bg-blue-50 p-3 text-blue-800 dark:bg-blue-950 dark:text-blue-200"
					>
						<Info class="h-4 w-4 flex-shrink-0" />
						<p class="text-xs">
							Formula and calculated fields are excluded from mass updates as they are computed automatically.
						</p>
					</div>
				{/if}

				<!-- Field Selection -->
				<div class="space-y-2">
					<Label>Field to Update</Label>
					<Select.Root
						type="single"
						value={selectedField || undefined}
						onValueChange={handleFieldSelect}
					>
						<Select.Trigger>
							<span>
								{selectedField
									? editableColumns.find((c) => c.id === selectedField)?.header || selectedField
									: 'Select a field'}
							</span>
						</Select.Trigger>
						<Select.Content>
							{#each editableColumns as column}
								<Select.Item value={column.id}>{column.header}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<!-- Value Input (dynamic based on field type) -->
				{#if selectedColumn}
					<div class="space-y-2">
						<Label>New Value</Label>

						{#if selectedColumn.type === 'text' || selectedColumn.type === 'email' || selectedColumn.type === 'phone' || selectedColumn.type === 'url'}
							<Input
								type={selectedColumn.type === 'email'
									? 'email'
									: selectedColumn.type === 'url'
										? 'url'
										: selectedColumn.type === 'phone'
											? 'tel'
											: 'text'}
								placeholder={`Enter new ${selectedColumn.header.toLowerCase()}`}
								bind:value={newValue}
							/>
						{:else if selectedColumn.type === 'textarea'}
							<Textarea
								placeholder={`Enter new ${selectedColumn.header.toLowerCase()}`}
								bind:value={newValue}
								rows={3}
							/>
						{:else if selectedColumn.type === 'number' || selectedColumn.type === 'decimal' || selectedColumn.type === 'currency' || selectedColumn.type === 'percent'}
							<Input
								type="number"
								step={selectedColumn.type === 'decimal' || selectedColumn.type === 'currency'
									? '0.01'
									: selectedColumn.type === 'percent'
										? '0.1'
										: '1'}
								placeholder={`Enter new ${selectedColumn.header.toLowerCase()}`}
								bind:value={newValue}
							/>
						{:else if selectedColumn.type === 'date'}
							<Input type="date" bind:value={newValue} />
						{:else if selectedColumn.type === 'datetime'}
							<Input type="datetime-local" bind:value={newValue} />
						{:else if selectedColumn.type === 'select' || selectedColumn.type === 'radio'}
							<Select.Root
								type="single"
								value={newValue || undefined}
								onValueChange={(value) => (newValue = value || '')}
							>
								<Select.Trigger>
									<span>
										{newValue
											? (selectedColumn.filterOptions || selectedColumn.options)?.find(
													(o) => o.value === newValue
												)?.label || newValue
											: `Select ${selectedColumn.header.toLowerCase()}`}
									</span>
								</Select.Trigger>
								<Select.Content>
									{#each selectedColumn.filterOptions || selectedColumn.options || [] as option}
										<Select.Item value={option.value}>{option.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						{:else if selectedColumn.type === 'checkbox' || selectedColumn.type === 'boolean' || selectedColumn.type === 'toggle'}
							<div class="flex items-center gap-2">
								<Checkbox
									id="mass-update-checkbox"
									checked={newValue || false}
									onCheckedChange={(checked) => (newValue = checked)}
								/>
								<Label for="mass-update-checkbox" class="cursor-pointer font-normal">
									{newValue ? 'Yes' : 'No'}
								</Label>
							</div>
						{:else}
							<Input
								type="text"
								placeholder={`Enter new ${selectedColumn.header.toLowerCase()}`}
								bind:value={newValue}
							/>
						{/if}

						<p class="text-xs text-muted-foreground">
							This value will be applied to all {selectedIds.length} selected record{selectedIds.length ===
							1
								? ''
								: 's'}.
						</p>
					</div>
				{/if}
			{/if}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={handleClose} disabled={isUpdating}>Cancel</Button>
			<Button
				onclick={handleUpdate}
				disabled={isUpdating || !selectedField || selectedIds.length === 0}
			>
				{#if isUpdating}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Updating...
				{:else}
					Update {selectedIds.length} Record{selectedIds.length === 1 ? '' : 's'}
				{/if}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
