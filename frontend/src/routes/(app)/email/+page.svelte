<script lang="ts">
	import {
		Inbox,
		Send,
		FileText,
		Star,
		Trash2,
		Archive,
		Search,
		Plus,
		RefreshCw,
		MoreHorizontal,
		Settings,
		ChevronLeft
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Resizable from '$lib/components/ui/resizable';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Separator } from '$lib/components/ui/separator';
	import { Badge } from '$lib/components/ui/badge';
	import EmailList from '$lib/components/email/EmailList.svelte';
	import EmailThread from '$lib/components/email/EmailThread.svelte';
	import EmailComposer from '$lib/components/email/EmailComposer.svelte';
	import {
		emailsApi,
		emailAccountsApi,
		type EmailMessage,
		type EmailAccount,
		type EmailFilters
	} from '$lib/api/email';
	import { cn } from '$lib/utils';

	// State
	let accounts = $state<EmailAccount[]>([]);
	let messages = $state<EmailMessage[]>([]);
	let selectedMessage = $state<EmailMessage | null>(null);
	let threadMessages = $state<EmailMessage[]>([]);
	let currentFolder = $state<'inbox' | 'sent' | 'drafts' | 'starred' | 'trash' | 'archive'>('inbox');
	let searchQuery = $state('');
	let isLoading = $state(false);
	let isSyncing = $state(false);
	let showComposer = $state(false);
	let composerReplyTo = $state<EmailMessage | undefined>(undefined);
	let composerForwardFrom = $state<EmailMessage | undefined>(undefined);
	let selectedIds = $state<number[]>([]);
	let unreadCount = $state(0);

	// Pagination
	let currentPage = $state(1);
	let totalPages = $state(1);
	let perPage = 25;

	// Derived - get folder filters based on current folder
	function getFolderFilters(): EmailFilters {
		switch (currentFolder) {
			case 'inbox':
				return { direction: 'inbound', folder: 'INBOX' };
			case 'sent':
				return { direction: 'outbound', status: 'sent' };
			case 'drafts':
				return { status: 'draft' };
			case 'starred':
				return { is_starred: true };
			case 'trash':
				return { folder: 'Trash' };
			case 'archive':
				return { folder: 'Archive' };
			default:
				return {};
		}
	}

	// Load data on mount and folder change
	$effect(() => {
		loadMessages();
	});

	$effect(() => {
		loadAccounts();
	});

	async function loadAccounts() {
		try {
			accounts = await emailAccountsApi.list();
		} catch (error) {
			console.error('Failed to load accounts:', error);
		}
	}

	async function loadMessages() {
		isLoading = true;
		try {
			const filters: EmailFilters = {
				...getFolderFilters(),
				search: searchQuery || undefined,
				per_page: perPage
			};

			const response = await emailsApi.list(filters);
			messages = response.data;
			currentPage = response.meta.current_page;
			totalPages = response.meta.last_page;

			// Get unread count for inbox
			if (currentFolder !== 'inbox') {
				const inboxResponse = await emailsApi.list({
					direction: 'inbound',
					folder: 'INBOX',
					is_read: false,
					per_page: 1
				});
				unreadCount = inboxResponse.meta.total;
			} else {
				unreadCount = messages.filter((m) => !m.is_read).length;
			}
		} catch (error) {
			console.error('Failed to load messages:', error);
		} finally {
			isLoading = false;
		}
	}

	async function syncEmails() {
		if (accounts.length === 0) return;

		isSyncing = true;
		try {
			for (const account of accounts.filter((a) => a.sync_enabled)) {
				await emailAccountsApi.sync(account.id);
			}
			await loadMessages();
		} catch (error) {
			console.error('Failed to sync emails:', error);
		} finally {
			isSyncing = false;
		}
	}

	async function selectMessage(message: EmailMessage) {
		selectedMessage = message;

		// Load thread if exists
		if (message.thread_id) {
			try {
				threadMessages = await emailsApi.getThread(message.id);
			} catch (error) {
				threadMessages = [message];
			}
		} else {
			threadMessages = [message];
		}

		// Mark as read
		if (!message.is_read && message.direction === 'inbound') {
			await emailsApi.markRead(message.id);
			message.is_read = true;
		}
	}

	function openComposer(replyTo?: EmailMessage, forwardFrom?: EmailMessage) {
		composerReplyTo = replyTo;
		composerForwardFrom = forwardFrom;
		showComposer = true;
	}

	function closeComposer() {
		showComposer = false;
		composerReplyTo = undefined;
		composerForwardFrom = undefined;
	}

	async function handleDelete(message: EmailMessage) {
		try {
			await emailsApi.delete(message.id);
			messages = messages.filter((m) => m.id !== message.id);
			if (selectedMessage?.id === message.id) {
				selectedMessage = null;
				threadMessages = [];
			}
		} catch (error) {
			console.error('Failed to delete message:', error);
		}
	}

	async function handleBulkDelete() {
		if (selectedIds.length === 0) return;
		try {
			await emailsApi.bulkDelete(selectedIds);
			messages = messages.filter((m) => !selectedIds.includes(m.id));
			selectedIds = [];
		} catch (error) {
			console.error('Failed to delete messages:', error);
		}
	}

	async function handleBulkMarkRead() {
		if (selectedIds.length === 0) return;
		try {
			await emailsApi.bulkMarkRead(selectedIds);
			messages = messages.map((m) =>
				selectedIds.includes(m.id) ? { ...m, is_read: true } : m
			);
			selectedIds = [];
		} catch (error) {
			console.error('Failed to mark messages as read:', error);
		}
	}

	function handleSearch() {
		loadMessages();
	}

	const folders = [
		{ id: 'inbox', label: 'Inbox', icon: Inbox, showCount: true },
		{ id: 'sent', label: 'Sent', icon: Send, showCount: false },
		{ id: 'drafts', label: 'Drafts', icon: FileText, showCount: false },
		{ id: 'starred', label: 'Starred', icon: Star, showCount: false },
		{ id: 'archive', label: 'Archive', icon: Archive, showCount: false },
		{ id: 'trash', label: 'Trash', icon: Trash2, showCount: false }
	] as const;
</script>

<div class="flex h-[calc(100vh-4rem)] flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b px-4 py-3">
		<div class="flex items-center gap-4">
			<h1 class="text-xl font-semibold">Email</h1>
			<div class="relative">
				<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<Input
					bind:value={searchQuery}
					placeholder="Search emails..."
					class="w-64 pl-9"
					onkeydown={(e) => e.key === 'Enter' && handleSearch()}
				/>
			</div>
		</div>
		<div class="flex items-center gap-2">
			<Button onclick={() => openComposer()}>
				<Plus class="mr-2 h-4 w-4" />
				Compose
			</Button>
			<Button variant="outline" onclick={syncEmails} disabled={isSyncing}>
				<RefreshCw class={cn('mr-2 h-4 w-4', isSyncing && 'animate-spin')} />
				Sync
			</Button>
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					{#snippet child({ props })}
						<Button {...props} variant="ghost" size="icon">
							<Settings class="h-4 w-4" />
						</Button>
					{/snippet}
				</DropdownMenu.Trigger>
				<DropdownMenu.Content align="end">
					<DropdownMenu.Item>
						<a href="/email/accounts" class="flex w-full">Email Accounts</a>
					</DropdownMenu.Item>
					<DropdownMenu.Item>
						<a href="/email/templates" class="flex w-full">Email Templates</a>
					</DropdownMenu.Item>
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</div>
	</div>

	<!-- Main Content -->
	<div class="flex flex-1 overflow-hidden">
		<!-- Sidebar -->
		<div class="w-56 shrink-0 border-r">
			<div class="flex flex-col gap-1 p-2">
				{#each folders as folder}
					<Button
						variant={currentFolder === folder.id ? 'secondary' : 'ghost'}
						class="justify-start"
						onclick={() => {
							currentFolder = folder.id;
							selectedMessage = null;
							threadMessages = [];
						}}
					>
						<folder.icon class="mr-2 h-4 w-4" />
						{folder.label}
						{#if folder.showCount && unreadCount > 0}
							<Badge variant="default" class="ml-auto">
								{unreadCount}
							</Badge>
						{/if}
					</Button>
				{/each}
			</div>

			<Separator class="my-2" />

			<!-- Accounts -->
			<div class="p-2">
				<div class="mb-2 px-2 text-xs font-medium text-muted-foreground">Accounts</div>
				{#each accounts as account}
					<div class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm">
						<div
							class={cn(
								'h-2 w-2 rounded-full',
								account.is_active ? 'bg-green-500' : 'bg-muted'
							)}
						></div>
						<span class="truncate">{account.name}</span>
					</div>
				{/each}
				{#if accounts.length === 0}
					<p class="px-2 text-xs text-muted-foreground">No accounts configured</p>
				{/if}
			</div>
		</div>

		<!-- Email List and Thread View -->
		<Resizable.PaneGroup direction="horizontal" class="flex-1">
			<Resizable.Pane defaultSize={40} minSize={30}>
				<div class="flex h-full flex-col">
					<!-- Bulk Actions -->
					{#if selectedIds.length > 0}
						<div class="flex items-center gap-2 border-b bg-muted/50 px-4 py-2">
							<span class="text-sm text-muted-foreground">
								{selectedIds.length} selected
							</span>
							<Button variant="ghost" size="sm" onclick={handleBulkMarkRead}>
								Mark as read
							</Button>
							<Button variant="ghost" size="sm" class="text-destructive" onclick={handleBulkDelete}>
								Delete
							</Button>
						</div>
					{/if}

					<!-- Message List -->
					<ScrollArea class="flex-1">
						{#if isLoading}
							<div class="flex items-center justify-center py-12">
								<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
							</div>
						{:else}
							<EmailList
								{messages}
								selectedId={selectedMessage?.id}
								{selectedIds}
								selectable
								onselect={selectMessage}
								onselectionchange={(ids) => (selectedIds = ids)}
							/>
						{/if}
					</ScrollArea>
				</div>
			</Resizable.Pane>

			<Resizable.Handle withHandle />

			<Resizable.Pane defaultSize={60} minSize={40}>
				{#if selectedMessage && threadMessages.length > 0}
					<EmailThread
						messages={threadMessages}
						onreply={(msg) => openComposer(msg)}
						onforward={(msg) => openComposer(undefined, msg)}
						ondelete={handleDelete}
						class="h-full"
					/>
				{:else}
					<div class="flex h-full items-center justify-center text-muted-foreground">
						<div class="text-center">
							<Inbox class="mx-auto mb-4 h-12 w-12 opacity-50" />
							<p>Select an email to view</p>
						</div>
					</div>
				{/if}
			</Resizable.Pane>
		</Resizable.PaneGroup>
	</div>

	<!-- Composer Modal -->
	{#if showComposer}
		<div class="fixed inset-0 z-50 flex items-end justify-end bg-black/20 p-4">
			<div class="h-[600px] w-[600px]">
				<EmailComposer
					replyTo={composerReplyTo}
					forwardFrom={composerForwardFrom}
					onclose={closeComposer}
					onsend={() => {
						closeComposer();
						loadMessages();
					}}
				/>
			</div>
		</div>
	{/if}
</div>
