<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { smsConnectionsApi, type SmsConnection } from '$lib/api/sms';
	import { Plus, Settings, Trash2, CheckCircle, XCircle, Loader2, Phone, RefreshCw } from 'lucide-svelte';

	let loading = $state(true);
	let connections = $state<SmsConnection[]>([]);
	let showCreateDialog = $state(false);
	let editingConnection = $state<SmsConnection | undefined>(undefined);
	let saving = $state(false);
	let verifying = $state<number | null>(null);

	// Form state
	let name = $state('');
	let provider = $state<string>('twilio');
	let phoneNumber = $state('');
	let accountSid = $state('');
	let authToken = $state('');
	let messagingServiceSid = $state('');
	let dailyLimit = $state(1000);
	let monthlyLimit = $state(30000);

	const providerOptions = [
		{ value: 'twilio', label: 'Twilio' },
		{ value: 'vonage', label: 'Vonage (Nexmo)' },
		{ value: 'messagebird', label: 'MessageBird' },
		{ value: 'plivo', label: 'Plivo' }
	];

	async function loadConnections() {
		loading = true;
		try {
			connections = await smsConnectionsApi.list();
		} catch (err) {
			console.error('Failed to load SMS connections:', err);
		}
		loading = false;
	}

	function openCreateDialog() {
		editingConnection = undefined;
		name = '';
		provider = 'twilio';
		phoneNumber = '';
		accountSid = '';
		authToken = '';
		messagingServiceSid = '';
		dailyLimit = 1000;
		monthlyLimit = 30000;
		showCreateDialog = true;
	}

	function openEditDialog(connection: SmsConnection) {
		editingConnection = connection;
		name = connection.name;
		provider = connection.provider;
		phoneNumber = connection.phone_number;
		accountSid = '';
		authToken = '';
		messagingServiceSid = '';
		dailyLimit = connection.daily_limit;
		monthlyLimit = connection.monthly_limit;
		showCreateDialog = true;
	}

	async function handleSave() {
		if (!name.trim() || !phoneNumber.trim()) return;

		saving = true;
		try {
			if (editingConnection) {
				const updateData: Record<string, unknown> = {
					name: name.trim(),
					phone_number: phoneNumber.trim(),
					daily_limit: dailyLimit,
					monthly_limit: monthlyLimit
				};
				if (accountSid) updateData.account_sid = accountSid;
				if (authToken) updateData.auth_token = authToken;
				if (messagingServiceSid) updateData.messaging_service_sid = messagingServiceSid;

				await smsConnectionsApi.update(editingConnection.id, updateData);
			} else {
				await smsConnectionsApi.create({
					name: name.trim(),
					provider: provider as 'twilio' | 'vonage' | 'messagebird' | 'plivo',
					phone_number: phoneNumber.trim(),
					account_sid: accountSid,
					auth_token: authToken,
					messaging_service_sid: messagingServiceSid || undefined,
					daily_limit: dailyLimit,
					monthly_limit: monthlyLimit,
					capabilities: ['sms']
				});
			}

			showCreateDialog = false;
			loadConnections();
		} catch (err) {
			console.error('Failed to save connection:', err);
		}
		saving = false;
	}

	async function handleDelete(connection: SmsConnection) {
		if (!confirm(`Are you sure you want to delete "${connection.name}"?`)) return;

		try {
			await smsConnectionsApi.delete(connection.id);
			loadConnections();
		} catch (err) {
			console.error('Failed to delete connection:', err);
		}
	}

	async function handleVerify(connection: SmsConnection) {
		verifying = connection.id;
		try {
			await smsConnectionsApi.verify(connection.id);
			loadConnections();
		} catch (err) {
			console.error('Failed to verify connection:', err);
		}
		verifying = null;
	}

	async function toggleActive(connection: SmsConnection) {
		try {
			await smsConnectionsApi.update(connection.id, { is_active: !connection.is_active });
			loadConnections();
		} catch (err) {
			console.error('Failed to toggle connection:', err);
		}
	}

	onMount(() => {
		loadConnections();
	});
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>SMS Connections</CardTitle>
				<CardDescription>
					Configure SMS gateway connections for sending and receiving messages
				</CardDescription>
			</div>
			<Button onclick={openCreateDialog}>
				<Plus class="h-4 w-4 mr-2" />
				Add Connection
			</Button>
		</div>
	</CardHeader>
	<CardContent>
		{#if loading}
			<div class="flex items-center justify-center h-32">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if connections.length === 0}
			<div class="flex flex-col items-center justify-center h-32 text-muted-foreground">
				<Phone class="h-8 w-8 mb-2 opacity-50" />
				<p class="text-sm">No SMS connections configured</p>
				<p class="text-xs">Add a connection to start sending SMS messages</p>
			</div>
		{:else}
			<div class="space-y-3">
				{#each connections as connection}
					<div class="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50">
						<div class="flex items-center gap-4">
							<div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
								<Phone class="h-5 w-5 text-primary" />
							</div>
							<div>
								<div class="flex items-center gap-2">
									<h4 class="font-medium">{connection.name}</h4>
									{#if connection.is_verified}
										<Badge variant="default">
											<CheckCircle class="h-3 w-3 mr-1" />
											Verified
										</Badge>
									{:else}
										<Badge variant="secondary">
											<XCircle class="h-3 w-3 mr-1" />
											Unverified
										</Badge>
									{/if}
									{#if !connection.is_active}
										<Badge variant="outline">Inactive</Badge>
									{/if}
								</div>
								<p class="text-sm text-muted-foreground">
									{connection.phone_number} &bull; {providerOptions.find(p => p.value === connection.provider)?.label || connection.provider}
								</p>
								<p class="text-xs text-muted-foreground">
									{connection.messages_count ?? 0} messages sent
								</p>
							</div>
						</div>
						<div class="flex items-center gap-2">
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8"
								onclick={() => handleVerify(connection)}
								disabled={verifying === connection.id}
							>
								<RefreshCw class="h-4 w-4 {verifying === connection.id ? 'animate-spin' : ''}" />
							</Button>
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8"
								onclick={() => openEditDialog(connection)}
							>
								<Settings class="h-4 w-4" />
							</Button>
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8 text-destructive"
								onclick={() => handleDelete(connection)}
							>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>

<!-- Create/Edit Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>{editingConnection ? 'Edit' : 'Add'} SMS Connection</Dialog.Title>
			<Dialog.Description>
				Configure your SMS gateway credentials to send and receive messages.
			</Dialog.Description>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleSave(); }} class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Connection Name *</Label>
				<Input id="name" bind:value={name} placeholder="My Twilio Number" />
			</div>

			{#if !editingConnection}
				<div class="space-y-2">
					<Label>Provider *</Label>
					<Select.Root type="single" bind:value={provider}>
						<Select.Trigger>
							{providerOptions.find(p => p.value === provider)?.label || 'Select provider...'}
						</Select.Trigger>
						<Select.Content>
							{#each providerOptions as opt}
								<Select.Item value={opt.value} label={opt.label}>{opt.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			{/if}

			<div class="space-y-2">
				<Label for="phoneNumber">Phone Number *</Label>
				<Input id="phoneNumber" bind:value={phoneNumber} placeholder="+1234567890" />
				<p class="text-xs text-muted-foreground">Include country code (e.g., +1 for US)</p>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="accountSid">Account SID {editingConnection ? '' : '*'}</Label>
					<Input id="accountSid" bind:value={accountSid} placeholder={editingConnection ? '(unchanged)' : 'ACxxxxxxxx'} />
				</div>
				<div class="space-y-2">
					<Label for="authToken">Auth Token {editingConnection ? '' : '*'}</Label>
					<Input id="authToken" type="password" bind:value={authToken} placeholder={editingConnection ? '(unchanged)' : 'Enter token'} />
				</div>
			</div>

			{#if provider === 'twilio'}
				<div class="space-y-2">
					<Label for="messagingServiceSid">Messaging Service SID (Optional)</Label>
					<Input id="messagingServiceSid" bind:value={messagingServiceSid} placeholder="MGxxxxxxxx" />
					<p class="text-xs text-muted-foreground">Use for Twilio Messaging Services</p>
				</div>
			{/if}

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="dailyLimit">Daily Limit</Label>
					<Input id="dailyLimit" type="number" bind:value={dailyLimit} min="1" />
				</div>
				<div class="space-y-2">
					<Label for="monthlyLimit">Monthly Limit</Label>
					<Input id="monthlyLimit" type="number" bind:value={monthlyLimit} min="1" />
				</div>
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={handleSave} disabled={saving || !name.trim() || !phoneNumber.trim() || (!editingConnection && (!accountSid || !authToken))}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				{editingConnection ? 'Save Changes' : 'Add Connection'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
