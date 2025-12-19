<script lang="ts">
	import { onMount, tick } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Badge } from '$lib/components/ui/badge';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import {
		chatConversationsApi,
		chatCannedResponsesApi,
		type ChatConversation,
		type ChatMessage,
		type ChatCannedResponse
	} from '$lib/api/live-chat';
	import {
		Send,
		MoreVertical,
		User,
		Clock,
		MapPin,
		Globe,
		MessageSquare,
		Lock,
		Check,
		X,
		ChevronDown
	} from 'lucide-svelte';

	interface Props {
		conversation: ChatConversation;
		onUpdate?: (conversation: ChatConversation) => void;
	}

	let { conversation, onUpdate }: Props = $props();

	let messages = $state<ChatMessage[]>([]);
	let loading = $state(true);
	let sending = $state(false);
	let messageContent = $state('');
	let isInternal = $state(false);
	let messagesEndRef = $state<HTMLDivElement | null>(null);
	let cannedResponses = $state<ChatCannedResponse[]>([]);
	let showCannedResponses = $state(false);

	async function loadMessages() {
		loading = true;
		try {
			messages = await chatConversationsApi.getMessages(conversation.id);
			await tick();
			scrollToBottom();
		} catch (err) {
			console.error('Failed to load messages:', err);
		}
		loading = false;
	}

	async function loadCannedResponses() {
		try {
			cannedResponses = await chatCannedResponsesApi.list();
		} catch (err) {
			console.error('Failed to load canned responses:', err);
		}
	}

	async function sendMessage() {
		if (!messageContent.trim() || sending) return;

		sending = true;
		try {
			const newMessage = await chatConversationsApi.sendMessage(conversation.id, {
				content: messageContent.trim(),
				is_internal: isInternal
			});
			messages = [...messages, newMessage];
			messageContent = '';
			isInternal = false;
			await tick();
			scrollToBottom();
		} catch (err) {
			console.error('Failed to send message:', err);
		}
		sending = false;
	}

	async function closeConversation() {
		try {
			const updated = await chatConversationsApi.close(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to close conversation:', err);
		}
	}

	async function reopenConversation() {
		try {
			const updated = await chatConversationsApi.reopen(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to reopen conversation:', err);
		}
	}

	function scrollToBottom() {
		messagesEndRef?.scrollIntoView({ behavior: 'smooth' });
	}

	function handleKeyDown(e: KeyboardEvent) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			sendMessage();
		}

		// Detect canned response shortcut
		if (messageContent.startsWith('/') && messageContent.length > 1) {
			showCannedResponses = true;
		} else {
			showCannedResponses = false;
		}
	}

	async function useCannedResponse(response: ChatCannedResponse) {
		const result = await chatCannedResponsesApi.use(response.id);
		messageContent = result.content;
		showCannedResponses = false;
	}

	function formatTime(dateStr: string): string {
		return new Date(dateStr).toLocaleTimeString('en-US', {
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	const filteredCannedResponses = $derived(
		cannedResponses.filter((r) => {
			if (!messageContent.startsWith('/')) return false;
			const query = messageContent.slice(1).toLowerCase();
			return r.shortcut.toLowerCase().includes(query) || r.title.toLowerCase().includes(query);
		})
	);

	onMount(() => {
		loadMessages();
		loadCannedResponses();
	});

	$effect(() => {
		if (conversation.id) {
			loadMessages();
		}
	});
</script>

<div class="flex flex-col h-full">
	<!-- Header -->
	<div class="p-4 border-b flex items-center justify-between">
		<div class="flex items-center gap-3">
			<div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center">
				<User class="h-5 w-5 text-muted-foreground" />
			</div>
			<div>
				<h3 class="font-medium">
					{conversation.visitor?.name || conversation.visitor?.email || `Visitor #${conversation.visitor?.id}`}
				</h3>
				<div class="flex items-center gap-2 text-xs text-muted-foreground">
					{#if conversation.visitor?.location}
						<span class="flex items-center gap-1">
							<MapPin class="h-3 w-3" />
							{conversation.visitor.location}
						</span>
					{/if}
					{#if conversation.visitor?.current_page}
						<span class="flex items-center gap-1">
							<Globe class="h-3 w-3" />
							{conversation.visitor.current_page}
						</span>
					{/if}
				</div>
			</div>
		</div>

		<div class="flex items-center gap-2">
			<Badge variant={conversation.status === 'open' ? 'default' : conversation.status === 'closed' ? 'outline' : 'secondary'}>
				{conversation.status}
			</Badge>

			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					<Button variant="ghost" size="icon">
						<MoreVertical class="h-4 w-4" />
					</Button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Content align="end">
					{#if conversation.status !== 'closed'}
						<DropdownMenu.Item onclick={closeConversation}>
							<X class="h-4 w-4 mr-2" />
							Close Conversation
						</DropdownMenu.Item>
					{:else}
						<DropdownMenu.Item onclick={reopenConversation}>
							<MessageSquare class="h-4 w-4 mr-2" />
							Reopen Conversation
						</DropdownMenu.Item>
					{/if}
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</div>
	</div>

	<!-- Messages -->
	<div class="flex-1 overflow-y-auto p-4 space-y-4">
		{#if loading}
			<div class="flex items-center justify-center h-full">
				<div class="text-muted-foreground">Loading messages...</div>
			</div>
		{:else if messages.length === 0}
			<div class="flex items-center justify-center h-full">
				<div class="text-muted-foreground">No messages yet</div>
			</div>
		{:else}
			{#each messages as message}
				<div
					class="flex {message.sender_type === 'agent' ? 'justify-end' : message.sender_type === 'system' ? 'justify-center' : 'justify-start'}"
				>
					{#if message.sender_type === 'system'}
						<div class="text-xs text-muted-foreground bg-muted px-3 py-1 rounded-full">
							{message.content}
						</div>
					{:else}
						<div
							class="max-w-[70%] {message.sender_type === 'agent'
								? message.is_internal
									? 'bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700'
									: 'bg-primary text-primary-foreground'
								: 'bg-muted'} rounded-lg px-4 py-2"
						>
							{#if message.is_internal}
								<div class="flex items-center gap-1 text-xs text-yellow-700 dark:text-yellow-400 mb-1">
									<Lock class="h-3 w-3" />
									Internal Note
								</div>
							{/if}
							<p class="text-sm whitespace-pre-wrap">{message.content}</p>
							<div
								class="flex items-center justify-end gap-1 mt-1 text-xs {message.sender_type === 'agent' && !message.is_internal
									? 'text-primary-foreground/70'
									: 'text-muted-foreground'}"
							>
								<span>{formatTime(message.created_at)}</span>
								{#if message.sender_type === 'agent' && message.read_at}
									<Check class="h-3 w-3" />
								{/if}
							</div>
						</div>
					{/if}
				</div>
			{/each}
			<div bind:this={messagesEndRef}></div>
		{/if}
	</div>

	<!-- Input -->
	<div class="p-4 border-t">
		{#if showCannedResponses && filteredCannedResponses.length > 0}
			<div class="mb-2 border rounded-lg overflow-hidden max-h-40 overflow-y-auto">
				{#each filteredCannedResponses as response}
					<button
						class="w-full text-left px-3 py-2 hover:bg-muted text-sm border-b last:border-b-0"
						onclick={() => useCannedResponse(response)}
					>
						<span class="font-medium">/{response.shortcut}</span>
						<span class="text-muted-foreground ml-2">{response.title}</span>
					</button>
				{/each}
			</div>
		{/if}

		<div class="flex items-center gap-2 mb-2">
			<Button
				variant={isInternal ? 'default' : 'outline'}
				size="sm"
				class="h-7 text-xs"
				onclick={() => (isInternal = !isInternal)}
			>
				<Lock class="h-3 w-3 mr-1" />
				Internal Note
			</Button>
			{#if isInternal}
				<span class="text-xs text-muted-foreground">
					This message will only be visible to agents
				</span>
			{/if}
		</div>

		<div class="flex gap-2">
			<Textarea
				bind:value={messageContent}
				placeholder={isInternal ? 'Add an internal note...' : 'Type a message...'}
				class="min-h-[80px] resize-none"
				onkeydown={handleKeyDown}
			/>
			<Button
				onclick={sendMessage}
				disabled={!messageContent.trim() || sending}
				class="h-[80px]"
			>
				<Send class="h-4 w-4" />
			</Button>
		</div>
		<p class="text-xs text-muted-foreground mt-1">
			Type / to use canned responses. Press Enter to send.
		</p>
	</div>
</div>
