<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Label } from '$lib/components/ui/label';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import {
		meetingsApi,
		type Meeting
	} from '$lib/api/meetings';
	import { toast } from 'svelte-sonner';
	import {
		ArrowLeft,
		Calendar,
		Clock,
		MapPin,
		Video,
		Users,
		LinkIcon,
		Edit,
		Trash2,
		CheckCircle,
		XCircle,
		RefreshCw,
		User,
		Mail,
		ExternalLink,
		Building2,
		Target
	} from 'lucide-svelte';

	const meetingId = $derived(parseInt($page.params.id ?? '0'));

	// State
	let meeting = $state<Meeting | null>(null);
	let loading = $state(true);
	let showOutcomeDialog = $state(false);
	let showDeleteConfirm = $state(false);
	let showLinkDealDialog = $state(false);

	// Form state
	let outcomeType = $state<'completed' | 'no_show' | 'rescheduled' | 'cancelled'>('completed');
	let outcomeNotes = $state('');
	let linkDealId = $state<number | null>(null);
	let saving = $state(false);

	async function loadMeeting() {
		loading = true;
		try {
			meeting = await meetingsApi.getMeeting(meetingId);
		} catch (e) {
			toast.error('Failed to load meeting');
			goto('/meetings');
		} finally {
			loading = false;
		}
	}

	async function handleLogOutcome() {
		saving = true;
		try {
			await meetingsApi.logMeetingOutcome(meetingId, outcomeType, outcomeNotes || undefined);
			toast.success('Outcome logged');
			showOutcomeDialog = false;
			await loadMeeting();
		} catch (e) {
			toast.error('Failed to log outcome');
		} finally {
			saving = false;
		}
	}

	async function handleDelete() {
		saving = true;
		try {
			await meetingsApi.deleteMeeting(meetingId);
			toast.success('Meeting deleted');
			goto('/meetings');
		} catch (e) {
			toast.error('Failed to delete meeting');
		} finally {
			saving = false;
			showDeleteConfirm = false;
		}
	}

	async function handleLinkDeal() {
		if (!linkDealId) return;
		saving = true;
		try {
			await meetingsApi.linkMeetingToDeal(meetingId, linkDealId);
			toast.success('Meeting linked to deal');
			showLinkDealDialog = false;
			await loadMeeting();
		} catch (e) {
			toast.error('Failed to link deal');
		} finally {
			saving = false;
		}
	}

	function formatDate(dateStr: string): string {
		return new Date(dateStr).toLocaleDateString('en-US', {
			weekday: 'long',
			year: 'numeric',
			month: 'long',
			day: 'numeric'
		});
	}

	function formatTime(dateStr: string): string {
		return new Date(dateStr).toLocaleTimeString('en-US', {
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function formatDuration(minutes: number): string {
		if (minutes < 60) return `${minutes}m`;
		const hours = Math.floor(minutes / 60);
		const mins = minutes % 60;
		return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
	}

	function getStatusColor(status: string): string {
		switch (status) {
			case 'confirmed':
				return 'bg-green-500/10 text-green-700 border-green-500/20';
			case 'tentative':
				return 'bg-yellow-500/10 text-yellow-700 border-yellow-500/20';
			case 'cancelled':
				return 'bg-red-500/10 text-red-700 border-red-500/20';
			default:
				return 'bg-gray-500/10 text-gray-700 border-gray-500/20';
		}
	}

	function getOutcomeColor(outcome: string | null): string {
		switch (outcome) {
			case 'completed':
				return 'bg-green-500/10 text-green-700 border-green-500/20';
			case 'no_show':
				return 'bg-red-500/10 text-red-700 border-red-500/20';
			case 'rescheduled':
				return 'bg-blue-500/10 text-blue-700 border-blue-500/20';
			case 'cancelled':
				return 'bg-gray-500/10 text-gray-700 border-gray-500/20';
			default:
				return '';
		}
	}

	function getResponseColor(status: string): string {
		switch (status) {
			case 'accepted':
				return 'text-green-600';
			case 'declined':
				return 'text-red-600';
			case 'tentative':
				return 'text-yellow-600';
			default:
				return 'text-muted-foreground';
		}
	}

	$effect(() => {
		loadMeeting();
	});

	const outcomeOptions = [
		{ value: 'completed', label: 'Completed' },
		{ value: 'no_show', label: 'No Show' },
		{ value: 'rescheduled', label: 'Rescheduled' },
		{ value: 'cancelled', label: 'Cancelled' }
	];
</script>

<svelte:head>
	<title>{meeting?.title ?? 'Meeting'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" onclick={() => goto('/meetings')}>
			<ArrowLeft class="h-5 w-5" />
		</Button>
		<div class="flex-1">
			{#if loading}
				<Skeleton class="h-8 w-64 mb-2" />
				<Skeleton class="h-4 w-48" />
			{:else if meeting}
				<div class="flex items-center gap-3">
					<h1 class="text-2xl font-bold">{meeting.title}</h1>
					<Badge class={getStatusColor(meeting.status)}>
						{meeting.status}
					</Badge>
					{#if meeting.outcome}
						<Badge class={getOutcomeColor(meeting.outcome)}>
							{meeting.outcome.replace('_', ' ')}
						</Badge>
					{/if}
					{#if meeting.is_today}
						<Badge variant="default">Today</Badge>
					{/if}
				</div>
			{/if}
		</div>
		{#if meeting && !meeting.outcome && new Date(meeting.end_time) < new Date()}
			<Button onclick={() => (showOutcomeDialog = true)}>
				<CheckCircle class="mr-2 h-4 w-4" />
				Log Outcome
			</Button>
		{/if}
		<Button variant="outline" class="text-destructive" onclick={() => (showDeleteConfirm = true)}>
			<Trash2 class="mr-2 h-4 w-4" />
			Delete
		</Button>
	</div>

	{#if meeting}
		<div class="grid md:grid-cols-3 gap-6">
			<!-- Main Content -->
			<div class="md:col-span-2 space-y-6">
				<!-- Details Card -->
				<Card>
					<CardHeader>
						<CardTitle>Meeting Details</CardTitle>
					</CardHeader>
					<CardContent class="space-y-4">
						<div class="flex items-start gap-3">
							<Calendar class="h-5 w-5 text-muted-foreground mt-0.5" />
							<div>
								<p class="font-medium">{formatDate(meeting.start_time)}</p>
								<p class="text-sm text-muted-foreground">
									{formatTime(meeting.start_time)} - {formatTime(meeting.end_time)}
								</p>
							</div>
						</div>

						<div class="flex items-center gap-3">
							<Clock class="h-5 w-5 text-muted-foreground" />
							<p>{formatDuration(meeting.duration_minutes)}</p>
						</div>

						{#if meeting.is_online}
							<div class="flex items-center gap-3">
								<Video class="h-5 w-5 text-muted-foreground" />
								<div class="flex items-center gap-2">
									<p>Online Meeting</p>
									{#if meeting.meeting_url}
										{@const meetingUrl = meeting.meeting_url}
										<Button variant="link" size="sm" class="p-0 h-auto" onclick={() => window.open(meetingUrl, '_blank')}>
											<ExternalLink class="h-4 w-4" />
										</Button>
									{/if}
								</div>
							</div>
						{:else if meeting.location}
							<div class="flex items-center gap-3">
								<MapPin class="h-5 w-5 text-muted-foreground" />
								<p>{meeting.location}</p>
							</div>
						{/if}

						{#if meeting.description}
							<div class="pt-4 border-t">
								<p class="text-sm text-muted-foreground mb-2">Description</p>
								<p class="whitespace-pre-wrap">{meeting.description}</p>
							</div>
						{/if}

						{#if meeting.outcome_notes}
							<div class="pt-4 border-t">
								<p class="text-sm text-muted-foreground mb-2">Outcome Notes</p>
								<p class="whitespace-pre-wrap">{meeting.outcome_notes}</p>
							</div>
						{/if}
					</CardContent>
				</Card>

				<!-- Participants Card -->
				<Card>
					<CardHeader>
						<div class="flex items-center justify-between">
							<CardTitle class="flex items-center gap-2">
								<Users class="h-5 w-5" />
								Participants ({meeting.participant_count})
							</CardTitle>
						</div>
					</CardHeader>
					<CardContent>
						{#if meeting.participants && meeting.participants.length > 0}
							<div class="space-y-3">
								{#each meeting.participants as participant}
									<div class="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
										<div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
											<User class="h-5 w-5 text-primary" />
										</div>
										<div class="flex-1 min-w-0">
											<div class="flex items-center gap-2">
												<p class="font-medium truncate">
													{participant.name || participant.email}
												</p>
												{#if participant.is_organizer}
													<Badge variant="outline" class="text-xs">Organizer</Badge>
												{/if}
											</div>
											<p class="text-sm text-muted-foreground flex items-center gap-1">
												<Mail class="h-3 w-3" />
												{participant.email}
											</p>
										</div>
										<div class={`text-sm capitalize ${getResponseColor(participant.response_status)}`}>
											{participant.response_status.replace('_', ' ')}
										</div>
									</div>
								{/each}
							</div>
						{:else}
							<div class="text-center py-6 text-muted-foreground">
								<Users class="h-12 w-12 mx-auto mb-2 opacity-50" />
								<p>No participants</p>
							</div>
						{/if}
					</CardContent>
				</Card>
			</div>

			<!-- Sidebar -->
			<div class="space-y-6">
				<!-- Linked Records -->
				<Card>
					<CardHeader>
						<CardTitle>Linked Records</CardTitle>
					</CardHeader>
					<CardContent class="space-y-3">
						{#if meeting.deal_id}
							{@const dealId = meeting.deal_id}
							<Button
								variant="outline"
								class="w-full justify-start"
								onclick={() => goto(`/records/deals/${dealId}`)}
							>
								<Target class="mr-2 h-4 w-4" />
								View Deal
							</Button>
						{:else}
							<Button
								variant="outline"
								class="w-full justify-start"
								onclick={() => (showLinkDealDialog = true)}
							>
								<LinkIcon class="mr-2 h-4 w-4" />
								Link to Deal
							</Button>
						{/if}

						{#if meeting.company_id}
							{@const companyId = meeting.company_id}
							<Button
								variant="outline"
								class="w-full justify-start"
								onclick={() => goto(`/records/accounts/${companyId}`)}
							>
								<Building2 class="mr-2 h-4 w-4" />
								View Company
							</Button>
						{/if}
					</CardContent>
				</Card>

				<!-- Quick Info -->
				<Card>
					<CardHeader>
						<CardTitle>Quick Info</CardTitle>
					</CardHeader>
					<CardContent class="space-y-3 text-sm">
						<div class="flex justify-between">
							<span class="text-muted-foreground">Provider</span>
							<span class="capitalize">{meeting.calendar_provider}</span>
						</div>
						<div class="flex justify-between">
							<span class="text-muted-foreground">Participants</span>
							<span>{meeting.participant_count}</span>
						</div>
						<div class="flex justify-between">
							<span class="text-muted-foreground">Duration</span>
							<span>{formatDuration(meeting.duration_minutes)}</span>
						</div>
					</CardContent>
				</Card>
			</div>
		</div>
	{/if}
</div>

<!-- Log Outcome Dialog -->
<Dialog.Root bind:open={showOutcomeDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Log Meeting Outcome</Dialog.Title>
			<Dialog.Description>
				Record the outcome of this meeting
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label>Outcome</Label>
				<Select.Root type="single" bind:value={outcomeType}>
					<Select.Trigger>
						{outcomeOptions.find((o) => o.value === outcomeType)?.label ?? 'Select outcome'}
					</Select.Trigger>
					<Select.Content>
						{#each outcomeOptions as option}
							<Select.Item value={option.value}>{option.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>
			<div class="space-y-2">
				<Label>Notes (optional)</Label>
				<Textarea
					bind:value={outcomeNotes}
					placeholder="Add notes about the meeting outcome..."
					rows={4}
				/>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showOutcomeDialog = false)}>Cancel</Button>
			<Button onclick={handleLogOutcome} disabled={saving}>
				{saving ? 'Saving...' : 'Log Outcome'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Delete Confirmation -->
<Dialog.Root bind:open={showDeleteConfirm}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Delete Meeting</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to delete this meeting? This action cannot be undone.
			</Dialog.Description>
		</Dialog.Header>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showDeleteConfirm = false)}>Cancel</Button>
			<Button variant="destructive" onclick={handleDelete} disabled={saving}>
				{saving ? 'Deleting...' : 'Delete'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Link Deal Dialog -->
<Dialog.Root bind:open={showLinkDealDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Link to Deal</Dialog.Title>
			<Dialog.Description>
				Associate this meeting with a deal
			</Dialog.Description>
		</Dialog.Header>
		<div class="py-4">
			<Label>Deal ID</Label>
			<input
				type="number"
				class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
				bind:value={linkDealId}
				placeholder="Enter deal ID"
			/>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showLinkDealDialog = false)}>Cancel</Button>
			<Button onclick={handleLinkDeal} disabled={saving || !linkDealId}>
				{saving ? 'Linking...' : 'Link Deal'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
