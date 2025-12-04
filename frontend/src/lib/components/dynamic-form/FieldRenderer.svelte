<script lang="ts">
	import type { Field } from '$lib/api/modules';
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';

	// Field components (we'll create these next)
	import TextField from './fields/TextField.svelte';
	import TextareaField from './fields/TextareaField.svelte';
	import EmailField from './fields/EmailField.svelte';
	import PhoneField from './fields/PhoneField.svelte';
	import UrlField from './fields/UrlField.svelte';
	import NumberField from './fields/NumberField.svelte';
	import DecimalField from './fields/DecimalField.svelte';
	import CurrencyField from './fields/CurrencyField.svelte';
	import PercentField from './fields/PercentField.svelte';
	import DateField from './fields/DateField.svelte';
	import DateTimeField from './fields/DateTimeField.svelte';
	import TimeField from './fields/TimeField.svelte';
	import SelectField from './fields/SelectField.svelte';
	import MultiselectField from './fields/MultiselectField.svelte';
	import RadioField from './fields/RadioField.svelte';
	import CheckboxField from './fields/CheckboxField.svelte';
	import ToggleField from './fields/ToggleField.svelte';
	import LookupField from './fields/LookupField.svelte';
	import FormulaField from './fields/FormulaField.svelte';
	import FileField from './fields/FileField.svelte';
	import ImageField from './fields/ImageField.svelte';
	import RichTextField from './fields/RichTextField.svelte';
	import ProgressMapperField from './fields/ProgressMapperField.svelte';
	import RatingField from './fields/RatingField.svelte';
	import SignatureField from './fields/SignatureField.svelte';
	import ColorField from './fields/ColorField.svelte';
	import AutoNumberField from './fields/AutoNumberField.svelte';

	interface Props {
		field: Field;
		value?: any;
		error?: string;
		isReadonly: boolean;
		onchange: (value: any) => void;
	}

	let { field, value, error, isReadonly, onchange }: Props = $props();

	// Generate unique IDs for accessibility
	const fieldId = `field-${field.id}`;
	const errorId = `field-${field.id}-error`;
	const descriptionId = `field-${field.id}-description`;
	const helpTextId = `field-${field.id}-help`;

	// Build aria-describedby based on what's present
	const ariaDescribedBy = $derived.by(() => {
		const ids: string[] = [];
		if (field.description) ids.push(descriptionId);
		if (field.help_text) ids.push(helpTextId);
		if (error) ids.push(errorId);
		return ids.length > 0 ? ids.join(' ') : undefined;
	});

	// Get field props to pass down
	const fieldProps = $derived({
		value: value || getDefaultValue(),
		error,
		disabled: isReadonly,
		placeholder: field.placeholder ?? undefined,
		required: field.is_required,
		onchange,
		id: fieldId,
		ariaDescribedBy,
		ariaInvalid: error ? true : undefined
	});

	// Get default value based on field type
	function getDefaultValue() {
		if (field.default_value !== null && field.default_value !== undefined) {
			return field.default_value;
		}

		switch (field.type) {
			case 'checkbox':
			case 'toggle':
				return false;
			case 'multiselect':
				return [];
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
			case 'rating':
				return null;
			case 'color':
				return '#000000';
			default:
				return '';
		}
	}
</script>

<div class="space-y-2">
	<!-- Field Label -->
	<div class="flex items-center gap-2">
		<Label for={fieldId} class="font-medium">
			{field.label}
			{#if field.is_required}
				<span class="ml-1 text-destructive" aria-hidden="true">*</span>
				<span class="sr-only">(required)</span>
			{/if}
		</Label>
		{#if field.type === 'formula'}
			<Badge variant="outline" class="text-xs">Calculated</Badge>
		{/if}
	</div>

	<!-- Field Description -->
	{#if field.description}
		<p id={descriptionId} class="-mt-1 text-xs text-muted-foreground">{field.description}</p>
	{/if}

	<!-- Field Input -->
	<div>
		{#if field.type === 'text'}
			<TextField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'textarea'}
			<TextareaField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'email'}
			<EmailField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'phone'}
			<PhoneField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'url'}
			<UrlField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'rich_text'}
			<RichTextField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'number'}
			<NumberField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'decimal'}
			<DecimalField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'currency'}
			<CurrencyField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'percent'}
			<PercentField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'date'}
			<DateField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'datetime'}
			<DateTimeField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'time'}
			<TimeField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'select'}
			<SelectField {...fieldProps} options={field.options} settings={field.settings} />
		{:else if field.type === 'multiselect'}
			<MultiselectField {...fieldProps} options={field.options} settings={field.settings} />
		{:else if field.type === 'radio'}
			<RadioField {...fieldProps} options={field.options} settings={field.settings} />
		{:else if field.type === 'checkbox'}
			<CheckboxField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'toggle'}
			<ToggleField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'lookup'}
			<LookupField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'formula'}
			<FormulaField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'file'}
			<FileField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'image'}
			<ImageField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'progress_mapper'}
			<ProgressMapperField {...fieldProps} options={field.options} settings={field.settings} />
		{:else if field.type === 'rating'}
			<RatingField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'signature'}
			<SignatureField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'color'}
			<ColorField {...fieldProps} settings={field.settings} />
		{:else if field.type === 'auto_number'}
			<AutoNumberField {...fieldProps} settings={field.settings} />
		{:else}
			<!-- Fallback for unknown field types -->
			<div class="rounded-md border bg-muted/30 p-4 text-center">
				<p class="text-sm text-muted-foreground">
					Unsupported field type: <code>{field.type}</code>
				</p>
			</div>
		{/if}
	</div>

	<!-- Help Text -->
	{#if field.help_text}
		<p id={helpTextId} class="text-xs text-muted-foreground">{field.help_text}</p>
	{/if}

	<!-- Error Message -->
	{#if error}
		<p id={errorId} class="text-xs text-destructive" role="alert">{error}</p>
	{/if}
</div>
