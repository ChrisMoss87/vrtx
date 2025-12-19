<script lang="ts">
	import type { HTMLAttributes } from 'svelte/elements';
	import { cn, type WithElementRef } from '$lib/utils.js';

	type Variant = 'default' | 'success' | 'warning' | 'error' | 'info' | 'dashed';

	interface Props extends WithElementRef<HTMLAttributes<HTMLDivElement>> {
		variant?: Variant;
	}

	let {
		ref = $bindable(null),
		class: className,
		variant = 'default',
		...restProps
	}: Props = $props();

	const variantStyles: Record<Variant, string> = {
		default: 'bg-border',
		success: 'bg-green-300 dark:bg-green-700',
		warning: 'bg-amber-300 dark:bg-amber-700',
		error: 'bg-red-300 dark:bg-red-700',
		info: 'bg-blue-300 dark:bg-blue-700',
		dashed: 'bg-transparent border-l-2 border-dashed border-border'
	};
</script>

<div
	bind:this={ref}
	data-slot="timeline-connector"
	data-variant={variant}
	class={cn(
		'absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5',
		variant === 'dashed' ? 'w-0' : '',
		variantStyles[variant],
		className
	)}
	{...restProps}
></div>
