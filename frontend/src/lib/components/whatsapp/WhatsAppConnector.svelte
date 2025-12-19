<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import * as Dialog from '$lib/components/ui/dialog';
	import { whatsappConnectionsApi, type WhatsappConnection } from '$lib/api/whatsapp';
	import { Plus, RefreshCw, Settings, Trash2, Link2, CheckCircle, AlertCircle, Loader2, Copy, Check } from 'lucide-svelte';

	let loading = $state(true);
	let connections = $state<WhatsappConnection[]>([]);
	let showAddDialog = $state(false);
	let showWebhookDialog = $state(false);
	let selectedConnection = $state<WhatsappConnection | null>(null);
	let saving = $state(false);
	let verifying = $state(false);
	let copied = $state(false);

	// Form state
	let name = $state('');
	let phoneNumberId = $state('');
	let wabaId = $state('');
	let accessToken = $state('');

	// Webhook config
	let webhookConfig = $state<{ webhook_url: string; verify_token: string; instructions: string[] } | null>(null);

	async function loadConnections() {
		loading = true;
		try {
			connections = await whatsappConnectionsApi.list();
		} catch (err) {
			console.error('Failed to load connections:', err);
		}
		loading = false;
	}

	function openAddDialog() {
		selectedConnection = null;
		name = '';
		phoneNumberId = '';
		wabaId = '';
		accessToken = '';
		showAddDialog = true;
	}

	function openEditDialog(connection: WhatsappConnection) {
		selectedConnection = connection;
		name = connection.name;
		phoneNumberId = connection.phone_number_id;
		wabaId = connection.waba_id || '';
		accessToken = '';
		showAddDialog = true;
	}

	async function handleSave() {
		if (!name.trim() || !phoneNumberId.trim() || (!selectedConnection && !accessToken)) return;

		saving = true;
		try {
			const data: Parameters<typeof whatsappConnectionsApi.create>[0] = {
				name: name.trim(),
				phone_number_id: phoneNumberId.trim(),
				access_token: accessToken
			};

			if (wabaId.trim()) {
				data.waba_id = wabaId.trim();
			}

			if (selectedConnection) {
				await whatsappConnectionsApi.update(selectedConnection.id, {
					name: data.name,
					phone_number_id: data.phone_number_id,
					waba_id: data.waba_id,
					...(accessToken ? { access_token: accessToken } : {})
				});
			} else {
				await whatsappConnectionsApi.create(data);
			}

			showAddDialog = false;
			loadConnections();
		} catch (err) {
			console.error('Failed to save connection:', err);
		}
		saving = false;
	}

	async function handleVerify(connection: WhatsappConnection) {
		verifying = true;
		try {
			await whatsappConnectionsApi.verify(connection.id);
			loadConnections();
		} catch (err) {
			console.error('Failed to verify connection:', err);
		}
		verifying = false;
	}

	async function handleDelete(connection: WhatsappConnection) {
		if (!confirm(`Are you sure you want to delete "${connection.name}"?`)) return;

		try {
			await whatsappConnectionsApi.delete(connection.id);
			loadConnections();
		} catch (err) {
			console.error('Failed to delete connection:', err);
		}
	}

	async function showWebhookConfig(connection: WhatsappConnection) {
		selectedConnection = connection;
		try {
			webhookConfig = await whatsappConnectionsApi.getWebhookConfig(connection.id);
			showWebhookDialog = true;
		} catch (err) {
			console.error('Failed to get webhook config:', err);
		}
	}

	async function copyToClipboard(text: string) {
		await navigator.clipboard.writeText(text);
		copied = true;
		setTimeout(() => (copied = false), 2000);
	}

	function getQualityBadge(rating: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
		switch (rating) {
			case 'GREEN':
				return 'default';
			case 'YELLOW':
				return 'secondary';
			case 'RED':
				return 'destructive';
			default:
				return 'outline';
		}
	}

	onMount(loadConnections);
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>WhatsApp Business Connections</CardTitle>
				<CardDescription>
					Connect your WhatsApp Business accounts to send and receive messages
				</CardDescription>
			</div>
			<Button onclick={openAddDialog}>
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
				<Link2 class="h-8 w-8 mb-2 opacity-50" />
				<p class="text-sm">No WhatsApp connections configured</p>
			</div>
		{:else}
			<div class="space-y-4">
				{#each connections as connection}
					<div class="flex items-center justify-between p-4 rounded-lg border">
						<div class="flex items-center gap-4">
							<div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
								<svg class="w-6 h-6 text-green-600" viewBox="0 0 24 24" fill="currentColor">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
								</svg>
							</div>
							<div>
								<div class="flex items-center gap-2">
									<h4 class="font-medium">{connection.name}</h4>
									{#if connection.is_verified}
										<CheckCircle class="h-4 w-4 text-green-500" />
									{:else}
										<AlertCircle class="h-4 w-4 text-yellow-500" />
									{/if}
								</div>
								<p class="text-sm text-muted-foreground">
									{connection.display_phone_number || connection.phone_number_id}
								</p>
								{#if connection.verified_name}
									<p class="text-xs text-muted-foreground">{connection.verified_name}</p>
								{/if}
							</div>
						</div>
						<div class="flex items-center gap-4">
							<div class="flex items-center gap-2">
								{#if connection.quality_rating}
									<Badge variant={getQualityBadge(connection.quality_rating)}>
										{connection.quality_rating}
									</Badge>
								{/if}
								<Badge variant={connection.is_active ? 'default' : 'outline'}>
									{connection.is_active ? 'Active' : 'Inactive'}
								</Badge>
							</div>
							<div class="flex items-center gap-1">
								<Button variant="ghost" size="icon" onclick={() => handleVerify(connection)} disabled={verifying}>
									<RefreshCw class="h-4 w-4 {verifying ? 'animate-spin' : ''}" />
								</Button>
								<Button variant="ghost" size="icon" onclick={() => showWebhookConfig(connection)}>
									<Link2 class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="icon" onclick={() => openEditDialog(connection)}>
									<Settings class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="icon" class="text-destructive" onclick={() => handleDelete(connection)}>
									<Trash2 class="h-4 w-4" />
								</Button>
							</div>
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>

<!-- Add/Edit Dialog -->
<Dialog.Root bind:open={showAddDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>
				{selectedConnection ? 'Edit Connection' : 'Add WhatsApp Connection'}
			</Dialog.Title>
			<Dialog.Description>
				Connect your WhatsApp Business account using the Meta Business API credentials.
			</Dialog.Description>
		</Dialog.Header>

		<form
			onsubmit={(e) => {
				e.preventDefault();
				handleSave();
			}}
			class="space-y-4"
		>
			<div class="space-y-2">
				<Label for="name">Connection Name *</Label>
				<Input id="name" bind:value={name} placeholder="My WhatsApp Business" />
			</div>

			<div class="space-y-2">
				<Label for="phoneNumberId">Phone Number ID *</Label>
				<Input id="phoneNumberId" bind:value={phoneNumberId} placeholder="Enter your Meta Phone Number ID" />
				<p class="text-xs text-muted-foreground">
					Find this in your Meta Business Suite under WhatsApp Manager
				</p>
			</div>

			<div class="space-y-2">
				<Label for="wabaId">WhatsApp Business Account ID</Label>
				<Input id="wabaId" bind:value={wabaId} placeholder="Optional - required for template management" />
			</div>

			<div class="space-y-2">
				<Label for="accessToken">
					Access Token {selectedConnection ? '(leave blank to keep existing)' : '*'}
				</Label>
				<Input
					id="accessToken"
					type="password"
					bind:value={accessToken}
					placeholder="Enter your permanent access token"
				/>
				<p class="text-xs text-muted-foreground">
					Generate a permanent token in your Meta App settings
				</p>
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showAddDialog = false)}>Cancel</Button>
			<Button
				onclick={handleSave}
				disabled={saving || !name.trim() || !phoneNumberId.trim() || (!selectedConnection && !accessToken)}
			>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				{selectedConnection ? 'Update' : 'Add Connection'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Webhook Config Dialog -->
<Dialog.Root bind:open={showWebhookDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Webhook Configuration</Dialog.Title>
			<Dialog.Description>
				Configure your Meta App webhook to receive messages from WhatsApp
			</Dialog.Description>
		</Dialog.Header>

		{#if webhookConfig}
			<div class="space-y-4">
				<div class="space-y-2">
					<Label>Webhook URL</Label>
					<div class="flex gap-2">
						<Input value={webhookConfig.webhook_url} readonly class="font-mono text-sm" />
						<Button variant="outline" size="icon" onclick={() => copyToClipboard(webhookConfig!.webhook_url)}>
							{#if copied}
								<Check class="h-4 w-4" />
							{:else}
								<Copy class="h-4 w-4" />
							{/if}
						</Button>
					</div>
				</div>

				<div class="space-y-2">
					<Label>Verify Token</Label>
					<div class="flex gap-2">
						<Input value={webhookConfig.verify_token} readonly class="font-mono text-sm" />
						<Button variant="outline" size="icon" onclick={() => copyToClipboard(webhookConfig!.verify_token)}>
							<Copy class="h-4 w-4" />
						</Button>
					</div>
				</div>

				<div class="space-y-2">
					<Label>Setup Instructions</Label>
					<ol class="list-decimal list-inside space-y-1 text-sm text-muted-foreground">
						{#each webhookConfig.instructions as instruction}
							<li>{instruction}</li>
						{/each}
					</ol>
				</div>
			</div>
		{/if}

		<Dialog.Footer>
			<Button onclick={() => (showWebhookDialog = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
