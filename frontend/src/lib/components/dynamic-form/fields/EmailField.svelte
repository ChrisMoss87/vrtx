<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Mail } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/api/modules';

	interface Props {
		value: string;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: FieldSettings;
		onchange: (value: string) => void;
		id?: string;
		ariaDescribedBy?: string;
		ariaInvalid?: boolean;
	}

	let {
		value = $bindable(''),
		error,
		disabled = false,
		placeholder = 'email@example.com',
		required,
		settings,
		onchange,
		id,
		ariaDescribedBy,
		ariaInvalid
	}: Props = $props();
</script>

<div class="relative">
	<Mail
		class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
		aria-hidden="true"
	/>
	<Input
		{id}
		type="email"
		bind:value
		oninput={() => onchange(value)}
		{placeholder}
		{disabled}
		{required}
		class={`pl-10 ${error ? 'border-destructive focus-visible:ring-destructive' : ''}`}
		aria-describedby={ariaDescribedBy}
		aria-invalid={ariaInvalid}
	/>
</div>
