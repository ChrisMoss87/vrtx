<script lang="ts">
	import { PermissionGuard } from '$lib/components/guards';

	let { children } = $props();
</script>

<PermissionGuard
	anyPermission={['settings.view', 'settings.edit']}
	deniedMessage="You don't have permission to access settings. Contact your administrator for access."
	{children}
/>
