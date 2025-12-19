<script lang="ts">
	import { Star, Paperclip, Eye, Check, Circle } from 'lucide-svelte';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Avatar, AvatarFallback } from '$lib/components/ui/avatar';
	import { Badge } from '$lib/components/ui/badge';
	import { emailsApi, type EmailMessage } from '$lib/api/email';
	import { cn } from '$lib/utils';
	import { formatDistanceToNow } from 'date-fns';

	interface Props {
		messages: EmailMessage[];
		selectedId?: number;
		selectedIds?: number[];
		selectable?: boolean;
		onselect?: (message: EmailMessage) => void;
		onselectionchange?: (ids: number[]) => void;
		class?: string;
	}

	let {
		messages,
		selectedId,
		selectedIds = [],
		selectable = false,
		onselect,
		onselectionchange,
		class: className = ''
	}: Props = $props();

	// Local selection state
	let localSelectedIds = $state<Set<number>>(new Set(selectedIds));

	$effect(() => {
		localSelectedIds = new Set(selectedIds);
	});

	function toggleSelection(id: number, event: Event) {
		event.stopPropagation();
		const newSet = new Set(localSelectedIds);
		if (newSet.has(id)) {
			newSet.delete(id);
		} else {
			newSet.add(id);
		}
		localSelectedIds = newSet;
		onselectionchange?.([...newSet]);
	}

	function selectAll() {
		localSelectedIds = new Set(messages.map((m) => m.id));
		onselectionchange?.([...localSelectedIds]);
	}

	function selectNone() {
		localSelectedIds = new Set();
		onselectionchange?.([]);
	}

	async function toggleStar(message: EmailMessage, event: Event) {
		event.stopPropagation();
		try {
			await emailsApi.toggleStar(message.id);
			message.is_starred = !message.is_starred;
		} catch (error) {
			console.error('Failed to toggle star:', error);
		}
	}

	function getInitials(name: string | null, email: string): string {
		if (name) {
			return name
				.split(' ')
				.map((n) => n[0])
				.join('')
				.toUpperCase()
				.slice(0, 2);
		}
		return email.slice(0, 2).toUpperCase();
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return '';
		const date = new Date(dateString);
		const now = new Date();
		const diffDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24));

		if (diffDays === 0) {
			return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		} else if (diffDays < 7) {
			return date.toLocaleDateString([], { weekday: 'short' });
		} else {
			return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
		}
	}

	function getDisplayName(message: EmailMessage): string {
		if (message.direction === 'outbound') {
			const to = message.to_emails[0];
			return typeof to === 'string' ? to : to?.name ?? to?.email ?? 'Unknown';
		}
		return message.from_name ?? message.from_email;
	}

	export function getSelectedIds(): number[] {
		return [...localSelectedIds];
	}
</script>

<div class={cn('flex flex-col divide-y', className)}>
	{#if selectable && messages.length > 0}
		<div class="flex items-center gap-2 bg-muted/30 px-4 py-2 text-sm">
			<Checkbox
				checked={localSelectedIds.size === messages.length}
				onCheckedChange={(checked) => (checked ? selectAll() : selectNone())}
			/>
			<span class="text-muted-foreground">
				{#if localSelectedIds.size > 0}
					{localSelectedIds.size} selected
				{:else}
					Select all
				{/if}
			</span>
		</div>
	{/if}

	{#each messages as message (message.id)}
		{@const isSelected = selectedId === message.id}
		{@const isChecked = localSelectedIds.has(message.id)}

		<button
			type="button"
			class={cn(
				'flex w-full items-start gap-3 p-4 text-left transition-colors hover:bg-muted/50',
				isSelected && 'bg-accent',
				!message.is_read && message.direction === 'inbound' && 'bg-muted/30'
			)}
			onclick={() => onselect?.(message)}
		>
			{#if selectable}
				<div class="pt-1">
					<Checkbox
						checked={isChecked}
						onCheckedChange={() => {}}
						onclick={(e) => toggleSelection(message.id, e)}
					/>
				</div>
			{/if}

			<Avatar class="h-10 w-10 shrink-0">
				<AvatarFallback class={cn(!message.is_read && message.direction === 'inbound' && 'bg-primary text-primary-foreground')}>
					{getInitials(
						message.direction === 'outbound' ? null : message.from_name,
						message.direction === 'outbound' ? (typeof message.to_emails[0] === 'string' ? message.to_emails[0] : message.to_emails[0]?.email ?? '') : message.from_email
					)}
				</AvatarFallback>
			</Avatar>

			<div class="min-w-0 flex-1">
				<div class="flex items-center justify-between gap-2">
					<span
						class={cn(
							'truncate',
							!message.is_read && message.direction === 'inbound' && 'font-semibold'
						)}
					>
						{getDisplayName(message)}
					</span>
					<span class="shrink-0 text-xs text-muted-foreground">
						{formatDate(message.received_at ?? message.sent_at)}
					</span>
				</div>

				<div class="flex items-center gap-2">
					<span
						class={cn(
							'truncate text-sm',
							!message.is_read && message.direction === 'inbound'
								? 'font-medium text-foreground'
								: 'text-muted-foreground'
						)}
					>
						{message.subject ?? '(No Subject)'}
					</span>
				</div>

				<div class="mt-1 flex items-center gap-2">
					<span class="truncate text-xs text-muted-foreground">
						{message.body_text?.slice(0, 80) ?? ''}
					</span>
				</div>

				<div class="mt-1 flex items-center gap-2">
					{#if message.direction === 'outbound'}
						<Badge variant="outline" class="text-xs">
							{message.status === 'sent' ? 'Sent' : message.status === 'draft' ? 'Draft' : message.status}
						</Badge>
					{/if}

					{#if message.has_attachments}
						<Paperclip class="h-3 w-3 text-muted-foreground" />
					{/if}

					{#if message.direction === 'outbound' && message.open_count > 0}
						<div class="flex items-center gap-0.5 text-xs text-green-600" title="Opened">
							<Eye class="h-3 w-3" />
						</div>
					{/if}
				</div>
			</div>

			<div class="flex shrink-0 flex-col items-end gap-1">
				<!-- Use div with role="button" to avoid nested button error -->
				<div
					role="button"
					tabindex="0"
					class={cn(
						'cursor-pointer transition-colors hover:text-yellow-500',
						message.is_starred ? 'text-yellow-500' : 'text-muted-foreground'
					)}
					onclick={(e) => toggleStar(message, e)}
					onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') toggleStar(message, e); }}
				>
					<Star class={cn('h-4 w-4', message.is_starred && 'fill-current')} />
				</div>

				{#if !message.is_read && message.direction === 'inbound'}
					<Circle class="h-2 w-2 fill-primary text-primary" />
				{/if}
			</div>
		</button>
	{/each}

	{#if messages.length === 0}
		<div class="flex flex-col items-center justify-center py-12 text-muted-foreground">
			<p>No emails found</p>
		</div>
	{/if}
</div>
