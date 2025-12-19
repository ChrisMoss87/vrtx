<script lang="ts">
	import PermissionGuard from './PermissionGuard.svelte';

	interface Props {
		/**
		 * Redirect path if not admin (default: shows access denied)
		 */
		redirectTo?: string;
		/**
		 * Children to render if authorized
		 */
		children?: import('svelte').Snippet;
	}

	let { redirectTo, children }: Props = $props();
</script>

<PermissionGuard
	role="admin"
	{redirectTo}
	deniedMessage="This page requires administrator privileges."
>
	{#snippet children()}
		{#if children}
			{@render children()}
		{/if}
	{/snippet}
</PermissionGuard>
