<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import type { BlueprintTransitionCondition } from '$lib/api/blueprints';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import TrashIcon from '@lucide/svelte/icons/trash-2';
	import FilterIcon from '@lucide/svelte/icons/filter';
	import GripVerticalIcon from '@lucide/svelte/icons/grip-vertical';

	interface FieldOption {
		id: number;
		label: string;
		value: string;
		color?: string | null;
	}

	interface Field {
		id: number;
		api_name: string;
		label: string;
		type: string;
		options?: FieldOption[];
	}

	// Field types that have options (picklists)
	const picklistTypes = ['select', 'multiselect', 'picklist', 'status', 'dropdown'];

	interface Props {
		conditions: BlueprintTransitionCondition[];
		fields: Field[];
		readonly?: boolean;
		onAdd?: (condition: Partial<BlueprintTransitionCondition>) => void;
		onUpdate?: (id: number, condition: Partial<BlueprintTransitionCondition>) => void;
		onDelete?: (id: number) => void;
	}

	let { conditions = [], fields = [], readonly = false, onAdd, onUpdate, onDelete }: Props = $props();

	let showAddForm = $state(false);
	let newCondition = $state<Partial<BlueprintTransitionCondition>>({
		field_id: undefined,
		operator: 'eq',
		value: '',
		logical_group: 'AND'
	});

	const operators = [
		{ value: 'eq', label: 'Equals' },
		{ value: 'ne', label: 'Not equals' },
		{ value: 'gt', label: 'Greater than' },
		{ value: 'gte', label: 'Greater than or equal' },
		{ value: 'lt', label: 'Less than' },
		{ value: 'lte', label: 'Less than or equal' },
		{ value: 'contains', label: 'Contains' },
		{ value: 'not_contains', label: 'Does not contain' },
		{ value: 'starts_with', label: 'Starts with' },
		{ value: 'ends_with', label: 'Ends with' },
		{ value: 'is_empty', label: 'Is empty' },
		{ value: 'is_not_empty', label: 'Is not empty' },
		{ value: 'in', label: 'Is one of' },
		{ value: 'not_in', label: 'Is not one of' }
	];

	const logicalGroups = [
		{ value: 'AND', label: 'AND' },
		{ value: 'OR', label: 'OR' }
	];

	// Operators that don't require a value
	const noValueOperators = ['is_empty', 'is_not_empty'];

	function getFieldById(id: number | null | undefined): Field | undefined {
		if (!id) return undefined;
		return fields.find((f) => f.id === id);
	}

	function getOperatorLabel(op: string): string {
		return operators.find((o) => o.value === op)?.label || op;
	}

	function handleAddCondition() {
		if (!newCondition.field_id) return;

		onAdd?.({
			field_id: newCondition.field_id,
			operator: newCondition.operator || 'eq',
			value: noValueOperators.includes(newCondition.operator || '') ? null : newCondition.value,
			logical_group: newCondition.logical_group || 'AND',
			display_order: conditions.length
		});

		// Reset form
		newCondition = {
			field_id: undefined,
			operator: 'eq',
			value: '',
			logical_group: 'AND'
		};
		showAddForm = false;
	}

	function handleDeleteCondition(id: number) {
		if (confirm('Delete this condition?')) {
			onDelete?.(id);
		}
	}

	// Get operators that make sense for the field type
	function getOperatorsForField(fieldType: string): typeof operators {
		const numericTypes = ['number', 'decimal', 'currency', 'integer', 'percent'];
		const textTypes = ['text', 'email', 'phone', 'url', 'textarea'];

		if (numericTypes.includes(fieldType)) {
			return operators.filter((op) =>
				['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty'].includes(op.value)
			);
		}

		if (textTypes.includes(fieldType)) {
			return operators.filter((op) =>
				['eq', 'ne', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'].includes(
					op.value
				)
			);
		}

		if (fieldType === 'boolean') {
			return operators.filter((op) => ['eq', 'ne'].includes(op.value));
		}

		if (fieldType === 'select' || fieldType === 'multiselect') {
			return operators.filter((op) => ['eq', 'ne', 'in', 'not_in', 'is_empty', 'is_not_empty'].includes(op.value));
		}

		return operators;
	}

	const selectedField = $derived(getFieldById(newCondition.field_id));
	const availableOperators = $derived(selectedField ? getOperatorsForField(selectedField.type) : operators);
	const isPicklistField = $derived(selectedField ? picklistTypes.includes(selectedField.type) : false);
	const fieldOptions = $derived(selectedField?.options || []);
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center gap-2">
			<FilterIcon class="h-5 w-5 text-amber-500" />
			<Card.Title class="text-base">Before-Phase Conditions</Card.Title>
		</div>
		<Card.Description>
			Conditions that must be met before this transition can be started.
		</Card.Description>
	</Card.Header>

	<Card.Content class="space-y-4">
		{#if conditions.length === 0 && !showAddForm}
			<div class="rounded-lg border border-dashed p-4 text-center">
				<p class="text-sm text-muted-foreground">No conditions configured</p>
				<p class="mt-1 text-xs text-muted-foreground">
					Anyone can start this transition from the allowed state.
				</p>
				{#if !readonly}
					<Button variant="outline" size="sm" class="mt-3" onclick={() => (showAddForm = true)}>
						<PlusIcon class="mr-2 h-4 w-4" />
						Add Condition
					</Button>
				{/if}
			</div>
		{:else}
			<!-- Existing conditions -->
			<div class="space-y-2">
				{#each conditions as condition, index (condition.id)}
					{@const field = getFieldById(condition.field_id)}
					<div class="flex items-center gap-2 rounded-lg border bg-card p-3">
						{#if !readonly}
							<GripVerticalIcon class="h-4 w-4 cursor-move text-muted-foreground" />
						{/if}

						{#if index > 0}
							<Badge variant="outline" class="shrink-0">
								{condition.logical_group || 'AND'}
							</Badge>
						{/if}

						<div class="flex flex-1 flex-wrap items-center gap-2 text-sm">
							<span class="font-medium">{field?.label || 'Unknown field'}</span>
							<Badge variant="secondary">{getOperatorLabel(condition.operator)}</Badge>
							{#if !noValueOperators.includes(condition.operator)}
								<span class="text-muted-foreground">"{condition.value}"</span>
							{/if}
						</div>

						{#if !readonly}
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8 shrink-0 text-destructive hover:bg-destructive/10"
								onclick={() => handleDeleteCondition(condition.id)}
							>
								<TrashIcon class="h-4 w-4" />
							</Button>
						{/if}
					</div>
				{/each}
			</div>

			<!-- Add new condition form -->
			{#if showAddForm && !readonly}
				<div class="rounded-lg border bg-muted/50 p-4">
					<div class="mb-3 text-sm font-medium">New Condition</div>

					<div class="grid gap-3">
						{#if conditions.length > 0}
							<div class="space-y-1.5">
								<Label class="text-xs">Combine with</Label>
								<Select.Root
									type="single"
									value={newCondition.logical_group || 'AND'}
									onValueChange={(v) => (newCondition.logical_group = v)}
								>
									<Select.Trigger class="h-8 w-24">
										{newCondition.logical_group || 'AND'}
									</Select.Trigger>
									<Select.Content>
										{#each logicalGroups as group}
											<Select.Item value={group.value}>{group.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>
						{/if}

						<div class="space-y-1.5">
							<Label class="text-xs">Field</Label>
							<Select.Root
								type="single"
								value={newCondition.field_id?.toString() || ''}
								onValueChange={(v) => (newCondition.field_id = parseInt(v))}
							>
								<Select.Trigger class="h-8">
									{selectedField?.label || 'Select field...'}
								</Select.Trigger>
								<Select.Content class="max-h-[300px]">
									{#each fields as field}
										<Select.Item value={field.id.toString()}>
											<div class="flex items-center gap-2">
												<span>{field.label}</span>
												<span class="text-xs text-muted-foreground">({field.type})</span>
											</div>
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>

						<div class="space-y-1.5">
							<Label class="text-xs">Operator</Label>
							<Select.Root
								type="single"
								value={newCondition.operator || 'eq'}
								onValueChange={(v) => (newCondition.operator = v)}
							>
								<Select.Trigger class="h-8">
									{getOperatorLabel(newCondition.operator || 'eq')}
								</Select.Trigger>
								<Select.Content>
									{#each availableOperators as op}
										<Select.Item value={op.value}>{op.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>

						{#if !noValueOperators.includes(newCondition.operator || '')}
							<div class="space-y-1.5">
								<Label class="text-xs">Value</Label>
								{#if isPicklistField && fieldOptions.length > 0}
									<!-- Dropdown for picklist fields -->
									<Select.Root
										type="single"
										value={newCondition.value?.toString() || ''}
										onValueChange={(v) => (newCondition.value = v)}
									>
										<Select.Trigger class="h-8">
											{fieldOptions.find(o => o.value === newCondition.value)?.label || 'Select value...'}
										</Select.Trigger>
										<Select.Content class="max-h-[300px]">
											{#each fieldOptions as option}
												<Select.Item value={option.value}>
													<div class="flex items-center gap-2">
														{#if option.color}
															<div
																class="h-3 w-3 rounded-full"
																style="background-color: {option.color}"
															></div>
														{/if}
														<span>{option.label}</span>
													</div>
												</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>
								{:else if selectedField?.type === 'boolean'}
									<!-- Dropdown for boolean fields -->
									<Select.Root
										type="single"
										value={newCondition.value?.toString() || ''}
										onValueChange={(v) => (newCondition.value = v)}
									>
										<Select.Trigger class="h-8">
											{newCondition.value === 'true' ? 'Yes' : newCondition.value === 'false' ? 'No' : 'Select value...'}
										</Select.Trigger>
										<Select.Content>
											<Select.Item value="true">Yes</Select.Item>
											<Select.Item value="false">No</Select.Item>
										</Select.Content>
									</Select.Root>
								{:else}
									<!-- Text input for other fields -->
									<Input
										class="h-8"
										placeholder="Enter value..."
										bind:value={newCondition.value}
									/>
								{/if}
							</div>
						{/if}
					</div>

					<div class="mt-4 flex justify-end gap-2">
						<Button variant="ghost" size="sm" onclick={() => (showAddForm = false)}>
							Cancel
						</Button>
						<Button size="sm" onclick={handleAddCondition} disabled={!newCondition.field_id}>
							Add Condition
						</Button>
					</div>
				</div>
			{:else if !readonly}
				<Button variant="outline" size="sm" onclick={() => (showAddForm = true)}>
					<PlusIcon class="mr-2 h-4 w-4" />
					Add Condition
				</Button>
			{/if}
		{/if}
	</Card.Content>
</Card.Root>
