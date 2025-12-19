<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import type { Meeting } from '$lib/api/meetings';
	import { Calendar, Clock, Video, MapPin, Users, ChevronRight } from 'lucide-svelte';

	interface Props {
		meetings: Meeting[];
		onViewAll?: () => void;
		onJoin?: (meeting: Meeting) => void;
	}

	let { meetings, onViewAll, onJoin }: Props = $props();

	function formatTime(dateStr: string): string {
		return new Date(dateStr).toLocaleTimeString('en-US', {
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function formatRelativeDate(dateStr: string): string {
		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = date.getTime() - now.getTime();
		const diffMins = Math.floor(diffMs / (1000 * 60));
		const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
		const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

		if (diffMins < 0) return 'Started';
		if (diffMins < 60) return `In ${diffMins}m`;
		if (diffHours < 24) return `In ${diffHours}h`;
		if (diffDays === 1) return 'Tomorrow';
		if (diffDays < 7) return `In ${diffDays} days`;
		return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
	}

	function isStartingSoon(dateStr: string): boolean {
		const date = new Date(dateStr);
		const now = new Date();
		const diffMins = Math.floor((date.getTime() - now.getTime()) / (1000 * 60));
		return diffMins >= 0 && diffMins <= 15;
	}
</script>

<Card>
	<CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
		<div>
			<CardTitle class="text-base">Upcoming Meetings</CardTitle>
			<CardDescription>Your next {meetings.length} meetings</CardDescription>
		</div>
		{#if onViewAll}
			<Button variant="ghost" size="sm" onclick={onViewAll}>
				View All
				<ChevronRight class="h-4 w-4 ml-1" />
			</Button>
		{/if}
	</CardHeader>
	<CardContent>
		{#if meetings.length === 0}
			<div class="text-center py-6 text-muted-foreground">
				<Calendar class="h-10 w-10 mx-auto mb-2 opacity-50" />
				<p class="text-sm">No upcoming meetings</p>
			</div>
		{:else}
			<div class="space-y-3">
				{#each meetings as meeting}
					{@const startingSoon = isStartingSoon(meeting.start_time)}
					<div
						class="flex items-start gap-3 p-3 rounded-lg border {startingSoon
							? 'border-primary bg-primary/5'
							: ''}"
					>
						<div class="shrink-0">
							{#if meeting.is_online}
								<div
									class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center"
								>
									<Video class="h-5 w-5 text-blue-600 dark:text-blue-400" />
								</div>
							{:else}
								<div
									class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
								>
									<MapPin class="h-5 w-5 text-gray-600 dark:text-gray-400" />
								</div>
							{/if}
						</div>

						<div class="flex-1 min-w-0">
							<div class="flex items-start justify-between gap-2">
								<h4 class="font-medium text-sm truncate">{meeting.title}</h4>
								{#if startingSoon}
									<Badge variant="default" class="shrink-0 animate-pulse">
										Starting Soon
									</Badge>
								{:else}
									<span class="text-xs text-muted-foreground shrink-0">
										{formatRelativeDate(meeting.start_time)}
									</span>
								{/if}
							</div>

							<div class="flex items-center gap-3 mt-1 text-xs text-muted-foreground">
								<span class="flex items-center gap-1">
									<Clock class="h-3 w-3" />
									{formatTime(meeting.start_time)} - {formatTime(meeting.end_time)}
								</span>
								<span class="flex items-center gap-1">
									<Users class="h-3 w-3" />
									{meeting.participant_count}
								</span>
							</div>

							{#if meeting.is_online && meeting.meeting_url && startingSoon}
								<Button
									variant="default"
									size="sm"
									class="mt-2"
									onclick={() => onJoin?.(meeting)}
								>
									<Video class="h-3.5 w-3.5 mr-1" />
									Join Meeting
								</Button>
							{/if}
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>
