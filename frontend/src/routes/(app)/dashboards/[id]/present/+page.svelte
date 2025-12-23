<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { toast } from 'svelte-sonner';
	import { dashboardsApi, type Dashboard } from '$lib/api/dashboards';
	import PresentationMode from '$lib/components/dashboard/PresentationMode.svelte';
	import { Loader2 } from 'lucide-svelte';

	let dashboard = $state<Dashboard | null>(null);
	let widgetData = $state<Record<number, unknown>>({});
	let loading = $state(true);

	const dashboardId = $derived(Number($page.params.id));

	onMount(async () => {
		await loadDashboard();
	});

	async function loadDashboard() {
		loading = true;
		try {
			dashboard = await dashboardsApi.get(dashboardId);
			widgetData = await dashboardsApi.getAllWidgetData(dashboardId);
		} catch (error) {
			console.error('Failed to load dashboard:', error);
			toast.error('Failed to load dashboard');
			goto(`/dashboards/${dashboardId}`);
		} finally {
			loading = false;
		}
	}

	function handleClose() {
		goto(`/dashboards/${dashboardId}`);
	}
</script>

<svelte:head>
	<title>{dashboard?.name || 'Presentation'} | VRTX CRM</title>
</svelte:head>

{#if loading}
	<div class="fixed inset-0 flex items-center justify-center bg-background">
		<div class="text-center space-y-4">
			<Loader2 class="h-12 w-12 animate-spin text-muted-foreground mx-auto" />
			<p class="text-muted-foreground">Loading presentation...</p>
		</div>
	</div>
{:else if dashboard?.widgets && dashboard.widgets.length > 0}
	<PresentationMode
		widgets={dashboard.widgets}
		{widgetData}
		onClose={handleClose}
	/>
{:else}
	<div class="fixed inset-0 flex items-center justify-center bg-background">
		<div class="text-center space-y-4">
			<p class="text-muted-foreground">No widgets to present</p>
			<button
				type="button"
				class="text-primary hover:underline"
				onclick={handleClose}
			>
				Back to dashboard
			</button>
		</div>
	</div>
{/if}
