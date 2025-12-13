<script lang="ts">
	import { cn, type WithElementRef } from '$lib/utils.js';
	import type { HTMLThAttributes } from 'svelte/elements';

	let {
		ref = $bindable(null),
		class: className,
		children,
		...restProps
	}: WithElementRef<HTMLThAttributes> = $props();
</script>

<th
	bind:this={ref}
	data-slot="table-head"
	class={cn(
		'h-12 bg-sky-50/80 dark:bg-sky-950/30 px-4 text-left align-middle font-semibold text-sm whitespace-nowrap text-slate-700 dark:text-slate-200 first:rounded-tl-lg last:rounded-tr-lg [&:has([role=checkbox])]:pe-0',
		className
	)}
	{...restProps}
>
	{@render children?.()}
</th>
