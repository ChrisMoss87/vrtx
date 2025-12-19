<script lang="ts">
	import type { HTMLAttributes } from 'svelte/elements';
	import { cn, type WithElementRef } from '$lib/utils.js';
	import type { Snippet } from 'svelte';

	type Variant = 'default' | 'success' | 'warning' | 'error' | 'info' | 'outline';

	interface Props extends WithElementRef<HTMLAttributes<HTMLDivElement>> {
		variant?: Variant;
		children?: Snippet;
	}

	let {
		ref = $bindable(null),
		class: className,
		variant = 'default',
		children,
		...restProps
	}: Props = $props();

	const variantStyles: Record<Variant, string> = {
		default: 'bg-muted text-muted-foreground border-muted',
		success: 'bg-green-100 text-green-600 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
		warning: 'bg-amber-100 text-amber-600 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800',
		error: 'bg-red-100 text-red-600 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800',
		info: 'bg-blue-100 text-blue-600 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800',
		outline: 'bg-background text-foreground border-border'
	};
</script>

<div
	bind:this={ref}
	data-slot="timeline-icon"
	data-variant={variant}
	class={cn(
		'absolute left-0 top-0 flex h-8 w-8 items-center justify-center rounded-full border-2 shadow-sm',
		variantStyles[variant],
		className
	)}
	{...restProps}
>
	{#if children}
		{@render children()}
	{:else}
		<div class="h-2 w-2 rounded-full bg-current"></div>
	{/if}
</div>
