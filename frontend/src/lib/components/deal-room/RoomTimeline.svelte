<script lang="ts">
	import { onMount } from 'svelte';
	import { Users, FileText, CheckSquare, MessageSquare, LogIn, LogOut, Eye, Plus } from 'lucide-svelte';
	import { getRoomActivities, type DealRoomActivity } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';

	export let roomId: number;
	export let limit = 20;
	export let compact = false;

	let activities: DealRoomActivity[] = [];
	let loading = true;

	onMount(async () => {
		const { data } = await tryCatch(getRoomActivities(roomId));
		loading = false;
		activities = (data ?? []).slice(0, limit);
	});

	function getActivityIcon(type: string) {
		switch (type) {
			case 'room_created':
				return Plus;
			case 'member_joined':
				return LogIn;
			case 'member_left':
				return LogOut;
			case 'document_uploaded':
				return FileText;
			case 'document_viewed':
				return Eye;
			case 'action_created':
			case 'action_completed':
				return CheckSquare;
			case 'message_sent':
				return MessageSquare;
			case 'room_accessed':
				return Users;
			default:
				return Users;
		}
	}

	function formatTime(dateStr: string): string {
		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = now.getTime() - date.getTime();
		const diffMins = Math.floor(diffMs / 60000);
		const diffHours = Math.floor(diffMs / 3600000);
		const diffDays = Math.floor(diffMs / 86400000);

		if (diffMins < 1) return 'Just now';
		if (diffMins < 60) return `${diffMins}m ago`;
		if (diffHours < 24) return `${diffHours}h ago`;
		if (diffDays < 7) return `${diffDays}d ago`;

		return date.toLocaleDateString();
	}
</script>

<div class="space-y-3">
	{#if loading}
		<div class="animate-pulse space-y-3">
			{#each Array(3) as _}
				<div class="flex gap-3">
					<div class="w-8 h-8 rounded-full bg-muted"></div>
					<div class="flex-1">
						<div class="h-4 bg-muted rounded w-3/4"></div>
						<div class="h-3 bg-muted rounded w-1/4 mt-1"></div>
					</div>
				</div>
			{/each}
		</div>
	{:else if activities.length === 0}
		<p class="text-sm text-muted-foreground text-center py-4">No activity yet</p>
	{:else}
		{#each activities as activity}
			<div class="flex gap-3 {compact ? 'text-sm' : ''}">
				<div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center flex-shrink-0">
					<svelte:component this={getActivityIcon(activity.type)} class="h-4 w-4 text-muted-foreground" />
				</div>
				<div class="flex-1 min-w-0">
					<p class="text-sm">{activity.description}</p>
					{#if activity.data && Object.keys(activity.data).length > 0 && !compact}
						<p class="text-xs text-muted-foreground mt-0.5">
							{#if activity.data.document_name}
								{activity.data.document_name}
							{/if}
							{#if activity.data.action_title}
								{activity.data.action_title}
							{/if}
							{#if typeof activity.data.time_spent === 'number'}
								({Math.round(activity.data.time_spent / 60)}m)
							{/if}
						</p>
					{/if}
					<p class="text-xs text-muted-foreground mt-0.5">{formatTime(activity.created_at)}</p>
				</div>
			</div>
		{/each}
	{/if}
</div>
