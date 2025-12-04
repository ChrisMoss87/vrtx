<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';
	import { cn } from '$lib/utils';

	interface Props {
		class?: string;
	}

	let { class: className, children, ...restProps }: Props = $props();
</script>

<Dialog.Footer class={cn('flex flex-col-reverse sm:flex-row', className)} {...restProps}>
	{@render children?.()}
</Dialog.Footer>
