<script lang="ts">
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import {
		WhatsAppConnector,
		WhatsAppConversation,
		WhatsAppTemplateManager,
		WhatsAppInbox
	} from '$lib/components/whatsapp';
	import { type WhatsappConversation as WhatsappConversationType } from '$lib/api/whatsapp';
	import { Phone, Settings, FileText } from 'lucide-svelte';

	let activeTab = $state('inbox');
	let selectedConversation = $state<WhatsappConversationType | null>(null);

	function handleSelectConversation(conversation: WhatsappConversationType) {
		selectedConversation = conversation;
	}

	function handleConversationUpdate(updated: WhatsappConversationType) {
		selectedConversation = updated;
	}
</script>

<svelte:head>
	<title>WhatsApp | VRTX</title>
</svelte:head>

<div class="flex flex-col h-[calc(100vh-4rem)]">
	<div class="flex items-center justify-between p-4 border-b">
		<div>
			<h1 class="text-2xl font-bold">WhatsApp</h1>
			<p class="text-muted-foreground">Send and receive WhatsApp messages</p>
		</div>
	</div>

	<Tabs bind:value={activeTab} class="flex-1 flex flex-col">
		<div class="border-b px-4">
			<TabsList>
				<TabsTrigger value="inbox" class="gap-2">
					<Phone class="h-4 w-4" />
					Inbox
				</TabsTrigger>
				<TabsTrigger value="templates" class="gap-2">
					<FileText class="h-4 w-4" />
					Templates
				</TabsTrigger>
				<TabsTrigger value="connections" class="gap-2">
					<Settings class="h-4 w-4" />
					Connections
				</TabsTrigger>
			</TabsList>
		</div>

		<TabsContent value="inbox" class="flex-1 m-0 data-[state=inactive]:hidden">
			<div class="flex h-full">
				<!-- Conversation List -->
				<div class="w-80 shrink-0">
					<WhatsAppInbox
						onSelectConversation={handleSelectConversation}
						selectedId={selectedConversation?.id}
					/>
				</div>

				<!-- Conversation Detail -->
				<div class="flex-1 border-l">
					{#if selectedConversation}
						<WhatsAppConversation
							conversation={selectedConversation}
							onUpdate={handleConversationUpdate}
						/>
					{:else}
						<div class="flex flex-col items-center justify-center h-full text-muted-foreground">
							<Phone class="h-12 w-12 mb-4 opacity-50" />
							<p class="text-lg font-medium">Select a conversation</p>
							<p class="text-sm">Choose a conversation from the left to view messages</p>
						</div>
					{/if}
				</div>
			</div>
		</TabsContent>

		<TabsContent value="templates" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			<div class="max-w-5xl mx-auto">
				<WhatsAppTemplateManager />
			</div>
		</TabsContent>

		<TabsContent value="connections" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			<div class="max-w-5xl mx-auto">
				<WhatsAppConnector />
			</div>
		</TabsContent>
	</Tabs>
</div>
