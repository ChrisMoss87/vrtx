<script lang="ts">
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import {
		SMSConnector,
		SMSTemplateEditor,
		SMSCampaignBuilder,
		SMSOptOutManager
	} from '$lib/components/sms';
	import { MessageSquare, Settings, FileText, Send, UserX } from 'lucide-svelte';

	let activeTab = $state('campaigns');
</script>

<svelte:head>
	<title>SMS | VRTX</title>
</svelte:head>

<div class="flex flex-col h-[calc(100vh-4rem)]">
	<div class="flex items-center justify-between p-4 border-b">
		<div>
			<h1 class="text-2xl font-bold">SMS Automation</h1>
			<p class="text-muted-foreground">Send SMS messages and manage campaigns</p>
		</div>
	</div>

	<Tabs bind:value={activeTab} class="flex-1 flex flex-col">
		<div class="border-b px-4">
			<TabsList>
				<TabsTrigger value="campaigns" class="gap-2">
					<Send class="h-4 w-4" />
					Campaigns
				</TabsTrigger>
				<TabsTrigger value="templates" class="gap-2">
					<FileText class="h-4 w-4" />
					Templates
				</TabsTrigger>
				<TabsTrigger value="opt-outs" class="gap-2">
					<UserX class="h-4 w-4" />
					Opt-Outs
				</TabsTrigger>
				<TabsTrigger value="connections" class="gap-2">
					<Settings class="h-4 w-4" />
					Connections
				</TabsTrigger>
			</TabsList>
		</div>

		<TabsContent value="campaigns" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			<div class="max-w-5xl mx-auto">
				<SMSCampaignBuilder />
			</div>
		</TabsContent>

		<TabsContent value="templates" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			<div class="max-w-5xl mx-auto">
				<SMSTemplateEditor />
			</div>
		</TabsContent>

		<TabsContent value="opt-outs" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			<div class="max-w-5xl mx-auto">
				<SMSOptOutManager />
			</div>
		</TabsContent>

		<TabsContent value="connections" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			<div class="max-w-5xl mx-auto">
				<SMSConnector />
			</div>
		</TabsContent>
	</Tabs>
</div>
