<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { ArrowLeft, Zap, Trash2, Copy, Play, Pause, Square } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { RecordingSummary } from '$lib/components/process-recorder';
	import {
		getRecording,
		deleteRecording,
		duplicateRecording,
		stopRecording,
		pauseRecording,
		resumeRecording,
		type Recording,
		type RecordingStep
	} from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	const recordingId = $derived(parseInt($page.params.id ?? '0'));

	let recording = $state<Recording | null>(null);
	let steps = $state<RecordingStep[]>([]);
	let loading = $state(true);
	let editingName = $state(false);
	let tempName = $state('');

	onMount(async () => {
		await loadRecording();
	});

	async function loadRecording() {
		loading = true;
		const { data, error } = await tryCatch(getRecording(recordingId));
		loading = false;

		if (error) {
			toast.error('Failed to load recording');
			goto('/recordings');
			return;
		}

		recording = data;
		steps = data.steps ?? [];
		tempName = data.name ?? '';
	}

	async function handleDelete() {
		if (!confirm('Are you sure you want to delete this recording?')) return;

		const { error } = await tryCatch(deleteRecording(recordingId));

		if (error) {
			toast.error('Failed to delete recording');
			return;
		}

		toast.success('Recording deleted');
		goto('/recordings');
	}

	async function handleDuplicate() {
		const { data, error } = await tryCatch(duplicateRecording(recordingId));

		if (error) {
			toast.error('Failed to duplicate recording');
			return;
		}

		toast.success('Recording duplicated');
		goto(`/recordings/${data.id}`);
	}

	async function handleStop() {
		const { data, error } = await tryCatch(stopRecording(recordingId, tempName || undefined));

		if (error) {
			toast.error('Failed to stop recording');
			return;
		}

		recording = data;
		steps = data.steps ?? [];
		toast.success('Recording stopped');
	}

	async function handlePause() {
		const { data, error } = await tryCatch(pauseRecording(recordingId));

		if (error) {
			toast.error('Failed to pause recording');
			return;
		}

		recording = data;
		toast.success('Recording paused');
	}

	async function handleResume() {
		const { data, error } = await tryCatch(resumeRecording(recordingId));

		if (error) {
			toast.error('Failed to resume recording');
			return;
		}

		recording = data;
		toast.success('Recording resumed');
	}

	function handleStepRemoved(stepId: number) {
		steps = steps.filter(s => s.id !== stepId);
		if (recording) {
			recording.step_count = steps.length;
		}
	}

	function handleStepParameterized(step: RecordingStep) {
		steps = steps.map(s => s.id === step.id ? step : s);
	}

	function getStatusLabel(status: string): string {
		return status.charAt(0).toUpperCase() + status.slice(1);
	}

	function getStatusColor(status: string): string {
		switch (status) {
			case 'recording': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
			case 'paused': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
			case 'completed': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
			case 'converted': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
			default: return 'bg-muted text-muted-foreground';
		}
	}

	function formatDuration(seconds: number | null): string {
		if (seconds === null) return '-';
		if (seconds < 60) return `${seconds} seconds`;
		const mins = Math.floor(seconds / 60);
		const secs = seconds % 60;
		return `${mins}m ${secs}s`;
	}

	function formatDate(dateStr: string): string {
		const date = new Date(dateStr);
		return date.toLocaleString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric',
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	const isActive = $derived(recording?.status === 'recording' || recording?.status === 'paused');
	const canGenerateWorkflow = $derived(recording?.status === 'completed' && steps.length > 0);
</script>

<svelte:head>
	<title>{recording?.name || `Recording #${recordingId}`} | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if recording}
		<!-- Header -->
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/recordings')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					{#if editingName}
						<Input
							bind:value={tempName}
							class="text-xl font-bold"
							onblur={() => (editingName = false)}
							onkeydown={(e) => e.key === 'Enter' && (editingName = false)}
						/>
					{:else}
						<h1
							class="text-2xl font-bold cursor-pointer hover:text-primary"
							onclick={() => {
								editingName = true;
								tempName = recording?.name ?? '';
							}}
						>
							{recording.name || `Recording #${recording.id}`}
						</h1>
					{/if}
					<div class="flex items-center gap-3 mt-1">
						<span class="px-2 py-0.5 text-xs rounded-full {getStatusColor(recording.status)}">
							{getStatusLabel(recording.status)}
						</span>
						<span class="text-sm text-muted-foreground">
							{steps.length} action{steps.length !== 1 ? 's' : ''}
						</span>
						{#if recording.module_name}
							<span class="text-sm text-muted-foreground">
								Module: {recording.module_name}
							</span>
						{/if}
					</div>
				</div>
			</div>

			<div class="flex items-center gap-2">
				{#if isActive}
					{#if recording.status === 'recording'}
						<Button variant="outline" onclick={handlePause}>
							<Pause class="h-4 w-4 mr-2" />
							Pause
						</Button>
					{:else}
						<Button variant="outline" onclick={handleResume}>
							<Play class="h-4 w-4 mr-2" />
							Resume
						</Button>
					{/if}
					<Button variant="destructive" onclick={handleStop}>
						<Square class="h-4 w-4 mr-2" />
						Stop
					</Button>
				{:else}
					{#if canGenerateWorkflow}
						<Button onclick={() => goto(`/recordings/${recording?.id}/generate`)}>
							<Zap class="h-4 w-4 mr-2" />
							Generate Workflow
						</Button>
					{/if}
					<Button variant="outline" onclick={handleDuplicate}>
						<Copy class="h-4 w-4 mr-2" />
						Duplicate
					</Button>
					<Button variant="outline" onclick={handleDelete}>
						<Trash2 class="h-4 w-4 mr-2" />
						Delete
					</Button>
				{/if}
			</div>
		</div>

		<!-- Info Cards -->
		<div class="grid gap-4 md:grid-cols-4">
			<div class="rounded-lg border p-4">
				<div class="text-sm text-muted-foreground">Started</div>
				<div class="font-medium mt-1">{formatDate(recording.started_at)}</div>
			</div>
			{#if recording.ended_at}
				<div class="rounded-lg border p-4">
					<div class="text-sm text-muted-foreground">Ended</div>
					<div class="font-medium mt-1">{formatDate(recording.ended_at)}</div>
				</div>
			{/if}
			<div class="rounded-lg border p-4">
				<div class="text-sm text-muted-foreground">Duration</div>
				<div class="font-medium mt-1">{formatDuration(recording.duration)}</div>
			</div>
			{#if recording.workflow_id}
				<div class="rounded-lg border p-4">
					<div class="text-sm text-muted-foreground">Generated Workflow</div>
					<Button variant="link" class="p-0 h-auto font-medium" onclick={() => goto(`/admin/workflows/${recording?.workflow_id}`)}>
						View Workflow #{recording.workflow_id}
					</Button>
				</div>
			{/if}
		</div>

		<!-- Steps -->
		<div class="rounded-lg border p-4">
			<h2 class="font-semibold mb-4">Captured Actions</h2>
			<RecordingSummary
				{recording}
				{steps}
				onStepRemoved={handleStepRemoved}
				onStepParameterized={handleStepParameterized}
			/>
		</div>

		<!-- Help text for completed recordings -->
		{#if canGenerateWorkflow}
			<div class="rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30 p-4">
				<h3 class="font-medium text-blue-800 dark:text-blue-300">Ready to create a workflow?</h3>
				<p class="text-sm text-blue-700 dark:text-blue-400 mt-1">
					Review your captured actions above. You can parameterize specific values (like emails or user IDs)
					to make the workflow work for any record. When ready, click "Generate Workflow" to convert this
					recording into an automated workflow.
				</p>
			</div>
		{/if}
	{/if}
</div>
