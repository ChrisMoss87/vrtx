<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { CalendarDays } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/api/modules';

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
		value = $bindable(''),
		error,
		disabled = false,
		placeholder,
		required,
		settings,
		onchange
	}: Props = $props();
</script>

<div class="relative">
	<CalendarDays
		class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
	/>
	<Input
		type="date"
		bind:value
		oninput={() => onchange(value)}
		{placeholder}
		{disabled}
		{required}
		min={settings?.min_date}
		max={settings?.max_date}
		class={`pl-10 ${error ? 'border-destructive' : ''}`}
	/>
</div>
