<script lang="ts">
	import { Circle, Pause, Square, ChevronDown } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { stopRecording, pauseRecording, resumeRecording, type Recording } from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { createEventDispatcher } from 'svelte';
	import { goto } from '$app/navigation';

	export let recording: Recording;

	const dispatch = createEventDispatcher<{
		stopped: Recording;
		paused: Recording;
		resumed: Recording;
	}>();

	let loading = false;

	$: isPaused = recording.status === 'paused';
	$: isRecording = recording.status === 'recording';

	async function handleStop() {
		loading = true;
		const { data, error } = await tryCatch(stopRecording(recording.id));
		loading = false;

		if (error) {
			toast.error('Failed to stop recording');
			return;
		}

		toast.success('Recording stopped');
		dispatch('stopped', data);
		goto(`/recordings/${recording.id}`);
	}

	async function handlePause() {
		loading = true;
		const { data, error } = await tryCatch(pauseRecording(recording.id));
		loading = false;

		if (error) {
			toast.error('Failed to pause recording');
			return;
		}

		toast.success('Recording paused');
		dispatch('paused', data);
	}

	async function handleResume() {
		loading = true;
		const { data, error } = await tryCatch(resumeRecording(recording.id));
		loading = false;

		if (error) {
			toast.error('Failed to resume recording');
			return;
		}

		toast.success('Recording resumed');
		dispatch('resumed', data);
	}

	function formatDuration(): string {
		if (!recording.started_at) return '0:00';
		const start = new Date(recording.started_at);
		const now = new Date();
		const seconds = Math.floor((now.getTime() - start.getTime()) / 1000);
		const mins = Math.floor(seconds / 60);
		const secs = seconds % 60;
		return `${mins}:${secs.toString().padStart(2, '0')}`;
	}
</script>

<div class="fixed bottom-4 right-4 z-50 animate-in slide-in-from-bottom-4 fade-in">
	<div class="flex items-center gap-2 bg-background border rounded-lg shadow-lg p-2 pr-3">
		<!-- Recording indicator -->
		<div class="flex items-center gap-2 px-2">
			{#if isRecording}
				<div class="relative">
					<Circle class="h-3 w-3 fill-red-500 text-red-500" />
					<Circle class="h-3 w-3 fill-red-500 text-red-500 absolute inset-0 animate-ping opacity-75" />
				</div>
				<span class="text-sm font-medium text-red-600">Recording</span>
			{:else}
				<Circle class="h-3 w-3 fill-amber-500 text-amber-500" />
				<span class="text-sm font-medium text-amber-600">Paused</span>
			{/if}
		</div>

		<!-- Step count -->
		<div class="text-sm text-muted-foreground border-l pl-2">
			{recording.step_count} action{recording.step_count !== 1 ? 's' : ''}
		</div>

		<!-- Duration -->
		<div class="text-sm text-muted-foreground tabular-nums">
			{formatDuration()}
		</div>

		<!-- Controls -->
		<div class="flex items-center gap-1 border-l pl-2 ml-1">
			{#if isRecording}
				<Button variant="ghost" size="icon" class="h-7 w-7" onclick={handlePause} disabled={loading}>
					<Pause class="h-4 w-4" />
				</Button>
			{:else}
				<Button variant="ghost" size="icon" class="h-7 w-7" onclick={handleResume} disabled={loading}>
					<Circle class="h-4 w-4 fill-current" />
				</Button>
			{/if}

			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					{#snippet child({ props })}
						<Button {...props} variant="ghost" size="icon" class="h-7 w-7">
							<ChevronDown class="h-4 w-4" />
						</Button>
					{/snippet}
				</DropdownMenu.Trigger>
				<DropdownMenu.Content align="end">
					<DropdownMenu.Item onclick={handleStop} class="text-red-600">
						<Square class="h-4 w-4 mr-2" />
						Stop Recording
					</DropdownMenu.Item>
					<DropdownMenu.Separator />
					<DropdownMenu.Item onclick={() => goto(`/recordings/${recording.id}`)}>
						View Details
					</DropdownMenu.Item>
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</div>
	</div>
</div>
