<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, Filter } from 'lucide-svelte';
	import type { ModuleField } from '$lib/api/reports';
	import type { FilterConfig, FilterOperator, FilterValue } from '$lib/types/filters';

	interface Props {
		fields: ModuleField[];
		filters: FilterConfig[];
	}

	let { fields, filters = $bindable([]) }: Props = $props();

	const operators: { value: FilterOperator; label: string; types: string[] }[] = [
		{ value: 'equals', label: 'Equals', types: ['all'] },
		{ value: 'not_equals', label: 'Not Equals', types: ['all'] },
		{ value: 'contains', label: 'Contains', types: ['text', 'textarea', 'email'] },
		{ value: 'not_contains', label: 'Does Not Contain', types: ['text', 'textarea', 'email'] },
		{ value: 'starts_with', label: 'Starts With', types: ['text', 'textarea', 'email'] },
		{ value: 'ends_with', label: 'Ends With', types: ['text', 'textarea', 'email'] },
		{ value: 'greater_than', label: 'Greater Than', types: ['number', 'decimal', 'currency', 'percent', 'date', 'datetime'] },
		{ value: 'less_than', label: 'Less Than', types: ['number', 'decimal', 'currency', 'percent', 'date', 'datetime'] },
		{ value: 'greater_than_or_equal', label: '≥', types: ['number', 'decimal', 'currency', 'percent', 'date', 'datetime'] },
		{ value: 'less_than_or_equal', label: '≤', types: ['number', 'decimal', 'currency', 'percent', 'date', 'datetime'] },
		{ value: 'between', label: 'Between', types: ['number', 'decimal', 'currency', 'percent', 'date', 'datetime'] },
		{ value: 'in', label: 'In List', types: ['select', 'multiselect', 'radio'] },
		{ value: 'not_in', label: 'Not In List', types: ['select', 'multiselect', 'radio'] },
		{ value: 'is_empty', label: 'Is Empty', types: ['all'] },
		{ value: 'is_not_empty', label: 'Is Not Empty', types: ['all'] }
	];

	function getOperatorsForField(field: ModuleField) {
		return operators.filter(op =>
			op.types.includes('all') || op.types.includes(field.type)
		);
	}

	function addFilter() {
		if (fields.length === 0) return;
		const firstField = fields[0];
		const availableOperators = getOperatorsForField(firstField);
		filters = [...filters, {
			field: firstField.name,
			operator: availableOperators[0]?.value ?? 'equals',
			value: ''
		}];
	}

	function removeFilter(index: number) {
		filters = filters.filter((_, i) => i !== index);
	}

	function updateFilter(index: number, updates: Partial<FilterConfig>) {
		filters = filters.map((f, i) => i === index ? { ...f, ...updates } : f);
	}

	function getField(fieldName: string): ModuleField | undefined {
		return fields.find(f => f.name === fieldName);
	}

	function getInputType(field: ModuleField | undefined): string {
		if (!field) return 'text';
		switch (field.type) {
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return 'number';
			case 'date':
				return 'date';
			case 'datetime':
				return 'datetime-local';
			default:
				return 'text';
		}
	}

	function needsValueInput(operator: string): boolean {
		return !['is_empty', 'is_not_empty'].includes(operator);
	}
</script>

<Card.Root>
	<Card.Header>
		<Card.Title class="flex items-center justify-between text-base">
			<span class="flex items-center gap-2">
				<Filter class="h-4 w-4" />
				Filters
			</span>
			<Button variant="outline" size="sm" onclick={addFilter} disabled={fields.length === 0}>
				<Plus class="mr-1 h-3 w-3" />
				Add Filter
			</Button>
		</Card.Title>
		<Card.Description>
			Filter records based on field values. All filters are combined with AND logic.
		</Card.Description>
	</Card.Header>
	<Card.Content>
		{#if filters.length === 0}
			<div class="flex flex-col items-center justify-center py-8 text-center">
				<Filter class="h-8 w-8 text-muted-foreground mb-2" />
				<p class="text-sm text-muted-foreground">No filters configured</p>
				<p class="text-xs text-muted-foreground mt-1">
					Add filters to narrow down your report results
				</p>
			</div>
		{:else}
			<div class="space-y-3">
				{#each filters as filter, index}
					{@const field = getField(filter.field)}
					{@const availableOperators = field ? getOperatorsForField(field) : operators}

					<div class="flex flex-wrap items-center gap-2 rounded-lg border p-3">
						<Badge variant="secondary" class="flex-shrink-0">{index + 1}</Badge>

						<!-- Field Select -->
						<Select.Root
							type="single"
							value={filter.field}
							onValueChange={(v) => {
								if (v) {
									const newField = getField(v);
									const newOperators = newField ? getOperatorsForField(newField) : operators;
									updateFilter(index, {
										field: v,
										operator: newOperators[0]?.value ?? 'equals',
										value: ''
									});
								}
							}}
						>
							<Select.Trigger class="w-40">
								<span>{field?.label || filter.field}</span>
							</Select.Trigger>
							<Select.Content>
								{#each fields as f}
									<Select.Item value={f.name}>{f.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>

						<!-- Operator Select -->
						<Select.Root
							type="single"
							value={filter.operator}
							onValueChange={(v) => {
								if (v) {
									updateFilter(index, { operator: v as FilterOperator });
								}
							}}
						>
							<Select.Trigger class="w-36">
								<span>{availableOperators.find(o => o.value === filter.operator)?.label || filter.operator}</span>
							</Select.Trigger>
							<Select.Content>
								{#each availableOperators as op}
									<Select.Item value={op.value}>{op.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>

						<!-- Value Input -->
						{#if needsValueInput(filter.operator)}
							{#if field?.options && field.options.length > 0}
								<!-- Select field with options -->
								<Select.Root
									type="single"
									value={String(filter.value)}
									onValueChange={(v) => {
										if (v !== undefined) {
											updateFilter(index, { value: v });
										}
									}}
								>
									<Select.Trigger class="min-w-32 flex-1">
										<span>
											{field.options.find(o => o.value === filter.value)?.label || filter.value || 'Select value'}
										</span>
									</Select.Trigger>
									<Select.Content>
										{#each field.options as option}
											<Select.Item value={option.value}>{option.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							{:else if filter.operator === 'between'}
								<!-- Between operator needs two values -->
								<div class="flex items-center gap-1">
									<Input
										type={getInputType(field)}
										placeholder="From"
										value={Array.isArray(filter.value) ? String(filter.value[0]) : ''}
										oninput={(e) => {
											const currentValue = filter.value;
											const newValue: [string, string] = Array.isArray(currentValue)
												? [String(currentValue[0] ?? ''), String(currentValue[1] ?? '')]
												: ['', ''];
											newValue[0] = e.currentTarget.value;
											updateFilter(index, { value: { from: newValue[0], to: newValue[1] } });
										}}
										class="w-24"
									/>
									<span class="text-muted-foreground">and</span>
									<Input
										type={getInputType(field)}
										placeholder="To"
										value={Array.isArray(filter.value) ? String(filter.value[1]) : ''}
										oninput={(e) => {
											const currentValue = filter.value;
											const newValue: [string, string] = Array.isArray(currentValue)
												? [String(currentValue[0] ?? ''), String(currentValue[1] ?? '')]
												: ['', ''];
											newValue[1] = e.currentTarget.value;
											updateFilter(index, { value: { from: newValue[0], to: newValue[1] } });
										}}
										class="w-24"
									/>
								</div>
							{:else}
								<!-- Regular input -->
								<Input
									type={getInputType(field)}
									placeholder="Enter value"
									value={filter.value}
									oninput={(e) => updateFilter(index, { value: e.currentTarget.value })}
									class="min-w-32 flex-1"
								/>
							{/if}
						{/if}

						<!-- Remove Button -->
						<Button
							variant="ghost"
							size="icon"
							class="ml-auto flex-shrink-0"
							onclick={() => removeFilter(index)}
						>
							<Trash2 class="h-4 w-4" />
						</Button>
					</div>
				{/each}
			</div>
		{/if}
	</Card.Content>
</Card.Root>
