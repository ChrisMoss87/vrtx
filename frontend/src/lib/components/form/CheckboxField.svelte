<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Label } from '$lib/components/ui/label';
	import { cn } from '$lib/lib/utils';

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

	function handleChange(checked: boolean | 'indeterminate') {
		const newValue = checked === true;
		value = newValue;
		onchange?.(newValue);
	}

	const widthClass = $derived(() => {
		switch (width) {
			case 25:
				return 'w-full lg:w-1/4';
			case 50:
				return 'w-full lg:w-1/2';
			case 75:
				return 'w-full lg:w-3/4';
			default:
				return 'w-full';
		}
	});
</script>

<div class={cn('space-y-2', widthClass(), className)}>
	<div class="flex items-start space-x-2">
		<Checkbox
			id={name}
			{name}
			checked={value}
			onCheckedChange={handleChange}
			{disabled}
			{required}
			aria-invalid={error ? true : undefined}
			aria-describedby={error ? `${name}-error` : description ? `${name}-description` : undefined}
		/>
		{#if label}
			<div class="grid gap-1.5 leading-none">
				<Label for={name} class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
					{label}
					{#if required}
						<span class="text-destructive">*</span>
					{/if}
				</Label>
				{#if description && !error}
					<p id="{name}-description" class="text-sm text-muted-foreground">
						{description}
					</p>
				{/if}
			</div>
		{/if}
	</div>

	{#if error}
		<p id="{name}-error" class="text-sm font-medium text-destructive">
			{error}
		</p>
	{/if}
</div>
