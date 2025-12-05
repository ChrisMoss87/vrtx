<script lang="ts">
	import { createEventDispatcher } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { AlertTriangle, Loader2, Edit, Plus, Trash2 } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import { toast } from 'svelte-sonner';

	interface Props {
		open: boolean;
		moduleApiName: string;
		moduleName: string;
		moduleFields: Field[];
		selectedRecordIds: number[];
		onBulkUpdate: (updates: Record<string, unknown>) => Promise<void>;
		onClose: () => void;
	}

	let {
		open = $bindable(),
		moduleApiName,
		moduleName,
		moduleFields,
		selectedRecordIds,
		onBulkUpdate,
		onClose
	}: Props = $props();

	const dispatch = createEventDispatcher();

	// Editable fields (exclude non-mass-updatable, system fields, and computed fields)
	const editableFields = $derived(
		moduleFields.filter(
			(f) =>
				f.is_mass_updatable !== false &&
				!['file', 'image', 'formula'].includes(f.type) &&
				!f.api_name.startsWith('_')
		)
	);

	// Field updates to apply
	let fieldUpdates = $state<Array<{ field: string; value: unknown }>>([]);
	let isUpdating = $state(false);

	function addFieldUpdate() {
		const availableFields = editableFields.filter(
			(f) => !fieldUpdates.some((u) => u.field === f.api_name)
		);
		if (availableFields.length > 0) {
			fieldUpdates = [
				...fieldUpdates,
				{ field: availableFields[0].api_name, value: getDefaultValue(availableFields[0]) }
			];
		}
	}

	function removeFieldUpdate(index: number) {
		fieldUpdates = fieldUpdates.filter((_, i) => i !== index);
	}

	function updateField(index: number, field: string, value: unknown) {
		fieldUpdates = fieldUpdates.map((u, i) =>
			i === index ? { field, value } : u
		);
	}

	function getDefaultValue(field: Field): unknown {
		switch (field.type) {
			case 'boolean':
			case 'switch':
				return false;
			case 'number':
			case 'integer':
			case 'currency':
			case 'percent':
				return 0;
			case 'multiselect':
				return [];
			default:
				return '';
		}
	}

	function getFieldByApiName(apiName: string): Field | undefined {
		return editableFields.find((f) => f.api_name === apiName);
	}

	async function handleSubmit() {
		if (fieldUpdates.length === 0) {
			toast.error('Please add at least one field to update');
			return;
		}

		isUpdating = true;
		try {
			const updates: Record<string, unknown> = {};
			for (const update of fieldUpdates) {
				updates[update.field] = update.value;
			}

			await onBulkUpdate(updates);
			toast.success(`Updated ${selectedRecordIds.length} records`);
			handleClose();
		} catch (error) {
			console.error('Bulk update failed:', error);
			toast.error('Failed to update records');
		} finally {
			isUpdating = false;
		}
	}

	function handleClose() {
		fieldUpdates = [];
		open = false;
		onClose();
		dispatch('close');
	}

	function handleOpenChange(isOpen: boolean) {
		if (!isOpen) {
			handleClose();
		}
	}
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Bulk Edit Records</Dialog.Title>
			<Dialog.Description>
				Update {selectedRecordIds.length} selected {selectedRecordIds.length === 1 ? 'record' : 'records'}
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<!-- Warning -->
			<div class="flex items-start gap-3 rounded-lg bg-yellow-50 dark:bg-yellow-950 border border-yellow-200 dark:border-yellow-900 p-3">
				<AlertTriangle class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mt-0.5" />
				<div class="text-sm">
					<p class="font-medium text-yellow-800 dark:text-yellow-200">
						This will update {selectedRecordIds.length} records
					</p>
					<p class="text-yellow-700 dark:text-yellow-300 mt-1">
						All selected fields will be overwritten with the new values.
					</p>
				</div>
			</div>

			<!-- Field Updates -->
			<div class="space-y-3">
				<div class="flex items-center justify-between">
					<Label>Fields to Update</Label>
					<Badge variant="secondary">{fieldUpdates.length} fields</Badge>
				</div>

				{#if fieldUpdates.length === 0}
					<div class="text-center py-6 border rounded-lg border-dashed">
						<p class="text-sm text-muted-foreground mb-3">
							No fields selected for update
						</p>
						<Button variant="outline" size="sm" onclick={addFieldUpdate}>
							<Plus class="mr-2 h-4 w-4" />
							Add Field
						</Button>
					</div>
				{:else}
					{#each fieldUpdates as update, index}
						{@const field = getFieldByApiName(update.field)}
						{@const availableFields = editableFields.filter(
							(f) => f.api_name === update.field || !fieldUpdates.some((u) => u.field === f.api_name)
						)}
						<div class="flex items-start gap-2 p-3 border rounded-lg">
							<div class="flex-1 space-y-2">
								<Select.Root
									type="single"
									value={update.field}
									onValueChange={(v) => {
										if (v) {
											const newField = getFieldByApiName(v);
											updateField(index, v, getDefaultValue(newField!));
										}
									}}
								>
									<Select.Trigger>
										{field?.label || 'Select field'}
									</Select.Trigger>
									<Select.Content>
										{#each availableFields as f}
											<Select.Item value={f.api_name}>{f.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>

								{#if field}
									{#if field.type === 'text' || field.type === 'email' || field.type === 'url' || field.type === 'phone'}
										<Input
											value={String(update.value || '')}
											oninput={(e) => updateField(index, update.field, e.currentTarget.value)}
											placeholder={`Enter ${field.label.toLowerCase()}`}
										/>
									{:else if field.type === 'textarea'}
										<Textarea
											value={String(update.value || '')}
											oninput={(e) => updateField(index, update.field, e.currentTarget.value)}
											placeholder={`Enter ${field.label.toLowerCase()}`}
											rows={2}
										/>
									{:else if field.type === 'number' || field.type === 'integer' || field.type === 'currency' || field.type === 'percent'}
										<Input
											type="number"
											value={String(update.value || '')}
											oninput={(e) => updateField(index, update.field, e.currentTarget.valueAsNumber)}
											placeholder={`Enter ${field.label.toLowerCase()}`}
										/>
									{:else if field.type === 'date'}
										<Input
											type="date"
											value={String(update.value || '')}
											oninput={(e) => updateField(index, update.field, e.currentTarget.value)}
										/>
									{:else if field.type === 'datetime'}
										<Input
											type="datetime-local"
											value={String(update.value || '')}
											oninput={(e) => updateField(index, update.field, e.currentTarget.value)}
										/>
									{:else if field.type === 'boolean' || field.type === 'switch'}
										<div class="flex items-center gap-2">
											<Checkbox
												checked={!!update.value}
												onCheckedChange={(checked) => updateField(index, update.field, checked)}
											/>
											<span class="text-sm">{update.value ? 'Yes' : 'No'}</span>
										</div>
									{:else if (field.type === 'select' || field.type === 'radio') && field.options}
										<Select.Root
											type="single"
											value={String(update.value || '')}
											onValueChange={(v) => {
												if (v !== undefined) updateField(index, update.field, v);
											}}
										>
											<Select.Trigger>
												{field.options.find((o) => o.value === update.value)?.label || 'Select value'}
											</Select.Trigger>
											<Select.Content>
												{#each field.options as opt}
													<Select.Item value={opt.value}>{opt.label}</Select.Item>
												{/each}
											</Select.Content>
										</Select.Root>
									{:else}
										<Input
											value={String(update.value || '')}
											oninput={(e) => updateField(index, update.field, e.currentTarget.value)}
											placeholder={`Enter value`}
										/>
									{/if}
								{/if}
							</div>

							<Button
								variant="ghost"
								size="icon"
								class="text-muted-foreground hover:text-destructive"
								onclick={() => removeFieldUpdate(index)}
							>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					{/each}

					{#if fieldUpdates.length < editableFields.length}
						<Button variant="outline" size="sm" onclick={addFieldUpdate}>
							<Plus class="mr-2 h-4 w-4" />
							Add Another Field
						</Button>
					{/if}
				{/if}
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={handleClose} disabled={isUpdating}>
				Cancel
			</Button>
			<Button
				onclick={handleSubmit}
				disabled={isUpdating || fieldUpdates.length === 0}
			>
				{#if isUpdating}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Updating...
				{:else}
					<Edit class="mr-2 h-4 w-4" />
					Update {selectedRecordIds.length} Records
				{/if}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
