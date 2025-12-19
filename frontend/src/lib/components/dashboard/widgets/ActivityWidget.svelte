<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Activity, User, Calendar } from 'lucide-svelte';
	import { ScrollArea } from '$lib/components/ui/scroll-area';

	interface ActivityItem {
		id: number;
		type: string;
		description: string;
		user_id?: number;
		user_name?: string;
		created_at: string;
	}

	interface Props {
		title: string;
		data: ActivityItem[] | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	function formatTimeAgo(dateStr: string): string {
		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = now.getTime() - date.getTime();
		const diffMins = Math.floor(diffMs / 60000);
		const diffHours = Math.floor(diffMins / 60);
		const diffDays = Math.floor(diffHours / 24);

		if (diffDays > 0) return `${diffDays}d ago`;
		if (diffHours > 0) return `${diffHours}h ago`;
		if (diffMins > 0) return `${diffMins}m ago`;
		return 'Just now';
	}
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<Activity class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content class="p-0">
		{#if loading}
			<div class="animate-pulse space-y-3 p-4">
				{#each [1, 2, 3, 4, 5] as _}
					<div class="flex gap-3">
						<div class="h-8 w-8 rounded-full bg-muted"></div>
						<div class="flex-1 space-y-1">
							<div class="h-4 w-3/4 rounded bg-muted"></div>
							<div class="h-3 w-1/4 rounded bg-muted"></div>
						</div>
					</div>
				{/each}
			</div>
		{:else if !data || data.length === 0}
			<div class="flex flex-col items-center justify-center py-8 text-muted-foreground">
				<Activity class="mb-2 h-8 w-8" />
				<p class="text-sm">No recent activity</p>
			</div>
		{:else}
			<ScrollArea class="max-h-[300px]">
				<div class="space-y-3 p-4">
					{#each data as activity}
						<div class="flex gap-3">
							<div
								class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted"
							>
								<User class="h-4 w-4 text-muted-foreground" />
							</div>
							<div class="min-w-0 flex-1">
								<p class="truncate text-sm">{activity.description}</p>
								<div class="flex items-center gap-2 text-xs text-muted-foreground">
									{#if activity.user_name}
										<span>{activity.user_name}</span>
										<span>-</span>
									{/if}
									<span>{formatTimeAgo(activity.created_at)}</span>
								</div>
							</div>
						</div>
					{/each}
				</div>
			</ScrollArea>
		{/if}
	</Card.Content>
</Card.Root>
