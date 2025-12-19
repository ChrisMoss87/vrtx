<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import {
		ChatInbox,
		ChatConversation,
		ChatWidgetBuilder,
		CannedResponseManager
	} from '$lib/components/live-chat';
	import { chatWidgetsApi, chatConversationsApi, type ChatConversation as ChatConversationType, type ChatWidget } from '$lib/api/live-chat';
	import { MessageSquare, Settings, MessageCircle, Plus, Loader2 } from 'lucide-svelte';

	let activeTab = $state('inbox');
	let selectedConversation = $state<ChatConversationType | null>(null);
	let widgets = $state<ChatWidget[]>([]);
	let loadingWidgets = $state(true);
	let showWidgetBuilder = $state(false);
	let editingWidget = $state<ChatWidget | undefined>(undefined);

	async function loadWidgets() {
		loadingWidgets = true;
		try {
			widgets = await chatWidgetsApi.list();
		} catch (err) {
			console.error('Failed to load widgets:', err);
		}
		loadingWidgets = false;
	}

	function handleSelectConversation(conversation: ChatConversationType) {
		selectedConversation = conversation;
	}

	function handleConversationUpdate(updated: ChatConversationType) {
		selectedConversation = updated;
	}

	function openWidgetBuilder(widget?: ChatWidget) {
		editingWidget = widget;
		showWidgetBuilder = true;
	}

	function handleWidgetSaved(widget: ChatWidget) {
		showWidgetBuilder = false;
		editingWidget = undefined;
		loadWidgets();
	}

	onMount(() => {
		loadWidgets();
	});
</script>

<svelte:head>
	<title>Live Chat | VRTX</title>
</svelte:head>

<div class="flex flex-col h-[calc(100vh-4rem)]">
	<div class="flex items-center justify-between p-4 border-b">
		<div>
			<h1 class="text-2xl font-bold">Live Chat</h1>
			<p class="text-muted-foreground">Manage chat conversations and widget settings</p>
		</div>
	</div>

	<Tabs bind:value={activeTab} class="flex-1 flex flex-col">
		<div class="border-b px-4">
			<TabsList>
				<TabsTrigger value="inbox" class="gap-2">
					<MessageSquare class="h-4 w-4" />
					Inbox
				</TabsTrigger>
				<TabsTrigger value="widgets" class="gap-2">
					<MessageCircle class="h-4 w-4" />
					Widgets
				</TabsTrigger>
				<TabsTrigger value="canned" class="gap-2">
					<Settings class="h-4 w-4" />
					Canned Responses
				</TabsTrigger>
			</TabsList>
		</div>

		<TabsContent value="inbox" class="flex-1 m-0 data-[state=inactive]:hidden">
			<div class="flex h-full">
				<!-- Conversation List -->
				<div class="w-80 shrink-0">
					<ChatInbox
						onSelectConversation={handleSelectConversation}
						selectedId={selectedConversation?.id}
					/>
				</div>

				<!-- Conversation Detail -->
				<div class="flex-1 border-l">
					{#if selectedConversation}
						<ChatConversation
							conversation={selectedConversation}
							onUpdate={handleConversationUpdate}
						/>
					{:else}
						<div class="flex flex-col items-center justify-center h-full text-muted-foreground">
							<MessageSquare class="h-12 w-12 mb-4 opacity-50" />
							<p class="text-lg font-medium">Select a conversation</p>
							<p class="text-sm">Choose a conversation from the left to view messages</p>
						</div>
					{/if}
				</div>
			</div>
		</TabsContent>

		<TabsContent value="widgets" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			{#if showWidgetBuilder}
				<div class="max-w-5xl mx-auto">
					<div class="flex items-center justify-between mb-6">
						<h2 class="text-xl font-semibold">
							{editingWidget ? 'Edit Widget' : 'Create Widget'}
						</h2>
						<Button variant="outline" onclick={() => (showWidgetBuilder = false)}>
							Cancel
						</Button>
					</div>
					<ChatWidgetBuilder
						widget={editingWidget}
						onSave={handleWidgetSaved}
						onCancel={() => (showWidgetBuilder = false)}
					/>
				</div>
			{:else}
				<div class="max-w-5xl mx-auto">
					<div class="flex items-center justify-between mb-6">
						<div>
							<h2 class="text-xl font-semibold">Chat Widgets</h2>
							<p class="text-muted-foreground">
								Create and configure chat widgets to embed on your website
							</p>
						</div>
						<Button onclick={() => openWidgetBuilder()}>
							<Plus class="h-4 w-4 mr-2" />
							Create Widget
						</Button>
					</div>

					{#if loadingWidgets}
						<div class="flex items-center justify-center h-64">
							<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
						</div>
					{:else if widgets.length === 0}
						<Card>
							<CardContent class="flex flex-col items-center justify-center py-12">
								<MessageCircle class="h-12 w-12 mb-4 text-muted-foreground opacity-50" />
								<h3 class="text-lg font-medium mb-2">No widgets yet</h3>
								<p class="text-muted-foreground text-center mb-4">
									Create your first chat widget to start engaging with visitors
								</p>
								<Button onclick={() => openWidgetBuilder()}>
									<Plus class="h-4 w-4 mr-2" />
									Create Widget
								</Button>
							</CardContent>
						</Card>
					{:else}
						<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
							{#each widgets as widget}
								<Card class="cursor-pointer hover:border-primary/50 transition-colors" onclick={() => openWidgetBuilder(widget)}>
									<CardHeader>
										<div class="flex items-center justify-between">
											<CardTitle class="text-base">{widget.name}</CardTitle>
											<span
												class="w-2 h-2 rounded-full {widget.is_active ? 'bg-green-500' : 'bg-gray-400'}"
											></span>
										</div>
										<CardDescription>
											{widget.is_active ? 'Active' : 'Inactive'}
										</CardDescription>
									</CardHeader>
									<CardContent>
										<div class="space-y-2 text-sm">
											<div class="flex items-center justify-between">
												<span class="text-muted-foreground">Conversations</span>
												<span class="font-medium">{widget.conversations_count || 0}</span>
											</div>
											<div class="flex items-center justify-between">
												<span class="text-muted-foreground">Position</span>
												<span class="font-medium capitalize">
													{widget.settings?.position?.replace('-', ' ') || 'Bottom Right'}
												</span>
											</div>
											{#if widget.allowed_domains?.length}
												<div class="flex items-center justify-between">
													<span class="text-muted-foreground">Domains</span>
													<span class="font-medium">{widget.allowed_domains.length}</span>
												</div>
											{/if}
										</div>
									</CardContent>
								</Card>
							{/each}
						</div>
					{/if}
				</div>
			{/if}
		</TabsContent>

		<TabsContent value="canned" class="flex-1 p-4 m-0 overflow-auto data-[state=inactive]:hidden">
			<div class="max-w-3xl mx-auto">
				<CannedResponseManager />
			</div>
		</TabsContent>
	</Tabs>
</div>
