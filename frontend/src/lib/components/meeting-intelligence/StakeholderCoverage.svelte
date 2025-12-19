<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import type { StakeholderCoverage } from '$lib/api/meetings';
	import { Users, Clock, Calendar } from 'lucide-svelte';

	interface Props {
		coverage: StakeholderCoverage;
		title?: string;
	}

	let { coverage, title = 'Stakeholder Coverage' }: Props = $props();

	function formatDate(dateStr: string | null): string {
		if (!dateStr) return 'Never';
		const date = new Date(dateStr);
		const now = new Date();
		const diffDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24));

		if (diffDays === 0) return 'Today';
		if (diffDays === 1) return 'Yesterday';
		if (diffDays < 7) return `${diffDays} days ago`;
		if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
		return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
	}

	function formatMinutes(minutes: number): string {
		const hours = Math.floor(minutes / 60);
		const mins = minutes % 60;
		if (hours === 0) return `${mins}m`;
		if (mins === 0) return `${hours}h`;
		return `${hours}h ${mins}m`;
	}

	function getEngagementLevel(meetingCount: number): { label: string; color: string } {
		if (meetingCount >= 5) return { label: 'High', color: 'bg-green-500' };
		if (meetingCount >= 3) return { label: 'Medium', color: 'bg-yellow-500' };
		if (meetingCount >= 1) return { label: 'Low', color: 'bg-orange-500' };
		return { label: 'None', color: 'bg-gray-400' };
	}

	const maxMeetings = $derived(
		Math.max(...coverage.stakeholders.map((s) => s.meeting_count), 1)
	);
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>{title}</CardTitle>
				<CardDescription>
					{coverage.total_stakeholders} stakeholders across {coverage.total_meetings} meetings
				</CardDescription>
			</div>
			<div class="flex items-center gap-4 text-sm">
				<div class="flex items-center gap-1.5">
					<Users class="h-4 w-4 text-muted-foreground" />
					<span class="font-medium">{coverage.total_stakeholders}</span>
				</div>
				<div class="flex items-center gap-1.5">
					<Calendar class="h-4 w-4 text-muted-foreground" />
					<span class="font-medium">{coverage.total_meetings}</span>
				</div>
			</div>
		</div>
	</CardHeader>
	<CardContent>
		{#if coverage.stakeholders.length === 0}
			<div class="text-center py-8 text-muted-foreground">
				<Users class="h-12 w-12 mx-auto mb-3 opacity-50" />
				<p>No stakeholder data available</p>
			</div>
		{:else}
			<div class="space-y-4">
				{#each coverage.stakeholders as stakeholder}
					{@const engagement = getEngagementLevel(stakeholder.meeting_count)}
					<div class="space-y-2">
						<div class="flex items-center justify-between">
							<div class="flex items-center gap-2">
								<div
									class="w-8 h-8 rounded-full bg-muted flex items-center justify-center text-sm font-medium"
								>
									{(stakeholder.name || stakeholder.email).charAt(0).toUpperCase()}
								</div>
								<div>
									<div class="font-medium text-sm">
										{stakeholder.name || stakeholder.email}
									</div>
									{#if stakeholder.name}
										<div class="text-xs text-muted-foreground">{stakeholder.email}</div>
									{/if}
								</div>
							</div>
							<div class="flex items-center gap-2">
								<Badge variant="outline" class="text-xs">
									{stakeholder.meeting_count} meeting{stakeholder.meeting_count !== 1 ? 's' : ''}
								</Badge>
								<div class="w-2 h-2 rounded-full {engagement.color}" title={engagement.label}></div>
							</div>
						</div>

						<div class="flex items-center gap-4">
							<Progress
								value={(stakeholder.meeting_count / maxMeetings) * 100}
								class="flex-1 h-2"
							/>
							<div class="flex items-center gap-3 text-xs text-muted-foreground shrink-0">
								<span class="flex items-center gap-1">
									<Clock class="h-3 w-3" />
									{formatMinutes(stakeholder.total_minutes)}
								</span>
								<span>Last: {formatDate(stakeholder.last_met)}</span>
							</div>
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>
