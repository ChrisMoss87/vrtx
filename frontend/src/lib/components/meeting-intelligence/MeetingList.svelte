<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import type { Meeting } from '$lib/api/meetings';
	import { Calendar, Clock, MapPin, Video, Users, ExternalLink } from 'lucide-svelte';

	interface Props {
		meetings: Meeting[];
		title?: string;
		description?: string;
		showDate?: boolean;
		onSelect?: (meeting: Meeting) => void;
		onLogOutcome?: (meeting: Meeting) => void;
	}

	let {
		meetings,
		title = 'Meetings',
		description,
		showDate = true,
		onSelect,
		onLogOutcome
	}: Props = $props();

	function formatDate(dateStr: string): string {
		return new Date(dateStr).toLocaleDateString('en-US', {
			weekday: 'short',
			month: 'short',
			day: 'numeric'
		});
	}

	function formatTime(dateStr: string): string {
		return new Date(dateStr).toLocaleTimeString('en-US', {
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function getStatusColor(meeting: Meeting): string {
		if (meeting.outcome === 'completed') return 'bg-green-500';
		if (meeting.outcome === 'no_show') return 'bg-red-500';
		if (meeting.outcome === 'cancelled' || meeting.status === 'cancelled') return 'bg-gray-500';
		if (meeting.is_today) return 'bg-blue-500';
		if (meeting.is_upcoming) return 'bg-yellow-500';
		return 'bg-gray-400';
	}

	function getOutcomeBadge(outcome: string | null): { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' } {
		switch (outcome) {
			case 'completed':
				return { label: 'Completed', variant: 'default' };
			case 'no_show':
				return { label: 'No Show', variant: 'destructive' };
			case 'rescheduled':
				return { label: 'Rescheduled', variant: 'secondary' };
			case 'cancelled':
				return { label: 'Cancelled', variant: 'outline' };
			default:
				return { label: 'Scheduled', variant: 'secondary' };
		}
	}
</script>

<Card>
	<CardHeader>
		<CardTitle>{title}</CardTitle>
		{#if description}
			<CardDescription>{description}</CardDescription>
		{/if}
	</CardHeader>
	<CardContent>
		{#if meetings.length === 0}
			<div class="text-center py-8 text-muted-foreground">
				<Calendar class="h-12 w-12 mx-auto mb-3 opacity-50" />
				<p>No meetings found</p>
			</div>
		{:else}
			<div class="space-y-3">
				{#each meetings as meeting}
					{@const outcomeBadge = getOutcomeBadge(meeting.outcome)}
					<div
						class="flex items-start gap-3 p-3 rounded-lg border hover:bg-muted/50 transition-colors cursor-pointer"
						onclick={() => onSelect?.(meeting)}
						role="button"
						tabindex="0"
						onkeydown={(e) => e.key === 'Enter' && onSelect?.(meeting)}
					>
						<!-- Status indicator -->
						<div class="mt-1">
							<div class="w-3 h-3 rounded-full {getStatusColor(meeting)}"></div>
						</div>

						<!-- Meeting details -->
						<div class="flex-1 min-w-0">
							<div class="flex items-start justify-between gap-2">
								<h4 class="font-medium truncate">{meeting.title}</h4>
								<Badge variant={outcomeBadge.variant} class="shrink-0">
									{outcomeBadge.label}
								</Badge>
							</div>

							<div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-muted-foreground">
								{#if showDate}
									<span class="flex items-center gap-1">
										<Calendar class="h-3.5 w-3.5" />
										{formatDate(meeting.start_time)}
									</span>
								{/if}
								<span class="flex items-center gap-1">
									<Clock class="h-3.5 w-3.5" />
									{formatTime(meeting.start_time)} - {formatTime(meeting.end_time)}
								</span>
								<span class="flex items-center gap-1">
									<Users class="h-3.5 w-3.5" />
									{meeting.participant_count}
								</span>
							</div>

							{#if meeting.location || meeting.is_online}
								<div class="flex items-center gap-1 mt-1 text-sm text-muted-foreground">
									{#if meeting.is_online}
										<Video class="h-3.5 w-3.5" />
										<span>Online Meeting</span>
										{#if meeting.meeting_url}
											<a
												href={meeting.meeting_url}
												target="_blank"
												rel="noopener"
												class="text-primary hover:underline"
												onclick={(e) => e.stopPropagation()}
											>
												<ExternalLink class="h-3 w-3 inline" />
											</a>
										{/if}
									{:else if meeting.location}
										<MapPin class="h-3.5 w-3.5" />
										<span class="truncate">{meeting.location}</span>
									{/if}
								</div>
							{/if}
						</div>

						<!-- Action button -->
						{#if !meeting.outcome && !meeting.is_upcoming && onLogOutcome}
							<Button
								variant="outline"
								size="sm"
								onclick={(e: MouseEvent) => {
									e.stopPropagation();
									onLogOutcome(meeting);
								}}
							>
								Log Outcome
							</Button>
						{/if}
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>
