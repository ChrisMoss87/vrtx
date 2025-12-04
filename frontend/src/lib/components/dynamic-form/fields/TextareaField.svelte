<script lang="ts">
	import { Textarea } from '$lib/components/ui/textarea';
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
		placeholder,
		required,
		settings,
		onchange
	}: Props = $props();
</script>

<Textarea
	bind:value
	oninput={() => onchange(value)}
	{placeholder}
	{disabled}
	{required}
	minlength={settings?.min_length}
	maxlength={settings?.max_length}
	rows={settings?.rows || 4}
	class={error ? 'resize-none border-destructive' : 'resize-none'}
/>
