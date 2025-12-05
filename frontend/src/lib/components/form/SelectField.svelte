<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import * as Select from '$lib/components/ui/select';

	interface Option {
		label: string;
		value: string;
		color?: string;
		metadata?: Record<string, unknown>;
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
		class?: string;
		showColors?: boolean;
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
		class: className,
		showColors = false,
		onchange
	}: Props = $props();

	function handleValueChange(newValue: string | undefined) {
		if (newValue !== undefined) {
			value = newValue;
			onchange?.(value);
		}
	}

	// Get color for the selected option
	const selectedOption = $derived(options.find((o) => o.value === value));
	const selectedColor = $derived(
		showColors && selectedOption?.color ? selectedOption.color : undefined
	);
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} class={className}>
	{#snippet children(props)}
		<Select.Root type="single" value={value} onValueChange={handleValueChange}>
			<Select.Trigger {...props}>
				<span class="flex items-center gap-2">
					{#if selectedColor}
						<span
							class="h-3 w-3 shrink-0 rounded-full"
							style="background-color: {selectedColor}"
						></span>
					{/if}
					<span>{selectedOption?.label ?? placeholder}</span>
				</span>
			</Select.Trigger>
			<Select.Content>
				{#each options as option (option.value)}
					<Select.Item value={option.value}>
						<span class="flex items-center gap-2">
							{#if showColors && option.color}
								<span
									class="h-3 w-3 shrink-0 rounded-full"
									style="background-color: {option.color}"
								></span>
							{/if}
							<span>{option.label}</span>
						</span>
					</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	{/snippet}
</FieldBase>
