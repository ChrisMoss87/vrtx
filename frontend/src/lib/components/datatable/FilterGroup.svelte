<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { X, Plus } from 'lucide-svelte';
	import type { FilterConfig, ColumnDef } from './types';
	import type { FilterGroupData } from './types';
	import { cn } from '$lib/utils';

	interface Props {
		group: FilterGroupData;
		columns: ColumnDef[];
		level: number;
		addCondition: (group: FilterGroupData) => void;
		removeCondition: (group: FilterGroupData, index: number) => void;
		updateCondition: (group: FilterGroupData, index: number, condition: FilterConfig) => void;
		addGroup: (group: FilterGroupData) => void;
		removeGroup: (parentGroup: FilterGroupData, groupId: string) => void;
		toggleLogic: (group: FilterGroupData) => void;
	}

	let {
		group,
		columns,
		level,
		addCondition,
		removeCondition,
		updateCondition,
		addGroup,
		removeGroup,
		toggleLogic
	}: Props = $props();

	// Operator options based on field type
	function getOperators(columnType: string) {
		switch (columnType) {
			case 'text':
			case 'email':
			case 'phone':
			case 'url':
				return [
					{ value: 'contains', label: 'Contains' },
					{ value: 'not_contains', label: 'Does not contain' },
					{ value: 'equals', label: 'Equals' },
					{ value: 'not_equals', label: 'Not equals' },
					{ value: 'starts_with', label: 'Starts with' },
					{ value: 'ends_with', label: 'Ends with' },
					{ value: 'is_empty', label: 'Is empty' },
					{ value: 'is_not_empty', label: 'Is not empty' }
				];
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return [
					{ value: 'equals', label: 'Equals' },
					{ value: 'not_equals', label: 'Not equals' },
					{ value: 'greater_than', label: 'Greater than' },
					{ value: 'greater_than_or_equal', label: 'Greater than or equal' },
					{ value: 'less_than', label: 'Less than' },
					{ value: 'less_than_or_equal', label: 'Less than or equal' },
					{ value: 'between', label: 'Between' }
				];
			case 'date':
			case 'datetime':
				return [
					{ value: 'equals', label: 'Is' },
					{ value: 'not_equals', label: 'Is not' },
					{ value: 'before', label: 'Before' },
					{ value: 'after', label: 'After' },
					{ value: 'between', label: 'Between' }
				];
			case 'select':
			case 'multiselect':
			case 'radio':
			case 'tags':
				return [
					{ value: 'equals', label: 'Is' },
					{ value: 'not_equals', label: 'Is not' },
					{ value: 'in', label: 'Is one of' },
					{ value: 'not_in', label: 'Is not one of' }
				];
			case 'boolean':
			case 'checkbox':
			case 'toggle':
				return [
					{ value: 'equals', label: 'Is' },
					{ value: 'not_equals', label: 'Is not' }
				];
			default:
				return [
					{ value: 'equals', label: 'Equals' },
					{ value: 'not_equals', label: 'Not equals' }
				];
		}
	}

	function getColumn(columnId: string): ColumnDef | undefined {
		return columns.find((col) => col.id === columnId);
	}

	function handleColumnChange(condition: FilterConfig, index: number, newColumnId: string) {
		const column = getColumn(newColumnId);
		if (!column) return;

		// Reset operator and value when column changes
		const newOperator = getOperators(column.type || 'text')[0]?.value || 'equals';
		updateCondition(group, index, {
			field: newColumnId,
			operator: newOperator as any,
			value: ''
		});
	}

	function handleOperatorChange(condition: FilterConfig, index: number, newOperator: string) {
		updateCondition(group, index, {
			...condition,
			operator: newOperator as any
		});
	}

	function handleValueChange(condition: FilterConfig, index: number, newValue: any) {
		updateCondition(group, index, {
			...condition,
			value: newValue
		});
	}

	// Check if operator requires value input
	function requiresValue(operator: string): boolean {
		return !['is_empty', 'is_not_empty', 'is_null', 'is_not_null'].includes(operator);
	}

	const borderColor = $derived(() => {
		const colors = ['border-primary', 'border-blue-500', 'border-purple-500', 'border-pink-500'];
		return colors[level % colors.length];
	});
</script>

<div class={cn('space-y-3 rounded-lg border-2 p-4', borderColor(), level > 0 && 'ml-6')}>
	<!-- Group Header (for nested groups) -->
	{#if level > 0}
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<Badge
					variant={group.logic === 'AND' ? 'default' : 'secondary'}
					class="cursor-pointer"
					onclick={() => toggleLogic(group)}
				>
					{group.logic}
				</Badge>
				<span class="text-xs text-muted-foreground">
					{group.conditions.length} condition{group.conditions.length === 1 ? '' : 's'}
				</span>
			</div>
			<Button
				variant="ghost"
				size="sm"
				class="h-6 w-6 p-0"
				onclick={() => removeGroup(group, group.id)}
				aria-label="Remove group"
			>
				<X class="h-3 w-3" />
			</Button>
		</div>
	{/if}

	<!-- Conditions -->
	{#if group.conditions.length > 0}
		<div class="space-y-2">
			{#each group.conditions as condition, index (index)}
				{@const column = getColumn(condition.field)}
				{@const operators = column ? getOperators(column.type || 'text') : []}
				{@const needsValue = requiresValue(condition.operator)}

				<div class="flex items-center gap-2">
					<!-- Column Select -->
					<Select.Root
						type="single"
						value={condition.field}
						onValueChange={(value) => {
							if (value) {
								handleColumnChange(condition, index, value);
							}
						}}
					>
						<Select.Trigger class="w-[180px]">
							{column?.header || condition.field || 'Select column'}
						</Select.Trigger>
						<Select.Content>
							{#each columns as col}
								<Select.Item value={col.id}>{col.header}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>

					<!-- Operator Select -->
					<Select.Root
						type="single"
						value={condition.operator}
						onValueChange={(value) => {
							if (value) {
								handleOperatorChange(condition, index, value);
							}
						}}
					>
						<Select.Trigger class="w-[160px]">
							{operators.find((o) => o.value === condition.operator)?.label || condition.operator || 'Select operator'}
						</Select.Trigger>
						<Select.Content>
							{#each operators as operator}
								<Select.Item value={operator.value}>{operator.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>

					<!-- Value Input -->
					{#if needsValue}
						{@const selectOptions = column?.filterOptions || column?.options || []}
						{#if (column?.type === 'select' || column?.type === 'multiselect' || column?.type === 'radio') && selectOptions.length > 0}
							<Select.Root
								type="single"
								value={condition.value || undefined}
								onValueChange={(value) => {
									handleValueChange(condition, index, value);
								}}
							>
								<Select.Trigger class="flex-1">
									{selectOptions.find((opt) => opt.value === condition.value)?.label || 'Select value'}
								</Select.Trigger>
								<Select.Content>
									{#each selectOptions as option}
										<Select.Item value={option.value}>{option.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						{:else if column?.type === 'boolean' || column?.type === 'checkbox' || column?.type === 'toggle'}
							<Select.Root
								type="single"
								value={condition.value !== undefined ? String(condition.value) : undefined}
								onValueChange={(value) => {
									handleValueChange(condition, index, value === 'true');
								}}
							>
								<Select.Trigger class="flex-1">
									{condition.value !== undefined ? (condition.value ? 'Yes' : 'No') : 'Select value'}
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="true">Yes</Select.Item>
									<Select.Item value="false">No</Select.Item>
								</Select.Content>
							</Select.Root>
						{:else if column?.type === 'number' || column?.type === 'decimal'}
							<Input
								type="number"
								placeholder="Enter value"
								value={condition.value}
								oninput={(e) => handleValueChange(condition, index, e.currentTarget.value)}
								class="flex-1"
							/>
						{:else}
							<Input
								type="text"
								placeholder="Enter value"
								value={condition.value}
								oninput={(e) => handleValueChange(condition, index, e.currentTarget.value)}
								class="flex-1"
							/>
						{/if}
					{:else}
						<div class="flex-1 text-sm text-muted-foreground italic">No value needed</div>
					{/if}

					<!-- Remove Button -->
					<Button
						variant="ghost"
						size="sm"
						class="h-8 w-8 flex-shrink-0 p-0"
						onclick={() => removeCondition(group, index)}
						aria-label="Remove condition"
					>
						<X class="h-3 w-3" />
					</Button>
				</div>

				<!-- Logic connector (except for last condition) -->
				{#if index < group.conditions.length - 1 || group.groups.length > 0}
					<div class="flex items-center gap-2 pl-2">
						<div class="h-px w-4 bg-border" />
						<Badge variant="outline" class="text-xs">{group.logic}</Badge>
						<div class="h-px flex-1 bg-border" />
					</div>
				{/if}
			{/each}
		</div>
	{/if}

	<!-- Nested Groups -->
	{#if group.groups.length > 0}
		<div class="space-y-3">
			{#each group.groups as subGroup (subGroup.id)}
				<svelte:self
					group={subGroup}
					{columns}
					level={level + 1}
					{addCondition}
					{removeCondition}
					{updateCondition}
					{addGroup}
					{removeGroup}
					{toggleLogic}
				/>

				<!-- Logic connector after group (except for last) -->
				{#if group.groups.indexOf(subGroup) < group.groups.length - 1}
					<div class="flex items-center gap-2 pl-2">
						<div class="h-px w-4 bg-border" />
						<Badge variant="outline" class="text-xs">{group.logic}</Badge>
						<div class="h-px flex-1 bg-border" />
					</div>
				{/if}
			{/each}
		</div>
	{/if}

	<!-- Add buttons for nested group -->
	{#if level > 0 && (group.conditions.length > 0 || group.groups.length > 0)}
		<div class="flex items-center gap-2 border-t pt-2">
			<Button variant="ghost" size="sm" onclick={() => addCondition(group)}>
				<Plus class="mr-1 h-3 w-3" />
				Add Condition
			</Button>
			<Button variant="ghost" size="sm" onclick={() => addGroup(group)}>
				<Plus class="mr-1 h-3 w-3" />
				Add Subgroup
			</Button>
		</div>
	{/if}
</div>
