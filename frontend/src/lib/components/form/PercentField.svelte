<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Input } from '$lib/components/ui/input';

	interface Props {
		label?: string;
		name: string;
		value?: string | number;
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		placeholder?: string;
		width?: 25 | 50 | 75 | 100;
		class?: string;
		min?: number;
		max?: number;
		allowDecimals?: boolean;
		onchange?: (value: string) => void;
	}

	let {
		label,
		name,
		value = $bindable(),
		description,
		error,
		required = false,
		disabled = false,
		placeholder = '0',
		width = 100,
		class: className,
		min = 0,
		max = 100,
		allowDecimals = true,
		onchange
	}: Props = $props();

	// Internal state for display value
	let displayValue = $state('');

	// Initialize display value
	if (value) {
		const numValue = typeof value === 'string' ? parseFloat(value) : value;
		if (!isNaN(numValue)) {
			displayValue = allowDecimals ? numValue.toString() : Math.round(numValue).toString();
		}
	}

	function handleInput(e: Event) {
		const target = e.target as HTMLInputElement;
		let inputValue = target.value.replace(/[^0-9.]/g, '');

		if (!allowDecimals) {
			inputValue = inputValue.replace(/\./g, '');
		} else {
			// Ensure only one decimal point
			const parts = inputValue.split('.');
			if (parts.length > 2) {
				inputValue = parts[0] + '.' + parts.slice(1).join('');
			}

			// Limit to 2 decimal places
			if (parts.length === 2 && parts[1].length > 2) {
				inputValue = parts[0] + '.' + parts[1].substring(0, 2);
			}
		}

		displayValue = inputValue;
		value = inputValue;
		onchange?.(inputValue);
	}

	function handleBlur() {
		if (displayValue) {
			const numValue = parseFloat(displayValue);
			if (!isNaN(numValue)) {
				// Apply min/max validation
				let finalValue = numValue;
				if (finalValue < min) {
					finalValue = min;
				}
				if (finalValue > max) {
					finalValue = max;
				}

				displayValue = allowDecimals ? finalValue.toString() : Math.round(finalValue).toString();
				value = displayValue;
				onchange?.(displayValue);
			}
		}
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<div class="relative">
			<Input
				{...props}
				type="text"
				{placeholder}
				value={displayValue}
				oninput={handleInput}
				onblur={handleBlur}
				class="pr-8"
			/>
			<span class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"> % </span>
		</div>
	{/snippet}
</FieldBase>
