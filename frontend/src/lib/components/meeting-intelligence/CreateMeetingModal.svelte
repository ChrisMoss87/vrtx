<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import { meetingsApi, type CreateMeetingData } from '$lib/api/meetings';
	import { Plus, X, Loader2 } from 'lucide-svelte';

	interface Props {
		open: boolean;
		dealId?: number;
		companyId?: number;
		onSuccess?: () => void;
	}

	let { open = $bindable(), dealId, companyId, onSuccess }: Props = $props();

	let saving = $state(false);
	let error = $state<string | null>(null);

	let title = $state('');
	let description = $state('');
	let startDate = $state('');
	let startTime = $state('');
	let endDate = $state('');
	let endTime = $state('');
	let location = $state('');
	let isOnline = $state(false);
	let meetingUrl = $state('');
	let participants = $state<{ email: string; name: string }[]>([]);
	let newParticipantEmail = $state('');
	let newParticipantName = $state('');

	function resetForm() {
		title = '';
		description = '';
		startDate = '';
		startTime = '';
		endDate = '';
		endTime = '';
		location = '';
		isOnline = false;
		meetingUrl = '';
		participants = [];
		newParticipantEmail = '';
		newParticipantName = '';
		error = null;
	}

	function addParticipant() {
		if (newParticipantEmail && !participants.some((p) => p.email === newParticipantEmail)) {
			participants = [...participants, { email: newParticipantEmail, name: newParticipantName }];
			newParticipantEmail = '';
			newParticipantName = '';
		}
	}

	function removeParticipant(email: string) {
		participants = participants.filter((p) => p.email !== email);
	}

	async function handleSubmit() {
		if (!title || !startDate || !startTime || !endDate || !endTime) {
			error = 'Please fill in all required fields';
			return;
		}

		saving = true;
		error = null;

		const data: CreateMeetingData = {
			title,
			description: description || undefined,
			start_time: `${startDate}T${startTime}:00`,
			end_time: `${endDate}T${endTime}:00`,
			location: location || undefined,
			is_online: isOnline,
			meeting_url: isOnline && meetingUrl ? meetingUrl : undefined,
			deal_id: dealId,
			company_id: companyId,
			participants: participants.length > 0 ? participants : undefined
		};

		try {
			await meetingsApi.createMeeting(data);
			saving = false;
			resetForm();
			onSuccess?.();
		} catch (err: any) {
			error = err.message || 'Failed to create meeting';
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
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Schedule Meeting</Dialog.Title>
			<Dialog.Description>Create a new meeting and invite participants.</Dialog.Description>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
			{#if error}
				<div class="text-sm text-destructive bg-destructive/10 p-2 rounded">{error}</div>
			{/if}

			<div class="space-y-2">
				<Label for="title">Title *</Label>
				<Input id="title" bind:value={title} placeholder="Meeting title" required />
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Meeting agenda or notes"
					rows={3}
				/>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="start-date">Start Date *</Label>
					<Input id="start-date" type="date" bind:value={startDate} required />
				</div>
				<div class="space-y-2">
					<Label for="start-time">Start Time *</Label>
					<Input id="start-time" type="time" bind:value={startTime} required />
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="end-date">End Date *</Label>
					<Input id="end-date" type="date" bind:value={endDate} required />
				</div>
				<div class="space-y-2">
					<Label for="end-time">End Time *</Label>
					<Input id="end-time" type="time" bind:value={endTime} required />
				</div>
			</div>

			<div class="flex items-center justify-between">
				<Label for="is-online">Online Meeting</Label>
				<Switch id="is-online" bind:checked={isOnline} />
			</div>

			{#if isOnline}
				<div class="space-y-2">
					<Label for="meeting-url">Meeting URL</Label>
					<Input
						id="meeting-url"
						type="url"
						bind:value={meetingUrl}
						placeholder="https://zoom.us/j/..."
					/>
				</div>
			{:else}
				<div class="space-y-2">
					<Label for="location">Location</Label>
					<Input id="location" bind:value={location} placeholder="Conference room, address, etc." />
				</div>
			{/if}

			<div class="space-y-2">
				<Label>Participants</Label>
				<div class="flex gap-2">
					<Input
						type="email"
						bind:value={newParticipantEmail}
						placeholder="Email"
						class="flex-1"
					/>
					<Input
						bind:value={newParticipantName}
						placeholder="Name (optional)"
						class="flex-1"
					/>
					<Button type="button" variant="outline" size="icon" onclick={addParticipant}>
						<Plus class="h-4 w-4" />
					</Button>
				</div>

				{#if participants.length > 0}
					<div class="flex flex-wrap gap-2 mt-2">
						{#each participants as participant}
							<div
								class="flex items-center gap-1 bg-muted px-2 py-1 rounded text-sm"
							>
								<span>{participant.name || participant.email}</span>
								<button
									type="button"
									class="text-muted-foreground hover:text-foreground"
									onclick={() => removeParticipant(participant.email)}
								>
									<X class="h-3 w-3" />
								</button>
							</div>
						{/each}
					</div>
				{/if}
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (open = false)}>Cancel</Button>
			<Button onclick={handleSubmit} disabled={saving}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-1 animate-spin" />
				{/if}
				Create Meeting
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
