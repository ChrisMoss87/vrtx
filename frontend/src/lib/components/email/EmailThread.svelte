<script lang="ts">
	import {
		Reply,
		Forward,
		Star,
		MoreHorizontal,
		ChevronDown,
		ChevronUp,
		Paperclip,
		Eye,
		MousePointer2,
		Trash2,
		Archive,
		Tag
	} from 'lucide-svelte';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import { Badge } from '$lib/components/ui/badge';
	import { Separator } from '$lib/components/ui/separator';
	import { Avatar, AvatarFallback } from '$lib/components/ui/avatar';
	import { emailsApi, type EmailMessage } from '$lib/api/email';
	import { cn } from '$lib/utils';
	import { formatDistanceToNow } from 'date-fns';

	interface Props {
		messages: EmailMessage[];
		onreply?: (message: EmailMessage) => void;
		onforward?: (message: EmailMessage) => void;
		ondelete?: (message: EmailMessage) => void;
		onarchive?: (message: EmailMessage) => void;
		class?: string;
	}

	let { messages, onreply, onforward, ondelete, onarchive, class: className = '' }: Props = $props();

	// Track expanded messages (last one is expanded by default)
	let expandedIds = $state<Set<number>>(new Set(messages.length > 0 ? [messages[messages.length - 1].id] : []));

	// Derived
	let subject = $derived(messages[0]?.subject ?? '(No Subject)');
	let participantCount = $derived(
		new Set([
			...messages.map((m) => m.from_email),
			...messages.flatMap((m) => m.to_emails.map((r) => (typeof r === 'string' ? r : r.email)))
		]).size
	);

	function toggleExpanded(id: number) {
		const newSet = new Set(expandedIds);
		if (newSet.has(id)) {
			newSet.delete(id);
		} else {
			newSet.add(id);
		}
		expandedIds = newSet;
	}

	function expandAll() {
		expandedIds = new Set(messages.map((m) => m.id));
	}

	function collapseAll() {
		expandedIds = new Set([messages[messages.length - 1].id]);
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
		return formatDistanceToNow(date, { addSuffix: true });
	}

	function formatFullDate(dateString: string | null): string {
		if (!dateString) return '';
		return new Date(dateString).toLocaleString();
	}

	function getRecipientList(message: EmailMessage): string {
		const recipients = message.to_emails.map((r) => (typeof r === 'string' ? r : r.email));
		if (recipients.length <= 2) {
			return recipients.join(', ');
		}
		return `${recipients[0]} and ${recipients.length - 1} others`;
	}
</script>

<Card.Root class={cn('flex h-full flex-col', className)}>
	<!-- Thread Header -->
	<Card.Header class="flex-none border-b">
		<div class="flex items-start justify-between">
			<div class="flex-1">
				<Card.Title class="text-lg">{subject}</Card.Title>
				<Card.Description>
					{messages.length} message{messages.length !== 1 ? 's' : ''} · {participantCount} participant{participantCount !==
					1
						? 's'
						: ''}
				</Card.Description>
			</div>
			<div class="flex items-center gap-1">
				<Button variant="ghost" size="sm" onclick={expandAll}>Expand all</Button>
				<Button variant="ghost" size="sm" onclick={collapseAll}>Collapse</Button>
			</div>
		</div>
	</Card.Header>

	<!-- Messages -->
	<Card.Content class="flex-1 space-y-2 overflow-y-auto p-4">
		{#each messages as message, index (message.id)}
			{@const isExpanded = expandedIds.has(message.id)}
			{@const isLast = index === messages.length - 1}

			<Collapsible.Root open={isExpanded} onOpenChange={() => toggleExpanded(message.id)}>
				<div
					class={cn(
						'rounded-lg border transition-colors',
						isExpanded ? 'bg-background' : 'bg-muted/30 hover:bg-muted/50'
					)}
				>
					<!-- Message Header (always visible) -->
					<Collapsible.Trigger class="w-full">
						<div class="flex items-start gap-3 p-4">
							<Avatar class="h-10 w-10">
								<AvatarFallback>
									{getInitials(message.from_name, message.from_email)}
								</AvatarFallback>
							</Avatar>

							<div class="min-w-0 flex-1 text-left">
								<div class="flex items-center gap-2">
									<span class="font-medium">
										{message.from_name ?? message.from_email}
									</span>
									{#if message.direction === 'outbound'}
										<Badge variant="outline" class="text-xs">Sent</Badge>
									{/if}
									{#if !message.is_read && message.direction === 'inbound'}
										<Badge variant="default" class="text-xs">New</Badge>
									{/if}
								</div>

								{#if isExpanded}
									<div class="mt-1 text-sm text-muted-foreground">
										<span>To: {getRecipientList(message)}</span>
									</div>
								{:else}
									<p class="mt-1 truncate text-sm text-muted-foreground">
										{message.body_text?.slice(0, 100) ?? ''}
									</p>
								{/if}
							</div>

							<div class="flex shrink-0 items-center gap-2">
								{#if message.has_attachments}
									<Paperclip class="h-4 w-4 text-muted-foreground" />
								{/if}

								{#if message.direction === 'outbound' && message.open_count > 0}
									<div class="flex items-center gap-1 text-xs text-muted-foreground" title="Opened">
										<Eye class="h-3 w-3" />
										{message.open_count}
									</div>
								{/if}

								{#if message.direction === 'outbound' && message.click_count > 0}
									<div class="flex items-center gap-1 text-xs text-muted-foreground" title="Clicked">
										<MousePointer2 class="h-3 w-3" />
										{message.click_count}
									</div>
								{/if}

								<button
									type="button"
									class={cn(
										'transition-colors hover:text-yellow-500',
										message.is_starred ? 'text-yellow-500' : 'text-muted-foreground'
									)}
									onclick={(e) => toggleStar(message, e)}
								>
									<Star class={cn('h-4 w-4', message.is_starred && 'fill-current')} />
								</button>

								<span class="text-xs text-muted-foreground" title={formatFullDate(message.received_at ?? message.sent_at)}>
									{formatDate(message.received_at ?? message.sent_at)}
								</span>

								{#if isExpanded}
									<ChevronUp class="h-4 w-4 text-muted-foreground" />
								{:else}
									<ChevronDown class="h-4 w-4 text-muted-foreground" />
								{/if}
							</div>
						</div>
					</Collapsible.Trigger>

					<!-- Message Body (collapsed content) -->
					<Collapsible.Content>
						<Separator />
						<div class="p-4">
							<!-- Email body -->
							<div
								class="prose prose-sm dark:prose-invert max-w-none"
							>
								{#if message.body_html}
									{@html message.body_html}
								{:else}
									<pre class="whitespace-pre-wrap font-sans">{message.body_text ?? ''}</pre>
								{/if}
							</div>

							<!-- Attachments -->
							{#if message.attachments && message.attachments.length > 0}
								<div class="mt-4 border-t pt-4">
									<h4 class="mb-2 text-sm font-medium">
										Attachments ({message.attachments.length})
									</h4>
									<div class="flex flex-wrap gap-2">
										{#each message.attachments as attachment}
											<div
												class="flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2"
											>
												<Paperclip class="h-4 w-4 text-muted-foreground" />
												<div class="text-sm">
													<div class="font-medium">{attachment.filename}</div>
													<div class="text-xs text-muted-foreground">
														{(attachment.size / 1024).toFixed(1)} KB
													</div>
												</div>
											</div>
										{/each}
									</div>
								</div>
							{/if}

							<!-- Actions -->
							<div class="mt-4 flex items-center gap-2 border-t pt-4">
								<Button variant="outline" size="sm" onclick={() => onreply?.(message)}>
									<Reply class="mr-2 h-4 w-4" />
									Reply
								</Button>
								<Button variant="outline" size="sm" onclick={() => onforward?.(message)}>
									<Forward class="mr-2 h-4 w-4" />
									Forward
								</Button>

								<div class="flex-1"></div>

								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button {...props} variant="ghost" size="icon">
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => onarchive?.(message)}>
											<Archive class="mr-2 h-4 w-4" />
											Archive
										</DropdownMenu.Item>
										<DropdownMenu.Item>
											<Tag class="mr-2 h-4 w-4" />
											Add label
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive"
											onclick={() => ondelete?.(message)}
										>
											<Trash2 class="mr-2 h-4 w-4" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</div>
						</div>
					</Collapsible.Content>
				</div>
			</Collapsible.Root>
		{/each}
	</Card.Content>

	<!-- Quick Reply -->
	<Card.Footer class="flex-none border-t p-4">
		<Button class="w-full" variant="outline" onclick={() => onreply?.(messages[messages.length - 1])}>
			<Reply class="mr-2 h-4 w-4" />
			Reply to this thread
		</Button>
	</Card.Footer>
</Card.Root>
