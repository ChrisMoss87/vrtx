<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Alert, AlertDescription, AlertTitle } from '$lib/components/ui/alert';
	import type { DealInsightsResponse, DealInsight } from '$lib/api/meetings';
	import { Calendar, Clock, Users, TrendingUp, AlertTriangle, CheckCircle, Info } from 'lucide-svelte';

	interface Props {
		insights: DealInsightsResponse;
		dealName?: string;
	}

	let { insights, dealName = 'Deal' }: Props = $props();

	const { analytics } = insights;

	function formatDate(dateStr: string | null): string {
		if (!dateStr) return 'N/A';
		return new Date(dateStr).toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric'
		});
	}

	function getInsightIcon(type: DealInsight['type']) {
		switch (type) {
			case 'success':
				return CheckCircle;
			case 'warning':
				return AlertTriangle;
			default:
				return Info;
		}
	}

	function getInsightColor(type: DealInsight['type']): string {
		switch (type) {
			case 'success':
				return 'border-green-500 bg-green-50 dark:bg-green-950';
			case 'warning':
				return 'border-yellow-500 bg-yellow-50 dark:bg-yellow-950';
			default:
				return 'border-blue-500 bg-blue-50 dark:bg-blue-950';
		}
	}
</script>

<div class="space-y-4">
	<!-- Stats Cards -->
	<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
		<Card>
			<CardContent class="pt-4">
				<div class="flex items-center gap-2">
					<Calendar class="h-4 w-4 text-muted-foreground" />
					<span class="text-sm text-muted-foreground">Meetings</span>
				</div>
				<p class="text-2xl font-bold mt-1">{analytics.total_meetings}</p>
			</CardContent>
		</Card>
		<Card>
			<CardContent class="pt-4">
				<div class="flex items-center gap-2">
					<Clock class="h-4 w-4 text-muted-foreground" />
					<span class="text-sm text-muted-foreground">Hours</span>
				</div>
				<p class="text-2xl font-bold mt-1">{analytics.total_hours}</p>
			</CardContent>
		</Card>
		<Card>
			<CardContent class="pt-4">
				<div class="flex items-center gap-2">
					<Users class="h-4 w-4 text-muted-foreground" />
					<span class="text-sm text-muted-foreground">Stakeholders</span>
				</div>
				<p class="text-2xl font-bold mt-1">{analytics.unique_stakeholders}</p>
			</CardContent>
		</Card>
		<Card>
			<CardContent class="pt-4">
				<div class="flex items-center gap-2">
					<TrendingUp class="h-4 w-4 text-muted-foreground" />
					<span class="text-sm text-muted-foreground">Per Week</span>
				</div>
				<p class="text-2xl font-bold mt-1">{analytics.meetings_per_week ?? '-'}</p>
			</CardContent>
		</Card>
	</div>

	<!-- Insights -->
	{#if insights.insights.length > 0}
		<Card>
			<CardHeader>
				<CardTitle class="text-base">Engagement Insights</CardTitle>
			</CardHeader>
			<CardContent class="space-y-3">
				{#each insights.insights as insight}
					{@const Icon = getInsightIcon(insight.type)}
					<Alert class={getInsightColor(insight.type)}>
						<Icon class="h-4 w-4" />
						<AlertTitle>{insight.title}</AlertTitle>
						<AlertDescription>{insight.description}</AlertDescription>
					</Alert>
				{/each}
			</CardContent>
		</Card>
	{/if}

	<!-- Timeline -->
	{#if analytics.timeline.length > 0}
		<Card>
			<CardHeader>
				<CardTitle class="text-base">Meeting Timeline</CardTitle>
				<CardDescription>
					{formatDate(analytics.first_meeting)} - {formatDate(analytics.last_meeting)}
				</CardDescription>
			</CardHeader>
			<CardContent>
				<div class="space-y-3">
					{#each analytics.timeline as item}
						<div class="flex items-center gap-3 text-sm">
							<div class="w-2 h-2 rounded-full bg-primary shrink-0"></div>
							<div class="flex-1 min-w-0">
								<span class="font-medium truncate">{item.title}</span>
							</div>
							<div class="flex items-center gap-2 text-muted-foreground shrink-0">
								<span>{item.duration_minutes}m</span>
								<span>·</span>
								<span>{item.participant_count} attendees</span>
							</div>
							<div class="text-muted-foreground shrink-0">
								{formatDate(item.date)}
							</div>
						</div>
					{/each}
				</div>
			</CardContent>
		</Card>
	{/if}

	<!-- Stakeholders -->
	{#if analytics.stakeholders.length > 0}
		<Card>
			<CardHeader>
				<CardTitle class="text-base">Key Stakeholders</CardTitle>
			</CardHeader>
			<CardContent>
				<div class="flex flex-wrap gap-2">
					{#each analytics.stakeholders as stakeholder}
						<Badge variant="secondary" class="gap-1">
							{stakeholder.name || stakeholder.email}
							<span class="text-muted-foreground">({stakeholder.meeting_count})</span>
						</Badge>
					{/each}
				</div>
			</CardContent>
		</Card>
	{/if}
</div>
