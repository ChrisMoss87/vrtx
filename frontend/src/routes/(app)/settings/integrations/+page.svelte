<script lang="ts">
	import { onMount } from 'svelte';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import * as Select from '$lib/components/ui/select';
	import { toast } from 'svelte-sonner';
	import {
		Key,
		Webhook,
		ArrowDownToLine,
		Plus,
		Copy,
		RefreshCw,
		Trash2,
		Eye,
		EyeOff,
		Send,
		Clock,
		CheckCircle,
		XCircle,
		AlertTriangle
	} from 'lucide-svelte';
	import { apiKeys, webhooks, incomingWebhooks, type ApiKey, type Webhook as WebhookType, type IncomingWebhook } from '$lib/api/integrations';

	let activeTab = $state('api-keys');

	// API Keys state
	let apiKeysList = $state<ApiKey[]>([]);
	let availableScopes = $state<Record<string, string>>({});
	let loadingApiKeys = $state(true);
	let showCreateApiKeyDialog = $state(false);
	let showSecretDialog = $state(false);
	let newSecret = $state('');
	let secretWarning = $state('');

	// Webhooks state
	let webhooksList = $state<WebhookType[]>([]);
	let availableEvents = $state<string[]>([]);
	let loadingWebhooks = $state(true);
	let showCreateWebhookDialog = $state(false);
	let showWebhookSecretDialog = $state(false);
	let webhookSecret = $state('');

	// Incoming Webhooks state
	let incomingWebhooksList = $state<IncomingWebhook[]>([]);
	let availableActions = $state<Record<string, string>>({});
	let loadingIncomingWebhooks = $state(true);
	let showCreateIncomingWebhookDialog = $state(false);
	let showIncomingWebhookUrlDialog = $state(false);
	let incomingWebhookUrl = $state('');
	let incomingWebhookToken = $state('');

	// Delete confirmation
	let deleteTarget = $state<{ type: string; id: number; name: string } | null>(null);

	// Form states
	let apiKeyForm = $state({
		name: '',
		description: '',
		scopes: [] as string[],
		allowed_ips: '',
		rate_limit: '',
		expires_at: ''
	});

	let webhookForm = $state({
		name: '',
		description: '',
		url: '',
		events: [] as string[],
		verify_ssl: true,
		timeout: 30,
		retry_count: 3,
		retry_delay: 60
	});

	let incomingWebhookForm = $state({
		name: '',
		description: '',
		module_id: '',
		action: 'create' as 'create' | 'update' | 'upsert',
		upsert_field: '',
		field_mapping: {} as Record<string, string>
	});

	async function loadApiKeys() {
		loadingApiKeys = true;
		try {
			const response = await apiKeys.list();
			apiKeysList = response.data;
			availableScopes = response.available_scopes;
		} catch (error) {
			toast.error('Failed to load API keys');
		} finally {
			loadingApiKeys = false;
		}
	}

	async function loadWebhooks() {
		loadingWebhooks = true;
		try {
			const response = await webhooks.list();
			webhooksList = response.data;
			availableEvents = response.available_events;
		} catch (error) {
			toast.error('Failed to load webhooks');
		} finally {
			loadingWebhooks = false;
		}
	}

	async function loadIncomingWebhooks() {
		loadingIncomingWebhooks = true;
		try {
			const response = await incomingWebhooks.list();
			incomingWebhooksList = response.data;
			availableActions = response.available_actions;
		} catch (error) {
			toast.error('Failed to load incoming webhooks');
		} finally {
			loadingIncomingWebhooks = false;
		}
	}

	async function createApiKey() {
		try {
			const data = {
				name: apiKeyForm.name,
				description: apiKeyForm.description || undefined,
				scopes: apiKeyForm.scopes,
				allowed_ips: apiKeyForm.allowed_ips ? apiKeyForm.allowed_ips.split(',').map(ip => ip.trim()) : undefined,
				rate_limit: apiKeyForm.rate_limit ? parseInt(apiKeyForm.rate_limit) : undefined,
				expires_at: apiKeyForm.expires_at || undefined
			};

			const response = await apiKeys.create(data);
			newSecret = response.secret || '';
			secretWarning = response.warning || '';
			showCreateApiKeyDialog = false;
			showSecretDialog = true;
			resetApiKeyForm();
			await loadApiKeys();
			toast.success('API key created');
		} catch (error) {
			toast.error('Failed to create API key');
		}
	}

	async function createWebhook() {
		try {
			const data = {
				name: webhookForm.name,
				description: webhookForm.description || undefined,
				url: webhookForm.url,
				events: webhookForm.events,
				verify_ssl: webhookForm.verify_ssl,
				timeout: webhookForm.timeout,
				retry_count: webhookForm.retry_count,
				retry_delay: webhookForm.retry_delay
			};

			const response = await webhooks.create(data);
			webhookSecret = response.secret;
			showCreateWebhookDialog = false;
			showWebhookSecretDialog = true;
			resetWebhookForm();
			await loadWebhooks();
			toast.success('Webhook created');
		} catch (error) {
			toast.error('Failed to create webhook');
		}
	}

	async function toggleApiKey(key: ApiKey) {
		try {
			await apiKeys.update(key.id, { is_active: !key.is_active });
			await loadApiKeys();
			toast.success(`API key ${key.is_active ? 'disabled' : 'enabled'}`);
		} catch (error) {
			toast.error('Failed to update API key');
		}
	}

	async function toggleWebhook(webhook: WebhookType) {
		try {
			await webhooks.update(webhook.id, { is_active: !webhook.is_active });
			await loadWebhooks();
			toast.success(`Webhook ${webhook.is_active ? 'disabled' : 'enabled'}`);
		} catch (error) {
			toast.error('Failed to update webhook');
		}
	}

	async function toggleIncomingWebhook(webhook: IncomingWebhook) {
		try {
			await incomingWebhooks.update(webhook.id, { is_active: !webhook.is_active });
			await loadIncomingWebhooks();
			toast.success(`Incoming webhook ${webhook.is_active ? 'disabled' : 'enabled'}`);
		} catch (error) {
			toast.error('Failed to update incoming webhook');
		}
	}

	async function testWebhook(webhook: WebhookType) {
		try {
			await webhooks.test(webhook.id);
			toast.success('Test webhook sent');
		} catch (error) {
			toast.error('Failed to send test webhook');
		}
	}

	async function confirmDelete() {
		if (!deleteTarget) return;

		try {
			if (deleteTarget.type === 'api-key') {
				await apiKeys.delete(deleteTarget.id);
				await loadApiKeys();
			} else if (deleteTarget.type === 'webhook') {
				await webhooks.delete(deleteTarget.id);
				await loadWebhooks();
			} else if (deleteTarget.type === 'incoming-webhook') {
				await incomingWebhooks.delete(deleteTarget.id);
				await loadIncomingWebhooks();
			}
			toast.success(`${deleteTarget.name} deleted`);
		} catch (error) {
			toast.error('Failed to delete');
		} finally {
			deleteTarget = null;
		}
	}

	function copyToClipboard(text: string) {
		navigator.clipboard.writeText(text);
		toast.success('Copied to clipboard');
	}

	function resetApiKeyForm() {
		apiKeyForm = {
			name: '',
			description: '',
			scopes: [],
			allowed_ips: '',
			rate_limit: '',
			expires_at: ''
		};
	}

	function resetWebhookForm() {
		webhookForm = {
			name: '',
			description: '',
			url: '',
			events: [],
			verify_ssl: true,
			timeout: 30,
			retry_count: 3,
			retry_delay: 60
		};
	}

	function getStatusIcon(status: string | null) {
		switch (status) {
			case 'success':
				return CheckCircle;
			case 'failed':
				return XCircle;
			case 'pending':
				return Clock;
			default:
				return AlertTriangle;
		}
	}

	function getStatusColor(status: string | null) {
		switch (status) {
			case 'success':
				return 'text-green-500';
			case 'failed':
				return 'text-red-500';
			case 'pending':
				return 'text-yellow-500';
			default:
				return 'text-gray-500';
		}
	}

	onMount(() => {
		loadApiKeys();
		loadWebhooks();
		loadIncomingWebhooks();
	});
</script>

<div class="container mx-auto py-6 space-y-6">
	<div>
		<h1 class="text-2xl font-bold">API & Integrations</h1>
		<p class="text-muted-foreground">Manage API keys, webhooks, and external integrations</p>
	</div>

	<Tabs.Root bind:value={activeTab}>
		<Tabs.List>
			<Tabs.Trigger value="api-keys" class="flex items-center gap-2">
				<Key class="h-4 w-4" />
				API Keys
			</Tabs.Trigger>
			<Tabs.Trigger value="webhooks" class="flex items-center gap-2">
				<Webhook class="h-4 w-4" />
				Webhooks
			</Tabs.Trigger>
			<Tabs.Trigger value="incoming" class="flex items-center gap-2">
				<ArrowDownToLine class="h-4 w-4" />
				Incoming Webhooks
			</Tabs.Trigger>
		</Tabs.List>

		<!-- API Keys Tab -->
		<Tabs.Content value="api-keys" class="space-y-4">
			<div class="flex justify-between items-center">
				<p class="text-sm text-muted-foreground">
					API keys allow external applications to access your data securely
				</p>
				<Button onclick={() => showCreateApiKeyDialog = true}>
					<Plus class="h-4 w-4 mr-2" />
					Create API Key
				</Button>
			</div>

			{#if loadingApiKeys}
				<Card.Root>
					<Card.Content class="py-8 text-center text-muted-foreground">
						Loading API keys...
					</Card.Content>
				</Card.Root>
			{:else if apiKeysList.length === 0}
				<Card.Root>
					<Card.Content class="py-8 text-center">
						<Key class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
						<p class="text-muted-foreground">No API keys yet</p>
						<Button variant="outline" class="mt-4" onclick={() => showCreateApiKeyDialog = true}>
							Create your first API key
						</Button>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="space-y-3">
					{#each apiKeysList as key}
						<Card.Root>
							<Card.Content class="py-4">
								<div class="flex items-center justify-between">
									<div class="space-y-1">
										<div class="flex items-center gap-2">
											<span class="font-medium">{key.name}</span>
											<Badge variant={key.is_active ? 'default' : 'secondary'}>
												{key.is_active ? 'Active' : 'Inactive'}
											</Badge>
											<code class="text-xs bg-muted px-2 py-0.5 rounded">{key.prefix}...</code>
										</div>
										{#if key.description}
											<p class="text-sm text-muted-foreground">{key.description}</p>
										{/if}
										<div class="flex items-center gap-4 text-xs text-muted-foreground">
											<span>Scopes: {key.scopes.join(', ')}</span>
											<span>Requests: {key.request_count}</span>
											{#if key.last_used_at}
												<span>Last used: {new Date(key.last_used_at).toLocaleDateString()}</span>
											{/if}
										</div>
									</div>
									<div class="flex items-center gap-2">
										<Switch
											checked={key.is_active}
											onCheckedChange={() => toggleApiKey(key)}
										/>
										<Button
											variant="ghost"
											size="icon"
											onclick={() => deleteTarget = { type: 'api-key', id: key.id, name: key.name }}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							</Card.Content>
						</Card.Root>
					{/each}
				</div>
			{/if}
		</Tabs.Content>

		<!-- Webhooks Tab -->
		<Tabs.Content value="webhooks" class="space-y-4">
			<div class="flex justify-between items-center">
				<p class="text-sm text-muted-foreground">
					Send data to external services when events occur
				</p>
				<Button onclick={() => showCreateWebhookDialog = true}>
					<Plus class="h-4 w-4 mr-2" />
					Create Webhook
				</Button>
			</div>

			{#if loadingWebhooks}
				<Card.Root>
					<Card.Content class="py-8 text-center text-muted-foreground">
						Loading webhooks...
					</Card.Content>
				</Card.Root>
			{:else if webhooksList.length === 0}
				<Card.Root>
					<Card.Content class="py-8 text-center">
						<Webhook class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
						<p class="text-muted-foreground">No webhooks configured</p>
						<Button variant="outline" class="mt-4" onclick={() => showCreateWebhookDialog = true}>
							Create your first webhook
						</Button>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="space-y-3">
					{#each webhooksList as webhook}
						<Card.Root>
							<Card.Content class="py-4">
								<div class="flex items-center justify-between">
									<div class="space-y-1">
										<div class="flex items-center gap-2">
											<span class="font-medium">{webhook.name}</span>
											<Badge variant={webhook.is_active ? 'default' : 'secondary'}>
												{webhook.is_active ? 'Active' : 'Inactive'}
											</Badge>
											{#if webhook.last_status}
												{@const StatusIcon = getStatusIcon(webhook.last_status)}
												<StatusIcon class="h-4 w-4 {getStatusColor(webhook.last_status)}" />
											{/if}
										</div>
										<p class="text-sm text-muted-foreground font-mono">{webhook.url}</p>
										<div class="flex items-center gap-4 text-xs text-muted-foreground">
											<span>Events: {webhook.events.join(', ')}</span>
											<span class="text-green-600">{webhook.success_count} success</span>
											<span class="text-red-600">{webhook.failure_count} failed</span>
										</div>
									</div>
									<div class="flex items-center gap-2">
										<Button
											variant="ghost"
											size="icon"
											onclick={() => testWebhook(webhook)}
											title="Send test"
										>
											<Send class="h-4 w-4" />
										</Button>
										<Switch
											checked={webhook.is_active}
											onCheckedChange={() => toggleWebhook(webhook)}
										/>
										<Button
											variant="ghost"
											size="icon"
											onclick={() => deleteTarget = { type: 'webhook', id: webhook.id, name: webhook.name }}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							</Card.Content>
						</Card.Root>
					{/each}
				</div>
			{/if}
		</Tabs.Content>

		<!-- Incoming Webhooks Tab -->
		<Tabs.Content value="incoming" class="space-y-4">
			<div class="flex justify-between items-center">
				<p class="text-sm text-muted-foreground">
					Receive data from external services to create or update records
				</p>
				<Button onclick={() => showCreateIncomingWebhookDialog = true}>
					<Plus class="h-4 w-4 mr-2" />
					Create Incoming Webhook
				</Button>
			</div>

			{#if loadingIncomingWebhooks}
				<Card.Root>
					<Card.Content class="py-8 text-center text-muted-foreground">
						Loading incoming webhooks...
					</Card.Content>
				</Card.Root>
			{:else if incomingWebhooksList.length === 0}
				<Card.Root>
					<Card.Content class="py-8 text-center">
						<ArrowDownToLine class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
						<p class="text-muted-foreground">No incoming webhooks configured</p>
						<Button variant="outline" class="mt-4" onclick={() => showCreateIncomingWebhookDialog = true}>
							Create your first incoming webhook
						</Button>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="space-y-3">
					{#each incomingWebhooksList as webhook}
						<Card.Root>
							<Card.Content class="py-4">
								<div class="flex items-center justify-between">
									<div class="space-y-1">
										<div class="flex items-center gap-2">
											<span class="font-medium">{webhook.name}</span>
											<Badge variant={webhook.is_active ? 'default' : 'secondary'}>
												{webhook.is_active ? 'Active' : 'Inactive'}
											</Badge>
											<Badge variant="outline">{webhook.action}</Badge>
										</div>
										{#if webhook.module}
											<p class="text-sm text-muted-foreground">
												Module: {webhook.module.name}
											</p>
										{/if}
										<div class="flex items-center gap-4 text-xs text-muted-foreground">
											<span>Received: {webhook.received_count}</span>
											{#if webhook.last_received_at}
												<span>Last: {new Date(webhook.last_received_at).toLocaleDateString()}</span>
											{/if}
										</div>
									</div>
									<div class="flex items-center gap-2">
										<Button
											variant="ghost"
											size="icon"
											onclick={() => copyToClipboard(webhook.url)}
											title="Copy URL"
										>
											<Copy class="h-4 w-4" />
										</Button>
										<Switch
											checked={webhook.is_active}
											onCheckedChange={() => toggleIncomingWebhook(webhook)}
										/>
										<Button
											variant="ghost"
											size="icon"
											onclick={() => deleteTarget = { type: 'incoming-webhook', id: webhook.id, name: webhook.name }}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							</Card.Content>
						</Card.Root>
					{/each}
				</div>
			{/if}
		</Tabs.Content>
	</Tabs.Root>
</div>

<!-- Create API Key Dialog -->
<Dialog.Root bind:open={showCreateApiKeyDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Create API Key</Dialog.Title>
			<Dialog.Description>
				Create a new API key for external access
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4">
			<div>
				<Label for="api-key-name">Name</Label>
				<Input id="api-key-name" bind:value={apiKeyForm.name} placeholder="My API Key" />
			</div>
			<div>
				<Label for="api-key-description">Description</Label>
				<Textarea id="api-key-description" bind:value={apiKeyForm.description} placeholder="What this key is used for..." />
			</div>
			<div>
				<Label>Scopes</Label>
				<div class="grid grid-cols-2 gap-2 mt-2">
					{#each Object.entries(availableScopes) as [scope, description]}
						<label class="flex items-center gap-2 text-sm">
							<input
								type="checkbox"
								checked={apiKeyForm.scopes.includes(scope)}
								onchange={(e) => {
									if (e.currentTarget.checked) {
										apiKeyForm.scopes = [...apiKeyForm.scopes, scope];
									} else {
										apiKeyForm.scopes = apiKeyForm.scopes.filter(s => s !== scope);
									}
								}}
							/>
							{scope}
						</label>
					{/each}
				</div>
			</div>
			<div>
				<Label for="api-key-ips">Allowed IPs (comma-separated)</Label>
				<Input id="api-key-ips" bind:value={apiKeyForm.allowed_ips} placeholder="192.168.1.1, 10.0.0.1" />
			</div>
			<div>
				<Label for="api-key-rate">Rate Limit (requests/hour)</Label>
				<Input id="api-key-rate" type="number" bind:value={apiKeyForm.rate_limit} placeholder="1000" />
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => showCreateApiKeyDialog = false}>Cancel</Button>
			<Button onclick={createApiKey} disabled={!apiKeyForm.name || apiKeyForm.scopes.length === 0}>
				Create
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Secret Display Dialog -->
<Dialog.Root bind:open={showSecretDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>API Key Created</Dialog.Title>
			<Dialog.Description class="text-amber-600">
				{secretWarning}
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4">
			<div class="bg-muted p-4 rounded-lg font-mono text-sm break-all">
				{newSecret}
			</div>
			<Button onclick={() => copyToClipboard(newSecret)} class="w-full">
				<Copy class="h-4 w-4 mr-2" />
				Copy to Clipboard
			</Button>
		</div>
		<Dialog.Footer>
			<Button onclick={() => { showSecretDialog = false; newSecret = ''; }}>Done</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Create Webhook Dialog -->
<Dialog.Root bind:open={showCreateWebhookDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Create Webhook</Dialog.Title>
			<Dialog.Description>
				Send data to an external URL when events occur
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4">
			<div>
				<Label for="webhook-name">Name</Label>
				<Input id="webhook-name" bind:value={webhookForm.name} placeholder="My Webhook" />
			</div>
			<div>
				<Label for="webhook-url">URL</Label>
				<Input id="webhook-url" bind:value={webhookForm.url} placeholder="https://example.com/webhook" />
			</div>
			<div>
				<Label>Events</Label>
				<div class="grid grid-cols-2 gap-2 mt-2 max-h-40 overflow-y-auto">
					{#each availableEvents as event}
						<label class="flex items-center gap-2 text-sm">
							<input
								type="checkbox"
								checked={webhookForm.events.includes(event)}
								onchange={(e) => {
									if (e.currentTarget.checked) {
										webhookForm.events = [...webhookForm.events, event];
									} else {
										webhookForm.events = webhookForm.events.filter(ev => ev !== event);
									}
								}}
							/>
							{event}
						</label>
					{/each}
				</div>
			</div>
			<div class="flex items-center gap-2">
				<Switch bind:checked={webhookForm.verify_ssl} />
				<Label>Verify SSL</Label>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => showCreateWebhookDialog = false}>Cancel</Button>
			<Button onclick={createWebhook} disabled={!webhookForm.name || !webhookForm.url || webhookForm.events.length === 0}>
				Create
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Webhook Secret Display Dialog -->
<Dialog.Root bind:open={showWebhookSecretDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Webhook Created</Dialog.Title>
			<Dialog.Description class="text-amber-600">
				Store this secret securely for signature verification.
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4">
			<div>
				<Label>Webhook Secret</Label>
				<div class="bg-muted p-4 rounded-lg font-mono text-sm break-all mt-2">
					{webhookSecret}
				</div>
			</div>
			<Button onclick={() => copyToClipboard(webhookSecret)} class="w-full">
				<Copy class="h-4 w-4 mr-2" />
				Copy Secret
			</Button>
		</div>
		<Dialog.Footer>
			<Button onclick={() => { showWebhookSecretDialog = false; webhookSecret = ''; }}>Done</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root open={!!deleteTarget} onOpenChange={(open) => { if (!open) deleteTarget = null; }}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete {deleteTarget?.name}?</AlertDialog.Title>
			<AlertDialog.Description>
				This action cannot be undone. This will permanently delete this item.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={confirmDelete}>Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
