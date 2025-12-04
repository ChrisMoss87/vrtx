<script lang="ts">
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Label } from '$lib/components/ui/label';
	import type { FieldSettings } from '$lib/api/modules';

	interface Props {
		value: boolean;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		onchange: (value: boolean) => void;
	}

	let {
		value = $bindable(false),
		error,
		disabled = false,
		placeholder,
		required,
		settings,
		onchange
	}: Props = $props();

	function handleCheckedChange(checked: boolean | 'indeterminate') {
		const val = checked === true;
		value = val;
		onchange(val);
	}
</script>

<div
	class={`flex items-center space-x-2 ${error ? 'rounded-md border border-destructive p-3' : ''}`}
>
	<Checkbox
		id="checkbox-field"
		checked={value}
		onCheckedChange={handleCheckedChange}
		{disabled}
		{required}
	/>
	{#if placeholder}
		<Label
			for="checkbox-field"
			class="text-sm leading-none font-normal peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
		>
			{placeholder}
		</Label>
	{/if}
</div>
