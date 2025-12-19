<script lang="ts">
	import { cn, type WithElementRef } from '$lib/utils.js';
	import type { HTMLAttributes } from 'svelte/elements';

	let {
		ref = $bindable(null),
		class: className,
		children,
		...restProps
	}: WithElementRef<HTMLAttributes<HTMLTableRowElement>> = $props();
</script>

<tr
	bind:this={ref}
	data-slot="table-row"
	class={cn(
		'border-b border-slate-100 dark:border-slate-800 transition-colors data-[state=selected]:bg-sky-50/50 dark:data-[state=selected]:bg-sky-950/20 hover:[&,&>svelte-css-wrapper]:[&>th,td]:bg-slate-50/80 dark:hover:[&,&>svelte-css-wrapper]:[&>th,td]:bg-slate-800/50',
		className
	)}
	{...restProps}
>
	{@render children?.()}
</tr>
