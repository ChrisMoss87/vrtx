<script lang="ts">
	import { X, Paperclip, Send, Clock, ChevronDown, Users, Maximize2, Minimize2 } from 'lucide-svelte';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Popover from '$lib/components/ui/popover';
	import { Badge } from '$lib/components/ui/badge';
	import RichTextEditor from '$lib/components/editor/RichTextEditor.svelte';
	import {
		emailsApi,
		emailAccountsApi,
		emailTemplatesApi,
		type EmailAccount,
		type EmailMessage,
		type EmailTemplate,
		type CreateEmailData
	} from '$lib/api/email';
	import { cn } from '$lib/utils';

	interface Props {
		initialTo?: string[];
		initialCc?: string[];
		initialBcc?: string[];
		initialSubject?: string;
		initialBody?: string;
		replyTo?: EmailMessage;
		forwardFrom?: EmailMessage;
		linkedRecordType?: string;
		linkedRecordId?: number;
		defaultAccountId?: number;
		onclose?: () => void;
		onsend?: (message: EmailMessage) => void;
		onsave?: (message: EmailMessage) => void;
		class?: string;
	}

	let {
		initialTo = [],
		initialCc = [],
		initialBcc = [],
		initialSubject = '',
		initialBody = '',
		replyTo,
		forwardFrom,
		linkedRecordType,
		linkedRecordId,
		defaultAccountId,
		onclose,
		onsend,
		onsave,
		class: className = ''
	}: Props = $props();

	// State
	let accounts = $state<EmailAccount[]>([]);
	let templates = $state<EmailTemplate[]>([]);
	let selectedAccountId = $state<number | null>(defaultAccountId ?? null);
	let toInput = $state('');
	let ccInput = $state('');
	let bccInput = $state('');
	let toEmails = $state<string[]>([...initialTo]);
	let ccEmails = $state<string[]>([...initialCc]);
	let bccEmails = $state<string[]>([...initialBcc]);
	let subject = $state(initialSubject);
	let bodyHtml = $state(initialBody);
	let showCc = $state(initialCc.length > 0);
	let showBcc = $state(initialBcc.length > 0);
	let isExpanded = $state(false);
	let isSending = $state(false);
	let isSaving = $state(false);
	let draftId = $state<number | null>(null);
	let lastSaved = $state<Date | null>(null);
	let editorRef: RichTextEditor;

	// Derived
	let selectedAccount = $derived(accounts.find((a) => a.id === selectedAccountId));
	let canSend = $derived(toEmails.length > 0 && selectedAccountId !== null);

	// Load accounts and templates
	$effect(() => {
		loadAccounts();
		loadTemplates();
	});

	async function loadAccounts() {
		try {
			accounts = await emailAccountsApi.list();
			if (!selectedAccountId && accounts.length > 0) {
				const defaultAccount = accounts.find((a) => a.is_default) ?? accounts[0];
				selectedAccountId = defaultAccount.id;
			}
		} catch (error) {
			console.error('Failed to load email accounts:', error);
		}
	}

	async function loadTemplates() {
		try {
			const response = await emailTemplatesApi.list({ is_active: true, per_page: 50 });
			templates = response.data;
		} catch (error) {
			console.error('Failed to load templates:', error);
		}
	}

	// Email validation
	function isValidEmail(email: string): boolean {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
	}

	function addEmail(type: 'to' | 'cc' | 'bcc') {
		const input = type === 'to' ? toInput : type === 'cc' ? ccInput : bccInput;
		const emails = input
			.split(/[,;]/)
			.map((e) => e.trim())
			.filter((e) => isValidEmail(e));

		if (emails.length > 0) {
			if (type === 'to') {
				toEmails = [...new Set([...toEmails, ...emails])];
				toInput = '';
			} else if (type === 'cc') {
				ccEmails = [...new Set([...ccEmails, ...emails])];
				ccInput = '';
			} else {
				bccEmails = [...new Set([...bccEmails, ...emails])];
				bccInput = '';
			}
		}
	}

	function removeEmail(type: 'to' | 'cc' | 'bcc', email: string) {
		if (type === 'to') {
			toEmails = toEmails.filter((e) => e !== email);
		} else if (type === 'cc') {
			ccEmails = ccEmails.filter((e) => e !== email);
		} else {
			bccEmails = bccEmails.filter((e) => e !== email);
		}
	}

	function handleKeydown(event: KeyboardEvent, type: 'to' | 'cc' | 'bcc') {
		if (event.key === 'Enter' || event.key === ',' || event.key === ';' || event.key === 'Tab') {
			event.preventDefault();
			addEmail(type);
		}
	}

	async function saveDraft() {
		if (!selectedAccountId) return;

		isSaving = true;
		try {
			const data: CreateEmailData = {
				account_id: selectedAccountId,
				to: toEmails,
				cc: ccEmails.length > 0 ? ccEmails : undefined,
				bcc: bccEmails.length > 0 ? bccEmails : undefined,
				subject,
				body_html: bodyHtml,
				body_text: editorRef?.getText?.() ?? '',
				linked_record_type: linkedRecordType,
				linked_record_id: linkedRecordId,
				parent_id: replyTo?.id,
				thread_id: replyTo?.thread_id ?? undefined
			};

			let message: EmailMessage;
			if (draftId) {
				const response = await emailsApi.update(draftId, data);
				message = response.data;
			} else {
				const response = await emailsApi.create(data);
				message = response.data;
				draftId = message.id;
			}

			lastSaved = new Date();
			onsave?.(message);
		} catch (error) {
			console.error('Failed to save draft:', error);
		} finally {
			isSaving = false;
		}
	}

	async function sendEmail() {
		if (!canSend || !selectedAccountId) return;

		isSending = true;
		try {
			// Save draft first if not saved
			if (!draftId) {
				await saveDraft();
			}

			if (draftId) {
				const response = await emailsApi.send(draftId);
				onsend?.(response.data);
				onclose?.();
			}
		} catch (error) {
			console.error('Failed to send email:', error);
		} finally {
			isSending = false;
		}
	}

	async function applyTemplate(template: EmailTemplate) {
		try {
			const response = await emailTemplatesApi.preview(template.id);
			subject = response.data.subject;
			bodyHtml = response.data.body_html;
		} catch (error) {
			console.error('Failed to apply template:', error);
		}
	}

	// Auto-save draft every 30 seconds
	$effect(() => {
		if (bodyHtml || subject || toEmails.length > 0) {
			const timer = setTimeout(() => {
				if (!isSending) {
					saveDraft();
				}
			}, 30000);

			return () => clearTimeout(timer);
		}
	});
</script>

<Card.Root class={cn('flex flex-col', isExpanded ? 'fixed inset-4 z-50' : 'h-full', className)}>
	<Card.Header class="flex-none border-b p-3">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<Card.Title class="text-base">
					{#if replyTo}
						Reply
					{:else if forwardFrom}
						Forward
					{:else}
						New Email
					{/if}
				</Card.Title>
				{#if lastSaved}
					<span class="text-xs text-muted-foreground">
						Saved {lastSaved.toLocaleTimeString()}
					</span>
				{/if}
			</div>
			<div class="flex items-center gap-1">
				<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => (isExpanded = !isExpanded)}>
					{#if isExpanded}
						<Minimize2 class="h-4 w-4" />
					{:else}
						<Maximize2 class="h-4 w-4" />
					{/if}
				</Button>
				{#if onclose}
					<Button variant="ghost" size="icon" class="h-8 w-8" onclick={onclose}>
						<X class="h-4 w-4" />
					</Button>
				{/if}
			</div>
		</div>
	</Card.Header>

	<Card.Content class="flex-1 overflow-y-auto p-0">
		<!-- From Account -->
		<div class="flex items-center gap-2 border-b px-3 py-2">
			<Label class="w-12 text-sm text-muted-foreground">From</Label>
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					{#snippet child({ props })}
						<Button {...props} variant="ghost" class="h-auto justify-start px-2 py-1">
							{#if selectedAccount}
								<span class="font-normal">{selectedAccount.email_address}</span>
							{:else}
								<span class="text-muted-foreground">Select account</span>
							{/if}
							<ChevronDown class="ml-1 h-3 w-3" />
						</Button>
					{/snippet}
				</DropdownMenu.Trigger>
				<DropdownMenu.Content>
					{#each accounts as account}
						<DropdownMenu.Item onclick={() => (selectedAccountId = account.id)}>
							<span class="font-medium">{account.name}</span>
							<span class="ml-2 text-muted-foreground">&lt;{account.email_address}&gt;</span>
						</DropdownMenu.Item>
					{/each}
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</div>

		<!-- To -->
		<div class="flex items-start gap-2 border-b px-3 py-2">
			<Label class="w-12 pt-1.5 text-sm text-muted-foreground">To</Label>
			<div class="flex flex-1 flex-wrap items-center gap-1">
				{#each toEmails as email}
					<Badge variant="secondary" class="gap-1 pr-1">
						{email}
						<button
							type="button"
							class="ml-1 rounded-full hover:bg-muted"
							onclick={() => removeEmail('to', email)}
						>
							<X class="h-3 w-3" />
						</button>
					</Badge>
				{/each}
				<Input
					bind:value={toInput}
					onkeydown={(e) => handleKeydown(e, 'to')}
					onblur={() => addEmail('to')}
					placeholder="Add recipients"
					class="h-7 min-w-[150px] flex-1 border-0 px-1 shadow-none focus-visible:ring-0"
				/>
			</div>
			<div class="flex gap-1">
				{#if !showCc}
					<Button variant="ghost" size="sm" class="h-7 text-xs" onclick={() => (showCc = true)}>
						Cc
					</Button>
				{/if}
				{#if !showBcc}
					<Button variant="ghost" size="sm" class="h-7 text-xs" onclick={() => (showBcc = true)}>
						Bcc
					</Button>
				{/if}
			</div>
		</div>

		<!-- CC -->
		{#if showCc}
			<div class="flex items-start gap-2 border-b px-3 py-2">
				<Label class="w-12 pt-1.5 text-sm text-muted-foreground">Cc</Label>
				<div class="flex flex-1 flex-wrap items-center gap-1">
					{#each ccEmails as email}
						<Badge variant="secondary" class="gap-1 pr-1">
							{email}
							<button
								type="button"
								class="ml-1 rounded-full hover:bg-muted"
								onclick={() => removeEmail('cc', email)}
							>
								<X class="h-3 w-3" />
							</button>
						</Badge>
					{/each}
					<Input
						bind:value={ccInput}
						onkeydown={(e) => handleKeydown(e, 'cc')}
						onblur={() => addEmail('cc')}
						placeholder="Add Cc recipients"
						class="h-7 min-w-[150px] flex-1 border-0 px-1 shadow-none focus-visible:ring-0"
					/>
				</div>
			</div>
		{/if}

		<!-- BCC -->
		{#if showBcc}
			<div class="flex items-start gap-2 border-b px-3 py-2">
				<Label class="w-12 pt-1.5 text-sm text-muted-foreground">Bcc</Label>
				<div class="flex flex-1 flex-wrap items-center gap-1">
					{#each bccEmails as email}
						<Badge variant="secondary" class="gap-1 pr-1">
							{email}
							<button
								type="button"
								class="ml-1 rounded-full hover:bg-muted"
								onclick={() => removeEmail('bcc', email)}
							>
								<X class="h-3 w-3" />
							</button>
						</Badge>
					{/each}
					<Input
						bind:value={bccInput}
						onkeydown={(e) => handleKeydown(e, 'bcc')}
						onblur={() => addEmail('bcc')}
						placeholder="Add Bcc recipients"
						class="h-7 min-w-[150px] flex-1 border-0 px-1 shadow-none focus-visible:ring-0"
					/>
				</div>
			</div>
		{/if}

		<!-- Subject -->
		<div class="flex items-center gap-2 border-b px-3 py-2">
			<Label class="w-12 text-sm text-muted-foreground">Subject</Label>
			<Input
				bind:value={subject}
				placeholder="Enter subject"
				class="h-7 flex-1 border-0 px-1 shadow-none focus-visible:ring-0"
			/>
		</div>

		<!-- Body -->
		<div class="flex-1 p-3">
			<RichTextEditor
				bind:this={editorRef}
				bind:content={bodyHtml}
				placeholder="Write your message..."
				minHeight="200px"
				maxHeight={isExpanded ? 'calc(100vh - 350px)' : '300px'}
			/>
		</div>
	</Card.Content>

	<Card.Footer class="flex-none border-t p-3">
		<div class="flex w-full items-center justify-between">
			<div class="flex items-center gap-2">
				<Button onclick={sendEmail} disabled={!canSend || isSending}>
					<Send class="mr-2 h-4 w-4" />
					{isSending ? 'Sending...' : 'Send'}
				</Button>

				<DropdownMenu.Root>
					<DropdownMenu.Trigger>
						{#snippet child({ props })}
							<Button {...props} variant="outline">
								<Clock class="mr-2 h-4 w-4" />
								Schedule
								<ChevronDown class="ml-1 h-3 w-3" />
							</Button>
						{/snippet}
					</DropdownMenu.Trigger>
					<DropdownMenu.Content>
						<DropdownMenu.Item>In 1 hour</DropdownMenu.Item>
						<DropdownMenu.Item>Tomorrow morning</DropdownMenu.Item>
						<DropdownMenu.Item>Tomorrow afternoon</DropdownMenu.Item>
						<DropdownMenu.Separator />
						<DropdownMenu.Item>Pick date & time...</DropdownMenu.Item>
					</DropdownMenu.Content>
				</DropdownMenu.Root>

				{#if templates.length > 0}
					<DropdownMenu.Root>
						<DropdownMenu.Trigger>
							{#snippet child({ props })}
								<Button {...props} variant="outline">
									<Users class="mr-2 h-4 w-4" />
									Templates
									<ChevronDown class="ml-1 h-3 w-3" />
								</Button>
							{/snippet}
						</DropdownMenu.Trigger>
						<DropdownMenu.Content class="max-h-[300px] overflow-y-auto">
							{#each templates as template}
								<DropdownMenu.Item onclick={() => applyTemplate(template)}>
									<div>
										<div class="font-medium">{template.name}</div>
										{#if template.description}
											<div class="text-xs text-muted-foreground">
												{template.description}
											</div>
										{/if}
									</div>
								</DropdownMenu.Item>
							{/each}
						</DropdownMenu.Content>
					</DropdownMenu.Root>
				{/if}
			</div>

			<div class="flex items-center gap-2">
				<Button variant="ghost" size="icon">
					<Paperclip class="h-4 w-4" />
				</Button>
				<Button variant="ghost" onclick={saveDraft} disabled={isSaving}>
					{isSaving ? 'Saving...' : 'Save Draft'}
				</Button>
			</div>
		</div>
	</Card.Footer>
</Card.Root>
