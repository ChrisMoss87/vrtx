<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import * as Tabs from '$lib/components/ui/tabs';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Calculator, Info, AlertCircle, CheckCircle2, Plus } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface FormulaDefinition {
		formula: string;
		formula_type: string;
		return_type: string;
		dependencies: string[];
		recalculate_on: string[];
	}

	interface Props {
		value: FormulaDefinition | null;
		availableFields: Pick<Field, 'api_name' | 'label' | 'type'>[];
		onchange?: (formula: FormulaDefinition | null) => void;
	}

	let { value = $bindable(), availableFields, onchange }: Props = $props();

	// Initialize with default if null
	if (!value) {
		value = {
			formula: '',
			formula_type: 'calculation',
			return_type: 'number',
			dependencies: [],
			recalculate_on: []
		};
	}

	const FORMULA_TYPES = [
		{ value: 'calculation', label: 'Calculation' },
		{ value: 'lookup', label: 'Lookup' },
		{ value: 'date_calculation', label: 'Date Calculation' },
		{ value: 'text_manipulation', label: 'Text Manipulation' },
		{ value: 'conditional', label: 'Conditional' }
	];

	const RETURN_TYPES = [
		{ value: 'number', label: 'Number' },
		{ value: 'text', label: 'Text' },
		{ value: 'date', label: 'Date' },
		{ value: 'currency', label: 'Currency' },
		{ value: 'boolean', label: 'Boolean' }
	];

	const FORMULA_FUNCTIONS = [
		{
			category: 'Math',
			functions: [
				{ name: 'SUM', syntax: 'SUM(value1, value2, ...)', description: 'Add numbers together' },
				{
					name: 'AVERAGE',
					syntax: 'AVERAGE(value1, value2, ...)',
					description: 'Calculate average'
				},
				{ name: 'MIN', syntax: 'MIN(value1, value2, ...)', description: 'Get minimum value' },
				{ name: 'MAX', syntax: 'MAX(value1, value2, ...)', description: 'Get maximum value' },
				{ name: 'ROUND', syntax: 'ROUND(value, decimals)', description: 'Round to decimals' }
			]
		},
		{
			category: 'Logic',
			functions: [
				{
					name: 'IF',
					syntax: 'IF(condition, true_value, false_value)',
					description: 'Conditional logic'
				},
				{
					name: 'AND',
					syntax: 'AND(condition1, condition2, ...)',
					description: 'All conditions true'
				},
				{ name: 'OR', syntax: 'OR(condition1, condition2, ...)', description: 'Any condition true' }
			]
		},
		{
			category: 'Text',
			functions: [
				{ name: 'CONCAT', syntax: 'CONCAT(text1, text2, ...)', description: 'Combine text' },
				{ name: 'UPPER', syntax: 'UPPER(text)', description: 'Convert to uppercase' },
				{ name: 'LOWER', syntax: 'LOWER(text)', description: 'Convert to lowercase' },
				{ name: 'TRIM', syntax: 'TRIM(text)', description: 'Remove whitespace' }
			]
		},
		{
			category: 'Date',
			functions: [
				{ name: 'NOW', syntax: 'NOW()', description: 'Current date and time' },
				{ name: 'TODAY', syntax: 'TODAY()', description: 'Current date' },
				{ name: 'DATE_ADD', syntax: 'DATE_ADD(date, days)', description: 'Add days to date' },
				{ name: 'DATE_DIFF', syntax: 'DATE_DIFF(date1, date2)', description: 'Days between dates' }
			]
		}
	];

	let validationMessage = $state<{ type: 'error' | 'success' | 'info'; message: string } | null>(
		null
	);

	function insertField(fieldApiName: string) {
		if (value) {
			const cursorPosition =
				(document.activeElement as HTMLTextAreaElement)?.selectionStart || value.formula.length;
			const before = value.formula.substring(0, cursorPosition);
			const after = value.formula.substring(cursorPosition);
			value.formula = before + `{${fieldApiName}}` + after;

			// Track dependency
			if (!value.dependencies.includes(fieldApiName)) {
				value.dependencies = [...value.dependencies, fieldApiName];
			}

			onchange?.(value);
		}
	}

	function insertFunction(functionSyntax: string) {
		if (value) {
			const cursorPosition =
				(document.activeElement as HTMLTextAreaElement)?.selectionStart || value.formula.length;
			const before = value.formula.substring(0, cursorPosition);
			const after = value.formula.substring(cursorPosition);
			value.formula = before + functionSyntax + after;
			onchange?.(value);
		}
	}

	function updateFormula(newFormula: string) {
		if (value) {
			value.formula = newFormula;

			// Extract dependencies from formula
			const fieldMatches = newFormula.match(/\{([a-z_][a-z0-9_]*)\}/gi);
			if (fieldMatches) {
				value.dependencies = [...new Set(fieldMatches.map((m) => m.slice(1, -1)))];
			} else {
				value.dependencies = [];
			}

			validateFormula(newFormula);
			onchange?.(value);
		}
	}

	function validateFormula(formula: string) {
		if (!formula.trim()) {
			validationMessage = null;
			return;
		}

		// Basic validation
		const openBraces = (formula.match(/\{/g) || []).length;
		const closeBraces = (formula.match(/\}/g) || []).length;
		const openParens = (formula.match(/\(/g) || []).length;
		const closeParens = (formula.match(/\)/g) || []).length;

		if (openBraces !== closeBraces) {
			validationMessage = { type: 'error', message: 'Unbalanced braces {}' };
			return;
		}

		if (openParens !== closeParens) {
			validationMessage = { type: 'error', message: 'Unbalanced parentheses ()' };
			return;
		}

		// Check for valid field references
		const fieldMatches = formula.match(/\{([a-z_][a-z0-9_]*)\}/gi);
		if (fieldMatches) {
			const invalidFields = fieldMatches
				.map((m) => m.slice(1, -1))
				.filter((field) => !availableFields.some((f) => f.api_name === field));

			if (invalidFields.length > 0) {
				validationMessage = {
					type: 'error',
					message: `Unknown fields: ${invalidFields.join(', ')}`
				};
				return;
			}
		}

		validationMessage = { type: 'success', message: 'Formula looks good!' };
	}

	function updateReturnType(type: string) {
		if (value) {
			value.return_type = type;
			onchange?.(value);
		}
	}

	function updateFormulaType(type: string) {
		if (value) {
			value.formula_type = type;
			onchange?.(value);
		}
	}
</script>

<Card.Root>
	<Card.CardHeader>
		<Card.CardTitle class="flex items-center gap-2">
			<Calculator class="h-4 w-4" />
			Formula Editor
		</Card.CardTitle>
		<Card.CardDescription>
			Create calculated fields using formulas and functions
		</Card.CardDescription>
	</Card.CardHeader>

	<Card.CardContent class="space-y-4">
		<Tabs.Root value="editor" class="w-full">
			<Tabs.List class="grid w-full grid-cols-2">
				<Tabs.Trigger value="editor">Editor</Tabs.Trigger>
				<Tabs.Trigger value="functions">Functions</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="editor" class="mt-4 space-y-4">
				<!-- Formula Type and Return Type -->
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label>Formula Type</Label>
						<Select.Root
							value={value?.formula_type || 'calculation'}
							onValueChange={(val) => {
								if (val) updateFormulaType(val);
							}}
						>
							<Select.Trigger>
								<span
									>{FORMULA_TYPES.find((t) => t.value === value?.formula_type)?.label ||
										'Calculation'}</span
								>
							</Select.Trigger>
							<Select.Content>
								{#each FORMULA_TYPES as type}
									<Select.Item value={type.value}>{type.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>

					<div class="space-y-2">
						<Label>Return Type</Label>
						<Select.Root
							value={value?.return_type || 'number'}
							onValueChange={(val) => {
								if (val) updateReturnType(val);
							}}
						>
							<Select.Trigger>
								<span
									>{RETURN_TYPES.find((t) => t.value === value?.return_type)?.label ||
										'Number'}</span
								>
							</Select.Trigger>
							<Select.Content>
								{#each RETURN_TYPES as type}
									<Select.Item value={type.value}>{type.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
				</div>

				<!-- Formula Input -->
				<div class="space-y-2">
					<Label>Formula</Label>
					<Textarea
						bind:value={value.formula}
						oninput={(e) => updateFormula(e.currentTarget.value)}
						placeholder="Enter your formula, e.g., {unit_price} * {quantity}"
						class="min-h-[120px] font-mono text-sm"
					/>
					<p class="flex items-center gap-1 text-xs text-muted-foreground">
						<Info class="h-3 w-3" />
						Use {'{'}field_name{'}'} to reference other fields
					</p>
				</div>

				<!-- Validation Message -->
				{#if validationMessage}
					<div
						class={`flex items-start gap-2 rounded-md p-3 text-sm ${
							validationMessage.type === 'error'
								? 'bg-destructive/10 text-destructive'
								: validationMessage.type === 'success'
									? 'bg-green-500/10 text-green-600 dark:text-green-400'
									: 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
						}`}
					>
						{#if validationMessage.type === 'error'}
							<AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
						{:else if validationMessage.type === 'success'}
							<CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
						{:else}
							<Info class="mt-0.5 h-4 w-4 shrink-0" />
						{/if}
						<span>{validationMessage.message}</span>
					</div>
				{/if}

				<!-- Insert Field -->
				<div class="space-y-2">
					<Label>Insert Field</Label>
					<div class="flex flex-wrap gap-2">
						{#each availableFields as field}
							<Button
								type="button"
								variant="outline"
								size="sm"
								onclick={() => insertField(field.api_name)}
								class="text-xs"
							>
								<Plus class="mr-1 h-3 w-3" />
								{field.label}
							</Button>
						{/each}
						{#if availableFields.length === 0}
							<p class="text-sm text-muted-foreground">No fields available yet</p>
						{/if}
					</div>
				</div>

				<!-- Dependencies -->
				{#if value && value.dependencies.length > 0}
					<div class="space-y-2">
						<Label>Dependencies</Label>
						<div class="flex flex-wrap gap-2">
							{#each value.dependencies as dep}
								<Badge variant="secondary">{dep}</Badge>
							{/each}
						</div>
						<p class="text-xs text-muted-foreground">
							Formula will recalculate when these fields change
						</p>
					</div>
				{/if}
			</Tabs.Content>

			<Tabs.Content value="functions" class="mt-4">
				<ScrollArea class="h-[400px] pr-4">
					<div class="space-y-4">
						{#each FORMULA_FUNCTIONS as category}
							<div>
								<h4 class="mb-2 text-sm font-semibold">{category.category}</h4>
								<div class="space-y-2">
									{#each category.functions as func}
										<button
											type="button"
											onclick={() => insertFunction(func.syntax)}
											class="w-full rounded-md border p-3 text-left transition-colors hover:bg-accent"
										>
											<div class="flex items-start justify-between gap-2">
												<div class="min-w-0 flex-1">
													<div class="font-mono text-sm font-medium">{func.syntax}</div>
													<div class="mt-1 text-xs text-muted-foreground">{func.description}</div>
												</div>
												<Plus class="h-4 w-4 shrink-0 text-muted-foreground" />
											</div>
										</button>
									{/each}
								</div>
							</div>
						{/each}
					</div>
				</ScrollArea>
			</Tabs.Content>
		</Tabs.Root>

		<!-- Examples -->
		<div class="space-y-2">
			<Label>Examples</Label>
			<div class="space-y-1.5 text-xs">
				<div class="rounded bg-muted/50 p-2">
					<code class="font-mono">{'SUM({price}, {tax})'}</code>
					<span class="ml-2 text-muted-foreground">- Add two fields</span>
				</div>
				<div class="rounded bg-muted/50 p-2">
					<code class="font-mono">{'IF({quantity} > 10, {price} * 0.9, {price})'}</code>
					<span class="ml-2 text-muted-foreground">- 10% discount for bulk</span>
				</div>
				<div class="rounded bg-muted/50 p-2">
					<code class="font-mono">{'CONCAT({first_name}, " ", {last_name})'}</code>
					<span class="ml-2 text-muted-foreground">- Combine text fields</span>
				</div>
			</div>
		</div>
	</Card.CardContent>
</Card.Root>
