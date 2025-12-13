<script lang="ts">
	import { CadenceBuilder } from '$lib/components/cadences';
	import type { Cadence } from '$lib/api/cadences';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft } from 'lucide-svelte';

	function handleSave(cadence: Cadence) {
		goto(`/marketing/cadences/${cadence.id}`);
	}

	function handleCancel() {
		goto('/marketing/cadences');
	}
</script>

<svelte:head>
	<title>Create Cadence | VRTX</title>
</svelte:head>

<div class="container mx-auto max-w-3xl space-y-6 p-6">
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" href="/marketing/cadences">
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold tracking-tight">Create Cadence</h1>
			<p class="text-muted-foreground">Set up a new outreach sequence</p>
		</div>
	</div>

	<CadenceBuilder onSave={handleSave} onCancel={handleCancel} />
</div>
