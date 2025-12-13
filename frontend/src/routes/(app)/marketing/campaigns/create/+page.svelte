<script lang="ts">
	import { CampaignBuilder } from '$lib/components/campaigns';
	import type { Campaign } from '$lib/api/campaigns';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft } from 'lucide-svelte';

	function handleSave(campaign: Campaign) {
		goto(`/marketing/campaigns/${campaign.id}`);
	}

	function handleCancel() {
		goto('/marketing/campaigns');
	}
</script>

<svelte:head>
	<title>Create Campaign | VRTX</title>
</svelte:head>

<div class="container mx-auto max-w-3xl space-y-6 p-6">
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" href="/marketing/campaigns">
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold tracking-tight">Create Campaign</h1>
			<p class="text-muted-foreground">Set up a new marketing campaign</p>
		</div>
	</div>

	<CampaignBuilder onSave={handleSave} onCancel={handleCancel} />
</div>
