<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';
	import { setContext } from 'svelte';

	interface Props {
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
	}

	let { open = $bindable(false), onOpenChange, children }: Props = $props();

	setContext('responsive-dialog', {
		get open() {
			return open;
		}
	});
</script>

<Dialog.Root bind:open {onOpenChange}>
	{@render children?.()}
</Dialog.Root>
