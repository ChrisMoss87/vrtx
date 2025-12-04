<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Phone } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/api/modules';

	interface Props {
		value: string;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		onchange: (value: string) => void;
	}

	let {
		value = $bindable(''),
		error,
		disabled = false,
		placeholder = '(555) 123-4567',
		required,
		settings,
		onchange
	}: Props = $props();
</script>

<div class="relative">
	<Phone class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
	<Input
		type="tel"
		bind:value
		oninput={() => onchange(value)}
		{placeholder}
		{disabled}
		{required}
		pattern={settings?.pattern}
		class={`pl-10 ${error ? 'border-destructive' : ''}`}
	/>
</div>
