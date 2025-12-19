<script lang="ts">
	import { onMount, tick } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Badge } from '$lib/components/ui/badge';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Select from '$lib/components/ui/select';
	import {
		whatsappConversationsApi,
		whatsappTemplatesApi,
		type WhatsappConversation,
		type WhatsappMessage,
		type WhatsappTemplate
	} from '$lib/api/whatsapp';
	import {
		Send,
		MoreVertical,
		Phone,
		Clock,
		Check,
		CheckCheck,
		X,
		AlertCircle,
		Image,
		FileText,
		Loader2
	} from 'lucide-svelte';

	interface Props {
		conversation: WhatsappConversation;
		onUpdate?: (conversation: WhatsappConversation) => void;
	}

	let { conversation, onUpdate }: Props = $props();

	let messages = $state<WhatsappMessage[]>([]);
	let loading = $state(true);
	let sending = $state(false);
	let messageContent = $state('');
	let messagesEndRef = $state<HTMLDivElement | null>(null);
	let templates = $state<WhatsappTemplate[]>([]);
	let selectedTemplate = $state<string>('');
	let showTemplateSelector = $state(false);

	// Check if we're within the 24-hour window
	const canSendFreeForm = $derived(() => {
		if (!conversation.last_incoming_at) return false;
		const lastIncoming = new Date(conversation.last_incoming_at);
		const now = new Date();
		const diff = now.getTime() - lastIncoming.getTime();
		return diff < 24 * 60 * 60 * 1000;
	});

	async function loadMessages() {
		loading = true;
		try {
			const result = await whatsappConversationsApi.getMessages(conversation.id);
			messages = result.data.reverse();
			await tick();
			scrollToBottom();
		} catch (err) {
			console.error('Failed to load messages:', err);
		}
		loading = false;
	}

	async function loadTemplates() {
		try {
			templates = await whatsappTemplatesApi.list({
				connection_id: conversation.connection_id,
				status: 'APPROVED'
			});
		} catch (err) {
			console.error('Failed to load templates:', err);
		}
	}

	async function sendMessage() {
		if (!messageContent.trim() || sending) return;

		if (!canSendFreeForm()) {
			showTemplateSelector = true;
			return;
		}

		sending = true;
		try {
			const newMessage = await whatsappConversationsApi.sendMessage(conversation.id, {
				type: 'text',
				content: messageContent.trim()
			});
			messages = [...messages, newMessage];
			messageContent = '';
			await tick();
			scrollToBottom();
		} catch (err) {
			console.error('Failed to send message:', err);
		}
		sending = false;
	}

	async function sendTemplateMessage() {
		if (!selectedTemplate || sending) return;

		sending = true;
		try {
			const newMessage = await whatsappConversationsApi.sendMessage(conversation.id, {
				type: 'template',
				template_id: parseInt(selectedTemplate)
			});
			messages = [...messages, newMessage];
			selectedTemplate = '';
			showTemplateSelector = false;
			await tick();
			scrollToBottom();
		} catch (err) {
			console.error('Failed to send template:', err);
		}
		sending = false;
	}

	async function closeConversation() {
		try {
			const updated = await whatsappConversationsApi.close(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to close conversation:', err);
		}
	}

	async function reopenConversation() {
		try {
			const updated = await whatsappConversationsApi.reopen(conversation.id);
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
	}

	function formatTime(dateStr: string | null): string {
		if (!dateStr) return '';
		return new Date(dateStr).toLocaleTimeString('en-US', {
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function getStatusIcon(status: string) {
		switch (status) {
			case 'sent':
				return Check;
			case 'delivered':
				return CheckCheck;
			case 'read':
				return CheckCheck;
			case 'failed':
				return AlertCircle;
			default:
				return Clock;
		}
	}

	onMount(() => {
		loadMessages();
		loadTemplates();
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
			<div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
				<Phone class="h-5 w-5 text-green-600" />
			</div>
			<div>
				<h3 class="font-medium">{conversation.display_name}</h3>
				<p class="text-sm text-muted-foreground">{conversation.contact_phone}</p>
			</div>
		</div>

		<div class="flex items-center gap-2">
			<Badge variant={conversation.status === 'open' ? 'default' : conversation.status === 'closed' ? 'outline' : 'secondary'}>
				{conversation.status}
			</Badge>

			{#if !canSendFreeForm()}
				<Badge variant="secondary" class="text-xs">
					<Clock class="h-3 w-3 mr-1" />
					Template Only
				</Badge>
			{/if}

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
							<Phone class="h-4 w-4 mr-2" />
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
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if messages.length === 0}
			<div class="flex items-center justify-center h-full">
				<div class="text-muted-foreground">No messages yet</div>
			</div>
		{:else}
			{#each messages as message}
				<div class="flex {message.direction === 'outbound' ? 'justify-end' : 'justify-start'}">
					<div
						class="max-w-[70%] {message.direction === 'outbound'
							? message.status === 'failed'
								? 'bg-destructive/10 border border-destructive'
								: 'bg-green-600 text-white'
							: 'bg-muted'} rounded-lg px-4 py-2"
					>
						{#if message.type === 'template' && message.template}
							<div class="text-xs opacity-70 mb-1">Template: {message.template.name}</div>
						{/if}

						{#if message.media}
							<div class="mb-2">
								{#if message.type === 'image'}
									<Image class="h-5 w-5 inline mr-1" />
									<span class="text-sm">Image</span>
								{:else if message.type === 'document'}
									<FileText class="h-5 w-5 inline mr-1" />
									<span class="text-sm">{message.media.filename || 'Document'}</span>
								{:else}
									<span class="text-sm">{message.type}</span>
								{/if}
							</div>
						{/if}

						<p class="text-sm whitespace-pre-wrap">{message.content || ''}</p>

						<div
							class="flex items-center justify-end gap-1 mt-1 text-xs {message.direction === 'outbound' && message.status !== 'failed'
								? 'text-white/70'
								: 'text-muted-foreground'}"
						>
							<span>{formatTime(message.created_at)}</span>
							{#if message.direction === 'outbound'}
								{@const StatusIcon = getStatusIcon(message.status)}
								<StatusIcon class="h-3 w-3 {message.status === 'read' ? 'text-blue-400' : ''} {message.status === 'failed' ? 'text-destructive' : ''}" />
							{/if}
						</div>

						{#if message.status === 'failed' && message.error_message}
							<p class="text-xs text-destructive mt-1">{message.error_message}</p>
						{/if}
					</div>
				</div>
			{/each}
			<div bind:this={messagesEndRef}></div>
		{/if}
	</div>

	<!-- Input -->
	<div class="p-4 border-t">
		{#if showTemplateSelector}
			<div class="mb-4 p-3 bg-muted rounded-lg">
				<p class="text-sm text-muted-foreground mb-2">
					Outside 24-hour window. Select a template to message this contact:
				</p>
				<div class="flex gap-2">
					<Select.Root type="single" bind:value={selectedTemplate}>
						<Select.Trigger class="flex-1">
							{templates.find(t => t.id.toString() === selectedTemplate)?.name || 'Select template...'}
						</Select.Trigger>
						<Select.Content>
							{#each templates as template}
								<Select.Item value={template.id.toString()} label={template.name}>
									{template.name}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
					<Button onclick={sendTemplateMessage} disabled={!selectedTemplate || sending}>
						{#if sending}
							<Loader2 class="h-4 w-4 animate-spin" />
						{:else}
							Send
						{/if}
					</Button>
					<Button variant="ghost" onclick={() => (showTemplateSelector = false)}>
						Cancel
					</Button>
				</div>
			</div>
		{/if}

		<div class="flex gap-2">
			<Textarea
				bind:value={messageContent}
				placeholder={canSendFreeForm() ? 'Type a message...' : 'Click to select a template...'}
				class="min-h-[60px] resize-none"
				onkeydown={handleKeyDown}
				onfocus={() => {
					if (!canSendFreeForm()) showTemplateSelector = true;
				}}
			/>
			<Button onclick={sendMessage} disabled={!messageContent.trim() || sending} class="h-[60px]">
				{#if sending}
					<Loader2 class="h-4 w-4 animate-spin" />
				{:else}
					<Send class="h-4 w-4" />
				{/if}
			</Button>
		</div>

		{#if !canSendFreeForm()}
			<p class="text-xs text-muted-foreground mt-1">
				WhatsApp requires using templates outside the 24-hour messaging window.
			</p>
		{/if}
	</div>
</div>
