<script lang="ts">
	import * as Select from '$lib/components/ui/select';
	import type { FieldSettings, FieldOption } from '$lib/api/modules';

	interface Props {
		value: string;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		options?: FieldOption[];
		onchange: (value: string) => void;
	}

	let {
		value = $bindable(''),
		error,
		disabled = false,
		placeholder = 'Select an option...',
		required,
		settings,
		options = [],
		onchange
	}: Props = $props();

	function handleValueChange(val: string | undefined) {
		if (val) {
			value = val;
			onchange(val);
		}
	}
</script>

<Select.Root type="single" value={value || undefined} onValueChange={handleValueChange} {disabled}>
	<Select.Trigger class={error ? 'border-destructive' : ''}>
		<span>{options.find((o) => o.value === value)?.label || placeholder}</span>
	</Select.Trigger>
	<Select.Content>
		<Select.Group>
			{#each options as option}
				<Select.Item value={option.value}>
					{option.label}
				</Select.Item>
			{/each}
			{#if options.length === 0}
				<Select.Item value="" disabled>No options available</Select.Item>
			{/if}
		</Select.Group>
	</Select.Content>
</Select.Root>
