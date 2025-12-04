<script lang="ts">
	import { writable, derived } from 'svelte/store';
	import type { Module, Field, Block } from '$lib/api/modules';
	import BlockRenderer from './BlockRenderer.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Loader2, Save, X } from 'lucide-svelte';
	import * as Alert from '$lib/components/ui/alert';
	import { getVisibleFieldIds } from '$lib/form-logic/conditionalVisibility';
	import {
		evaluateFormula,
		getFormulaDependencies,
		detectCircularDependencies
	} from '$lib/form-logic/formulaCalculator';

	interface Props {
		module: Module;
		initialData?: Record<string, any>;
		mode?: 'create' | 'edit' | 'view';
		onSubmit?: (data: Record<string, any>) => Promise<void>;
		onCancel?: () => void;
	}

	let { module, initialData = {}, mode = 'create', onSubmit, onCancel }: Props = $props();

	// Form state
	let formData = writable<Record<string, any>>(initialData);
	let errors = writable<Record<string, string>>({});
	let touched = writable<Record<string, boolean>>({});
	let isSubmitting = $state(false);
	let submitError = $state<string | null>(null);
	let submitSuccess = $state(false);

	// Build formula field map and dependency graph
	// Get flattened fields from module (either direct or from blocks)
	const allFields = $derived.by(() => {
		if (module.fields) return module.fields;
		return module.blocks?.flatMap((block) => block.fields) ?? [];
	});

	const formulaFields = $derived.by(() => {
		if (!allFields.length) return new Map<string, Field>();
		const map = new Map<string, Field>();
		allFields.forEach((field: Field) => {
			if (field.type === 'formula' && field.settings?.formula_definition) {
				map.set(field.api_name, field);
			}
		});
		return map;
	});

	// Map from field api_name to field id for formula evaluation
	const fieldApiToId = $derived.by(() => {
		if (!allFields.length) return new Map<string, number>();
		const map = new Map<string, number>();
		allFields.forEach((field: Field) => {
			map.set(field.api_name, field.id);
		});
		return map;
	});

	const fieldIdToApi = $derived.by(() => {
		if (!allFields.length) return new Map<number, string>();
		const map = new Map<number, string>();
		allFields.forEach((field: Field) => {
			map.set(field.id, field.api_name);
		});
		return map;
	});

	// Computed visibility for fields (with conditional visibility evaluation)
	let visibleFields = derived(formData, ($data) => {
		if (!allFields.length) {
			return new Set<number>();
		}
		return getVisibleFieldIds(allFields, $data);
	});

	// Calculate formula values when form data changes
	$effect(() => {
		if (formulaFields.size === 0) return;

		const currentData = $formData;
		const updates: Record<string, any> = {};

		// Convert formData (keyed by ID) to context (keyed by api_name)
		const context: Record<string, any> = {};
		for (const [id, value] of Object.entries(currentData)) {
			const apiName = fieldIdToApi.get(Number(id));
			if (apiName) {
				context[apiName] = value;
			}
		}

		// Calculate each formula field
		formulaFields.forEach((field, apiName) => {
			const formulaDef = field.settings?.formula_definition;
			if (formulaDef) {
				const calculatedValue = evaluateFormula(formulaDef, context);
				const fieldId = field.id;

				// Only update if value has changed
				if (currentData[fieldId] !== calculatedValue) {
					updates[fieldId] = calculatedValue;
				}
			}
		});

		// Apply updates if any
		if (Object.keys(updates).length > 0) {
			formData.update((data) => ({
				...data,
				...updates
			}));
		}
	});

	// Update form field value
	function updateField(fieldId: number, value: any) {
		formData.update((data) => ({
			...data,
			[fieldId]: value
		}));

		// Mark field as touched
		touched.update((t) => ({
			...t,
			[fieldId]: true
		}));

		// Clear error for this field
		errors.update((e) => {
			const newErrors = { ...e };
			delete newErrors[fieldId];
			return newErrors;
		});

		// Reset submit success on any field change
		submitSuccess = false;
	}

	// Validate single field
	function validateField(field: Field, value: any): string | null {
		// Required validation
		if (field.is_required && (value === null || value === undefined || value === '')) {
			return `${field.label} is required`;
		}

		// Type-specific validation
		if (value !== null && value !== undefined && value !== '') {
			switch (field.type) {
				case 'email':
					const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
					if (!emailRegex.test(value)) {
						return 'Please enter a valid email address';
					}
					break;

				case 'phone':
					const phoneRegex = /^[\d\s\-\+\(\)]+$/;
					if (!phoneRegex.test(value)) {
						return 'Please enter a valid phone number';
					}
					break;

				case 'url':
					try {
						new URL(value);
					} catch {
						return 'Please enter a valid URL';
					}
					break;

				case 'number':
				case 'decimal':
				case 'currency':
				case 'percent':
					const numValue = Number(value);
					if (isNaN(numValue)) {
						return 'Please enter a valid number';
					}
					if (field.settings?.min_value !== undefined && numValue < field.settings.min_value) {
						return `Value must be at least ${field.settings.min_value}`;
					}
					if (field.settings?.max_value !== undefined && numValue > field.settings.max_value) {
						return `Value must be at most ${field.settings.max_value}`;
					}
					break;

				case 'text':
				case 'textarea':
					const strValue = String(value);
					if (field.settings?.min_length && strValue.length < field.settings.min_length) {
						return `Minimum length is ${field.settings.min_length} characters`;
					}
					if (field.settings?.max_length && strValue.length > field.settings.max_length) {
						return `Maximum length is ${field.settings.max_length} characters`;
					}
					break;
			}
		}

		return null;
	}

	// Validate all fields
	function validateForm(): boolean {
		const newErrors: Record<string, string> = {};
		let isValid = true;

		allFields.forEach((field: Field) => {
			const value = $formData[field.id];
			const error = validateField(field, value);
			if (error) {
				newErrors[field.id] = error;
				isValid = false;
			}
		});

		errors.set(newErrors);
		return isValid;
	}

	// Handle form submission
	async function handleSubmit() {
		// Mark all fields as touched
		const allTouched: Record<string, boolean> = {};
		module.fields?.forEach((field) => {
			allTouched[field.id] = true;
		});
		touched.set(allTouched);

		// Validate form
		if (!validateForm()) {
			submitError = 'Please fix the errors above before submitting';
			return;
		}

		// Submit form
		isSubmitting = true;
		submitError = null;

		try {
			if (onSubmit) {
				await onSubmit($formData);
				submitSuccess = true;
				submitError = null;
			}
		} catch (error) {
			submitError = error instanceof Error ? error.message : 'An error occurred while submitting';
			submitSuccess = false;
		} finally {
			isSubmitting = false;
		}
	}

	// Handle cancel
	function handleCancel() {
		if (onCancel) {
			onCancel();
		}
	}

	// Readonly mode check
	const isReadonly = $derived(mode === 'view');
</script>

<form
	onsubmit={(e) => {
		e.preventDefault();
		handleSubmit();
	}}
	class="space-y-6"
>
	<!-- Form Header -->
	<div class="flex items-center justify-between border-b pb-4">
		<div>
			<h2 class="text-2xl font-bold">{module.name}</h2>
			{#if module.description}
				<p class="mt-1 text-sm text-muted-foreground">{module.description}</p>
			{/if}
		</div>
		<div class="flex items-center gap-2">
			{#if mode !== 'view'}
				<span class="text-xs text-muted-foreground">* Required fields</span>
			{/if}
		</div>
	</div>

	<!-- Submit Error -->
	{#if submitError}
		<Alert.Root variant="destructive">
			<Alert.AlertDescription>{submitError}</Alert.AlertDescription>
		</Alert.Root>
	{/if}

	<!-- Submit Success -->
	{#if submitSuccess}
		<Alert.Root class="border-green-500 bg-green-50 text-green-900">
			<Alert.AlertDescription>
				{mode === 'create' ? 'Record created successfully!' : 'Record updated successfully!'}
			</Alert.AlertDescription>
		</Alert.Root>
	{/if}

	<!-- Render Blocks -->
	{#if module.blocks && module.blocks.length > 0}
		{#each module.blocks as block (block.id)}
			<BlockRenderer
				{block}
				{formData}
				{errors}
				{touched}
				{visibleFields}
				{isReadonly}
				{updateField}
			/>
		{/each}
	{:else}
		<div class="rounded-md border bg-muted/30 p-8 text-center">
			<p class="text-sm text-muted-foreground">No fields configured for this module</p>
		</div>
	{/if}

	<!-- Form Actions -->
	{#if mode !== 'view'}
		<div class="sticky bottom-0 flex items-center gap-3 border-t bg-background py-4 pt-4">
			<Button type="submit" disabled={isSubmitting}>
				{#if isSubmitting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					{mode === 'create' ? 'Creating...' : 'Saving...'}
				{:else}
					<Save class="mr-2 h-4 w-4" />
					{mode === 'create' ? 'Create' : 'Save'}
				{/if}
			</Button>
			<Button type="button" variant="outline" onclick={handleCancel} disabled={isSubmitting}>
				<X class="mr-2 h-4 w-4" />
				Cancel
			</Button>
		</div>
	{/if}
</form>
