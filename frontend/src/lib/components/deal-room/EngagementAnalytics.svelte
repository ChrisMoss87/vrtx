<script lang="ts">
	import { onMount } from 'svelte';
	import { Users, FileText, MessageSquare, Clock, Eye, TrendingUp } from 'lucide-svelte';
	import { getRoomAnalytics, type DealRoomAnalytics } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	interface Props {
		roomId: number;
	}

	let { roomId }: Props = $props();

	let analytics = $state<DealRoomAnalytics | null>(null);
	let loading = $state(true);

	onMount(async () => {
		const { data, error } = await tryCatch(getRoomAnalytics(roomId));
		loading = false;

		if (error) {
			toast.error('Failed to load analytics');
			return;
		}

		analytics = data;
	});

	function formatTime(seconds: number): string {
		if (seconds < 60) return `${seconds}s`;
		if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
		return `${(seconds / 3600).toFixed(1)}h`;
	}

	function formatDate(dateStr: string | null): string {
		if (!dateStr) return 'Never';
		const date = new Date(dateStr);
		return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
	}
</script>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
	</div>
{:else if analytics}
	<div class="space-y-6">
		<!-- Summary Cards -->
		<div class="grid gap-4 md:grid-cols-4">
			<div class="rounded-lg border p-4">
				<div class="flex items-center gap-2 text-muted-foreground text-sm">
					<TrendingUp class="h-4 w-4" />
					Action Plan
				</div>
				<div class="mt-2">
					<span class="text-2xl font-bold">{analytics.action_plan.percentage}%</span>
					<span class="text-sm text-muted-foreground ml-1">complete</span>
				</div>
				<div class="text-xs text-muted-foreground mt-1">
					{analytics.action_plan.completed} of {analytics.action_plan.total} items
				</div>
			</div>

			<div class="rounded-lg border p-4">
				<div class="flex items-center gap-2 text-muted-foreground text-sm">
					<FileText class="h-4 w-4" />
					Documents
				</div>
				<div class="mt-2">
					<span class="text-2xl font-bold">{analytics.documents.length}</span>
				</div>
				<div class="text-xs text-muted-foreground mt-1">
					{analytics.documents.reduce((sum, d) => sum + d.view_count, 0)} total views
				</div>
			</div>

			<div class="rounded-lg border p-4">
				<div class="flex items-center gap-2 text-muted-foreground text-sm">
					<MessageSquare class="h-4 w-4" />
					Messages
				</div>
				<div class="mt-2">
					<span class="text-2xl font-bold">{analytics.message_count}</span>
				</div>
			</div>

			<div class="rounded-lg border p-4">
				<div class="flex items-center gap-2 text-muted-foreground text-sm">
					<Clock class="h-4 w-4" />
					Activities
				</div>
				<div class="mt-2">
					<span class="text-2xl font-bold">{analytics.activity_count}</span>
				</div>
			</div>
		</div>

		<!-- Document Engagement -->
		{#if analytics.documents.length > 0}
			<div class="rounded-lg border p-4">
				<h3 class="font-semibold flex items-center gap-2 mb-4">
					<Eye class="h-4 w-4" />
					Document Engagement
				</h3>
				<div class="space-y-3">
					{#each analytics.documents as doc}
						<div class="flex items-center gap-4">
							<FileText class="h-5 w-5 text-muted-foreground" />
							<div class="flex-1 min-w-0">
								<div class="text-sm font-medium truncate">{doc.name}</div>
							</div>
							<div class="flex items-center gap-6 text-sm">
								<div class="text-center">
									<div class="font-medium">{doc.view_count}</div>
									<div class="text-xs text-muted-foreground">views</div>
								</div>
								<div class="text-center">
									<div class="font-medium">{doc.unique_viewers}</div>
									<div class="text-xs text-muted-foreground">viewers</div>
								</div>
								<div class="text-center">
									<div class="font-medium">{formatTime(doc.total_time_spent)}</div>
									<div class="text-xs text-muted-foreground">time spent</div>
								</div>
							</div>
						</div>
					{/each}
				</div>
			</div>
		{/if}

		<!-- Member Engagement -->
		<div class="rounded-lg border p-4">
			<h3 class="font-semibold flex items-center gap-2 mb-4">
				<Users class="h-4 w-4" />
				Member Engagement
			</h3>
			<div class="overflow-x-auto">
				<table class="w-full text-sm">
					<thead>
						<tr class="border-b">
							<th class="text-left py-2 font-medium">Name</th>
							<th class="text-left py-2 font-medium">Type</th>
							<th class="text-center py-2 font-medium">Docs Viewed</th>
							<th class="text-center py-2 font-medium">Messages</th>
							<th class="text-right py-2 font-medium">Last Active</th>
						</tr>
					</thead>
					<tbody>
						{#each analytics.member_engagement as member}
							<tr class="border-b last:border-0 hover:bg-muted/50">
								<td class="py-3">
									<div class="flex items-center gap-2">
										<div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-xs font-medium">
											{member.name.charAt(0).toUpperCase()}
										</div>
										{member.name}
									</div>
								</td>
								<td class="py-3">
									<span class="px-2 py-0.5 text-xs rounded-full {member.is_internal ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'}">
										{member.is_internal ? 'Team' : 'External'}
									</span>
								</td>
								<td class="py-3 text-center">{member.documents_viewed}</td>
								<td class="py-3 text-center">{member.messages_sent}</td>
								<td class="py-3 text-right text-muted-foreground">
									{formatDate(member.last_accessed)}
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		</div>
	</div>
{/if}
