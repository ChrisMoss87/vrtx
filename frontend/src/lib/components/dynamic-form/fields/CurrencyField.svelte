<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import type { FieldSettings } from '$lib/api/modules';

	interface Props {
		value: number | null;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		onchange: (value: number | null) => void;
	}

	let {
		value = $bindable(null),
		error,
		disabled = false,
		placeholder = '0.00',
		required,
		settings,
		onchange
	}: Props = $props();

	const currencySymbol = settings?.currency_symbol || '$';
	const precision = settings?.precision || 2;
	const step = 1 / Math.pow(10, precision);

	function handleInput(event: Event) {
		const target = event.target as HTMLInputElement;
		const val = target.value === '' ? null : parseFloat(target.value);
		value = val;
		onchange(val);
	}
</script>

<div class="relative">
	<span class="absolute top-1/2 left-3 -translate-y-1/2 text-sm font-medium text-muted-foreground">
		{currencySymbol}
	</span>
	<Input
		type="number"
		value={value ?? ''}
		oninput={handleInput}
		{placeholder}
		{disabled}
		{required}
		min={settings?.min_value || 0}
		max={settings?.max_value}
		{step}
		class={`pl-8 ${error ? 'border-destructive' : ''}`}
	/>
</div>
