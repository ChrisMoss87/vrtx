<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';

	let { children, ...restProps } = $props();
</script>

<Dialog.Title {...restProps}>
	{@render children?.()}
</Dialog.Title>
