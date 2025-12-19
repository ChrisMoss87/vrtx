<script lang="ts">
	import type { HTMLAttributes } from 'svelte/elements';
	import { cn, type WithElementRef } from '$lib/utils.js';

	type Variant = 'default' | 'success' | 'warning' | 'error' | 'info';

	interface Props extends WithElementRef<HTMLAttributes<HTMLDivElement>> {
		variant?: Variant;
		isLast?: boolean;
	}

	let {
		ref = $bindable(null),
		class: className,
		variant = 'default',
		isLast = false,
		children,
		...restProps
	}: Props = $props();

	const variantStyles: Record<Variant, string> = {
		default: '',
		success: '[--timeline-color:theme(colors.green.500)]',
		warning: '[--timeline-color:theme(colors.amber.500)]',
		error: '[--timeline-color:theme(colors.red.500)]',
		info: '[--timeline-color:theme(colors.blue.500)]'
	};
</script>

<div
	bind:this={ref}
	data-slot="timeline-item"
	data-variant={variant}
	data-last={isLast}
	class={cn('relative pb-8 pl-10 last:pb-0', variantStyles[variant], className)}
	{...restProps}
>
	{@render children?.()}
</div>
