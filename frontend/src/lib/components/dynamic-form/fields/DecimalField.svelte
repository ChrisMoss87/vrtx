<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import type { FieldSettings } from '$lib/api/modules';

	interface Props {
		value: number | null;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: FieldSettings;
		onchange: (value: number | null) => void;
	}

	let {
		value = $bindable(null),
		error,
		disabled = false,
		placeholder,
		required,
		settings,
		onchange
	}: Props = $props();

	const precision = settings?.precision || 2;
	const step = 1 / Math.pow(10, precision);

	function handleInput(event: Event) {
		const target = event.target as HTMLInputElement;
		const val = target.value === '' ? null : parseFloat(target.value);
		value = val;
		onchange(val);
	}
</script>

<Input
	type="number"
	value={value ?? ''}
	oninput={handleInput}
	{placeholder}
	{disabled}
	{required}
	min={settings?.min_value}
	max={settings?.max_value}
	{step}
	class={error ? 'border-destructive' : ''}
/>
