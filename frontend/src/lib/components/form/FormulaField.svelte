<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Calculator } from 'lucide-svelte';
	import { cn } from '$lib/utils';

	interface Props {
		label?: string;
		name: string;
		value?: string | number | null;
		description?: string;
		error?: string;
		returnType?: 'number' | 'currency' | 'percent' | 'text' | 'date' | 'boolean';
		currencySymbol?: string;
		class?: string;
	}

	let {
		label,
		name,
		value = $bindable(),
		description,
		error,
		returnType = 'text',
		currencySymbol = '$',
		class: className
	}: Props = $props();

	// Format the displayed value based on return type
	const formattedValue = $derived(() => {
		if (value === null || value === undefined || value === '') {
			return '—';
		}

		switch (returnType) {
			case 'currency':
				const numVal = typeof value === 'number' ? value : parseFloat(String(value));
				if (isNaN(numVal)) return '—';
				return `${currencySymbol}${numVal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

			case 'percent':
				const pctVal = typeof value === 'number' ? value : parseFloat(String(value));
				if (isNaN(pctVal)) return '—';
				return `${pctVal.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 2 })}%`;

			case 'number':
				const num = typeof value === 'number' ? value : parseFloat(String(value));
				if (isNaN(num)) return '—';
				return num.toLocaleString();

			case 'date':
				try {
					const date = new Date(String(value));
					return date.toLocaleDateString();
				} catch {
					return String(value);
				}

			case 'boolean':
				return value ? 'Yes' : 'No';

			default:
				return String(value);
		}
	});
</script>

<FieldBase {label} {name} {description} {error} required={false} disabled={true} class={className}>
	{#snippet children(props)}
		<div
			class={cn(
				'flex items-center gap-2 rounded-md border border-input bg-muted/50 px-3 py-2 text-sm',
				'cursor-not-allowed opacity-70'
			)}
		>
			<Calculator class="h-4 w-4 text-muted-foreground flex-shrink-0" />
			<span class="font-medium tabular-nums">{formattedValue()}</span>
		</div>
		<p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
			<span>Calculated field</span>
			<span class="text-muted-foreground/60">(read-only)</span>
		</p>
	{/snippet}
</FieldBase>
