<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Card, CardContent, CardHeader, CardTitle } from '$lib/components/ui/card';
	import {
		TextField,
		TextareaField,
		SelectField,
		DateField,
		DateTimeField,
		TimeField,
		CurrencyField,
		PercentField,
		CheckboxField,
		LookupField,
		RadioField,
		SwitchField,
		MultiselectField,
		FileField,
		ImageField,
		FormulaField
	} from '$lib/components/form';
	import type { Module, FormulaDefinition } from '$lib/api/modules';
	import type { ModuleRecord } from '$lib/types/modules';
	import { Loader2 } from 'lucide-svelte';
	import Block from '$lib/components/form/Block.svelte';
	import { evaluateFormula, type FormulaDefinition as FormulaDefCalc } from '$lib/utils/formulaCalculator';

	interface Props {
		module: Module;
		initialData?: ModuleRecord;
		onSubmit: (data: Record<string, any>) => void;
		onCancel: () => void;
		isSubmitting?: boolean;
		errors?: Record<string, string>;
	}

	let {
		module,
		initialData,
		onSubmit,
		onCancel,
		isSubmitting = false,
		errors = {}
	}: Props = $props();

	// Initialize form data with default values for all fields
	const initializeFormData = () => {
		const data: Record<string, any> = { ...(initialData?.data || {}) };

		// Ensure all fields have at least an empty string value to avoid undefined
		module.blocks?.forEach((block) => {
			block.fields?.forEach((field) => {
				if (!(field.api_name in data)) {
					// Set default values based on field type
					if (field.type === 'checkbox' || field.type === 'toggle') {
						data[field.api_name] = false;
					} else if (field.type === 'multiselect') {
						data[field.api_name] = [];
					} else if (
						field.type === 'number' ||
						field.type === 'decimal' ||
						field.type === 'currency' ||
						field.type === 'percent'
					) {
						data[field.api_name] = '';
					} else {
						data[field.api_name] = '';
					}
				}
			});
		});

		return data;
	};

	let formData = $state(initializeFormData());

	function handleSubmit(e: Event) {
		e.preventDefault();
		onSubmit(formData);
	}

	function getFieldType(fieldType: string): string {
		switch (fieldType) {
			case 'email':
				return 'email';
			case 'phone':
				return 'tel';
			case 'url':
				return 'url';
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return 'number';
			case 'date':
				return 'date';
			case 'datetime':
				return 'datetime-local';
			case 'time':
				return 'time';
			default:
				return 'text';
		}
	}
</script>

<form onsubmit={handleSubmit}>
	<div class="space-y-6">
		<!-- Blocks -->
		{#each module.blocks || [] as block (block.id)}
			<Block {block}>
				{#each block.fields as field (field.id)}
					{@const fieldType = getFieldType(field.type)}

					{#if field.type === 'select'}
						{@const isPipelineField = field.options?.some((opt) => opt.metadata?.pipeline_id)}
						<SelectField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							showColors={isPipelineField}
							options={field.options?.map((opt) => ({
								label: opt.label,
								value: opt.value,
								color: opt.metadata?.color as string | undefined
							})) || []}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'radio'}
						<RadioField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							options={field.options?.map((opt) => ({
								label: opt.label,
								value: opt.value
							})) || []}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'multiselect'}
						<MultiselectField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							options={field.options?.map((opt) => ({
								label: opt.label,
								value: opt.value
							})) || []}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'checkbox'}
						<CheckboxField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'toggle'}
						<SwitchField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'date'}
						<DateField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'datetime'}
						<DateTimeField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'time'}
						<TimeField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'currency'}
						<CurrencyField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							placeholder={field.default_value || '0.00'}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'percent'}
						<PercentField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							placeholder={field.default_value || '0'}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'lookup'}
						<LookupField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							relationshipId={field.settings?.related_module_id ?? 0}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'file'}
						<FileField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'image'}
						<ImageField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'textarea' || field.type === 'rich_text'}
						<TextareaField
							label={field.label}
							name={field.api_name}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							placeholder={field.default_value || ''}
							bind:value={formData[field.api_name]}
						/>
					{:else if field.type === 'formula'}
						{@const formulaResult = field.formula_definition
							? evaluateFormula(field.formula_definition as FormulaDefCalc, { data: formData })
							: { success: false, value: null }}
						<FormulaField
							label={field.label}
							name={field.api_name}
							value={formulaResult.success ? formulaResult.value : formData[field.api_name]}
							description={field.help_text || undefined}
							error={formulaResult.success ? undefined : formulaResult.error}
							returnType={field.formula_definition?.return_type as any || 'text'}
						/>
					{:else}
						<TextField
							label={field.label}
							name={field.api_name}
							type={fieldType as 'text' | 'email' | 'tel' | 'url' | 'password'}
							required={field.is_required}
							description={field.help_text ?? undefined}
							error={errors[field.api_name]}
							placeholder={field.default_value || ''}
							bind:value={formData[field.api_name]}
						/>
					{/if}
				{/each}
			</Block>
		{/each}

		<!-- Form Actions -->
		<div class="flex items-center justify-end gap-4">
			<Button type="button" variant="outline" onclick={onCancel} disabled={isSubmitting}>
				Cancel
			</Button>
			<Button type="submit" disabled={isSubmitting}>
				{#if isSubmitting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Saving...
				{:else}
					Save {module.name.replace(/s$/, '')}
				{/if}
			</Button>
		</div>
	</div>
</form>
