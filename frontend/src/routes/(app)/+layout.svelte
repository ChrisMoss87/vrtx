<script lang="ts">
	import '../../app.css';
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { browser } from '$app/environment';
	import favicon from '$lib/assets/favicon.svg';
	import AppSidebar from '$lib/components/app-sidebar.svelte';
	import CommandPalette from '$lib/components/command-palette/CommandPalette.svelte';
	import { authStore } from '$lib/stores/auth.svelte';
	import { authApi } from '$lib/api/auth';
	import { license } from '$lib/stores/license';
	import { permissions } from '$lib/stores/permissions';
	import { sidebarStyle } from '$lib/stores/sidebar';
	import { TooltipProvider } from '$lib/components/ui/tooltip';

	let { children } = $props();
	let checkingAuth = $state(true);

	onMount(async () => {
		// Check if user is authenticated
		if (!authStore.isAuthenticated) {
			// No stored auth, redirect to login
			const currentPath = window.location.pathname;
			goto(`/login?redirect=${encodeURIComponent(currentPath)}`);
			return;
		}

		// Verify the token is still valid by calling /auth/me
		try {
			const response = await authApi.me();
			// Update the auth store with fresh user data
			if (response.data && authStore.token) {
				authStore.setAuth(response.data, authStore.token);
			}
		} catch (error: any) {
			// Token is invalid or expired - the API client will handle 401 redirect
			console.error('Auth check failed:', error);
			return;
		}

		// Load license/subscription info, permissions, and sidebar preference in parallel
		try {
			await Promise.all([
				license.load(),
				permissions.load(),
				sidebarStyle.load()
			]);
		} catch (error) {
			console.error('Failed to load app data:', error);
		}

		checkingAuth = false;
	});
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
</svelte:head>

{#if checkingAuth}
	<div class="flex min-h-screen items-center justify-center animate-fade-in">
		<div class="flex flex-col items-center gap-3">
			<div class="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent shadow-soft"></div>
			<span class="text-sm text-muted-foreground animate-pulse">Loading...</span>
		</div>
	</div>
{:else}
	<CommandPalette />
	<TooltipProvider>
		<div class="flex h-screen overflow-hidden">
			<AppSidebar />
			<main class="flex-1 overflow-auto bg-muted/30">
				<div class="p-6">
					{@render children()}
				</div>
			</main>
		</div>
	</TooltipProvider>
{/if}
