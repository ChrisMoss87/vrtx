<script lang="ts">
	import { PermissionGuard } from '$lib/components/guards';

	let { children } = $props();
</script>

<PermissionGuard
	anyPermission={[
		'settings.view',
		'settings.edit',
		'users.view',
		'roles.view',
		'blueprints.view',
		'webhooks.view',
		'api_keys.view',
		'activity.view'
	]}
	deniedMessage="You don't have permission to access the admin area. Contact your administrator for access."
	{children}
/>
