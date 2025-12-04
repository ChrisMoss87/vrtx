<script lang="ts">
	import * as RadioGroup from '$lib/components/ui/radio-group';
	import { Label } from '$lib/components/ui/label';
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
		placeholder,
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

<RadioGroup.Root
	{value}
	onValueChange={handleValueChange}
	{disabled}
	class={error ? 'rounded-md border border-destructive p-3' : 'space-y-2'}
>
	{#each options as option}
		<div class="flex items-center space-x-2">
			<RadioGroup.Item value={option.value} id={`radio-${option.value}`} />
			<Label for={`radio-${option.value}`} class="font-normal">
				{option.label}
			</Label>
		</div>
	{/each}
	{#if options.length === 0}
		<div class="text-sm text-muted-foreground">No options available</div>
	{/if}
</RadioGroup.Root>
