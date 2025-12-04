<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import { Plus, Trash2, Eye, EyeOff } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface Condition {
		field: string;
		operator: string;
		value: string | number | boolean;
	}

	interface ConditionalVisibility {
		enabled: boolean;
		operator: 'and' | 'or';
		conditions: Condition[];
	}

	interface Props {
		value: ConditionalVisibility | null;
		availableFields: Pick<Field, 'api_name' | 'label' | 'type'>[];
		onchange?: (visibility: ConditionalVisibility | null) => void;
	}

	let { value = $bindable(), availableFields, onchange }: Props = $props();

	// Initialize with default if null
	if (!value) {
		value = {
			enabled: false,
			operator: 'and',
			conditions: []
		};
	}

	const OPERATORS = [
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
		{ value: 'in', label: 'In' },
		{ value: 'not_in', label: 'Not In' },
		{ value: 'is_empty', label: 'Is Empty' },
		{ value: 'is_not_empty', label: 'Is Not Empty' },
		{ value: 'is_checked', label: 'Is Checked' },
		{ value: 'is_not_checked', label: 'Is Not Checked' }
	];

	function toggleEnabled() {
		if (value) {
			value.enabled = !value.enabled;
			onchange?.(value);
		}
	}

	function addCondition() {
		if (value) {
			value.conditions = [
				...value.conditions,
				{
					field: availableFields[0]?.api_name || '',
					operator: 'equals',
					value: ''
				}
			];
			onchange?.(value);
		}
	}

	function removeCondition(index: number) {
		if (value) {
			value.conditions = value.conditions.filter((_, i) => i !== index);
			onchange?.(value);
		}
	}

	function updateCondition(index: number, updates: Partial<Condition>) {
		if (value) {
			value.conditions[index] = { ...value.conditions[index], ...updates };
			value = { ...value }; // Trigger reactivity
			onchange?.(value);
		}
	}

	function setLogicOperator(operator: 'and' | 'or') {
		if (value) {
			value.operator = operator;
			onchange?.(value);
		}
	}
</script>

<Card.Root>
	<Card.CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<Card.CardTitle class="flex items-center gap-2">
					{#if value?.enabled}
						<Eye class="h-4 w-4" />
					{:else}
						<EyeOff class="h-4 w-4 text-muted-foreground" />
					{/if}
					Conditional Visibility
				</Card.CardTitle>
				<Card.CardDescription>
					Show or hide this field based on other field values
				</Card.CardDescription>
			</div>
			<Switch checked={value?.enabled || false} onCheckedChange={toggleEnabled} />
		</div>
	</Card.CardHeader>

	{#if value?.enabled}
		<Card.CardContent class="space-y-4">
			<!-- Logic Operator -->
			{#if value.conditions.length > 1}
				<div class="space-y-2">
					<Label>Logic</Label>
					<div class="flex gap-2">
						<Button
							type="button"
							variant={value.operator === 'and' ? 'default' : 'outline'}
							size="sm"
							onclick={() => setLogicOperator('and')}
							class="flex-1"
						>
							AND (All must match)
						</Button>
						<Button
							type="button"
							variant={value.operator === 'or' ? 'default' : 'outline'}
							size="sm"
							onclick={() => setLogicOperator('or')}
							class="flex-1"
						>
							OR (Any can match)
						</Button>
					</div>
					<p class="text-xs text-muted-foreground">
						{#if value.operator === 'and'}
							Field will show only if <strong>all</strong> conditions are met
						{:else}
							Field will show if <strong>any</strong> condition is met
						{/if}
					</p>
				</div>
			{/if}

			<!-- Conditions -->
			<div class="space-y-3">
				{#each value.conditions as condition, index}
					<div class="flex flex-col gap-3 rounded-lg border bg-muted/30 p-4">
						<div class="flex items-center justify-between">
							<Badge variant="outline">Condition {index + 1}</Badge>
							<Button
								type="button"
								variant="ghost"
								size="icon"
								onclick={() => removeCondition(index)}
								class="h-7 w-7"
							>
								<Trash2 class="h-3.5 w-3.5" />
							</Button>
						</div>

						<div class="grid gap-3">
							<!-- Field -->
							<div class="space-y-1.5">
								<Label class="text-xs">Field</Label>
								<Select.Root
									type="single"
									value={condition.field}
									onValueChange={(value) => {
										if (value) updateCondition(index, { field: value });
									}}
								>
									<Select.Trigger>
										<span
											>{availableFields.find((f) => f.api_name === condition.field)?.label ||
												'Select field'}</span
										>
									</Select.Trigger>
									<Select.Content>
										{#each availableFields as field}
											<Select.Item value={field.api_name}>
												{field.label} ({field.type})
											</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>

							<!-- Operator -->
							<div class="space-y-1.5">
								<Label class="text-xs">Operator</Label>
								<Select.Root
									type="single"
									value={condition.operator}
									onValueChange={(value) => {
										if (value) updateCondition(index, { operator: value });
									}}
								>
									<Select.Trigger>
										<span
											>{OPERATORS.find((o) => o.value === condition.operator)?.label ||
												'Select operator'}</span
										>
									</Select.Trigger>
									<Select.Content>
										{#each OPERATORS as operator}
											<Select.Item value={operator.value}>
												{operator.label}
											</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>

							<!-- Value (only if not is_empty/is_not_empty) -->
							{#if !['is_empty', 'is_not_empty', 'is_checked', 'is_not_checked'].includes(condition.operator)}
								<div class="space-y-1.5">
									<Label class="text-xs">Value</Label>
									<Input
										type="text"
										bind:value={condition.value}
										onchange={() => updateCondition(index, { value: condition.value })}
										placeholder="Enter value"
									/>
									<p class="text-xs text-muted-foreground">
										{#if condition.operator === 'in' || condition.operator === 'not_in'}
											Separate multiple values with commas
										{:else if condition.operator === 'between'}
											Enter two values separated by comma (min,max)
										{/if}
									</p>
								</div>
							{/if}
						</div>
					</div>

					{#if index < value.conditions.length - 1}
						<div class="flex justify-center">
							<Badge variant="secondary" class="text-xs uppercase">
								{value.operator}
							</Badge>
						</div>
					{/if}
				{/each}
			</div>

			<!-- Add Condition Button -->
			<Button
				type="button"
				variant="outline"
				size="sm"
				onclick={addCondition}
				class="w-full"
				disabled={availableFields.length === 0}
			>
				<Plus class="mr-2 h-4 w-4" />
				Add Condition
			</Button>

			{#if availableFields.length === 0}
				<p class="text-center text-xs text-muted-foreground">
					Add more fields to the form first to create conditions
				</p>
			{/if}
		</Card.CardContent>
	{/if}
</Card.Root>
