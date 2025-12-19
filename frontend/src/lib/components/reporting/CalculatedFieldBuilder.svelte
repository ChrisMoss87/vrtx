<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, AlertCircle, Check, Code, Calculator } from 'lucide-svelte';
	import { advancedReportsApi, type CalculatedField, type CrossObjectField } from '$lib/api/reports';

	interface Props {
		calculatedFields?: CalculatedField[];
		availableFields?: CrossObjectField[];
		onFieldsChange?: (fields: CalculatedField[]) => void;
	}

	let {
		calculatedFields = $bindable([]),
		availableFields = [],
		onFieldsChange
	}: Props = $props();

	let validationResults = $state<
		Record<string, { valid: boolean; errors: string[]; dependencies: string[] }>
	>({});
	let validating = $state<string | null>(null);

	const resultTypes = [
		{ value: 'number', label: 'Number' },
		{ value: 'string', label: 'Text' },
		{ value: 'date', label: 'Date' },
		{ value: 'boolean', label: 'Boolean' }
	];

	const formulaExamples = [
		{ label: 'Sum', formula: '{amount} + {tax}', description: 'Add two fields' },
		{ label: 'Percentage', formula: '({won} / {total}) * 100', description: 'Calculate percentage' },
		{
			label: 'Days Between',
			formula: 'DATEDIFF({closed_at}, {created_at})',
			description: 'Days between dates'
		},
		{
			label: 'Conditional',
			formula: "IF({status} = 'won', {amount}, 0)",
			description: 'Conditional value'
		},
		{
			label: 'Profit Margin',
			formula: '({revenue} - {cost}) / {revenue} * 100',
			description: 'Calculate margin'
		}
	];

	function addField() {
		const newField: CalculatedField = {
			name: `calc_${Date.now()}`,
			formula: '',
			label: 'New Calculated Field',
			result_type: 'number',
			precision: 2
		};
		calculatedFields = [...calculatedFields, newField];
		onFieldsChange?.(calculatedFields);
	}

	function removeField(index: number) {
		const name = calculatedFields[index].name;
		calculatedFields = calculatedFields.filter((_, i) => i !== index);
		delete validationResults[name];
		onFieldsChange?.(calculatedFields);
	}

	function updateField(index: number, updates: Partial<CalculatedField>) {
		calculatedFields = calculatedFields.map((field, i) =>
			i === index ? { ...field, ...updates } : field
		);
		onFieldsChange?.(calculatedFields);
	}

	async function validateField(index: number) {
		const field = calculatedFields[index];
		if (!field.formula) return;

		validating = field.name;
		try {
			const result = await advancedReportsApi.validateFormula(field.formula, field.name);
			validationResults = {
				...validationResults,
				[field.name]: {
					valid: result.valid,
					errors: result.errors,
					dependencies: result.dependencies
				}
			};
		} catch (error) {
			validationResults = {
				...validationResults,
				[field.name]: {
					valid: false,
					errors: ['Failed to validate formula'],
					dependencies: []
				}
			};
		} finally {
			validating = null;
		}
	}

	function insertFieldReference(index: number, fieldName: string) {
		const field = calculatedFields[index];
		const newFormula = field.formula + `{${fieldName}}`;
		updateField(index, { formula: newFormula });
	}

	function applyExample(index: number, formula: string) {
		updateField(index, { formula });
	}
</script>

<div class="space-y-4">
	<div class="flex items-center justify-between">
		<Label class="text-base font-medium">Calculated Fields</Label>
		<Button variant="outline" size="sm" onclick={addField}>
			<Plus class="mr-1 h-4 w-4" />
			Add Field
		</Button>
	</div>

	{#if calculatedFields.length === 0}
		<div class="rounded-lg border border-dashed p-6 text-center">
			<Calculator class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
			<p class="text-sm text-muted-foreground">No calculated fields yet</p>
			<p class="text-xs text-muted-foreground">
				Create custom formulas to derive new values from your data
			</p>
		</div>
	{:else}
		<div class="space-y-4">
			{#each calculatedFields as field, index (field.name)}
				{@const validation = validationResults[field.name]}
				<div class="rounded-lg border p-4 space-y-4">
					<div class="flex items-start justify-between">
						<div class="flex-1 grid grid-cols-2 gap-4">
							<div class="space-y-2">
								<Label for={`calc-name-${index}`}>Field Name</Label>
								<Input
									id={`calc-name-${index}`}
									value={field.name}
									onchange={(e) => updateField(index, { name: e.currentTarget.value })}
									placeholder="field_name"
								/>
							</div>
							<div class="space-y-2">
								<Label for={`calc-label-${index}`}>Display Label</Label>
								<Input
									id={`calc-label-${index}`}
									value={field.label || ''}
									onchange={(e) => updateField(index, { label: e.currentTarget.value })}
									placeholder="Field Label"
								/>
							</div>
						</div>
						<Button
							variant="ghost"
							size="icon"
							class="ml-2 text-destructive"
							onclick={() => removeField(index)}
						>
							<Trash2 class="h-4 w-4" />
						</Button>
					</div>

					<div class="grid grid-cols-2 gap-4">
						<div class="space-y-2">
							<Label>Result Type</Label>
							<Select.Root
								type="single"
								value={field.result_type || 'number'}
								onValueChange={(v) => v && updateField(index, { result_type: v as CalculatedField['result_type'] })}
							>
								<Select.Trigger>
									<span>{resultTypes.find((t) => t.value === field.result_type)?.label || 'Number'}</span>
								</Select.Trigger>
								<Select.Content>
									{#each resultTypes as type}
										<Select.Item value={type.value}>{type.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
						{#if field.result_type === 'number'}
							<div class="space-y-2">
								<Label for={`calc-precision-${index}`}>Decimal Precision</Label>
								<Input
									id={`calc-precision-${index}`}
									type="number"
									min="0"
									max="10"
									value={field.precision ?? 2}
									onchange={(e) => updateField(index, { precision: parseInt(e.currentTarget.value) })}
								/>
							</div>
						{/if}
					</div>

					<div class="space-y-2">
						<div class="flex items-center justify-between">
							<Label for={`calc-formula-${index}`}>Formula</Label>
							<div class="flex items-center gap-2">
								{#if validation}
									{#if validation.valid}
										<Badge variant="outline" class="text-green-600 border-green-600">
											<Check class="mr-1 h-3 w-3" />
											Valid
										</Badge>
									{:else}
										<Badge variant="outline" class="text-red-600 border-red-600">
											<AlertCircle class="mr-1 h-3 w-3" />
											Invalid
										</Badge>
									{/if}
								{/if}
								<Button
									variant="outline"
									size="sm"
									onclick={() => validateField(index)}
									disabled={validating === field.name || !field.formula}
								>
									{validating === field.name ? 'Validating...' : 'Validate'}
								</Button>
							</div>
						</div>
						<Textarea
							id={`calc-formula-${index}`}
							value={field.formula}
							onchange={(e) => updateField(index, { formula: e.currentTarget.value })}
							placeholder={'e.g., {amount} * 0.1 or DATEDIFF({closed_at}, {created_at})'}
							rows={3}
							class="font-mono text-sm"
						/>
						{#if validation && !validation.valid && validation.errors.length > 0}
							<div class="text-sm text-destructive">
								{#each validation.errors as error}
									<p>{error}</p>
								{/each}
							</div>
						{/if}
						{#if validation && validation.dependencies.length > 0}
							<div class="flex flex-wrap gap-1">
								<span class="text-xs text-muted-foreground">Dependencies:</span>
								{#each validation.dependencies as dep}
									<Badge variant="secondary" class="text-xs">{dep}</Badge>
								{/each}
							</div>
						{/if}
					</div>

					<!-- Quick Insert -->
					{#if availableFields.length > 0}
						<div class="space-y-2">
							<Label class="text-xs text-muted-foreground">Insert Field</Label>
							<div class="flex flex-wrap gap-1">
								{#each availableFields.slice(0, 10) as f}
									<Button
										variant="outline"
										size="sm"
										class="h-6 text-xs"
										onclick={() => insertFieldReference(index, f.qualified_name)}
									>
										{f.label}
									</Button>
								{/each}
								{#if availableFields.length > 10}
									<span class="text-xs text-muted-foreground self-center">
										+{availableFields.length - 10} more
									</span>
								{/if}
							</div>
						</div>
					{/if}

					<!-- Examples -->
					<div class="space-y-2">
						<Label class="text-xs text-muted-foreground">Examples</Label>
						<div class="flex flex-wrap gap-1">
							{#each formulaExamples as example}
								<Button
									variant="ghost"
									size="sm"
									class="h-6 text-xs"
									title={example.description}
									onclick={() => applyExample(index, example.formula)}
								>
									<Code class="mr-1 h-3 w-3" />
									{example.label}
								</Button>
							{/each}
						</div>
					</div>
				</div>
			{/each}
		</div>
	{/if}

	<!-- Help Text -->
	<div class="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
		<p class="font-medium mb-1">Formula Syntax:</p>
		<ul class="list-disc list-inside space-y-1">
			<li>Reference fields with curly braces: <code class="bg-muted px-1">{'{field_name}'}</code></li>
			<li>
				Reference joined fields: <code class="bg-muted px-1">{'{module.field_name}'}</code>
			</li>
			<li>Arithmetic: <code class="bg-muted px-1">+</code>, <code class="bg-muted px-1">-</code>, <code class="bg-muted px-1">*</code>, <code class="bg-muted px-1">/</code></li>
			<li>
				Functions: <code class="bg-muted px-1">IF()</code>, <code class="bg-muted px-1">DATEDIFF()</code>, <code class="bg-muted px-1">ROUND()</code>, <code class="bg-muted px-1">COALESCE()</code>
			</li>
		</ul>
	</div>
</div>
