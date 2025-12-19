<script lang="ts">
	import type { Snippet } from 'svelte';
	import * as Dialog from '$lib/components/ui/dialog';
	import { cn } from '$lib/utils';

	interface Props {
		class?: string;
		children?: Snippet;
	}

	let { class: className, children, ...restProps }: Props = $props();
</script>

<Dialog.Content class={cn('max-h-[85vh] overflow-y-auto', className)} {...restProps}>
	{@render children?.()}
</Dialog.Content>
