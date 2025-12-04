<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Calculator, Info } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/api/modules';
	import * as Tooltip from '$lib/components/ui/tooltip';

	interface Props {
		value: any;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		onchange: (value: any) => void;
	}

	let {
		value = $bindable(''),
		error,
		disabled = true,
		placeholder = 'Calculated value',
		required,
		settings,
		onchange
	}: Props = $props();

	// Get formula info
	const formulaDef = $derived(settings?.formula_definition);
	const returnType = $derived(formulaDef?.return_type || 'number');

	// Format the display value based on return type
	const displayValue = $derived.by(() => {
		if (value === null || value === undefined) {
			return '';
		}

		switch (returnType) {
			case 'number':
				return typeof value === 'number' ? value.toLocaleString() : String(value);
			case 'currency':
				const currencyCode = settings?.currency_code || 'USD';
				if (typeof value === 'number') {
					return new Intl.NumberFormat('en-US', {
						style: 'currency',
						currency: currencyCode
					}).format(value);
				}
				return String(value);
			case 'percentage':
				if (typeof value === 'number') {
					return `${(value * 100).toFixed(2)}%`;
				}
				return String(value);
			case 'date':
				if (value instanceof Date) {
					return value.toLocaleDateString();
				}
				return String(value);
			case 'boolean':
				return value ? 'Yes' : 'No';
			default:
				return String(value);
		}
	});
</script>

<div class="space-y-2">
	<div class="flex items-center gap-2">
		<Badge variant="outline" class="text-xs">
			<Calculator class="mr-1 h-3 w-3" />
			Formula
		</Badge>
		{#if formulaDef?.formula}
			<Tooltip.Root>
				<Tooltip.Trigger>
					<Info class="h-3.5 w-3.5 cursor-help text-muted-foreground" />
				</Tooltip.Trigger>
				<Tooltip.Content>
					<p class="font-mono text-xs">{formulaDef.formula}</p>
				</Tooltip.Content>
			</Tooltip.Root>
		{/if}
	</div>
	<div class="relative">
		<Input
			type="text"
			value={displayValue}
			readonly
			disabled
			{placeholder}
			class={`bg-muted/50 ${error ? 'border-destructive' : ''}`}
		/>
	</div>
</div>
