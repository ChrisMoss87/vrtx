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

	// Default sample data if none provided
	const defaultSampleData = {
		title: 'Sample Card Title',
		description: 'This is a sample description for the kanban card preview',
		status: 'In Progress',
		priority: 'High',
		amount: 25000,
		date: new Date().toISOString()
	};

	const data = $derived(Object.keys(sampleData).length > 0 ? sampleData : defaultSampleData);

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
