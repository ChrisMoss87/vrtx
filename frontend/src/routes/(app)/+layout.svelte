<script lang="ts">
	import '../../app.css';
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { browser } from '$app/environment';
	import favicon from '$lib/assets/favicon.svg';
	import { Separator } from '$lib/components/ui/separator';
	import AppSidebar from '$lib/components/app-sidebar.svelte';
	import CommandPalette from '$lib/components/command-palette/CommandPalette.svelte';
	import { authStore } from '$lib/stores/auth.svelte';
	import { authApi } from '$lib/api/auth';
	import { license } from '$lib/stores/license';
	import {
		Provider as SidebarProvider,
		Inset as SidebarInset,
		Trigger as SidebarTrigger
	} from '$lib/components/ui/sidebar';
	import {
		Root as BreadcrumbRoot,
		List as BreadcrumbList,
		Link as BreadcrumbLink,
		Separator as BreadcrumbSeparator,
		Item as BreadcrumbItem,
		Page as BreadcrumbPage
	} from '$lib/components/ui/breadcrumb';

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

		// Load license/subscription info
		await license.load();

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
	<SidebarProvider>
		<AppSidebar />
		<SidebarInset>
			<header
				class="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 bg-background/95 backdrop-blur-sm border-b border-border/50 transition-all duration-200 ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
			>
				<div class="flex items-center gap-2 px-4">
					<SidebarTrigger class="-ml-1" />
					<Separator orientation="vertical" class="mr-2 data-[orientation=vertical]:h-4" />
					<BreadcrumbRoot>
						<BreadcrumbList>
							<BreadcrumbItem class="hidden md:block">
								<BreadcrumbLink href="##">Building Your Application</BreadcrumbLink>
							</BreadcrumbItem>
							<BreadcrumbSeparator class="hidden md:block" />
							<BreadcrumbItem>
								<BreadcrumbPage>Data Fetching</BreadcrumbPage>
							</BreadcrumbItem>
						</BreadcrumbList>
					</BreadcrumbRoot>
				</div>
			</header>
			<div class="flex flex-1 flex-col gap-4 p-4 pt-0">
				{@render children()}
			</div>
		</SidebarInset>
	</SidebarProvider>
{/if}
