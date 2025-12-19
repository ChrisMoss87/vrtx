<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import {
		teamChatConnectionsApi,
		type TeamChatConnection,
		type TeamChatChannel
	} from '$lib/api/team-chat';
	import {
		Plus,
		Settings,
		Trash2,
		RefreshCw,
		CheckCircle,
		XCircle,
		Hash,
		Users,
		MessageSquare
	} from 'lucide-svelte';

	let connections = $state<TeamChatConnection[]>([]);
	let loading = $state(true);
	let showCreateDialog = $state(false);
	let showChannelsDialog = $state(false);
	let selectedConnection = $state<TeamChatConnection | null>(null);
	let channels = $state<TeamChatChannel[]>([]);
	let channelsLoading = $state(false);
	let syncing = $state<number | null>(null);
	let verifying = $state<number | null>(null);

	let form = $state({
		name: '',
		provider: 'slack' as 'slack' | 'teams',
		access_token: '',
		bot_token: '',
		webhook_url: ''
	});

	async function loadConnections() {
		loading = true;
		try {
			connections = await teamChatConnectionsApi.list();
		} catch (err) {
			console.error('Failed to load connections:', err);
		} finally {
			loading = false;
		}
	}

	async function createConnection() {
		try {
			const connection = await teamChatConnectionsApi.create({
				name: form.name,
				provider: form.provider,
				access_token: form.access_token,
				bot_token: form.bot_token || undefined,
				webhook_url: form.webhook_url || undefined
			});
			connections = [connection, ...connections];
			showCreateDialog = false;
			resetForm();
		} catch (err) {
			console.error('Failed to create connection:', err);
		}
	}

	async function deleteConnection(id: number) {
		if (!confirm('Are you sure you want to delete this connection?')) return;
		try {
			await teamChatConnectionsApi.delete(id);
			connections = connections.filter((c) => c.id !== id);
		} catch (err) {
			console.error('Failed to delete connection:', err);
		}
	}

	async function verifyConnection(connection: TeamChatConnection) {
		verifying = connection.id;
		try {
			const result = await teamChatConnectionsApi.verify(connection.id);
			const idx = connections.findIndex((c) => c.id === connection.id);
			if (idx !== -1) {
				connections[idx] = result.data;
			}
		} catch (err) {
			console.error('Failed to verify connection:', err);
		} finally {
			verifying = null;
		}
	}

	async function syncChannels(connection: TeamChatConnection) {
		syncing = connection.id;
		try {
			const result = await teamChatConnectionsApi.syncChannels(connection.id);
			const idx = connections.findIndex((c) => c.id === connection.id);
			if (idx !== -1) {
				connections[idx] = { ...connections[idx], channels_count: result.synced_count };
			}
		} catch (err) {
			console.error('Failed to sync channels:', err);
		} finally {
			syncing = null;
		}
	}

	async function viewChannels(connection: TeamChatConnection) {
		selectedConnection = connection;
		channelsLoading = true;
		showChannelsDialog = true;
		try {
			channels = await teamChatConnectionsApi.getChannels(connection.id);
		} catch (err) {
			console.error('Failed to load channels:', err);
		} finally {
			channelsLoading = false;
		}
	}

	function resetForm() {
		form = {
			name: '',
			provider: 'slack',
			access_token: '',
			bot_token: '',
			webhook_url: ''
		};
	}

	function getProviderIcon(provider: string) {
		if (provider === 'slack') {
			return '🔵';
		}
		return '🟣';
	}

	$effect(() => {
		loadConnections();
	});
</script>

<div class="space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h2 class="text-lg font-semibold">Team Chat Connections</h2>
			<p class="text-sm text-muted-foreground">
				Connect Slack or Microsoft Teams for notifications
			</p>
		</div>
		<Button onclick={() => (showCreateDialog = true)}>
			<Plus class="mr-2 h-4 w-4" />
			Add Connection
		</Button>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-8">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if connections.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<MessageSquare class="h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="font-medium mb-2">No Connections</h3>
				<p class="text-sm text-muted-foreground mb-4">
					Add a Slack or Teams connection to get started
				</p>
				<Button onclick={() => (showCreateDialog = true)}>
					<Plus class="mr-2 h-4 w-4" />
					Add Connection
				</Button>
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="grid gap-4 md:grid-cols-2">
			{#each connections as connection (connection.id)}
				<Card.Root>
					<Card.Header class="pb-2">
						<div class="flex items-start justify-between">
							<div class="flex items-center gap-2">
								<span class="text-2xl">{getProviderIcon(connection.provider)}</span>
								<div>
									<Card.Title class="text-base">{connection.name}</Card.Title>
									<p class="text-xs text-muted-foreground capitalize">
										{connection.provider}
										{#if connection.workspace_name}
											· {connection.workspace_name}
										{/if}
									</p>
								</div>
							</div>
							<div class="flex items-center gap-1">
								{#if connection.is_verified}
									<Badge variant="default" class="bg-green-500">
										<CheckCircle class="mr-1 h-3 w-3" />
										Verified
									</Badge>
								{:else}
									<Badge variant="destructive">
										<XCircle class="mr-1 h-3 w-3" />
										Unverified
									</Badge>
								{/if}
								{#if !connection.is_active}
									<Badge variant="secondary">Inactive</Badge>
								{/if}
							</div>
						</div>
					</Card.Header>
					<Card.Content>
						<div class="flex items-center gap-4 text-sm text-muted-foreground mb-4">
							<div class="flex items-center gap-1">
								<Hash class="h-4 w-4" />
								<span>{connection.channels_count ?? 0} channels</span>
							</div>
							<div class="flex items-center gap-1">
								<MessageSquare class="h-4 w-4" />
								<span>{connection.notifications_count ?? 0} notifications</span>
							</div>
						</div>
						<div class="flex items-center gap-2">
							<Button
								variant="outline"
								size="sm"
								onclick={() => verifyConnection(connection)}
								disabled={verifying === connection.id}
							>
								{#if verifying === connection.id}
									<RefreshCw class="mr-1 h-3 w-3 animate-spin" />
								{:else}
									<CheckCircle class="mr-1 h-3 w-3" />
								{/if}
								Verify
							</Button>
							<Button
								variant="outline"
								size="sm"
								onclick={() => syncChannels(connection)}
								disabled={syncing === connection.id}
							>
								{#if syncing === connection.id}
									<RefreshCw class="mr-1 h-3 w-3 animate-spin" />
								{:else}
									<RefreshCw class="mr-1 h-3 w-3" />
								{/if}
								Sync
							</Button>
							<Button variant="outline" size="sm" onclick={() => viewChannels(connection)}>
								<Hash class="mr-1 h-3 w-3" />
								Channels
							</Button>
							<Button
								variant="ghost"
								size="sm"
								class="text-destructive ml-auto"
								onclick={() => deleteConnection(connection.id)}
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

<!-- Create Connection Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Add Team Chat Connection</Dialog.Title>
			<Dialog.Description>Connect Slack or Microsoft Teams to send notifications</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="name">Connection Name</Label>
				<Input id="name" bind:value={form.name} placeholder="e.g., Sales Team Slack" />
			</div>

			<div class="space-y-2">
				<Label>Provider</Label>
				<Select.Root
					type="single"
					value={form.provider}
					onValueChange={(v) => {
						if (v) form.provider = v as 'slack' | 'teams';
					}}
				>
					<Select.Trigger>
						<span>{form.provider === 'slack' ? '🔵 Slack' : '🟣 Microsoft Teams'}</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="slack">🔵 Slack</Select.Item>
						<Select.Item value="teams">🟣 Microsoft Teams</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>

			{#if form.provider === 'slack'}
				<div class="space-y-2">
					<Label for="bot_token">Bot Token</Label>
					<Input
						id="bot_token"
						type="password"
						bind:value={form.bot_token}
						placeholder="xoxb-..."
					/>
					<p class="text-xs text-muted-foreground">
						Get this from your Slack App's OAuth & Permissions page
					</p>
				</div>
				<div class="space-y-2">
					<Label for="access_token">User OAuth Token (Optional)</Label>
					<Input
						id="access_token"
						type="password"
						bind:value={form.access_token}
						placeholder="xoxp-..."
					/>
				</div>
			{:else}
				<div class="space-y-2">
					<Label for="webhook_url">Incoming Webhook URL</Label>
					<Input
						id="webhook_url"
						bind:value={form.webhook_url}
						placeholder="https://outlook.office.com/webhook/..."
					/>
					<p class="text-xs text-muted-foreground">
						Create an incoming webhook in your Teams channel
					</p>
				</div>
				<div class="space-y-2">
					<Label for="access_token">Access Token (for Graph API)</Label>
					<Input
						id="access_token"
						type="password"
						bind:value={form.access_token}
						placeholder="eyJ0eXAi..."
					/>
				</div>
			{/if}
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={createConnection} disabled={!form.name || (!form.access_token && !form.bot_token && !form.webhook_url)}>
				Create Connection
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Channels Dialog -->
<Dialog.Root bind:open={showChannelsDialog}>
	<Dialog.Content class="sm:max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Channels - {selectedConnection?.name}</Dialog.Title>
			<Dialog.Description>Available channels for sending notifications</Dialog.Description>
		</Dialog.Header>
		<div class="max-h-96 overflow-y-auto py-4">
			{#if channelsLoading}
				<div class="flex items-center justify-center py-8">
					<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else if channels.length === 0}
				<p class="text-center text-muted-foreground py-8">
					No channels found. Click "Sync" to fetch channels.
				</p>
			{:else}
				<div class="space-y-2">
					{#each channels as channel (channel.id)}
						<div
							class="flex items-center justify-between p-3 border rounded-lg"
							class:opacity-50={channel.is_archived}
						>
							<div class="flex items-center gap-2">
								<Hash class="h-4 w-4 text-muted-foreground" />
								<div>
									<p class="font-medium">{channel.name}</p>
									{#if channel.description}
										<p class="text-xs text-muted-foreground">{channel.description}</p>
									{/if}
								</div>
							</div>
							<div class="flex items-center gap-2">
								{#if channel.is_private}
									<Badge variant="secondary">Private</Badge>
								{/if}
								{#if channel.is_archived}
									<Badge variant="outline">Archived</Badge>
								{/if}
								<span class="text-xs text-muted-foreground">
									<Users class="inline h-3 w-3" />
									{channel.member_count}
								</span>
							</div>
						</div>
					{/each}
				</div>
			{/if}
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showChannelsDialog = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
