<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Tabs from '$lib/components/ui/tabs';
	import {
		TeamChatConnector,
		TeamChatNotificationBuilder,
		TeamChatMessageList
	} from '$lib/components/team-chat';
	import { MessageSquare, Bell, Settings, History } from 'lucide-svelte';

	let activeTab = $state('connections');
</script>

<svelte:head>
	<title>Team Chat Integration | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Team Chat Integration</h1>
			<p class="text-muted-foreground">
				Connect Slack or Microsoft Teams to receive notifications and collaborate
			</p>
		</div>
	</div>

	<Tabs.Root bind:value={activeTab}>
		<Tabs.List>
			<Tabs.Trigger value="connections" class="flex items-center gap-2">
				<Settings class="h-4 w-4" />
				Connections
			</Tabs.Trigger>
			<Tabs.Trigger value="notifications" class="flex items-center gap-2">
				<Bell class="h-4 w-4" />
				Notifications
			</Tabs.Trigger>
			<Tabs.Trigger value="history" class="flex items-center gap-2">
				<History class="h-4 w-4" />
				Message History
			</Tabs.Trigger>
		</Tabs.List>

		<div class="mt-6">
			<Tabs.Content value="connections">
				<TeamChatConnector />
			</Tabs.Content>

			<Tabs.Content value="notifications">
				<TeamChatNotificationBuilder />
			</Tabs.Content>

			<Tabs.Content value="history">
				<TeamChatMessageList />
			</Tabs.Content>
		</div>
	</Tabs.Root>
</div>
