<script lang="ts">
	import { onMount } from 'svelte';
	import { Circle, Plus } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { RecordingsList, RecordingControls, RecordingIndicator } from '$lib/components/process-recorder';
	import { PluginGate } from '$lib/components/billing';
	import { getActiveRecording, type Recording } from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';

	let activeRecording = $state<Recording | null>(null);
	let loading = $state(true);
	let showRecordingIndicator = $state(false);

	onMount(async () => {
		const { data } = await tryCatch(getActiveRecording());
		loading = false;

		if (data?.is_recording) {
			activeRecording = data.data;
			showRecordingIndicator = true;
		}
	});

	function handleStarted(recording: Recording) {
		activeRecording = recording;
		showRecordingIndicator = true;
	}

	function handleStopped(recording: Recording) {
		activeRecording = null;
		showRecordingIndicator = false;
	}

	function handlePaused(recording: Recording) {
		activeRecording = recording;
	}

	function handleResumed(recording: Recording) {
		activeRecording = recording;
	}
</script>

<svelte:head>
	<title>Process Recorder | VRTX</title>
</svelte:head>

<PluginGate
	plugin="process-recorder"
	title="Process Recorder"
	description="Record your CRM workflows and automatically generate automation rules from your actions."
>
<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Process Recorder</h1>
			<p class="text-muted-foreground">Record your actions and turn them into automated workflows</p>
		</div>
	</div>

	<!-- Recording Controls -->
	<div class="rounded-lg border p-4 bg-card">
		<h2 class="font-semibold mb-4 flex items-center gap-2">
			<Circle class="h-4 w-4 fill-red-500 text-red-500" />
			Start New Recording
		</h2>
		<RecordingControls
			currentRecording={activeRecording}
			onStarted={handleStarted}
			onStopped={handleStopped}
			onPaused={handlePaused}
			onResumed={handleResumed}
		/>
		<p class="text-sm text-muted-foreground mt-3">
			Select a module to focus the recording, or leave blank to record actions across all modules.
			Once started, perform your CRM actions and they'll be captured automatically.
		</p>
	</div>

	<!-- Recordings List -->
	<div>
		<h2 class="font-semibold mb-4">Past Recordings</h2>
		<RecordingsList />
	</div>
</div>

<!-- Floating Recording Indicator -->
{#if showRecordingIndicator && activeRecording}
	<RecordingIndicator
		recording={activeRecording}
		onStopped={handleStopped}
		onPaused={handlePaused}
		onResumed={handleResumed}
	/>
{/if}
</PluginGate>
