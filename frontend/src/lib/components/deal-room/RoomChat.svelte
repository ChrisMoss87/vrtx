<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Send, Lock } from 'lucide-svelte';
	import { getMessages, sendMessage, type DealRoomMessage } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	interface Props {
		roomId: number;
	}

	let { roomId }: Props = $props();

	let messages = $state<DealRoomMessage[]>([]);
	let loading = $state(true);
	let newMessage = $state('');
	let isInternal = $state(false);
	let sending = $state(false);
	let showInternal = $state(true);

	onMount(async () => {
		await loadMessages();
	});

	async function loadMessages() {
		loading = true;
		const { data, error } = await tryCatch(getMessages(roomId, showInternal));
		loading = false;

		if (error) {
			toast.error('Failed to load messages');
			return;
		}

		messages = data ?? [];
	}

	async function handleSend() {
		if (!newMessage.trim()) return;

		sending = true;
		const { data, error } = await tryCatch(sendMessage(roomId, newMessage.trim(), isInternal));
		sending = false;

		if (error) {
			toast.error('Failed to send message');
			return;
		}

		if (data) {
			messages = [data, ...messages];
		}
		newMessage = '';
	}

	function formatTime(dateStr: string): string {
		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = now.getTime() - date.getTime();
		const diffMins = Math.floor(diffMs / 60000);
		const diffHours = Math.floor(diffMs / 3600000);
		const diffDays = Math.floor(diffMs / 86400000);

		if (diffMins < 1) return 'Just now';
		if (diffMins < 60) return `${diffMins}m ago`;
		if (diffHours < 24) return `${diffHours}h ago`;
		if (diffDays < 7) return `${diffDays}d ago`;

		return date.toLocaleDateString();
	}
</script>

<div class="flex flex-col h-[calc(100vh-16rem)]">
	<!-- Messages -->
	<div class="flex-1 overflow-y-auto p-4 space-y-4">
		{#if loading}
			<div class="flex items-center justify-center py-12">
				<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
			</div>
		{:else if messages.length === 0}
			<div class="text-center py-12 text-muted-foreground">
				<p>No messages yet</p>
				<p class="text-sm mt-1">Start the conversation</p>
			</div>
		{:else}
			{#each messages as msg}
				<div class="flex gap-3 {msg.is_internal ? 'bg-amber-50/50 dark:bg-amber-950/20 -mx-2 px-2 py-2 rounded-lg' : ''}">
					<div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium flex-shrink-0">
						{msg.member?.name?.charAt(0)?.toUpperCase() ?? '?'}
					</div>
					<div class="flex-1 min-w-0">
						<div class="flex items-center gap-2">
							<span class="font-medium text-sm">{msg.member?.name ?? 'Unknown'}</span>
							{#if msg.is_internal}
								<span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 flex items-center gap-1">
									<Lock class="h-3 w-3" />
									Internal
								</span>
							{:else if !msg.member?.is_internal}
								<span class="text-xs text-muted-foreground">External</span>
							{/if}
							<span class="text-xs text-muted-foreground">{formatTime(msg.created_at)}</span>
						</div>
						<p class="text-sm mt-1 whitespace-pre-wrap">{msg.message}</p>
					</div>
				</div>
			{/each}
		{/if}
	</div>

	<!-- Input -->
	<div class="border-t p-4">
		<form
			onsubmit={(e) => {
				e.preventDefault();
				handleSend();
			}}
			class="flex gap-2"
		>
			<div class="flex-1">
				<Input
					bind:value={newMessage}
					placeholder={isInternal ? 'Internal message (team only)...' : 'Send a message...'}
					disabled={sending}
					class={isInternal ? 'border-amber-300 focus-visible:ring-amber-400' : ''}
				/>
			</div>
			<div class="flex items-center gap-2">
				<label class="flex items-center gap-1.5 text-sm cursor-pointer">
					<Checkbox bind:checked={isInternal} />
					<Lock class="h-3.5 w-3.5 text-muted-foreground" />
				</label>
				<Button type="submit" size="icon" disabled={!newMessage.trim() || sending}>
					<Send class="h-4 w-4" />
				</Button>
			</div>
		</form>
		{#if isInternal}
			<p class="text-xs text-amber-600 mt-2">
				This message will only be visible to internal team members
			</p>
		{/if}
	</div>
</div>
