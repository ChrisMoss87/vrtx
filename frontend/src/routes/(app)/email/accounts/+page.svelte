<script lang="ts">
	import {
		Plus,
		Trash2,
		Settings,
		Check,
		X,
		RefreshCw,
		Mail,
		ArrowLeft
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import {
		emailAccountsApi,
		type EmailAccount,
		type CreateAccountData
	} from '$lib/api/email';
	import { cn } from '$lib/utils';

	// State
	let accounts = $state<EmailAccount[]>([]);
	let isLoading = $state(true);
	let showDialog = $state(false);
	let editingAccount = $state<EmailAccount | null>(null);
	let testingAccountId = $state<number | null>(null);
	let testResult = $state<{ success: boolean; message: string } | null>(null);
	let isSaving = $state(false);

	// Form state
	let formData = $state<CreateAccountData>({
		name: '',
		email_address: '',
		provider: 'imap',
		smtp_host: '',
		smtp_port: 587,
		smtp_encryption: 'tls',
		imap_host: '',
		imap_port: 993,
		imap_encryption: 'ssl',
		username: '',
		password: '',
		signature: '',
		sync_folders: ['INBOX'],
		is_default: false
	});

	// Load accounts on mount
	$effect(() => {
		loadAccounts();
	});

	async function loadAccounts() {
		isLoading = true;
		try {
			accounts = await emailAccountsApi.list();
		} catch (error) {
			console.error('Failed to load accounts:', error);
		} finally {
			isLoading = false;
		}
	}

	function openDialog(account?: EmailAccount) {
		if (account) {
			editingAccount = account;
			formData = {
				name: account.name,
				email_address: account.email_address,
				provider: account.provider,
				smtp_host: account.smtp_host,
				smtp_port: account.smtp_port,
				smtp_encryption: account.smtp_encryption,
				imap_host: account.imap_host ?? '',
				imap_port: account.imap_port,
				imap_encryption: account.imap_encryption,
				username: account.username ?? '',
				password: '',
				signature: account.signature ?? '',
				sync_folders: account.sync_folders,
				is_default: account.is_default
			};
		} else {
			editingAccount = null;
			formData = {
				name: '',
				email_address: '',
				provider: 'imap',
				smtp_host: '',
				smtp_port: 587,
				smtp_encryption: 'tls',
				imap_host: '',
				imap_port: 993,
				imap_encryption: 'ssl',
				username: '',
				password: '',
				signature: '',
				sync_folders: ['INBOX'],
				is_default: false
			};
		}
		testResult = null;
		showDialog = true;
	}

	async function saveAccount() {
		isSaving = true;
		try {
			if (editingAccount) {
				await emailAccountsApi.update(editingAccount.id, formData);
			} else {
				await emailAccountsApi.create(formData);
			}
			showDialog = false;
			await loadAccounts();
		} catch (error) {
			console.error('Failed to save account:', error);
		} finally {
			isSaving = false;
		}
	}

	async function deleteAccount(account: EmailAccount) {
		if (!confirm('Are you sure you want to delete this email account?')) return;

		try {
			await emailAccountsApi.delete(account.id);
			await loadAccounts();
		} catch (error) {
			console.error('Failed to delete account:', error);
		}
	}

	async function testConnection(accountId: number) {
		testingAccountId = accountId;
		testResult = null;
		try {
			testResult = await emailAccountsApi.testConnection(accountId);
		} catch (error) {
			testResult = { success: false, message: 'Connection test failed' };
		} finally {
			testingAccountId = null;
		}
	}

	async function toggleDefault(account: EmailAccount) {
		try {
			await emailAccountsApi.update(account.id, { is_default: !account.is_default });
			await loadAccounts();
		} catch (error) {
			console.error('Failed to update account:', error);
		}
	}

	async function toggleSync(account: EmailAccount) {
		try {
			await emailAccountsApi.update(account.id, { sync_enabled: !account.sync_enabled });
			await loadAccounts();
		} catch (error) {
			console.error('Failed to update account:', error);
		}
	}

	const providerOptions = [
		{ value: 'imap', label: 'IMAP/SMTP' },
		{ value: 'gmail', label: 'Gmail (OAuth)' },
		{ value: 'outlook', label: 'Outlook (OAuth)' },
		{ value: 'smtp_only', label: 'SMTP Only (Send Only)' }
	];

	const encryptionOptions = [
		{ value: 'ssl', label: 'SSL' },
		{ value: 'tls', label: 'TLS' },
		{ value: 'none', label: 'None' }
	];
</script>

<div class="container max-w-4xl py-6">
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" href="/email">
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-semibold">Email Accounts</h1>
				<p class="text-muted-foreground">Manage your connected email accounts</p>
			</div>
		</div>
		<Button onclick={() => openDialog()}>
			<Plus class="mr-2 h-4 w-4" />
			Add Account
		</Button>
	</div>

	{#if isLoading}
		<div class="flex items-center justify-center py-12">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if accounts.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Mail class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="mb-2 text-lg font-medium">No email accounts</h3>
				<p class="mb-4 text-muted-foreground">Add an email account to start sending and receiving emails</p>
				<Button onclick={() => openDialog()}>
					<Plus class="mr-2 h-4 w-4" />
					Add Account
				</Button>
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="space-y-4">
			{#each accounts as account}
				<Card.Root>
					<Card.Content class="flex items-center justify-between p-4">
						<div class="flex items-center gap-4">
							<div
								class={cn(
									'flex h-10 w-10 items-center justify-center rounded-full',
									account.is_active ? 'bg-green-100 text-green-600' : 'bg-muted text-muted-foreground'
								)}
							>
								<Mail class="h-5 w-5" />
							</div>
							<div>
								<div class="flex items-center gap-2">
									<span class="font-medium">{account.name}</span>
									{#if account.is_default}
										<Badge variant="secondary">Default</Badge>
									{/if}
									<Badge variant="outline" class="text-xs">{account.provider}</Badge>
								</div>
								<p class="text-sm text-muted-foreground">{account.email_address}</p>
								{#if account.last_sync_at}
									<p class="text-xs text-muted-foreground">
										Last synced: {new Date(account.last_sync_at).toLocaleString()}
									</p>
								{/if}
							</div>
						</div>

						<div class="flex items-center gap-2">
							<div class="flex items-center gap-2 mr-4">
								<Label class="text-sm text-muted-foreground">Sync</Label>
								<Switch
									checked={account.sync_enabled}
									onCheckedChange={() => toggleSync(account)}
								/>
							</div>

							<Button
								variant="outline"
								size="sm"
								onclick={() => testConnection(account.id)}
								disabled={testingAccountId === account.id}
							>
								{#if testingAccountId === account.id}
									<RefreshCw class="mr-2 h-4 w-4 animate-spin" />
								{:else}
									<Check class="mr-2 h-4 w-4" />
								{/if}
								Test
							</Button>

							<Button variant="outline" size="sm" onclick={() => openDialog(account)}>
								<Settings class="mr-2 h-4 w-4" />
								Edit
							</Button>

							<Button
								variant="ghost"
								size="icon"
								class="text-destructive hover:text-destructive"
								onclick={() => deleteAccount(account)}
							>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{/if}

	{#if testResult}
		<div
			class={cn(
				'fixed bottom-4 right-4 flex items-center gap-2 rounded-lg px-4 py-2 shadow-lg',
				testResult.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
			)}
		>
			{#if testResult.success}
				<Check class="h-4 w-4" />
			{:else}
				<X class="h-4 w-4" />
			{/if}
			{testResult.message}
		</div>
	{/if}
</div>

<!-- Account Dialog -->
<Dialog.Root bind:open={showDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>{editingAccount ? 'Edit Account' : 'Add Email Account'}</Dialog.Title>
			<Dialog.Description>
				{editingAccount
					? 'Update your email account settings'
					: 'Connect a new email account to send and receive emails'}
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="name">Account Name</Label>
					<Input id="name" bind:value={formData.name} placeholder="Work Email" />
				</div>
				<div class="space-y-2">
					<Label for="email">Email Address</Label>
					<Input
						id="email"
						type="email"
						bind:value={formData.email_address}
						placeholder="you@example.com"
					/>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="provider">Provider</Label>
				<Select.Root
					type="single"
					value={formData.provider}
					onValueChange={(v) => v && (formData.provider = v as typeof formData.provider)}
				>
					<Select.Trigger>
						{providerOptions.find(p => p.value === formData.provider)?.label ?? 'Select provider'}
					</Select.Trigger>
					<Select.Content>
						{#each providerOptions as option}
							<Select.Item value={option.value}>{option.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			{#if formData.provider === 'imap' || formData.provider === 'smtp_only'}
				<div class="space-y-4 rounded-lg border p-4">
					<h4 class="font-medium">SMTP Settings (Outgoing)</h4>
					<div class="grid grid-cols-3 gap-4">
						<div class="col-span-2 space-y-2">
							<Label for="smtp_host">SMTP Host</Label>
							<Input id="smtp_host" bind:value={formData.smtp_host} placeholder="smtp.example.com" />
						</div>
						<div class="space-y-2">
							<Label for="smtp_port">Port</Label>
							<Input id="smtp_port" type="number" bind:value={formData.smtp_port} />
						</div>
					</div>
					<div class="space-y-2">
						<Label for="smtp_encryption">Encryption</Label>
						<Select.Root
							type="single"
							value={formData.smtp_encryption}
							onValueChange={(v) => v && (formData.smtp_encryption = v as typeof formData.smtp_encryption)}
						>
							<Select.Trigger>
								{encryptionOptions.find(e => e.value === formData.smtp_encryption)?.label ?? 'Select encryption'}
							</Select.Trigger>
							<Select.Content>
								{#each encryptionOptions as option}
									<Select.Item value={option.value}>{option.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
				</div>

				{#if formData.provider === 'imap'}
					<div class="space-y-4 rounded-lg border p-4">
						<h4 class="font-medium">IMAP Settings (Incoming)</h4>
						<div class="grid grid-cols-3 gap-4">
							<div class="col-span-2 space-y-2">
								<Label for="imap_host">IMAP Host</Label>
								<Input id="imap_host" bind:value={formData.imap_host} placeholder="imap.example.com" />
							</div>
							<div class="space-y-2">
								<Label for="imap_port">Port</Label>
								<Input id="imap_port" type="number" bind:value={formData.imap_port} />
							</div>
						</div>
						<div class="space-y-2">
							<Label for="imap_encryption">Encryption</Label>
							<Select.Root
								type="single"
								value={formData.imap_encryption}
								onValueChange={(v) => v && (formData.imap_encryption = v as typeof formData.imap_encryption)}
							>
								<Select.Trigger>
									{encryptionOptions.find(e => e.value === formData.imap_encryption)?.label ?? 'Select encryption'}
								</Select.Trigger>
								<Select.Content>
									{#each encryptionOptions as option}
										<Select.Item value={option.value}>{option.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
					</div>
				{/if}

				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="username">Username</Label>
						<Input id="username" bind:value={formData.username} placeholder="Usually your email" />
					</div>
					<div class="space-y-2">
						<Label for="password">Password</Label>
						<Input
							id="password"
							type="password"
							bind:value={formData.password}
							placeholder={editingAccount ? '(unchanged)' : 'Enter password'}
						/>
					</div>
				</div>
			{:else}
				<div class="rounded-lg bg-muted p-4 text-center text-sm text-muted-foreground">
					Click save to connect with {formData.provider === 'gmail' ? 'Google' : 'Microsoft'} OAuth
				</div>
			{/if}

			<div class="space-y-2">
				<Label for="signature">Email Signature</Label>
				<Textarea
					id="signature"
					bind:value={formData.signature}
					placeholder="Your email signature..."
					rows={3}
				/>
			</div>

			<div class="flex items-center gap-2">
				<Switch
					id="is_default"
					checked={formData.is_default}
					onCheckedChange={(v) => (formData.is_default = v)}
				/>
				<Label for="is_default">Set as default account</Label>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showDialog = false)}>Cancel</Button>
			<Button onclick={saveAccount} disabled={isSaving}>
				{isSaving ? 'Saving...' : editingAccount ? 'Update Account' : 'Add Account'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
