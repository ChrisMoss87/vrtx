<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Label } from '$lib/components/ui/label';
	import { RadioGroup, RadioGroupItem } from '$lib/components/ui/radio-group';

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
		width?: 25 | 50 | 75 | 100;
		class?: string;
		orientation?: 'horizontal' | 'vertical';
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
		width = 100,
		class: className,
		orientation = 'vertical',
		onchange
	}: Props = $props();

	function handleValueChange(newValue: string) {
		value = newValue;
		onchange?.(newValue);
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} class={className}>
	{#snippet children(props)}
		<RadioGroup
			{...props}
			{value}
			onValueChange={handleValueChange}
			{disabled}
			class={orientation === 'horizontal' ? 'flex flex-wrap gap-4' : 'flex flex-col gap-3'}
		>
			{#each options as option (option.value)}
				<div class="flex items-center space-x-2">
					<RadioGroupItem value={option.value} id={`${name}-${option.value}`} {disabled} />
					<Label
						for={`${name}-${option.value}`}
						class="cursor-pointer text-sm font-normal {disabled
							? 'cursor-not-allowed opacity-50'
							: ''}"
					>
						{option.label}
					</Label>
				</div>
			{/each}
		</RadioGroup>
	{/snippet}
</FieldBase>
