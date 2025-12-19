<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import {
		inboxConversationApi,
		inboxCannedResponseApi,
		type InboxConversation,
		type InboxMessage,
		type InboxCannedResponse
	} from '$lib/api/shared-inbox';
	import {
		Send,
		StickyNote,
		CheckCircle,
		RotateCcw,
		Star,
		StarOff,
		User,
		Clock,
		Mail,
		MailOpen,
		AlertTriangle,
		Tag,
		RefreshCw,
		ChevronDown,
		Archive
	} from 'lucide-svelte';

	interface Props {
		conversation: InboxConversation;
		onUpdate?: (conversation: InboxConversation) => void;
	}

	let { conversation, onUpdate }: Props = $props();

	let replyBody = $state('');
	let noteBody = $state('');
	let sending = $state(false);
	let activeTab = $state<'reply' | 'note'>('reply');
	let cannedResponses = $state<InboxCannedResponse[]>([]);
	let showCannedDropdown = $state(false);
	let newTag = $state('');

	async function loadCannedResponses() {
		try {
			cannedResponses = await inboxCannedResponseApi.list({ inbox_id: conversation.inbox_id });
		} catch (err) {
			console.error('Failed to load canned responses:', err);
		}
	}

	async function sendReply() {
		if (!replyBody.trim()) return;
		sending = true;
		try {
			await inboxConversationApi.reply(conversation.id, { body: replyBody });
			replyBody = '';
			// Refresh conversation
			const updated = await inboxConversationApi.get(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to send reply:', err);
		} finally {
			sending = false;
		}
	}

	async function addNote() {
		if (!noteBody.trim()) return;
		sending = true;
		try {
			await inboxConversationApi.addNote(conversation.id, noteBody);
			noteBody = '';
			const updated = await inboxConversationApi.get(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to add note:', err);
		} finally {
			sending = false;
		}
	}

	async function resolve() {
		try {
			const updated = await inboxConversationApi.resolve(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to resolve:', err);
		}
	}

	async function reopen() {
		try {
			const updated = await inboxConversationApi.reopen(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to reopen:', err);
		}
	}

	async function toggleStar() {
		try {
			const updated = await inboxConversationApi.toggleStar(conversation.id);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to toggle star:', err);
		}
	}

	async function updatePriority(priority: string) {
		try {
			const updated = await inboxConversationApi.update(conversation.id, { priority });
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to update priority:', err);
		}
	}

	async function addTag() {
		if (!newTag.trim()) return;
		try {
			const updated = await inboxConversationApi.addTag(conversation.id, newTag.trim());
			newTag = '';
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to add tag:', err);
		}
	}

	async function removeTag(tag: string) {
		try {
			const updated = await inboxConversationApi.removeTag(conversation.id, tag);
			onUpdate?.(updated);
		} catch (err) {
			console.error('Failed to remove tag:', err);
		}
	}

	function useCannedResponse(response: InboxCannedResponse) {
		if (activeTab === 'reply') {
			replyBody = response.body;
		} else {
			noteBody = response.body;
		}
		showCannedDropdown = false;
	}

	function formatDate(dateStr: string | null): string {
		if (!dateStr) return '';
		return new Date(dateStr).toLocaleString();
	}

	$effect(() => {
		loadCannedResponses();
	});
</script>

<div class="flex flex-col h-full">
	<!-- Header -->
	<div class="border-b p-4">
		<div class="flex items-start justify-between mb-2">
			<div>
				<h2 class="text-lg font-semibold">{conversation.subject}</h2>
				<p class="text-sm text-muted-foreground">
					{conversation.contact_name || conversation.contact_email}
					{#if conversation.contact_name && conversation.contact_email}
						&lt;{conversation.contact_email}&gt;
					{/if}
				</p>
			</div>
			<div class="flex items-center gap-2">
				<Button variant="ghost" size="icon" onclick={toggleStar}>
					{#if conversation.is_starred}
						<Star class="h-5 w-5 fill-yellow-400 text-yellow-400" />
					{:else}
						<StarOff class="h-5 w-5" />
					{/if}
				</Button>
				{#if conversation.status === 'resolved' || conversation.status === 'closed'}
					<Button variant="outline" onclick={reopen}>
						<RotateCcw class="mr-2 h-4 w-4" />
						Reopen
					</Button>
				{:else}
					<Button onclick={resolve}>
						<CheckCircle class="mr-2 h-4 w-4" />
						Resolve
					</Button>
				{/if}
			</div>
		</div>

		<div class="flex items-center gap-3 flex-wrap">
			<Badge variant={conversation.status === 'open' ? 'default' : 'secondary'}>
				{conversation.status}
			</Badge>

			<Select.Root
				type="single"
				value={conversation.priority}
				onValueChange={(v) => v && updatePriority(v)}
			>
				<Select.Trigger class="w-28 h-7">
					<span class="capitalize text-sm">{conversation.priority}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="urgent">Urgent</Select.Item>
					<Select.Item value="high">High</Select.Item>
					<Select.Item value="normal">Normal</Select.Item>
					<Select.Item value="low">Low</Select.Item>
				</Select.Content>
			</Select.Root>

			{#if conversation.assignee}
				<span class="text-sm flex items-center gap-1">
					<User class="h-4 w-4" />
					{conversation.assignee.name}
				</span>
			{:else}
				<span class="text-sm text-muted-foreground">Unassigned</span>
			{/if}

			<span class="text-sm text-muted-foreground">
				{conversation.message_count} messages
			</span>

			{#if conversation.response_time_seconds}
				<span class="text-sm text-muted-foreground flex items-center gap-1">
					<Clock class="h-4 w-4" />
					{Math.round(conversation.response_time_seconds / 60)}m response time
				</span>
			{/if}
		</div>

		{#if conversation.tags && conversation.tags.length > 0}
			<div class="flex items-center gap-1 mt-2 flex-wrap">
				{#each conversation.tags as tag}
					<Badge variant="outline" class="cursor-pointer" onclick={() => removeTag(tag)}>
						{tag} ×
					</Badge>
				{/each}
			</div>
		{/if}
	</div>

	<!-- Messages -->
	<div class="flex-1 overflow-y-auto p-4 space-y-4">
		{#if conversation.messages}
			{#each conversation.messages as message (message.id)}
				{@const messageClasses = [
					'rounded-lg border p-4',
					message.direction === 'outbound' ? 'bg-muted/50' : '',
					message.type === 'note' ? 'border-yellow-200 bg-yellow-50' : ''
				].filter(Boolean).join(' ')}
				<div class={messageClasses}>
					<div class="flex items-start justify-between mb-2">
						<div class="flex items-center gap-2">
							{#if message.type === 'note'}
								<StickyNote class="h-4 w-4 text-yellow-600" />
								<span class="font-medium text-yellow-700">Internal Note</span>
							{:else if message.direction === 'inbound'}
								<Mail class="h-4 w-4" />
								<span class="font-medium">{message.from_name || message.from_email}</span>
							{:else}
								<MailOpen class="h-4 w-4" />
								<span class="font-medium">{message.sender?.name || 'You'}</span>
							{/if}
						</div>
						<span class="text-xs text-muted-foreground">
							{formatDate(message.sent_at || message.created_at)}
						</span>
					</div>

					{#if message.body_html}
						<div class="prose prose-sm max-w-none">
							{@html message.body_html}
						</div>
					{:else if message.body_text}
						<p class="whitespace-pre-wrap">{message.body_text}</p>
					{/if}

					{#if message.attachments && message.attachments.length > 0}
						<div class="mt-2 flex items-center gap-2 flex-wrap">
							{#each message.attachments as attachment}
								<Badge variant="outline">
									📎 {attachment.name}
								</Badge>
							{/each}
						</div>
					{/if}
				</div>
			{/each}
		{/if}
	</div>

	<!-- Compose -->
	<div class="border-t p-4">
		<div class="flex items-center gap-2 mb-2">
			<Button
				variant={activeTab === 'reply' ? 'default' : 'ghost'}
				size="sm"
				onclick={() => (activeTab = 'reply')}
			>
				<Mail class="mr-1 h-4 w-4" />
				Reply
			</Button>
			<Button
				variant={activeTab === 'note' ? 'default' : 'ghost'}
				size="sm"
				onclick={() => (activeTab = 'note')}
			>
				<StickyNote class="mr-1 h-4 w-4" />
				Note
			</Button>

			<div class="ml-auto relative">
				<Button variant="outline" size="sm" onclick={() => (showCannedDropdown = !showCannedDropdown)}>
					Canned Responses
					<ChevronDown class="ml-1 h-4 w-4" />
				</Button>
				{#if showCannedDropdown && cannedResponses.length > 0}
					<div class="absolute right-0 bottom-full mb-1 w-64 bg-background border rounded-lg shadow-lg max-h-48 overflow-y-auto z-10">
						{#each cannedResponses as response (response.id)}
							<button
								class="w-full text-left px-3 py-2 hover:bg-muted text-sm"
								onclick={() => useCannedResponse(response)}
							>
								<div class="font-medium">{response.name}</div>
								{#if response.shortcut}
									<div class="text-xs text-muted-foreground">/{response.shortcut}</div>
								{/if}
							</button>
						{/each}
					</div>
				{/if}
			</div>
		</div>

		{#if activeTab === 'reply'}
			<Textarea
				bind:value={replyBody}
				placeholder="Type your reply..."
				rows={4}
				class="mb-2"
			/>
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-2">
					<input
						type="text"
						class="text-sm border rounded px-2 py-1 w-32"
						placeholder="Add tag..."
						bind:value={newTag}
						onkeydown={(e) => e.key === 'Enter' && addTag()}
					/>
				</div>
				<Button onclick={sendReply} disabled={!replyBody.trim() || sending}>
					{#if sending}
						<RefreshCw class="mr-2 h-4 w-4 animate-spin" />
					{:else}
						<Send class="mr-2 h-4 w-4" />
					{/if}
					Send Reply
				</Button>
			</div>
		{:else}
			<Textarea
				bind:value={noteBody}
				placeholder="Add an internal note (only visible to team members)..."
				rows={4}
				class="mb-2 border-yellow-200"
			/>
			<div class="flex justify-end">
				<Button onclick={addNote} disabled={!noteBody.trim() || sending} variant="secondary">
					{#if sending}
						<RefreshCw class="mr-2 h-4 w-4 animate-spin" />
					{:else}
						<StickyNote class="mr-2 h-4 w-4" />
					{/if}
					Add Note
				</Button>
			</div>
		{/if}
	</div>
</div>
