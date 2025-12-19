<script lang="ts">
	import { Input } from '$lib/components/ui/input';
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
		placeholder,
		required,
		settings,
		onchange,
		id,
		ariaDescribedBy,
		ariaInvalid
	}: Props = $props();
</script>

<Input
	{id}
	type="text"
	bind:value
	oninput={() => onchange(value)}
	{placeholder}
	{disabled}
	{required}
	minlength={settings?.min_length}
	maxlength={settings?.max_length}
	pattern={settings?.pattern}
	class={error ? 'border-destructive focus-visible:ring-destructive' : ''}
	aria-describedby={ariaDescribedBy}
	aria-invalid={ariaInvalid}
/>
