<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { Plus, Trash2, Filter } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { ExportFilter } from '$lib/api/exports';

	interface Props {
		fields: Field[];
		filters: ExportFilter[];
	}

	let {
		fields,
		filters = $bindable()
	}: Props = $props();

	// Get operators based on field type
	function getOperators(fieldType: string): { value: string; label: string }[] {
		const commonOperators = [
			{ value: '=', label: 'Equals' },
			{ value: '!=', label: 'Not equals' },
			{ value: 'is_null', label: 'Is empty' },
			{ value: 'is_not_null', label: 'Is not empty' }
		];

		const textOperators = [
			{ value: 'contains', label: 'Contains' },
			{ value: 'starts_with', label: 'Starts with' },
			{ value: 'ends_with', label: 'Ends with' }
		];

		const numberOperators = [
			{ value: '>', label: 'Greater than' },
			{ value: '>=', label: 'Greater than or equal' },
			{ value: '<', label: 'Less than' },
			{ value: '<=', label: 'Less than or equal' }
		];

		switch (fieldType) {
			case 'text':
			case 'textarea':
			case 'email':
			case 'url':
			case 'phone':
				return [...commonOperators, ...textOperators];
			case 'number':
			case 'integer':
			case 'currency':
			case 'percent':
			case 'date':
			case 'datetime':
				return [...commonOperators, ...numberOperators];
			case 'select':
			case 'radio':
				return [
					...commonOperators,
					{ value: 'in', label: 'Is one of' },
					{ value: 'not_in', label: 'Is not one of' }
				];
			case 'boolean':
			case 'switch':
				return [
					{ value: '=', label: 'Is' }
				];
			default:
				return commonOperators;
		}
	}

	function addFilter() {
		const firstField = fields[0];
		filters = [
			...filters,
			{
				field: firstField?.api_name || '',
				operator: '=',
				value: ''
			}
		];
	}

	function removeFilter(index: number) {
		filters = filters.filter((_, i) => i !== index);
	}

	function updateFilter(index: number, updates: Partial<ExportFilter>) {
		filters = filters.map((f, i) =>
			i === index ? { ...f, ...updates } : f
		);
	}

	function getFieldByApiName(apiName: string): Field | undefined {
		return fields.find((f) => f.api_name === apiName);
	}

	function needsValueInput(operator: string): boolean {
		return !['is_null', 'is_not_null'].includes(operator);
	}

	function renderValueInput(filter: ExportFilter, index: number) {
		const field = getFieldByApiName(filter.field);
		if (!field) return;

		switch (field.type) {
			case 'boolean':
			case 'switch':
				return 'select-boolean';
			case 'select':
			case 'radio':
				return 'select-options';
			case 'date':
				return 'date';
			case 'datetime':
				return 'datetime-local';
			case 'number':
			case 'integer':
			case 'currency':
			case 'percent':
				return 'number';
			default:
				return 'text';
		}
	}
</script>

<div class="space-y-4">
	{#if filters.length === 0}
		<div class="flex flex-col items-center justify-center py-8 text-center border rounded-lg border-dashed">
			<Filter class="h-8 w-8 text-muted-foreground mb-3" />
			<p class="text-sm text-muted-foreground mb-4">
				No filters applied. All records will be exported.
			</p>
			<Button variant="outline" onclick={addFilter}>
				<Plus class="mr-2 h-4 w-4" />
				Add Filter
			</Button>
		</div>
	{:else}
		<div class="space-y-3">
			{#each filters as filter, index}
				{@const field = getFieldByApiName(filter.field)}
				{@const operators = getOperators(field?.type || 'text')}
				{@const inputType = renderValueInput(filter, index)}
				<div class="flex items-start gap-2 p-3 border rounded-lg bg-muted/30">
					<div class="grid gap-2 flex-1" style="grid-template-columns: 1fr 1fr 1fr">
						<!-- Field Select -->
						<Select.Root
							type="single"
							value={filter.field}
							onValueChange={(v) => {
								if (v) {
									const newField = getFieldByApiName(v);
									const newOperators = getOperators(newField?.type || 'text');
									updateFilter(index, {
										field: v,
										operator: newOperators[0]?.value || '=',
										value: ''
									});
								}
							}}
						>
							<Select.Trigger>
								{field?.label || 'Select field'}
							</Select.Trigger>
							<Select.Content>
								{#each fields as f}
									<Select.Item value={f.api_name}>{f.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>

						<!-- Operator Select -->
						<Select.Root
							type="single"
							value={filter.operator}
							onValueChange={(v) => {
								if (v) updateFilter(index, { operator: v });
							}}
						>
							<Select.Trigger>
								{operators.find((o) => o.value === filter.operator)?.label || 'Select'}
							</Select.Trigger>
							<Select.Content>
								{#each operators as op}
									<Select.Item value={op.value}>{op.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>

						<!-- Value Input -->
						{#if needsValueInput(filter.operator)}
							{#if inputType === 'select-boolean'}
								<Select.Root
									type="single"
									value={String(filter.value)}
									onValueChange={(v) => {
										if (v !== undefined) updateFilter(index, { value: v === 'true' });
									}}
								>
									<Select.Trigger>
										{filter.value === true ? 'Yes' : filter.value === false ? 'No' : 'Select'}
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="true">Yes</Select.Item>
										<Select.Item value="false">No</Select.Item>
									</Select.Content>
								</Select.Root>
							{:else if inputType === 'select-options' && field?.options}
								<Select.Root
									type="single"
									value={String(filter.value)}
									onValueChange={(v) => {
										if (v) updateFilter(index, { value: v });
									}}
								>
									<Select.Trigger>
										{field.options.find((o) => o.value === filter.value)?.label || 'Select'}
									</Select.Trigger>
									<Select.Content>
										{#each field.options as opt}
											<Select.Item value={opt.value}>{opt.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							{:else}
								<Input
									type={inputType}
									value={String(filter.value || '')}
									oninput={(e) => {
										const target = e.target as HTMLInputElement;
										let value: string | number = target.value;
										if (inputType === 'number') {
											value = target.valueAsNumber;
										}
										updateFilter(index, { value });
									}}
									placeholder="Value"
								/>
							{/if}
						{:else}
							<div class="flex items-center text-sm text-muted-foreground">
								(no value needed)
							</div>
						{/if}
					</div>

					<Button
						variant="ghost"
						size="icon"
						class="text-muted-foreground hover:text-destructive"
						onclick={() => removeFilter(index)}
					>
						<Trash2 class="h-4 w-4" />
					</Button>
				</div>
			{/each}
		</div>

		<Button variant="outline" size="sm" onclick={addFilter}>
			<Plus class="mr-2 h-4 w-4" />
			Add Filter
		</Button>
	{/if}

	<p class="text-xs text-muted-foreground">
		Filters are combined with AND logic. Only records matching all filters will be exported.
	</p>
</div>
