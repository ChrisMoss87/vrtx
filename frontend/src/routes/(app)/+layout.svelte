<script lang="ts">
	import '../../app.css';
	import favicon from '$lib/assets/favicon.svg';
	import { Separator } from '$lib/components/ui/separator';
	import AppSidebar from '$lib/components/app-sidebar.svelte';
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
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
</svelte:head>

<SidebarProvider>
	<AppSidebar />
	<SidebarInset>
		<header
			class="sticky top-0 flex h-16 shrink-0 items-center gap-2 bg-background transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
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
