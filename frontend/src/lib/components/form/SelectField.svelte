<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import * as Select from '$lib/components/ui/select';

	interface Option {
		label: string;
		value: string;
	}

	interface Props {
		label?: string;
		name: string;
		value?: string;
		options: Option[];
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		placeholder?: string;
		width?: 25 | 50 | 75 | 100;
		class?: string;
		onchange?: (value: string) => void;
	}

	let {
		label,
		name,
		value = $bindable(),
		options,
		description,
		error,
		required = false,
		disabled = false,
		placeholder = 'Select an option',
		width = 100,
		class: className,
		onchange
	}: Props = $props();

	function handleValueChange(newValue: string | undefined) {
		if (newValue !== undefined) {
			value = newValue;
			onchange?.(value);
		}
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<Select.Root
			selected={{ value, label: options.find((o) => o.value === value)?.label ?? placeholder }}
			onSelectedChange={(selected) => handleValueChange(selected?.value)}
		>
			<Select.Trigger {...props}>
				{options.find((o) => o.value === value)?.label ?? placeholder}
			</Select.Trigger>
			<Select.Content>
				{#each options as option (option.value)}
					<Select.Item value={option.value}>
						{option.label}
					</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	{/snippet}
</FieldBase>
