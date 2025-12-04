<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Switch } from '$lib/components/ui/switch';
	import { Label } from '$lib/components/ui/label';

	interface Props {
		label?: string;
		name: string;
		value?: boolean;
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		width?: 25 | 50 | 75 | 100;
		class?: string;
		onchange?: (value: boolean) => void;
	}

	let {
		label,
		name,
		value = $bindable(false),
		description,
		error,
		required = false,
		disabled = false,
		width = 100,
		class: className,
		onchange
	}: Props = $props();

	function handleCheckedChange(checked: boolean) {
		value = checked;
		onchange?.(checked);
	}
</script>

<FieldBase {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<div class="flex items-center space-x-2">
			<Switch
				{...props}
				id={name}
				checked={value}
				onCheckedChange={handleCheckedChange}
				{disabled}
			/>
			{#if label}
				<Label
					for={name}
					class="cursor-pointer text-sm font-medium {disabled
						? 'cursor-not-allowed opacity-50'
						: ''}"
				>
					{label}
				</Label>
			{/if}
		</div>
	{/snippet}
</FieldBase>
