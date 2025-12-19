<script lang="ts">
	import { Circle, Square, Pause, Play, Loader2 } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { startRecording, stopRecording, pauseRecording, resumeRecording, type Recording } from '$lib/api/recordings';
	import { getModules, type Module } from '$lib/api/modules';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { onMount } from 'svelte';

	interface Props {
		currentRecording?: Recording | null;
		onStarted?: (recording: Recording) => void;
		onStopped?: (recording: Recording) => void;
		onPaused?: (recording: Recording) => void;
		onResumed?: (recording: Recording) => void;
	}

	let {
		currentRecording = null,
		onStarted,
		onStopped,
		onPaused,
		onResumed,
	}: Props = $props();

	let modules = $state<Module[]>([]);
	let selectedModuleId = $state<number | null>(null);
	let loading = $state(false);
	let loadingModules = $state(true);

	const isRecording = $derived(currentRecording?.status === 'recording');
	const isPaused = $derived(currentRecording?.status === 'paused');
	const hasActiveSession = $derived(currentRecording !== null);

	onMount(async () => {
		const { data } = await tryCatch(getModules());
		loadingModules = false;
		modules = data ?? [];
	});

	async function handleStart() {
		loading = true;
		const { data, error } = await tryCatch(startRecording(selectedModuleId ?? undefined));
		loading = false;

		if (error) {
			toast.error('Failed to start recording');
			return;
		}

		toast.success('Recording started - perform your actions');
		onStarted?.(data);
	}

	async function handleStop() {
		if (!currentRecording) return;

		loading = true;
		const { data, error } = await tryCatch(stopRecording(currentRecording.id));
		loading = false;

		if (error) {
			toast.error('Failed to stop recording');
			return;
		}

		toast.success('Recording stopped');
		onStopped?.(data);
	}

	async function handlePause() {
		if (!currentRecording) return;

		loading = true;
		const { data, error } = await tryCatch(pauseRecording(currentRecording.id));
		loading = false;

		if (error) {
			toast.error('Failed to pause recording');
			return;
		}

		toast.success('Recording paused');
		onPaused?.(data);
	}

	async function handleResume() {
		if (!currentRecording) return;

		loading = true;
		const { data, error } = await tryCatch(resumeRecording(currentRecording.id));
		loading = false;

		if (error) {
			toast.error('Failed to resume recording');
			return;
		}

		toast.success('Recording resumed');
		onResumed?.(data);
	}
</script>

<div class="flex items-center gap-4">
	{#if !hasActiveSession}
		<!-- Module selector -->
		<div class="w-48">
			<Select.Root
				type="single"
				value={selectedModuleId?.toString() ?? ''}
				onValueChange={(val) => (selectedModuleId = val ? parseInt(val) : null)}
			>
				<Select.Trigger disabled={loadingModules}>
					<span>{selectedModuleId ? modules.find(m => m.id === selectedModuleId)?.name : 'Select module (optional)'}</span>
				</Select.Trigger>
				<Select.Content>
					{#each modules as module}
						<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>

		<!-- Start button -->
		<Button onclick={handleStart} disabled={loading} class="gap-2">
			{#if loading}
				<Loader2 class="h-4 w-4 animate-spin" />
			{:else}
				<Circle class="h-4 w-4 fill-red-500 text-red-500" />
			{/if}
			Start Recording
		</Button>
	{:else}
		<!-- Recording controls -->
		<div class="flex items-center gap-2">
			{#if isRecording}
				<Button variant="outline" onclick={handlePause} disabled={loading} class="gap-2">
					<Pause class="h-4 w-4" />
					Pause
				</Button>
			{:else if isPaused}
				<Button variant="outline" onclick={handleResume} disabled={loading} class="gap-2">
					<Play class="h-4 w-4" />
					Resume
				</Button>
			{/if}

			<Button variant="destructive" onclick={handleStop} disabled={loading} class="gap-2">
				{#if loading}
					<Loader2 class="h-4 w-4 animate-spin" />
				{:else}
					<Square class="h-4 w-4" />
				{/if}
				Stop Recording
			</Button>
		</div>

		<!-- Status -->
		<div class="flex items-center gap-2 text-sm text-muted-foreground">
			{#if isRecording}
				<div class="relative">
					<Circle class="h-2 w-2 fill-red-500 text-red-500" />
					<Circle class="h-2 w-2 fill-red-500 text-red-500 absolute inset-0 animate-ping opacity-75" />
				</div>
				<span>Recording...</span>
			{:else if isPaused}
				<Circle class="h-2 w-2 fill-amber-500 text-amber-500" />
				<span>Paused</span>
			{/if}
			<span>({currentRecording?.step_count ?? 0} actions)</span>
		</div>
	{/if}
</div>
