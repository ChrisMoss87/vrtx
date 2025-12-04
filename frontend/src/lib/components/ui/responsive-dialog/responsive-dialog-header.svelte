<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';

	let { children, ...restProps } = $props();
</script>

<Dialog.Header {...restProps}>
	{@render children?.()}
</Dialog.Header>
