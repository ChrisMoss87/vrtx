<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, FolderPlus, Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { Condition, ConditionGroup, WorkflowConditions } from '$lib/api/workflows';

	interface Props {
		conditions: WorkflowConditions | Condition[] | null;
		moduleFields?: Field[];
		onConditionsChange?: (conditions: WorkflowConditions) => void;
	}

	let {
		conditions = $bindable(),
		moduleFields = [],
		onConditionsChange
	}: Props = $props();

	// Normalize conditions to WorkflowConditions format
	function normalizeConditions(conds: WorkflowConditions | Condition[] | null): WorkflowConditions {
		if (!conds) {
			return { logic: 'and', groups: [] };
		}
		if (Array.isArray(conds)) {
			// Convert flat array to single group
			return {
				logic: 'and',
				groups: conds.length > 0 ? [{ logic: 'and', conditions: conds }] : []
			};
		}
		return conds;
	}

	let normalizedConditions = $state<WorkflowConditions>(normalizeConditions(conditions));

	// Sync external changes
	$effect(() => {
		normalizedConditions = normalizeConditions(conditions);
	});

	// Operators organized by category
	const OPERATORS: { category: string; operators: { value: string; label: string; description: string; noValue?: boolean }[] }[] = [
		{
			category: 'Comparison',
			operators: [
				{ value: 'equals', label: 'Equals', description: 'Exact match' },
				{ value: 'not_equals', label: 'Not Equals', description: 'Does not match' },
				{ value: 'greater_than', label: 'Greater Than', description: 'Value is greater' },
				{ value: 'less_than', label: 'Less Than', description: 'Value is less' },
				{ value: 'greater_than_or_equal', label: 'Greater or Equal', description: 'Value is greater or equal' },
				{ value: 'less_than_or_equal', label: 'Less or Equal', description: 'Value is less or equal' },
				{ value: 'between', label: 'Between', description: 'Value is in range (min,max)' }
			]
		},
		{
			category: 'Text',
			operators: [
				{ value: 'contains', label: 'Contains', description: 'Text contains value' },
				{ value: 'not_contains', label: 'Does Not Contain', description: 'Text does not contain value' },
				{ value: 'starts_with', label: 'Starts With', description: 'Text begins with value' },
				{ value: 'ends_with', label: 'Ends With', description: 'Text ends with value' },
				{ value: 'matches_regex', label: 'Matches Regex', description: 'Matches regular expression' }
			]
		},
		{
			category: 'List',
			operators: [
				{ value: 'in', label: 'In List', description: 'Value is one of (comma-separated)' },
				{ value: 'not_in', label: 'Not In List', description: 'Value is not one of' }
			]
		},
		{
			category: 'Empty/Null',
			operators: [
				{ value: 'is_empty', label: 'Is Empty', description: 'Field has no value', noValue: true },
				{ value: 'is_not_empty', label: 'Is Not Empty', description: 'Field has a value', noValue: true },
				{ value: 'is_null', label: 'Is Null', description: 'Field is null', noValue: true },
				{ value: 'is_not_null', label: 'Is Not Null', description: 'Field is not null', noValue: true }
			]
		},
		{
			category: 'Boolean',
			operators: [
				{ value: 'is_true', label: 'Is True', description: 'Checkbox is checked', noValue: true },
				{ value: 'is_false', label: 'Is False', description: 'Checkbox is unchecked', noValue: true }
			]
		},
		{
			category: 'Date',
			operators: [
				{ value: 'is_today', label: 'Is Today', description: 'Date is today', noValue: true },
				{ value: 'is_this_week', label: 'Is This Week', description: 'Date is in current week', noValue: true },
				{ value: 'is_this_month', label: 'Is This Month', description: 'Date is in current month', noValue: true },
				{ value: 'date_before', label: 'Date Before', description: 'Date is before value' },
				{ value: 'date_after', label: 'Date After', description: 'Date is after value' },
				{ value: 'date_in_next', label: 'In Next X Days', description: 'Date is within next N days' },
				{ value: 'date_in_past', label: 'In Past X Days', description: 'Date is within past N days' }
			]
		},
		{
			category: 'User',
			operators: [
				{ value: 'is_current_user', label: 'Is Current User', description: 'Assigned to current user', noValue: true },
				{ value: 'is_record_owner', label: 'Is Record Owner', description: 'Field is record owner', noValue: true },
				{ value: 'is_in_role', label: 'Is In Role', description: 'User has specified role' }
			]
		},
		{
			category: 'Change Detection',
			operators: [
				{ value: 'has_changed', label: 'Has Changed', description: 'Field value changed', noValue: true },
				{ value: 'has_not_changed', label: 'Has Not Changed', description: 'Field value unchanged', noValue: true },
				{ value: 'changed_from', label: 'Changed From', description: 'Previous value was' },
				{ value: 'changed_to', label: 'Changed To', description: 'New value is' },
				{ value: 'changed_from_to', label: 'Changed From X to Y', description: 'Use format: oldValue,newValue' },
				{ value: 'was_empty_now_filled', label: 'Was Empty, Now Filled', description: 'Field was empty and now has value', noValue: true },
				{ value: 'was_filled_now_empty', label: 'Was Filled, Now Empty', description: 'Field had value and now empty', noValue: true }
			]
		}
	];

	// Flat list for lookups
	const allOperators = OPERATORS.flatMap((cat) => cat.operators);

	function getOperatorInfo(value: string) {
		return allOperators.find((op) => op.value === value);
	}

	function isNoValueOperator(operator: string): boolean {
		return getOperatorInfo(operator)?.noValue === true;
	}

	function emitChange() {
		conditions = normalizedConditions;
		onConditionsChange?.(normalizedConditions);
	}

	function setRootLogic(logic: 'and' | 'or') {
		normalizedConditions = { ...normalizedConditions, logic };
		emitChange();
	}

	function addGroup() {
		normalizedConditions = {
			...normalizedConditions,
			groups: [
				...normalizedConditions.groups,
				{ logic: 'and', conditions: [{ field: moduleFields[0]?.api_name || '', operator: 'equals', value: '' }] }
			]
		};
		emitChange();
	}

	function removeGroup(groupIndex: number) {
		normalizedConditions = {
			...normalizedConditions,
			groups: normalizedConditions.groups.filter((_, i) => i !== groupIndex)
		};
		emitChange();
	}

	function setGroupLogic(groupIndex: number, logic: 'and' | 'or') {
		const groups = [...normalizedConditions.groups];
		groups[groupIndex] = { ...groups[groupIndex], logic };
		normalizedConditions = { ...normalizedConditions, groups };
		emitChange();
	}

	function addCondition(groupIndex: number) {
		const groups = [...normalizedConditions.groups];
		groups[groupIndex] = {
			...groups[groupIndex],
			conditions: [
				...groups[groupIndex].conditions,
				{ field: moduleFields[0]?.api_name || '', operator: 'equals', value: '' }
			]
		};
		normalizedConditions = { ...normalizedConditions, groups };
		emitChange();
	}

	function removeCondition(groupIndex: number, conditionIndex: number) {
		const groups = [...normalizedConditions.groups];
		groups[groupIndex] = {
			...groups[groupIndex],
			conditions: groups[groupIndex].conditions.filter((_, i) => i !== conditionIndex)
		};
		// Remove empty groups
		if (groups[groupIndex].conditions.length === 0) {
			groups.splice(groupIndex, 1);
		}
		normalizedConditions = { ...normalizedConditions, groups };
		emitChange();
	}

	function updateCondition(groupIndex: number, conditionIndex: number, updates: Partial<Condition>) {
		const groups = [...normalizedConditions.groups];
		const conditions = [...groups[groupIndex].conditions];
		conditions[conditionIndex] = { ...conditions[conditionIndex], ...updates };
		groups[groupIndex] = { ...groups[groupIndex], conditions };
		normalizedConditions = { ...normalizedConditions, groups };
		emitChange();
	}

	// Get the field label for display
	function getFieldLabel(apiName: string): string {
		const field = moduleFields.find((f) => f.api_name === apiName);
		return field?.label || apiName || 'Select field';
	}

	// Check if conditions are empty
	const hasConditions = $derived(normalizedConditions.groups.length > 0);
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<Card.Title class="text-base">Conditions</Card.Title>
		<Card.Description>
			Define when this workflow should run. Leave empty to run for all records.
		</Card.Description>
	</Card.Header>
	<Card.Content class="space-y-4">
		{#if hasConditions}
			<!-- Root Logic Selector (only show if multiple groups) -->
			{#if normalizedConditions.groups.length > 1}
				<div class="space-y-2">
					<Label class="text-sm">Match</Label>
					<div class="flex gap-2">
						<Button
							type="button"
							variant={normalizedConditions.logic === 'and' ? 'default' : 'outline'}
							size="sm"
							onclick={() => setRootLogic('and')}
						>
							ALL groups (AND)
						</Button>
						<Button
							type="button"
							variant={normalizedConditions.logic === 'or' ? 'default' : 'outline'}
							size="sm"
							onclick={() => setRootLogic('or')}
						>
							ANY group (OR)
						</Button>
					</div>
				</div>
			{/if}

			<!-- Condition Groups -->
			<div class="space-y-4">
				{#each normalizedConditions.groups as group, groupIndex}
					<div class="rounded-lg border bg-muted/30 p-4">
						<div class="mb-3 flex items-center justify-between">
							<div class="flex items-center gap-2">
								<Badge variant="outline">Group {groupIndex + 1}</Badge>
								{#if group.conditions.length > 1}
									<div class="flex gap-1">
										<Button
											type="button"
											variant={group.logic === 'and' ? 'secondary' : 'ghost'}
											size="sm"
											class="h-6 px-2 text-xs"
											onclick={() => setGroupLogic(groupIndex, 'and')}
										>
											AND
										</Button>
										<Button
											type="button"
											variant={group.logic === 'or' ? 'secondary' : 'ghost'}
											size="sm"
											class="h-6 px-2 text-xs"
											onclick={() => setGroupLogic(groupIndex, 'or')}
										>
											OR
										</Button>
									</div>
								{/if}
							</div>
							<Button
								type="button"
								variant="ghost"
								size="icon"
								class="h-7 w-7 text-destructive hover:bg-destructive/10"
								onclick={() => removeGroup(groupIndex)}
							>
								<Trash2 class="h-3.5 w-3.5" />
							</Button>
						</div>

						<div class="space-y-3">
							{#each group.conditions as condition, conditionIndex}
								<div class="flex flex-col gap-2 rounded-md border bg-background p-3">
									<div class="flex items-start justify-between gap-2">
										<div class="grid flex-1 gap-2 sm:grid-cols-3">
											<!-- Field Select -->
											<div class="space-y-1">
												<Label class="text-xs text-muted-foreground">Field</Label>
												<Select.Root
													type="single"
													value={condition.field}
													onValueChange={(value) => value && updateCondition(groupIndex, conditionIndex, { field: value })}
												>
													<Select.Trigger class="h-9">
														{getFieldLabel(condition.field)}
													</Select.Trigger>
													<Select.Content>
														{#each moduleFields as field}
															<Select.Item value={field.api_name}>
																<div class="flex flex-col">
																	<span>{field.label}</span>
																	<span class="text-xs text-muted-foreground">{field.type}</span>
																</div>
															</Select.Item>
														{/each}
													</Select.Content>
												</Select.Root>
											</div>

											<!-- Operator Select -->
											<div class="space-y-1">
												<Label class="text-xs text-muted-foreground">Operator</Label>
												<Select.Root
													type="single"
													value={condition.operator}
													onValueChange={(value) => value && updateCondition(groupIndex, conditionIndex, { operator: value })}
												>
													<Select.Trigger class="h-9">
														{getOperatorInfo(condition.operator)?.label || 'Select operator'}
													</Select.Trigger>
													<Select.Content>
														{#each OPERATORS as category}
															<Select.Group>
																<Select.GroupHeading>{category.category}</Select.GroupHeading>
																{#each category.operators as operator}
																	<Select.Item value={operator.value}>
																		<div class="flex flex-col">
																			<span>{operator.label}</span>
																			<span class="text-xs text-muted-foreground">{operator.description}</span>
																		</div>
																	</Select.Item>
																{/each}
															</Select.Group>
														{/each}
													</Select.Content>
												</Select.Root>
											</div>

											<!-- Value Input -->
											{#if !isNoValueOperator(condition.operator)}
												<div class="space-y-1">
													<Label class="text-xs text-muted-foreground">Value</Label>
													<Input
														class="h-9"
														value={String(condition.value || '')}
														oninput={(e) => updateCondition(groupIndex, conditionIndex, { value: e.currentTarget.value })}
														placeholder="Enter value"
													/>
												</div>
											{:else}
												<div class="flex items-end">
													<p class="pb-2 text-xs text-muted-foreground italic">
														No value needed
													</p>
												</div>
											{/if}
										</div>

										<Button
											type="button"
											variant="ghost"
											size="icon"
											class="mt-5 h-7 w-7"
											onclick={() => removeCondition(groupIndex, conditionIndex)}
										>
											<Trash2 class="h-3.5 w-3.5" />
										</Button>
									</div>
								</div>

								{#if conditionIndex < group.conditions.length - 1}
									<div class="flex justify-center">
										<Badge variant="secondary" class="text-xs uppercase">
											{group.logic}
										</Badge>
									</div>
								{/if}
							{/each}

							<Button
								type="button"
								variant="ghost"
								size="sm"
								class="w-full"
								onclick={() => addCondition(groupIndex)}
							>
								<Plus class="mr-2 h-3.5 w-3.5" />
								Add condition
							</Button>
						</div>
					</div>

					{#if groupIndex < normalizedConditions.groups.length - 1}
						<div class="flex justify-center">
							<Badge variant="default" class="uppercase">
								{normalizedConditions.logic}
							</Badge>
						</div>
					{/if}
				{/each}
			</div>
		{/if}

		<!-- Add Group Button -->
		<div class="flex gap-2">
			<Button
				type="button"
				variant="outline"
				size="sm"
				onclick={addGroup}
				disabled={moduleFields.length === 0}
				class="flex-1"
			>
				<FolderPlus class="mr-2 h-4 w-4" />
				{hasConditions ? 'Add condition group' : 'Add conditions'}
			</Button>
		</div>

		{#if moduleFields.length === 0}
			<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
				<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
				<p class="text-sm text-muted-foreground">
					Select a module first to configure conditions based on its fields.
				</p>
			</div>
		{:else if !hasConditions}
			<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
				<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
				<p class="text-sm text-muted-foreground">
					Without conditions, this workflow will run for all records that match the trigger.
				</p>
			</div>
		{/if}
	</Card.Content>
</Card.Root>
