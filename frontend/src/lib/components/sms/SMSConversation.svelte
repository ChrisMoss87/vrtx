<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { smsMessagesApi, type SmsMessage } from '$lib/api/sms';
	import { Send, Loader2, Phone, CheckCircle, Clock, XCircle, ArrowDownLeft, ArrowUpRight } from 'lucide-svelte';
	import { formatDistanceToNow } from 'date-fns';

	interface Props {
		phone: string;
		connectionId: number;
		onSend?: (content: string) => Promise<void>;
	}

	let { phone, connectionId, onSend }: Props = $props();

	let loading = $state(true);
	let sending = $state(false);
	let messages = $state<SmsMessage[]>([]);
	let newMessage = $state('');
	let messagesContainer: HTMLDivElement | undefined = $state();

	async function loadMessages() {
		loading = true;
		try {
			messages = await smsMessagesApi.getConversation(phone, connectionId, 50);
		} catch (err) {
			console.error('Failed to load messages:', err);
		}
		loading = false;
		scrollToBottom();
	}

	function scrollToBottom() {
		setTimeout(() => {
			if (messagesContainer) {
				messagesContainer.scrollTop = messagesContainer.scrollHeight;
			}
		}, 100);
	}

	async function handleSend() {
		if (!newMessage.trim()) return;

		sending = true;
		try {
			if (onSend) {
				await onSend(newMessage.trim());
			} else {
				await smsMessagesApi.send({
					connection_id: connectionId,
					to: phone,
					content: newMessage.trim()
				});
			}
			newMessage = '';
			loadMessages();
		} catch (err) {
			console.error('Failed to send message:', err);
		}
		sending = false;
	}

	function getStatusIcon(status: string) {
		switch (status) {
			case 'delivered':
				return CheckCircle;
			case 'sent':
			case 'queued':
			case 'pending':
				return Clock;
			case 'failed':
			case 'undelivered':
				return XCircle;
			default:
				return Clock;
		}
	}

	function getStatusColor(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
		switch (status) {
			case 'delivered':
				return 'default';
			case 'sent':
			case 'queued':
			case 'pending':
				return 'secondary';
			case 'failed':
			case 'undelivered':
				return 'destructive';
			default:
				return 'outline';
		}
	}

	onMount(() => {
		loadMessages();
	});

	$effect(() => {
		if (phone && connectionId) {
			loadMessages();
		}
	});
</script>

<div class="flex flex-col h-full">
	<!-- Header -->
	<div class="p-4 border-b">
		<div class="flex items-center gap-3">
			<div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
				<Phone class="h-5 w-5 text-primary" />
			</div>
			<div>
				<h3 class="font-medium">{phone}</h3>
				<p class="text-sm text-muted-foreground">{messages.length} messages</p>
			</div>
		</div>
	</div>

	<!-- Messages -->
	<div class="flex-1 overflow-hidden">
		{#if loading}
			<div class="flex items-center justify-center h-full">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if messages.length === 0}
			<div class="flex flex-col items-center justify-center h-full text-muted-foreground">
				<Phone class="h-8 w-8 mb-2 opacity-50" />
				<p class="text-sm">No messages yet</p>
				<p class="text-xs">Send a message to start the conversation</p>
			</div>
		{:else}
			<ScrollArea class="h-full p-4">
				<div bind:this={messagesContainer} class="space-y-4">
					{#each messages as message}
						<div class="flex {message.direction === 'outbound' ? 'justify-end' : 'justify-start'}">
							<div class="max-w-[80%] {message.direction === 'outbound' ? 'bg-primary text-primary-foreground' : 'bg-muted'} rounded-lg p-3">
								<div class="flex items-center gap-2 mb-1 text-xs opacity-70">
									{#if message.direction === 'outbound'}
										<ArrowUpRight class="h-3 w-3" />
										<span>Sent</span>
									{:else}
										<ArrowDownLeft class="h-3 w-3" />
										<span>Received</span>
									{/if}
								</div>
								<p class="text-sm whitespace-pre-wrap">{message.content}</p>
								<div class="flex items-center justify-between gap-2 mt-2 text-xs opacity-70">
									<span>{formatDistanceToNow(new Date(message.created_at), { addSuffix: true })}</span>
									{#if message.direction === 'outbound'}
										{@const StatusIcon = getStatusIcon(message.status)}
										<Badge variant={getStatusColor(message.status)} class="text-xs h-5">
											<StatusIcon class="h-3 w-3 mr-1" />
											{message.status}
										</Badge>
									{/if}
								</div>
								{#if message.error_message}
									<p class="text-xs text-destructive mt-1">{message.error_message}</p>
								{/if}
							</div>
						</div>
					{/each}
				</div>
			</ScrollArea>
		{/if}
	</div>

	<!-- Input -->
	<div class="p-4 border-t">
		<form onsubmit={(e) => { e.preventDefault(); handleSend(); }} class="flex gap-2">
			<Input
				bind:value={newMessage}
				placeholder="Type a message..."
				disabled={sending}
				class="flex-1"
			/>
			<Button type="submit" disabled={sending || !newMessage.trim()}>
				{#if sending}
					<Loader2 class="h-4 w-4 animate-spin" />
				{:else}
					<Send class="h-4 w-4" />
				{/if}
			</Button>
		</form>
		<p class="text-xs text-muted-foreground mt-1 text-right">
			{newMessage.length} / 160 characters
		</p>
	</div>
</div>
