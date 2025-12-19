<script lang="ts">
	import { onMount } from 'svelte';
	import { Circle, Clock, Zap, MoreVertical, Trash2, Copy, Eye } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Select from '$lib/components/ui/select';
	import { getRecordings, deleteRecording, duplicateRecording, type Recording } from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';

	let recordings = $state<Recording[]>([]);
	let loading = $state(true);
	let statusFilter = $state<string | null>(null);

	onMount(async () => {
		await loadRecordings();
	});

	async function loadRecordings() {
		loading = true;
		const { data, error } = await tryCatch(getRecordings(statusFilter ?? undefined));
		loading = false;

		if (error) {
			toast.error('Failed to load recordings');
			return;
		}

		recordings = data ?? [];
	}

	async function handleDelete(id: number) {
		const { error } = await tryCatch(deleteRecording(id));

		if (error) {
			toast.error('Failed to delete recording');
			return;
		}

		toast.success('Recording deleted');
		recordings = recordings.filter(r => r.id !== id);
	}

	async function handleDuplicate(id: number) {
		const { data, error } = await tryCatch(duplicateRecording(id));

		if (error) {
			toast.error('Failed to duplicate recording');
			return;
		}

		toast.success('Recording duplicated');
		recordings = [data, ...recordings];
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
		if (seconds < 60) return `${seconds}s`;
		const mins = Math.floor(seconds / 60);
		const secs = seconds % 60;
		return `${mins}m ${secs}s`;
	}

	function formatDate(dateStr: string): string {
		const date = new Date(dateStr);
		return date.toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	const filteredRecordings = $derived(statusFilter
		? recordings.filter(r => r.status === statusFilter)
		: recordings);
</script>

<div class="space-y-4">
	<!-- Filters -->
	<div class="flex items-center gap-4">
		<div class="w-48">
			<Select.Root
				type="single"
				value={statusFilter ?? ''}
				onValueChange={(val) => {
					statusFilter = val || null;
					loadRecordings();
				}}
			>
				<Select.Trigger>
					<span>{statusFilter ? statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1) : 'All statuses'}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="">All statuses</Select.Item>
					<Select.Item value="completed">Completed</Select.Item>
					<Select.Item value="converted">Converted</Select.Item>
					<Select.Item value="recording">Recording</Select.Item>
					<Select.Item value="paused">Paused</Select.Item>
				</Select.Content>
			</Select.Root>
		</div>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if filteredRecordings.length === 0}
		<div class="text-center py-12 text-muted-foreground">
			<Circle class="h-12 w-12 mx-auto mb-3 opacity-50" />
			<p>No recordings found</p>
			<p class="text-sm mt-1">Start a recording to capture your CRM actions</p>
		</div>
	{:else}
		<div class="rounded-lg border">
			<table class="w-full">
				<thead>
					<tr class="border-b bg-muted/50">
						<th class="text-left p-3 font-medium text-sm">Recording</th>
						<th class="text-left p-3 font-medium text-sm">Status</th>
						<th class="text-center p-3 font-medium text-sm">Actions</th>
						<th class="text-center p-3 font-medium text-sm">Duration</th>
						<th class="text-right p-3 font-medium text-sm">Started</th>
						<th class="w-12"></th>
					</tr>
				</thead>
				<tbody>
					{#each filteredRecordings as recording (recording.id)}
						<tr class="border-b last:border-0 hover:bg-muted/30">
							<td class="p-3">
								<button
									class="text-left hover:underline"
									onclick={() => goto(`/recordings/${recording.id}`)}
								>
									<div class="font-medium">{recording.name || `Recording #${recording.id}`}</div>
									{#if recording.module_name}
										<div class="text-xs text-muted-foreground">{recording.module_name}</div>
									{/if}
								</button>
							</td>
							<td class="p-3">
								<span class="px-2 py-1 text-xs rounded-full {getStatusColor(recording.status)}">
									{recording.status}
								</span>
							</td>
							<td class="p-3 text-center">
								<span class="text-sm">{recording.step_count}</span>
							</td>
							<td class="p-3 text-center">
								<span class="text-sm text-muted-foreground">
									{formatDuration(recording.duration)}
								</span>
							</td>
							<td class="p-3 text-right text-sm text-muted-foreground">
								{formatDate(recording.started_at)}
							</td>
							<td class="p-3">
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button {...props} variant="ghost" size="icon" class="h-8 w-8">
												<MoreVertical class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => goto(`/recordings/${recording.id}`)}>
											<Eye class="h-4 w-4 mr-2" />
											View Details
										</DropdownMenu.Item>
										{#if recording.status === 'completed'}
											<DropdownMenu.Item onclick={() => goto(`/recordings/${recording.id}/generate`)}>
												<Zap class="h-4 w-4 mr-2" />
												Generate Workflow
											</DropdownMenu.Item>
										{/if}
										<DropdownMenu.Item onclick={() => handleDuplicate(recording.id)}>
											<Copy class="h-4 w-4 mr-2" />
											Duplicate
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										<DropdownMenu.Item onclick={() => handleDelete(recording.id)} class="text-destructive">
											<Trash2 class="h-4 w-4 mr-2" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	{/if}
</div>
