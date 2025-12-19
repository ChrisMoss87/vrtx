<script lang="ts">
	import type { Cadence, CadenceAnalytics } from '$lib/api/cadences';
	import { getCadenceAnalytics } from '$lib/api/cadences';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import { Spinner } from '$lib/components/ui/spinner';
	import {
		Users,
		CheckCircle,
		MessageSquare,
		Calendar,
		TrendingUp,
		Mail,
		Phone,
		Linkedin,
		ClipboardList
	} from 'lucide-svelte';

	interface Props {
		cadence: Cadence;
	}

	let { cadence }: Props = $props();

	let loading = $state(true);
	let analytics = $state<CadenceAnalytics | null>(null);

	async function loadAnalytics() {
		loading = true;
		try {
			analytics = await getCadenceAnalytics(cadence.id);
		} catch (error) {
			console.error('Failed to load analytics:', error);
		} finally {
			loading = false;
		}
	}

	function getChannelIcon(channel: string) {
		switch (channel) {
			case 'email':
				return Mail;
			case 'call':
				return Phone;
			case 'linkedin':
				return Linkedin;
			case 'task':
				return ClipboardList;
			default:
				return Mail;
		}
	}

	function formatPercent(value: number): string {
		return value.toFixed(1) + '%';
	}

	$effect(() => {
		loadAnalytics();
	});
</script>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<Spinner class="h-8 w-8" />
	</div>
{:else if analytics}
	<div class="space-y-6">
		<!-- Summary KPIs -->
		<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Total Enrolled</p>
							<p class="text-2xl font-bold">{analytics.summary.total_enrollments}</p>
						</div>
						<div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
							<Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
						</div>
					</div>
					<p class="mt-2 text-xs text-muted-foreground">
						{analytics.summary.active_enrollments} currently active
					</p>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Completion Rate</p>
							<p class="text-2xl font-bold">{formatPercent(analytics.summary.completion_rate)}</p>
						</div>
						<div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
							<CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
						</div>
					</div>
					<Progress value={analytics.summary.completion_rate} class="mt-2" />
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Reply Rate</p>
							<p class="text-2xl font-bold">{formatPercent(analytics.summary.reply_rate)}</p>
						</div>
						<div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
							<MessageSquare class="h-5 w-5 text-purple-600 dark:text-purple-400" />
						</div>
					</div>
					<Progress value={analytics.summary.reply_rate} class="mt-2" />
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Meetings Booked</p>
							<p class="text-2xl font-bold">{analytics.summary.meetings_booked}</p>
						</div>
						<div class="rounded-full bg-emerald-100 p-3 dark:bg-emerald-900">
							<Calendar class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
						</div>
					</div>
					<p class="mt-2 text-xs text-muted-foreground">
						{formatPercent(analytics.summary.meeting_rate)} of enrolled
					</p>
				</Card.Content>
			</Card.Root>
		</div>

		<!-- Step Performance -->
		{#if analytics.steps.length > 0}
			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center gap-2">
						<TrendingUp class="h-5 w-5" />
						Step Performance
					</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="space-y-4">
						{#each analytics.steps as step, index}
							{@const Icon = getChannelIcon(step.channel)}
							{@const openRate = step.stats.sent > 0 ? (step.stats.opened / step.stats.sent) * 100 : 0}
							{@const clickRate = step.stats.opened > 0 ? (step.stats.clicked / step.stats.opened) * 100 : 0}
							{@const replyRate = step.stats.sent > 0 ? (step.stats.replied / step.stats.sent) * 100 : 0}

							<div class="rounded-lg border p-4">
								<div class="flex items-center justify-between mb-3">
									<div class="flex items-center gap-3">
										<Badge variant="outline" class="h-8 w-8 p-0 justify-center">
											{index + 1}
										</Badge>
										<div class="flex items-center gap-2">
											<Icon class="h-4 w-4 text-muted-foreground" />
											<span class="font-medium">{step.name}</span>
										</div>
									</div>
									<Badge variant="secondary">
										{step.stats.completed} / {step.stats.total} executed
									</Badge>
								</div>

								{#if step.channel === 'email'}
									<div class="grid gap-4 sm:grid-cols-4 text-sm">
										<div>
											<p class="text-muted-foreground">Sent</p>
											<p class="font-medium">{step.stats.sent}</p>
										</div>
										<div>
											<p class="text-muted-foreground">Open Rate</p>
											<p class="font-medium">{formatPercent(openRate)}</p>
										</div>
										<div>
											<p class="text-muted-foreground">Click Rate</p>
											<p class="font-medium">{formatPercent(clickRate)}</p>
										</div>
										<div>
											<p class="text-muted-foreground">Reply Rate</p>
											<p class="font-medium">{formatPercent(replyRate)}</p>
										</div>
									</div>
								{:else}
									<div class="grid gap-4 sm:grid-cols-3 text-sm">
										<div>
											<p class="text-muted-foreground">Completed</p>
											<p class="font-medium">{step.stats.completed}</p>
										</div>
										<div>
											<p class="text-muted-foreground">Replied</p>
											<p class="font-medium">{step.stats.replied}</p>
										</div>
										<div>
											<p class="text-muted-foreground">Bounced</p>
											<p class="font-medium">{step.stats.bounced}</p>
										</div>
									</div>
								{/if}
							</div>
						{/each}
					</div>
				</Card.Content>
			</Card.Root>
		{/if}

		<!-- Daily Metrics -->
		{#if analytics.daily_metrics.length > 0}
			<Card.Root>
				<Card.Header>
					<Card.Title>Daily Activity</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="overflow-x-auto">
						<table class="w-full text-sm">
							<thead>
								<tr class="border-b">
									<th class="pb-2 text-left font-medium">Date</th>
									<th class="pb-2 text-right font-medium">Enrollments</th>
									<th class="pb-2 text-right font-medium">Completions</th>
									<th class="pb-2 text-right font-medium">Replies</th>
									<th class="pb-2 text-right font-medium">Meetings</th>
								</tr>
							</thead>
							<tbody>
								{#each analytics.daily_metrics.slice(-14) as metric}
									<tr class="border-b">
										<td class="py-2">{new Date(metric.date).toLocaleDateString()}</td>
										<td class="py-2 text-right">{metric.enrollments}</td>
										<td class="py-2 text-right">{metric.completions}</td>
										<td class="py-2 text-right">{metric.replies}</td>
										<td class="py-2 text-right">{metric.meetings_booked}</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
				</Card.Content>
			</Card.Root>
		{/if}
	</div>
{:else}
	<div class="rounded-lg border border-dashed p-8 text-center">
		<TrendingUp class="mx-auto h-12 w-12 text-muted-foreground" />
		<h3 class="mt-4 text-lg font-medium">No Analytics Data</h3>
		<p class="text-sm text-muted-foreground mt-1">
			Analytics will appear here once the cadence has active enrollments.
		</p>
	</div>
{/if}
