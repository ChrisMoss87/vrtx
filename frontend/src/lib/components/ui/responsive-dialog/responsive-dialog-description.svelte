<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';

	let { children, ...restProps } = $props();
</script>

<Dialog.Description {...restProps}>
	{@render children?.()}
</Dialog.Description>
