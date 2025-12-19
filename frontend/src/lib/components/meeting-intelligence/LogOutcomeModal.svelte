<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as RadioGroup from '$lib/components/ui/radio-group';
	import { meetingsApi, type Meeting } from '$lib/api/meetings';
	import { Loader2, CheckCircle, XCircle, Clock, Ban } from 'lucide-svelte';

	interface Props {
		open: boolean;
		meeting: Meeting | null;
		onSuccess?: () => void;
	}

	let { open = $bindable(), meeting, onSuccess }: Props = $props();

	let saving = $state(false);
	let error = $state<string | null>(null);
	let outcome = $state<'completed' | 'no_show' | 'rescheduled' | 'cancelled'>('completed');
	let notes = $state('');

	const outcomeOptions = [
		{
			value: 'completed',
			label: 'Completed',
			description: 'Meeting occurred as scheduled',
			icon: CheckCircle,
			color: 'text-green-600'
		},
		{
			value: 'no_show',
			label: 'No Show',
			description: 'One or more participants did not attend',
			icon: XCircle,
			color: 'text-red-600'
		},
		{
			value: 'rescheduled',
			label: 'Rescheduled',
			description: 'Meeting was moved to a different time',
			icon: Clock,
			color: 'text-yellow-600'
		},
		{
			value: 'cancelled',
			label: 'Cancelled',
			description: 'Meeting was cancelled and not rescheduled',
			icon: Ban,
			color: 'text-gray-600'
		}
	] as const;

	function resetForm() {
		outcome = 'completed';
		notes = '';
		error = null;
	}

	async function handleSubmit() {
		if (!meeting) return;

		saving = true;
		error = null;

		try {
			await meetingsApi.logMeetingOutcome(meeting.id, outcome, notes || undefined);
			saving = false;
			resetForm();
			onSuccess?.();
		} catch (err: any) {
			error = err.message || 'Failed to log outcome';
			saving = false;
		}
	}

	$effect(() => {
		if (!open) {
			resetForm();
		}
	});
</script>

<Dialog.Root bind:open>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Log Meeting Outcome</Dialog.Title>
			{#if meeting}
				<Dialog.Description>
					Record the outcome for "{meeting.title}"
				</Dialog.Description>
			{/if}
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
			{#if error}
				<div class="text-sm text-destructive bg-destructive/10 p-2 rounded">{error}</div>
			{/if}

			<div class="space-y-3">
				<Label>Outcome</Label>
				<RadioGroup.Root bind:value={outcome} class="space-y-2">
					{#each outcomeOptions as option}
						{@const Icon = option.icon}
						<label
							class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-muted/50 transition-colors {outcome === option.value ? 'border-primary bg-primary/5' : ''}"
						>
							<RadioGroup.Item value={option.value} class="mt-0.5" />
							<div class="flex-1">
								<div class="flex items-center gap-2">
									<Icon class="h-4 w-4 {option.color}" />
									<span class="font-medium">{option.label}</span>
								</div>
								<p class="text-sm text-muted-foreground mt-0.5">
									{option.description}
								</p>
							</div>
						</label>
					{/each}
				</RadioGroup.Root>
			</div>

			<div class="space-y-2">
				<Label for="notes">Notes (optional)</Label>
				<Textarea
					id="notes"
					bind:value={notes}
					placeholder="Add any notes about the meeting outcome..."
					rows={3}
				/>
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (open = false)}>Cancel</Button>
			<Button onclick={handleSubmit} disabled={saving}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-1 animate-spin" />
				{/if}
				Save Outcome
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
