<script lang="ts">
	import { goto } from '$app/navigation';
	import { permissions, isLoaded } from '$lib/stores/permissions';
	import { get } from 'svelte/store';
	import { ShieldAlert } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';

	interface Props {
		/**
		 * System permission required (e.g., 'users.view', 'settings.edit')
		 */
		permission?: string;
		/**
		 * Multiple permissions - user must have ALL
		 */
		permissions?: string[];
		/**
		 * Multiple permissions - user must have ANY
		 */
		anyPermission?: string[];
		/**
		 * Required role
		 */
		role?: string;
		/**
		 * Module access check (e.g., { module: 'leads', action: 'view' })
		 */
		module?: {
			name: string;
			action: 'view' | 'create' | 'edit' | 'delete' | 'export' | 'import';
		};
		/**
		 * Redirect path if access denied (default: shows access denied message)
		 */
		redirectTo?: string;
		/**
		 * Custom message for access denied
		 */
		deniedMessage?: string;
		/**
		 * Children to render if authorized
		 */
		children?: import('svelte').Snippet;
		/**
		 * Fallback content if not authorized (instead of default denied UI)
		 */
		fallback?: import('svelte').Snippet;
	}

	let {
		permission,
		permissions: requiredPermissions,
		anyPermission,
		role,
		module,
		redirectTo,
		deniedMessage = "You don't have permission to access this page.",
		children,
		fallback
	}: Props = $props();

	let hasAccess = $derived.by(() => {
		const loaded = get(isLoaded);
		if (!loaded) return false;

		const store = permissions;

		// Check single permission
		if (permission && !store.hasPermission(permission)) {
			return false;
		}

		// Check ALL permissions
		if (requiredPermissions?.length) {
			const hasAll = requiredPermissions.every((p) => store.hasPermission(p));
			if (!hasAll) return false;
		}

		// Check ANY permission
		if (anyPermission?.length) {
			const hasAny = anyPermission.some((p) => store.hasPermission(p));
			if (!hasAny) return false;
		}

		// Check role
		if (role && !store.hasRole(role)) {
			return false;
		}

		// Check module access
		if (module && !store.canAccessModule(module.name, module.action)) {
			return false;
		}

		return true;
	});

	let isLoading = $derived(!get(isLoaded));

	$effect(() => {
		if (!isLoading && !hasAccess && redirectTo) {
			goto(redirectTo);
		}
	});
</script>

{#if isLoading}
	<div class="flex min-h-[200px] items-center justify-center">
		<div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
	</div>
{:else if hasAccess}
	{#if children}
		{@render children()}
	{/if}
{:else if !redirectTo}
	{#if fallback}
		{@render fallback()}
	{:else}
		<div class="flex min-h-[400px] flex-col items-center justify-center gap-4 text-center">
			<div class="rounded-full bg-destructive/10 p-4">
				<ShieldAlert class="h-12 w-12 text-destructive" />
			</div>
			<div class="space-y-2">
				<h2 class="text-xl font-semibold">Access Denied</h2>
				<p class="text-muted-foreground max-w-md">{deniedMessage}</p>
			</div>
			<Button variant="outline" onclick={() => history.back()}>
				Go Back
			</Button>
		</div>
	{/if}
{/if}
