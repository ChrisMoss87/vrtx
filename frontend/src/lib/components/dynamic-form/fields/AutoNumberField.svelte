<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import type { FieldSettings } from '$lib/api/modules';
	import { cn } from '$lib/utils';
	import { Hash } from 'lucide-svelte';

	interface Props {
		value: string | null;
		error?: string;
		disabled?: boolean;
		required?: boolean;
		settings?: FieldSettings;
		onchange: (value: string | null) => void;
	}

	let {
		value = $bindable(null),
		error,
		disabled = true, // Auto-number fields are always disabled for editing
		required,
		settings,
		onchange
	}: Props = $props();

	const prefix = $derived(settings?.prefix ?? '');
	const suffix = $derived(settings?.suffix ?? '');
	const padLength = $derived(settings?.pad_length ?? 4);

	// Format the display value
	const formattedValue = $derived.by(() => {
		if (!value) return null;

		// If value is just a number, pad it
		const numericValue = value.replace(/\D/g, '');
		if (numericValue) {
			const paddedNumber = numericValue.padStart(padLength, '0');
			return `${prefix}${paddedNumber}${suffix}`;
		}

		return value;
	});

	const displayValue = $derived(formattedValue ?? 'Auto-generated');
</script>

<div class={cn('flex items-center gap-2', error && 'text-destructive')}>
	<div
		class={cn(
			'flex min-w-[150px] items-center gap-2 rounded-md border bg-muted/50 px-3 py-2',
			error && 'border-destructive'
		)}
	>
		<Hash class="h-4 w-4 text-muted-foreground" />
		<span class={cn('font-mono', !value && 'text-muted-foreground')}>
			{displayValue}
		</span>
	</div>

	{#if !value}
		<Badge variant="outline" class="text-xs">Auto-assigned on save</Badge>
	{/if}
</div>

{#if settings?.prefix || settings?.suffix}
	<div class="mt-1 text-xs text-muted-foreground">
		Format: {prefix}<span class="text-muted-foreground/70">{'0'.repeat(padLength)}</span>{suffix}
	</div>
{/if}
