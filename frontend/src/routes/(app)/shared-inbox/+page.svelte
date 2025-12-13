<script lang="ts">
	import { InboxList, ConversationList, ConversationView } from '$lib/components/shared-inbox';
	import type { SharedInbox, InboxConversation } from '$lib/api/shared-inbox';
	import { Inbox, ArrowLeft } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';

	let selectedInbox = $state<SharedInbox | null>(null);
	let selectedConversation = $state<InboxConversation | null>(null);

	function handleInboxSelect(inbox: SharedInbox) {
		selectedInbox = inbox;
		selectedConversation = null;
	}

	function handleConversationSelect(conversation: InboxConversation) {
		selectedConversation = conversation;
	}

	function handleConversationUpdate(conversation: InboxConversation) {
		selectedConversation = conversation;
	}

	function goBack() {
		if (selectedConversation) {
			selectedConversation = null;
		} else if (selectedInbox) {
			selectedInbox = null;
		}
	}
</script>

<svelte:head>
	<title>Shared Inbox | VRTX</title>
</svelte:head>

<div class="h-[calc(100vh-4rem)]">
	{#if !selectedInbox}
		<!-- Inbox List View -->
		<div class="container mx-auto py-6">
			<InboxList onSelect={handleInboxSelect} />
		</div>
	{:else if !selectedConversation}
		<!-- Conversation List View -->
		<div class="flex flex-col h-full">
			<div class="border-b px-4 py-3 flex items-center gap-3">
				<Button variant="ghost" size="icon" onclick={goBack}>
					<ArrowLeft class="h-5 w-5" />
				</Button>
				<div>
					<h1 class="text-lg font-semibold">{selectedInbox.name}</h1>
					<p class="text-sm text-muted-foreground">{selectedInbox.email}</p>
				</div>
			</div>
			<div class="flex-1 overflow-hidden">
				<ConversationList inboxId={selectedInbox.id} onSelect={handleConversationSelect} />
			</div>
		</div>
	{:else}
		<!-- Conversation Detail View -->
		<div class="flex flex-col h-full">
			<div class="border-b px-4 py-2 flex items-center gap-3">
				<Button variant="ghost" size="icon" onclick={goBack}>
					<ArrowLeft class="h-5 w-5" />
				</Button>
				<span class="text-sm text-muted-foreground">
					{selectedInbox.name} / Conversations
				</span>
			</div>
			<div class="flex-1 overflow-hidden">
				<ConversationView
					conversation={selectedConversation}
					onUpdate={handleConversationUpdate}
				/>
			</div>
		</div>
	{/if}
</div>
