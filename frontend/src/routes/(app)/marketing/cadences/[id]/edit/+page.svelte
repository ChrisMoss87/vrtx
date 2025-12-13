<script lang="ts">
	import { page } from '$app/stores';
	import type { Cadence } from '$lib/api/cadences';
	import { getCadence } from '$lib/api/cadences';
	import { CadenceBuilder } from '$lib/components/cadences';
	import { Button } from '$lib/components/ui/button';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';
	import { ArrowLeft } from 'lucide-svelte';

	const cadenceId = $derived(parseInt($page.params.id ?? '0'));

	let loading = $state(true);
	let cadence = $state<Cadence | null>(null);

	async function loadCadence() {
		loading = true;
		try {
			const result = await getCadence(cadenceId);
			cadence = result.cadence;
		} catch (error) {
			console.error('Failed to load cadence:', error);
			toast.error('Failed to load cadence');
		} finally {
			loading = false;
		}
	}

	function handleSave(updatedCadence: Cadence) {
		goto(`/marketing/cadences/${updatedCadence.id}`);
	}

	function handleCancel() {
		goto(`/marketing/cadences/${cadenceId}`);
	}

	$effect(() => {
		loadCadence();
	});
</script>

<svelte:head>
	<title>Edit Cadence | VRTX</title>
</svelte:head>

<div class="container mx-auto max-w-3xl space-y-6 p-6">
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" href={`/marketing/cadences/${cadenceId}`}>
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold tracking-tight">Edit Cadence</h1>
			<p class="text-muted-foreground">Update cadence settings</p>
		</div>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if cadence}
		<CadenceBuilder {cadence} onSave={handleSave} onCancel={handleCancel} />
	{:else}
		<div class="rounded-lg border border-dashed p-8 text-center">
			<p class="text-muted-foreground">Cadence not found</p>
			<Button class="mt-4" href="/marketing/cadences">Back to Cadences</Button>
		</div>
	{/if}
</div>
