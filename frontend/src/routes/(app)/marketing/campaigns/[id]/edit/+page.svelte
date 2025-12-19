<script lang="ts">
	import { page } from '$app/stores';
	import type { Campaign } from '$lib/api/campaigns';
	import { getCampaign } from '$lib/api/campaigns';
	import { CampaignBuilder } from '$lib/components/campaigns';
	import { Button } from '$lib/components/ui/button';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';
	import { ArrowLeft } from 'lucide-svelte';

	const campaignId = $derived(parseInt($page.params.id ?? '0'));

	let loading = $state(true);
	let campaign = $state<Campaign | null>(null);

	async function loadCampaign() {
		loading = true;
		try {
			const result = await getCampaign(campaignId);
			campaign = result.campaign;
		} catch (error) {
			console.error('Failed to load campaign:', error);
			toast.error('Failed to load campaign');
		} finally {
			loading = false;
		}
	}

	function handleSave(updatedCampaign: Campaign) {
		goto(`/marketing/campaigns/${updatedCampaign.id}`);
	}

	function handleCancel() {
		goto(`/marketing/campaigns/${campaignId}`);
	}

	$effect(() => {
		loadCampaign();
	});
</script>

<svelte:head>
	<title>Edit Campaign | VRTX</title>
</svelte:head>

<div class="container mx-auto max-w-3xl space-y-6 p-6">
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" href={`/marketing/campaigns/${campaignId}`}>
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold tracking-tight">Edit Campaign</h1>
			<p class="text-muted-foreground">Update campaign settings</p>
		</div>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if campaign}
		<CampaignBuilder {campaign} onSave={handleSave} onCancel={handleCancel} />
	{:else}
		<div class="rounded-lg border border-dashed p-8 text-center">
			<p class="text-muted-foreground">Campaign not found</p>
			<Button class="mt-4" href="/marketing/campaigns">Back to Campaigns</Button>
		</div>
	{/if}
</div>
