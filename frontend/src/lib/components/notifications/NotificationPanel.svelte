<script lang="ts">
	import { CheckCheck, Settings, Loader2 } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import {
		notifications,
		notificationsList,
		notificationsLoading,
		unreadCount
	} from '$lib/stores/notifications';
	import type { NotificationCategory } from '$lib/api/notifications';
	import NotificationItem from './NotificationItem.svelte';

	interface Props {
		onClose?: () => void;
	}

	let { onClose }: Props = $props();

	let activeTab = $state<'all' | NotificationCategory>('all');

	const categoryLabels: Record<NotificationCategory, string> = {
		approvals: 'Approvals',
		assignments: 'Assigned',
		mentions: 'Mentions',
		updates: 'Updates',
		reminders: 'Reminders',
		deals: 'Deals',
		tasks: 'Tasks',
		system: 'System'
	};

	let filteredNotifications = $derived(
		activeTab === 'all'
			? $notificationsList
			: $notificationsList.filter((n) => n.category === activeTab)
	);

	async function handleMarkAllRead() {
		await notifications.markAllAsRead(activeTab === 'all' ? undefined : activeTab);
	}

	function handleLoadMore() {
		notifications.loadMore();
	}

	function handleNotificationClick(id: number, actionUrl: string | null) {
		notifications.markAsRead(id);
		if (actionUrl) {
			onClose?.();
			// Navigate to the action URL
			window.location.href = actionUrl;
		}
	}

	function handleArchive(id: number) {
		notifications.archive(id);
	}
</script>

<div class="flex flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b px-4 py-3">
		<h3 class="font-semibold">Notifications</h3>
		<div class="flex items-center gap-2">
			{#if $unreadCount > 0}
				<Button variant="ghost" size="sm" onclick={handleMarkAllRead} class="h-8 text-xs">
					<CheckCheck class="mr-1 h-3.5 w-3.5" />
					Mark all read
				</Button>
			{/if}
			<Button variant="ghost" size="icon" class="h-8 w-8" href="/settings/notifications">
				<Settings class="h-4 w-4" />
			</Button>
		</div>
	</div>

	<!-- Category Tabs -->
	<Tabs bind:value={activeTab} class="w-full">
		<div class="border-b px-2">
			<TabsList class="h-9 w-full justify-start bg-transparent p-0">
				<TabsTrigger value="all" class="text-xs data-[state=active]:bg-muted">All</TabsTrigger>
				<TabsTrigger value="mentions" class="text-xs data-[state=active]:bg-muted">
					Mentions
				</TabsTrigger>
				<TabsTrigger value="approvals" class="text-xs data-[state=active]:bg-muted">
					Approvals
				</TabsTrigger>
				<TabsTrigger value="tasks" class="text-xs data-[state=active]:bg-muted">Tasks</TabsTrigger>
			</TabsList>
		</div>

		<TabsContent value={activeTab} class="m-0">
			<ScrollArea class="h-[400px]">
				{#if $notificationsLoading && filteredNotifications.length === 0}
					<div class="flex items-center justify-center py-12">
						<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
					</div>
				{:else if filteredNotifications.length === 0}
					<div class="flex flex-col items-center justify-center py-12 text-center">
						<div
							class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted"
						>
							<CheckCheck class="h-6 w-6 text-muted-foreground" />
						</div>
						<p class="text-sm font-medium">All caught up!</p>
						<p class="text-xs text-muted-foreground">No notifications to show</p>
					</div>
				{:else}
					<div class="divide-y">
						{#each filteredNotifications as notification (notification.id)}
							<NotificationItem
								{notification}
								onClick={() =>
									handleNotificationClick(notification.id, notification.action_url)}
								onArchive={() => handleArchive(notification.id)}
							/>
						{/each}
					</div>

					{#if $notificationsLoading}
						<div class="flex items-center justify-center py-4">
							<Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
						</div>
					{:else}
						<div class="p-2">
							<Button
								variant="ghost"
								class="w-full text-xs"
								onclick={handleLoadMore}
							>
								Load more
							</Button>
						</div>
					{/if}
				{/if}
			</ScrollArea>
		</TabsContent>
	</Tabs>

	<!-- Footer -->
	<div class="border-t px-4 py-2">
		<Button variant="link" class="h-auto p-0 text-xs" href="/settings/notifications">
			Manage notification settings
		</Button>
	</div>
</div>
