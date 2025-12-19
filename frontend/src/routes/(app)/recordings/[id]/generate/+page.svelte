<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { ArrowLeft } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { WorkflowPreview } from '$lib/components/process-recorder';
	import { getRecording, type Recording } from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	const recordingId = $derived(parseInt($page.params.id ?? '0'));

	let recording = $state<Recording | null>(null);
	let loading = $state(true);

	onMount(async () => {
		const { data, error } = await tryCatch(getRecording(recordingId));
		loading = false;

		if (error) {
			toast.error('Failed to load recording');
			goto('/recordings');
			return;
		}

		if (data.status !== 'completed') {
			toast.error('Recording must be completed to generate a workflow');
			goto(`/recordings/${recordingId}`);
			return;
		}

		if ((data.steps?.length ?? 0) === 0) {
			toast.error('Recording has no steps to convert');
			goto(`/recordings/${recordingId}`);
			return;
		}

		recording = data;
	});
</script>

<svelte:head>
	<title>Generate Workflow | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6 max-w-3xl">
	<!-- Header -->
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" onclick={() => goto(`/recordings/${recordingId}`)}>
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold">Generate Workflow</h1>
			<p class="text-muted-foreground">
				Convert recording {recording?.name ? `"${recording.name}"` : `#${recordingId}`} into an automated workflow
			</p>
		</div>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if recording}
		<WorkflowPreview {recording} />
	{/if}
</div>
