<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Button } from '$lib/components/ui/button';
	import type { FieldSettings } from '$lib/api/modules';
	import { cn } from '$lib/utils';

	interface Props {
		value: string;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: FieldSettings;
		onchange: (value: string) => void;
	}

	let {
		value = $bindable('#000000'),
		error,
		disabled = false,
		placeholder = '#000000',
		required,
		settings,
		onchange
	}: Props = $props();

	// Preset colors (can be customized via settings)
	const defaultPresets = [
		'#ef4444', // red
		'#f97316', // orange
		'#eab308', // yellow
		'#22c55e', // green
		'#14b8a6', // teal
		'#3b82f6', // blue
		'#8b5cf6', // purple
		'#ec4899', // pink
		'#6b7280', // gray
		'#000000', // black
		'#ffffff' // white
	];
	const presetColors = $derived(
		(Array.isArray(settings?.additional_settings?.presetColors)
			? (settings.additional_settings.presetColors as string[])
			: defaultPresets)
	);

	let showPicker = $state(false);

	function handleColorChange(newColor: string) {
		value = newColor;
		onchange(newColor);
	}

	function handleHexInput(event: Event) {
		const input = event.target as HTMLInputElement;
		let hex = input.value;

		// Add # if missing
		if (hex && !hex.startsWith('#')) {
			hex = '#' + hex;
		}

		// Validate hex color
		if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
			value = hex;
			onchange(hex);
		}
	}

	function handleNativeColorChange(event: Event) {
		const input = event.target as HTMLInputElement;
		handleColorChange(input.value);
	}
</script>

<div class={cn('space-y-2', error && 'text-destructive')}>
	<div class="flex items-center gap-2">
		<!-- Color preview/picker button -->
		<label class="relative cursor-pointer">
			<input
				type="color"
				{value}
				onchange={handleNativeColorChange}
				{disabled}
				class="absolute inset-0 h-10 w-10 cursor-pointer opacity-0"
			/>
			<div
				class={cn(
					'h-10 w-10 rounded-md border-2 transition-colors',
					disabled && 'cursor-not-allowed opacity-50',
					error ? 'border-destructive' : 'border-input hover:border-primary'
				)}
				style="background-color: {value || '#ffffff'};"
			></div>
		</label>

		<!-- Hex input -->
		<Input
			{value}
			oninput={handleHexInput}
			{placeholder}
			{disabled}
			class={cn('flex-1 font-mono', error && 'border-destructive')}
		/>
	</div>

	<!-- Preset colors -->
	{#if !disabled}
		<div class="flex flex-wrap gap-1">
			{#each presetColors as color}
				<button
					type="button"
					class={cn(
						'h-6 w-6 rounded border transition-transform hover:scale-110',
						value === color ? 'ring-2 ring-primary ring-offset-1' : ''
					)}
					style="background-color: {color}; border-color: rgba(0,0,0,0.1);"
					onclick={() => handleColorChange(color)}
					title={color}
				></button>
			{/each}
		</div>
	{/if}
</div>
