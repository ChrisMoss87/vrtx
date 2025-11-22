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
		currency?: string;
		min?: number;
		max?: number;
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
		placeholder = '0.00',
		width = 100,
		class: className,
		currency = 'USD',
		min,
		max,
		onchange
	}: Props = $props();

	const currencySymbols: Record<string, string> = {
		USD: '$',
		EUR: '€',
		GBP: '£',
		JPY: '¥',
		CAD: 'C$',
		AUD: 'A$',
		CHF: 'CHF',
		CNY: '¥',
		INR: '₹'
	};

	const symbol = $derived(currencySymbols[currency] || '$');

	// Internal state for display value
	let displayValue = $state('');
	let isFocused = $state(false);

	// Initialize display value
	if (value) {
		const numValue = typeof value === 'string' ? parseFloat(value) : value;
		if (!isNaN(numValue)) {
			displayValue = numValue.toFixed(2);
		}
	}

	function formatCurrency(num: number): string {
		return num.toLocaleString('en-US', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function handleInput(e: Event) {
		const target = e.target as HTMLInputElement;
		let inputValue = target.value.replace(/[^0-9.]/g, '');

		// Ensure only one decimal point
		const parts = inputValue.split('.');
		if (parts.length > 2) {
			inputValue = parts[0] + '.' + parts.slice(1).join('');
		}

		// Limit to 2 decimal places
		if (parts.length === 2 && parts[1].length > 2) {
			inputValue = parts[0] + '.' + parts[1].substring(0, 2);
		}

		displayValue = inputValue;
		value = inputValue;
		onchange?.(inputValue);
	}

	function handleFocus() {
		isFocused = true;
	}

	function handleBlur() {
		isFocused = false;
		if (displayValue) {
			const numValue = parseFloat(displayValue);
			if (!isNaN(numValue)) {
				// Apply min/max validation
				let finalValue = numValue;
				if (min !== undefined && finalValue < min) {
					finalValue = min;
				}
				if (max !== undefined && finalValue > max) {
					finalValue = max;
				}
				displayValue = finalValue.toFixed(2);
				value = displayValue;
				onchange?.(displayValue);
			}
		}
	}

	// Format display value when not focused
	const formattedDisplay = $derived(() => {
		if (isFocused || !displayValue) {
			return displayValue;
		}
		const numValue = parseFloat(displayValue);
		return isNaN(numValue) ? displayValue : formatCurrency(numValue);
	});
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<div class="relative">
			<span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
				{symbol}
			</span>
			<Input
				{...props}
				type="text"
				{placeholder}
				value={formattedDisplay()}
				oninput={handleInput}
				onfocus={handleFocus}
				onblur={handleBlur}
				class="pl-8"
			/>
		</div>
	{/snippet}
</FieldBase>
