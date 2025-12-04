<script lang="ts">
	import { RichTextEditor } from '$lib/components/editor';
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

	// Get character limit from settings if available
	const characterLimit = settings?.max_length;

	function handleChange(html: string) {
		value = html;
		onchange(html);
	}
</script>

<div class="space-y-1">
	<RichTextEditor
		bind:content={value}
		{placeholder}
		{disabled}
		{characterLimit}
		minHeight="150px"
		maxHeight="400px"
		onchange={handleChange}
		class={error ? 'border-destructive' : ''}
	/>
	{#if error}
		<p class="text-sm text-destructive">{error}</p>
	{/if}
</div>
