<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Textarea } from '$lib/components/ui/textarea';

	interface Props {
		label?: string;
		name: string;
		value?: string;
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		placeholder?: string;
		rows?: number;
		width?: 25 | 50 | 75 | 100;
		class?: string;
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
		placeholder,
		rows = 4,
		width = 100,
		class: className,
		onchange
	}: Props = $props();

	function handleInput(e: Event) {
		const target = e.target as HTMLTextAreaElement;
		value = target.value;
		onchange?.(value);
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<Textarea {...props} {placeholder} {rows} {value} oninput={handleInput} />
	{/snippet}
</FieldBase>
