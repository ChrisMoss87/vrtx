<script lang="ts">
	import { cn } from '$lib/utils';
    import { Field, Label, Error } from '../ui/field';
	interface Props {
		label?: string;
		name: string;
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		class?: string;
		children: import('svelte').Snippet<[{
			id: string;
			name: string;
			required: boolean;
			disabled: boolean;
			'aria-invalid'?: boolean;
			'aria-describedby'?: string;
		}]>;
	}

	let {
		label,
		name,
		description,
		error,
		required = false,
		disabled = false,
		class: className,
		children
	}: Props = $props();



	const inputProps = $derived({
		id: name,
		name,
		required,
		disabled,
		'aria-invalid': error ? true : undefined,
		'aria-describedby': error ? `${name}-error` : description ? `${name}-description` : undefined
	});
</script>

<Field id={name}>
	{#if label}
		<Label for={name} >
			{label}
            {#if required}
                <span class="text-destructive">*</span>
            {/if}
        </Label>
    {/if}

    {@render children(inputProps)}

	{#if description && !error}
		<p id="{name}-description" class="text-sm text-muted-foreground">
			{description}
		</p>
	{/if}

	{#if error}
        <Error id="{name}-error"> {error}</Error>
	{/if}
</Field>
