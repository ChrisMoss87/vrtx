<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { Card, CardContent, CardHeader, CardTitle } from '$lib/components/ui/card';
	import Plus from 'lucide-svelte/icons/plus';
	import Trash2 from 'lucide-svelte/icons/trash-2';
	import X from 'lucide-svelte/icons/x';

	interface Condition {
		field: string;
		operator: string;
		value: unknown;
	}

	interface ConditionGroup {
		logic: 'and' | 'or';
		conditions: Condition[];
	}

	interface Props {
		conditions: ConditionGroup[];
		logic?: 'and' | 'or';
		fields?: Array<{ name: string; label: string; type: string }>;
		onChange?: (conditions: ConditionGroup[], logic: 'and' | 'or') => void;
	}

	let { conditions = $bindable([]), logic = $bindable('and'), fields = [], onChange }: Props =
		$props();

	// Operators aligned with types/modules.ts ConditionOperator type
	const operators = [
		{ value: 'equals', label: 'Equals' },
		{ value: 'not_equals', label: 'Not Equals' },
		{ value: 'contains', label: 'Contains' },
		{ value: 'not_contains', label: 'Does Not Contain' },
		{ value: 'starts_with', label: 'Starts With' },
		{ value: 'ends_with', label: 'Ends With' },
		{ value: 'greater_than', label: 'Greater Than' },
		{ value: 'less_than', label: 'Less Than' },
		{ value: 'greater_than_or_equal', label: 'Greater Than or Equal' },
		{ value: 'less_than_or_equal', label: 'Less Than or Equal' },
		{ value: 'between', label: 'Between' },
		{ value: 'in', label: 'In List' },
		{ value: 'not_in', label: 'Not In List' },
		{ value: 'is_empty', label: 'Is Empty' },
		{ value: 'is_not_empty', label: 'Is Not Empty' },
		{ value: 'is_checked', label: 'Is Checked' },
		{ value: 'is_not_checked', label: 'Is Not Checked' }
	];

	function addGroup() {
		conditions = [...conditions, { logic: 'and', conditions: [{ field: '', operator: 'equals', value: '' }] }];
		notifyChange();
	}

	function removeGroup(groupIndex: number) {
		conditions = conditions.filter((_, i) => i !== groupIndex);
		notifyChange();
	}

	function addCondition(groupIndex: number) {
		conditions = conditions.map((group, i) => {
			if (i === groupIndex) {
				return {
					...group,
					conditions: [...group.conditions, { field: '', operator: 'equals', value: '' }]
				};
			}
			return group;
		});
		notifyChange();
	}

	function removeCondition(groupIndex: number, condIndex: number) {
		conditions = conditions.map((group, i) => {
			if (i === groupIndex) {
				return {
					...group,
					conditions: group.conditions.filter((_, j) => j !== condIndex)
				};
			}
			return group;
		});
		notifyChange();
	}

	function updateCondition(
		groupIndex: number,
		condIndex: number,
		field: keyof Condition,
		value: unknown
	) {
		conditions = conditions.map((group, i) => {
			if (i === groupIndex) {
				return {
					...group,
					conditions: group.conditions.map((cond, j) => {
						if (j === condIndex) {
							return { ...cond, [field]: value };
						}
						return cond;
					})
				};
			}
			return group;
		});
		notifyChange();
	}

	function updateGroupLogic(groupIndex: number, newLogic: 'and' | 'or') {
		conditions = conditions.map((group, i) => {
			if (i === groupIndex) {
				return { ...group, logic: newLogic };
			}
			return group;
		});
		notifyChange();
	}

	function notifyChange() {
		onChange?.(conditions, logic);
	}

	function getFieldLabel(fieldName: string): string {
		const field = fields.find((f) => f.name === fieldName);
		return field?.label || fieldName || 'Select field';
	}
</script>

<div class="space-y-4">
	<!-- Main logic selector -->
	{#if conditions.length > 1}
		<div class="flex items-center gap-2">
			<Label>Match</Label>
			<Select.Root
				type="single"
				value={logic}
				onValueChange={(v) => {
					if (v === 'and' || v === 'or') {
						logic = v;
						notifyChange();
					}
				}}
			>
				<Select.Trigger class="w-24">
					{logic === 'and' ? 'All' : 'Any'}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="and">All (AND)</Select.Item>
					<Select.Item value="or">Any (OR)</Select.Item>
				</Select.Content>
			</Select.Root>
			<span class="text-sm text-muted-foreground">of the following groups</span>
		</div>
	{/if}

	{#if conditions.length === 0}
		<div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed py-6">
			<p class="mb-2 text-muted-foreground">No conditions defined</p>
			<p class="mb-4 text-sm text-muted-foreground">
				The workflow will run for all records that match the trigger.
			</p>
			<Button type="button" variant="outline" size="sm" onclick={addGroup}>
				<Plus class="mr-2 h-4 w-4" />
				Add Condition Group
			</Button>
		</div>
	{:else}
		<div class="space-y-4">
			{#each conditions as group, groupIndex}
				<Card>
					<CardHeader class="pb-3">
						<div class="flex items-center justify-between">
							<div class="flex items-center gap-2">
								<CardTitle class="text-sm">Group {groupIndex + 1}</CardTitle>
								{#if group.conditions.length > 1}
									<Select.Root
										type="single"
										value={group.logic}
										onValueChange={(v) => {
											if (v === 'and' || v === 'or') {
												updateGroupLogic(groupIndex, v);
											}
										}}
									>
										<Select.Trigger class="h-7 w-20 text-xs">
											{group.logic === 'and' ? 'AND' : 'OR'}
										</Select.Trigger>
										<Select.Content>
											<Select.Item value="and">AND</Select.Item>
											<Select.Item value="or">OR</Select.Item>
										</Select.Content>
									</Select.Root>
								{/if}
							</div>
							<Button
								type="button"
								variant="ghost"
								size="icon"
								class="h-7 w-7 text-destructive"
								onclick={() => removeGroup(groupIndex)}
							>
								<X class="h-4 w-4" />
							</Button>
						</div>
					</CardHeader>
					<CardContent class="space-y-3">
						{#each group.conditions as cond, condIndex}
							<div class="flex items-center gap-2">
								<!-- Field selector -->
								<div class="flex-1">
									{#if fields.length > 0}
										<Select.Root
											type="single"
											value={cond.field}
											onValueChange={(v) => {
												if (v) updateCondition(groupIndex, condIndex, 'field', v);
											}}
										>
											<Select.Trigger class="w-full">
												{getFieldLabel(cond.field)}
											</Select.Trigger>
											<Select.Content>
												{#each fields as field}
													<Select.Item value={field.name}>{field.label}</Select.Item>
												{/each}
											</Select.Content>
										</Select.Root>
									{:else}
										<Input
											value={cond.field}
											oninput={(e) =>
												updateCondition(groupIndex, condIndex, 'field', e.currentTarget.value)}
											placeholder="Field name (e.g., status, email)"
										/>
									{/if}
								</div>

								<!-- Operator selector -->
								<Select.Root
									type="single"
									value={cond.operator}
									onValueChange={(v) => {
										if (v) updateCondition(groupIndex, condIndex, 'operator', v);
									}}
								>
									<Select.Trigger class="w-36">
										{operators.find((o) => o.value === cond.operator)?.label || 'Select'}
									</Select.Trigger>
									<Select.Content>
										{#each operators as op}
											<Select.Item value={op.value}>{op.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>

								<!-- Value input (hidden for is_empty/is_not_empty) -->
								{#if !['is_empty', 'is_not_empty'].includes(cond.operator)}
									<div class="flex-1">
										<Input
											value={String(cond.value || '')}
											oninput={(e) =>
												updateCondition(groupIndex, condIndex, 'value', e.currentTarget.value)}
											placeholder="Value"
										/>
									</div>
								{/if}

								<Button
									type="button"
									variant="ghost"
									size="icon"
									class="h-9 w-9 shrink-0 text-muted-foreground hover:text-destructive"
									onclick={() => removeCondition(groupIndex, condIndex)}
								>
									<Trash2 class="h-4 w-4" />
								</Button>
							</div>

							{#if condIndex < group.conditions.length - 1}
								<div class="flex justify-center">
									<span class="rounded-full bg-muted px-3 py-0.5 text-xs font-medium text-muted-foreground">
										{group.logic.toUpperCase()}
									</span>
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
							<Plus class="mr-2 h-4 w-4" />
							Add Condition
						</Button>
					</CardContent>
				</Card>

				{#if groupIndex < conditions.length - 1}
					<div class="flex justify-center">
						<span class="rounded-full bg-primary/10 px-4 py-1 text-sm font-medium text-primary">
							{logic.toUpperCase()}
						</span>
					</div>
				{/if}
			{/each}
		</div>

		<Button type="button" variant="outline" size="sm" onclick={addGroup}>
			<Plus class="mr-2 h-4 w-4" />
			Add Condition Group
		</Button>
	{/if}
</div>
