<script lang="ts">
	import type { CardStyle, CardLayout } from '$lib/types/kanban-card-config';
	import { Badge } from '$lib/components/ui/badge';
	import { formatFieldValue } from '$lib/utils/field-formatters';
	import { cn } from '$lib/utils';

	interface Props {
		style: CardStyle;
		layout: CardLayout;
		sampleData?: Record<string, unknown>;
		availableFields?: Array<{ api_name: string; label: string; type: string }>;
		class?: string;
	}

	let {
		style,
		layout,
		sampleData = {},
		availableFields = [],
		class: className
	}: Props = $props();

	// Generate sample value based on field type
	function getSampleValue(fieldType: string, fieldLabel: string): unknown {
		switch (fieldType) {
			case 'currency':
			case 'decimal':
				return 25000;
			case 'number':
			case 'integer':
			case 'percent':
				return 75;
			case 'date':
			case 'datetime':
				return new Date().toISOString();
			case 'checkbox':
			case 'boolean':
				return true;
			case 'email':
				return 'sample@example.com';
			case 'phone':
				return '(555) 123-4567';
			case 'url':
				return 'https://example.com';
			case 'select':
			case 'radio':
			case 'picklist':
				return 'Option A';
			case 'multiselect':
				return ['Option A', 'Option B'];
			default:
				// Generate a readable sample text based on field label
				return `Sample ${fieldLabel}`;
		}
	}

	// Build dynamic sample data from available fields and explicit sample data
	const data = $derived.by(() => {
		const result: Record<string, unknown> = { ...sampleData };

		// Add sample values for any fields in layout that aren't in sampleData
		for (const field of layout.fields) {
			if (result[field.fieldApiName] === undefined) {
				const fieldMeta = availableFields.find((f) => f.api_name === field.fieldApiName);
				const fieldType = fieldMeta?.type || 'text';
				const fieldLabel = fieldMeta?.label || field.fieldApiName;
				result[field.fieldApiName] = getSampleValue(fieldType, fieldLabel);
			}
		}

		return result;
	});

	function getFieldValue(fieldApiName: string): string {
		const value = data[fieldApiName];
		if (value === undefined || value === null || value === '') return '';

		const field = availableFields.find((f) => f.api_name === fieldApiName);
		const fieldType = field?.type || 'text';

		return formatFieldValue(value, fieldType);
	}

	function getFieldLabel(fieldApiName: string): string {
		const field = availableFields.find((f) => f.api_name === fieldApiName);
		return field?.label || fieldApiName;
	}
</script>

<div class={cn('rounded-lg border-2 border-dashed bg-muted/30 p-4', className)}>
	<p class="mb-3 text-xs font-medium uppercase tracking-wider text-muted-foreground">
		Card Preview
	</p>

	<div
		class="rounded-lg shadow-md transition-all hover:shadow-lg"
		style="background-color: {style.backgroundColor || '#ffffff'};
               border: 1px solid {style.borderColor || '#e5e7eb'};
               border-left: {style.accentWidth || 3}px solid {style.accentColor || '#3b82f6'};"
	>
		<div class="p-4 space-y-3">
			{#each layout.fields as field}
				{@const value = getFieldValue(field.fieldApiName)}
				{@const label = getFieldLabel(field.fieldApiName)}

				{#if value}
					{#if field.displayAs === 'title'}
						<h4
							class="font-semibold text-base line-clamp-2"
							style="color: {style.titleColor || '#111827'}"
						>
							{#if field.showLabel || layout.showFieldLabels}
								<span class="text-xs font-medium text-muted-foreground">{label}:</span>
							{/if}
							{value}
						</h4>
					{:else if field.displayAs === 'subtitle'}
						<p
							class="text-sm line-clamp-1"
							style="color: {style.subtitleColor || '#6b7280'}"
						>
							{#if field.showLabel || layout.showFieldLabels}
								<span class="text-xs font-medium">{label}:</span>
							{/if}
							{value}
						</p>
					{:else if field.displayAs === 'badge'}
						<div class="flex items-center gap-2">
							{#if field.showLabel || layout.showFieldLabels}
								<span class="text-xs font-medium" style="color: {style.textColor || '#374151'}">
									{label}:
								</span>
							{/if}
							<Badge variant="outline" class="text-xs">{value}</Badge>
						</div>
					{:else if field.displayAs === 'value'}
						<div class="flex items-center gap-2">
							{#if field.showLabel || layout.showFieldLabels}
								<span class="text-xs font-medium" style="color: {style.textColor || '#374151'}">
									{label}:
								</span>
							{/if}
							<span class="text-lg font-bold text-primary">{value}</span>
						</div>
					{:else if field.displayAs === 'text'}
						<div class="flex items-center gap-2">
							{#if field.showLabel || layout.showFieldLabels}
								<span class="text-xs font-medium" style="color: {style.textColor || '#374151'}">
									{label}:
								</span>
							{/if}
							<span class="text-sm" style="color: {style.textColor || '#374151'}">{value}</span>
						</div>
					{:else if field.displayAs === 'small'}
						<div class="flex items-center gap-2">
							{#if field.showLabel || layout.showFieldLabels}
								<span class="text-xs text-muted-foreground">{label}:</span>
							{/if}
							<span class="text-xs text-muted-foreground">{value}</span>
						</div>
					{/if}
				{/if}
			{/each}

			{#if layout.fields.length === 0}
				<div class="text-center py-4 text-sm text-muted-foreground">
					Add fields to see preview
				</div>
			{/if}
		</div>
	</div>
</div>
