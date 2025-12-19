<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { sharedInboxApi, type SharedInbox } from '$lib/api/shared-inbox';
	import {
		Plus,
		Settings,
		Trash2,
		RefreshCw,
		CheckCircle,
		XCircle,
		Inbox,
		Users,
		Mail,
		AlertCircle
	} from 'lucide-svelte';

	interface Props {
		onSelect?: (inbox: SharedInbox) => void;
	}

	let { onSelect }: Props = $props();

	let inboxes = $state<SharedInbox[]>([]);
	let loading = $state(true);
	let showCreateDialog = $state(false);
	let syncing = $state<number | null>(null);
	let verifying = $state<number | null>(null);

	let form = $state({
		name: '',
		email: '',
		description: '',
		type: 'support' as 'support' | 'sales' | 'general',
		imap_host: '',
		imap_port: 993,
		imap_encryption: 'ssl' as 'ssl' | 'tls' | 'none',
		smtp_host: '',
		smtp_port: 587,
		smtp_encryption: 'tls' as 'ssl' | 'tls' | 'none',
		username: '',
		password: '',
		assignment_method: 'round_robin' as 'round_robin' | 'load_balanced' | 'manual'
	});

	async function loadInboxes() {
		loading = true;
		try {
			inboxes = await sharedInboxApi.list();
		} catch (err) {
			console.error('Failed to load inboxes:', err);
		} finally {
			loading = false;
		}
	}

	async function createInbox() {
		try {
			const inbox = await sharedInboxApi.create({
				name: form.name,
				email: form.email,
				description: form.description || undefined,
				type: form.type,
				imap_host: form.imap_host || undefined,
				imap_port: form.imap_port || undefined,
				imap_encryption: form.imap_encryption,
				smtp_host: form.smtp_host || undefined,
				smtp_port: form.smtp_port || undefined,
				smtp_encryption: form.smtp_encryption,
				username: form.username || undefined,
				password: form.password || undefined,
				assignment_method: form.assignment_method
			});
			inboxes = [inbox, ...inboxes];
			showCreateDialog = false;
			resetForm();
		} catch (err) {
			console.error('Failed to create inbox:', err);
		}
	}

	async function deleteInbox(id: number) {
		if (!confirm('Are you sure you want to delete this inbox?')) return;
		try {
			await sharedInboxApi.delete(id);
			inboxes = inboxes.filter((i) => i.id !== id);
		} catch (err) {
			console.error('Failed to delete inbox:', err);
		}
	}

	async function verifyInbox(inbox: SharedInbox) {
		verifying = inbox.id;
		try {
			const result = await sharedInboxApi.verify(inbox.id);
			const idx = inboxes.findIndex((i) => i.id === inbox.id);
			if (idx !== -1) {
				inboxes[idx] = result.data;
			}
			if (result.verification.imap.success && result.verification.smtp.success) {
				alert('Connection verified successfully!');
			} else {
				const errors = [];
				if (!result.verification.imap.success) errors.push(`IMAP: ${result.verification.imap.error}`);
				if (!result.verification.smtp.success) errors.push(`SMTP: ${result.verification.smtp.error}`);
				alert(`Verification issues:\n${errors.join('\n')}`);
			}
		} catch (err) {
			console.error('Failed to verify inbox:', err);
		} finally {
			verifying = null;
		}
	}

	async function syncInbox(inbox: SharedInbox) {
		syncing = inbox.id;
		try {
			const result = await sharedInboxApi.sync(inbox.id);
			const idx = inboxes.findIndex((i) => i.id === inbox.id);
			if (idx !== -1) {
				inboxes[idx] = result.data;
			}
			alert(`Synced ${result.synced_count} new messages`);
		} catch (err) {
			console.error('Failed to sync inbox:', err);
		} finally {
			syncing = null;
		}
	}

	function resetForm() {
		form = {
			name: '',
			email: '',
			description: '',
			type: 'support',
			imap_host: '',
			imap_port: 993,
			imap_encryption: 'ssl',
			smtp_host: '',
			smtp_port: 587,
			smtp_encryption: 'tls',
			username: '',
			password: '',
			assignment_method: 'round_robin'
		};
	}

	function getTypeLabel(type: string): string {
		const labels: Record<string, string> = {
			support: 'Support',
			sales: 'Sales',
			general: 'General'
		};
		return labels[type] ?? type;
	}

	$effect(() => {
		loadInboxes();
	});
</script>

<div class="space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h2 class="text-lg font-semibold">Shared Inboxes</h2>
			<p class="text-sm text-muted-foreground">Manage team email inboxes</p>
		</div>
		<Button onclick={() => (showCreateDialog = true)}>
			<Plus class="mr-2 h-4 w-4" />
			Create Inbox
		</Button>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-8">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if inboxes.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Inbox class="h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="font-medium mb-2">No Shared Inboxes</h3>
				<p class="text-sm text-muted-foreground mb-4">Create a shared inbox to manage team emails</p>
				<Button onclick={() => (showCreateDialog = true)}>
					<Plus class="mr-2 h-4 w-4" />
					Create Inbox
				</Button>
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each inboxes as inbox (inbox.id)}
				<Card.Root
					class="cursor-pointer hover:shadow-md transition-shadow"
					onclick={() => onSelect?.(inbox)}
				>
					<Card.Header class="pb-2">
						<div class="flex items-start justify-between">
							<div class="flex items-center gap-2">
								<Mail class="h-5 w-5 text-muted-foreground" />
								<div>
									<Card.Title class="text-base">{inbox.name}</Card.Title>
									<p class="text-xs text-muted-foreground">{inbox.email}</p>
								</div>
							</div>
							<div class="flex items-center gap-1">
								<Badge variant={inbox.type === 'support' ? 'default' : inbox.type === 'sales' ? 'secondary' : 'outline'}>
									{getTypeLabel(inbox.type)}
								</Badge>
							</div>
						</div>
					</Card.Header>
					<Card.Content>
						<div class="flex items-center gap-4 text-sm text-muted-foreground mb-3">
							{#if inbox.is_connected}
								<span class="flex items-center gap-1 text-green-600">
									<CheckCircle class="h-4 w-4" />
									Connected
								</span>
							{:else}
								<span class="flex items-center gap-1 text-yellow-600">
									<AlertCircle class="h-4 w-4" />
									Not connected
								</span>
							{/if}
							{#if !inbox.is_active}
								<Badge variant="secondary">Inactive</Badge>
							{/if}
						</div>

						{#if inbox.stats}
							<div class="grid grid-cols-3 gap-2 text-center text-sm mb-3">
								<div>
									<div class="font-semibold">{inbox.stats.open}</div>
									<div class="text-xs text-muted-foreground">Open</div>
								</div>
								<div>
									<div class="font-semibold">{inbox.stats.unassigned}</div>
									<div class="text-xs text-muted-foreground">Unassigned</div>
								</div>
								<div>
									<div class="font-semibold">{inbox.members_count ?? 0}</div>
									<div class="text-xs text-muted-foreground">Members</div>
								</div>
							</div>
						{/if}

						<div class="flex items-center gap-2" onclick={(e) => e.stopPropagation()}>
							<Button
								variant="outline"
								size="sm"
								onclick={() => verifyInbox(inbox)}
								disabled={verifying === inbox.id}
							>
								{#if verifying === inbox.id}
									<RefreshCw class="mr-1 h-3 w-3 animate-spin" />
								{:else}
									<CheckCircle class="mr-1 h-3 w-3" />
								{/if}
								Verify
							</Button>
							<Button
								variant="outline"
								size="sm"
								onclick={() => syncInbox(inbox)}
								disabled={syncing === inbox.id || !inbox.is_connected}
							>
								{#if syncing === inbox.id}
									<RefreshCw class="mr-1 h-3 w-3 animate-spin" />
								{:else}
									<RefreshCw class="mr-1 h-3 w-3" />
								{/if}
								Sync
							</Button>
							<Button
								variant="ghost"
								size="sm"
								class="text-destructive ml-auto"
								onclick={() => deleteInbox(inbox.id)}
							>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{/if}
</div>

<!-- Create Inbox Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
		<Dialog.Header>
			<Dialog.Title>Create Shared Inbox</Dialog.Title>
			<Dialog.Description>Set up a shared email inbox for your team</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="name">Inbox Name</Label>
					<Input id="name" bind:value={form.name} placeholder="Support Inbox" />
				</div>
				<div class="space-y-2">
					<Label for="email">Email Address</Label>
					<Input id="email" type="email" bind:value={form.email} placeholder="support@company.com" />
				</div>
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Input id="description" bind:value={form.description} placeholder="Handle customer support inquiries" />
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label>Type</Label>
					<Select.Root
						type="single"
						value={form.type}
						onValueChange={(v) => {
							if (v) form.type = v as 'support' | 'sales' | 'general';
						}}
					>
						<Select.Trigger>
							<span>{getTypeLabel(form.type)}</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="support">Support</Select.Item>
							<Select.Item value="sales">Sales</Select.Item>
							<Select.Item value="general">General</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>
				<div class="space-y-2">
					<Label>Assignment Method</Label>
					<Select.Root
						type="single"
						value={form.assignment_method}
						onValueChange={(v) => {
							if (v) form.assignment_method = v as 'round_robin' | 'load_balanced' | 'manual';
						}}
					>
						<Select.Trigger>
							<span class="capitalize">{form.assignment_method.replace('_', ' ')}</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="round_robin">Round Robin</Select.Item>
							<Select.Item value="load_balanced">Load Balanced</Select.Item>
							<Select.Item value="manual">Manual</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="border-t pt-4">
				<h4 class="font-medium mb-3">IMAP Settings (Incoming)</h4>
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="imap_host">Host</Label>
						<Input id="imap_host" bind:value={form.imap_host} placeholder="imap.gmail.com" />
					</div>
					<div class="space-y-2">
						<Label for="imap_port">Port</Label>
						<Input id="imap_port" type="number" bind:value={form.imap_port} />
					</div>
				</div>
			</div>

			<div class="border-t pt-4">
				<h4 class="font-medium mb-3">SMTP Settings (Outgoing)</h4>
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="smtp_host">Host</Label>
						<Input id="smtp_host" bind:value={form.smtp_host} placeholder="smtp.gmail.com" />
					</div>
					<div class="space-y-2">
						<Label for="smtp_port">Port</Label>
						<Input id="smtp_port" type="number" bind:value={form.smtp_port} />
					</div>
				</div>
			</div>

			<div class="border-t pt-4">
				<h4 class="font-medium mb-3">Credentials</h4>
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="username">Username</Label>
						<Input id="username" bind:value={form.username} placeholder="Email or username" />
					</div>
					<div class="space-y-2">
						<Label for="password">Password / App Password</Label>
						<Input id="password" type="password" bind:value={form.password} />
					</div>
				</div>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={createInbox} disabled={!form.name || !form.email}>Create Inbox</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
