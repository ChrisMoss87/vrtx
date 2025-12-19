<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import {
		teamChatNotificationsApi,
		teamChatConnectionsApi,
		type TeamChatNotification,
		type TeamChatConnection,
		type TeamChatChannel,
		type TriggerEvent
	} from '$lib/api/team-chat';
	import {
		Plus,
		Edit,
		Trash2,
		RefreshCw,
		Bell,
		BellOff,
		Play,
		Copy,
		Zap,
		Hash,
		CheckCircle
	} from 'lucide-svelte';

	let notifications = $state<TeamChatNotification[]>([]);
	let connections = $state<TeamChatConnection[]>([]);
	let channels = $state<TeamChatChannel[]>([]);
	let events = $state<TriggerEvent[]>([]);
	let loading = $state(true);
	let showEditor = $state(false);
	let editingNotification = $state<TeamChatNotification | null>(null);
	let testing = $state<number | null>(null);

	let form = $state({
		connection_id: 0,
		channel_id: 0,
		name: '',
		description: '',
		trigger_event: '',
		trigger_module: '',
		message_template: '',
		include_mentions: false,
		mention_field: '',
		is_active: true
	});

	async function loadData() {
		loading = true;
		try {
			const [notifs, conns, evts] = await Promise.all([
				teamChatNotificationsApi.list(),
				teamChatConnectionsApi.list(),
				teamChatNotificationsApi.getEvents()
			]);
			notifications = notifs;
			connections = conns;
			events = evts;
		} catch (err) {
			console.error('Failed to load data:', err);
		} finally {
			loading = false;
		}
	}

	async function loadChannels(connectionId: number) {
		if (!connectionId) {
			channels = [];
			return;
		}
		try {
			channels = await teamChatConnectionsApi.getChannels(connectionId);
		} catch (err) {
			console.error('Failed to load channels:', err);
			channels = [];
		}
	}

	async function saveNotification() {
		try {
			if (editingNotification) {
				const updated = await teamChatNotificationsApi.update(editingNotification.id, {
					connection_id: form.connection_id,
					channel_id: form.channel_id || undefined,
					name: form.name,
					description: form.description || undefined,
					trigger_event: form.trigger_event,
					trigger_module: form.trigger_module || undefined,
					message_template: form.message_template,
					include_mentions: form.include_mentions,
					mention_field: form.mention_field || undefined,
					is_active: form.is_active
				});
				const idx = notifications.findIndex((n) => n.id === editingNotification!.id);
				if (idx !== -1) {
					notifications[idx] = updated;
				}
			} else {
				const created = await teamChatNotificationsApi.create({
					connection_id: form.connection_id,
					channel_id: form.channel_id || undefined,
					name: form.name,
					description: form.description || undefined,
					trigger_event: form.trigger_event,
					trigger_module: form.trigger_module || undefined,
					message_template: form.message_template,
					include_mentions: form.include_mentions,
					mention_field: form.mention_field || undefined,
					is_active: form.is_active
				});
				notifications = [created, ...notifications];
			}
			showEditor = false;
			resetForm();
		} catch (err) {
			console.error('Failed to save notification:', err);
		}
	}

	async function deleteNotification(id: number) {
		if (!confirm('Are you sure you want to delete this notification?')) return;
		try {
			await teamChatNotificationsApi.delete(id);
			notifications = notifications.filter((n) => n.id !== id);
		} catch (err) {
			console.error('Failed to delete notification:', err);
		}
	}

	async function testNotification(notification: TeamChatNotification) {
		testing = notification.id;
		try {
			const result = await teamChatNotificationsApi.test(notification.id);
			alert(`Test message sent!\n\nRendered: ${result.rendered_content}`);
		} catch (err) {
			console.error('Failed to test notification:', err);
			alert('Failed to send test message');
		} finally {
			testing = null;
		}
	}

	async function duplicateNotification(notification: TeamChatNotification) {
		try {
			const copy = await teamChatNotificationsApi.duplicate(notification.id);
			notifications = [copy, ...notifications];
		} catch (err) {
			console.error('Failed to duplicate notification:', err);
		}
	}

	async function toggleActive(notification: TeamChatNotification) {
		try {
			const updated = await teamChatNotificationsApi.update(notification.id, {
				is_active: !notification.is_active
			});
			const idx = notifications.findIndex((n) => n.id === notification.id);
			if (idx !== -1) {
				notifications[idx] = updated;
			}
		} catch (err) {
			console.error('Failed to toggle notification:', err);
		}
	}

	function editNotification(notification: TeamChatNotification) {
		editingNotification = notification;
		form = {
			connection_id: notification.connection_id,
			channel_id: notification.channel_id ?? 0,
			name: notification.name,
			description: notification.description ?? '',
			trigger_event: notification.trigger_event,
			trigger_module: notification.trigger_module ?? '',
			message_template: notification.message_template,
			include_mentions: notification.include_mentions,
			mention_field: notification.mention_field ?? '',
			is_active: notification.is_active
		};
		loadChannels(notification.connection_id);
		showEditor = true;
	}

	function resetForm() {
		editingNotification = null;
		form = {
			connection_id: 0,
			channel_id: 0,
			name: '',
			description: '',
			trigger_event: '',
			trigger_module: '',
			message_template: '',
			include_mentions: false,
			mention_field: '',
			is_active: true
		};
		channels = [];
	}

	function openNewEditor() {
		resetForm();
		showEditor = true;
	}

	function getEventLabel(value: string): string {
		const event = events.find((e) => e.value === value);
		return event?.label ?? value;
	}

	$effect(() => {
		loadData();
	});

	$effect(() => {
		if (form.connection_id) {
			loadChannels(form.connection_id);
		}
	});
</script>

<div class="space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h2 class="text-lg font-semibold">Notifications</h2>
			<p class="text-sm text-muted-foreground">
				Automatically send messages to Slack or Teams when events occur
			</p>
		</div>
		<Button onclick={openNewEditor} disabled={connections.length === 0}>
			<Plus class="mr-2 h-4 w-4" />
			Create Notification
		</Button>
	</div>

	{#if connections.length === 0 && !loading}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Zap class="h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="font-medium mb-2">No Connections Available</h3>
				<p class="text-sm text-muted-foreground">
					Add a Slack or Teams connection first to create notifications
				</p>
			</Card.Content>
		</Card.Root>
	{:else if loading}
		<div class="flex items-center justify-center py-8">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if notifications.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Bell class="h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="font-medium mb-2">No Notifications</h3>
				<p class="text-sm text-muted-foreground mb-4">
					Create a notification to send messages when events happen
				</p>
				<Button onclick={openNewEditor}>
					<Plus class="mr-2 h-4 w-4" />
					Create Notification
				</Button>
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="space-y-3">
			{#each notifications as notification (notification.id)}
				<Card.Root class={!notification.is_active ? 'opacity-60' : ''}>
					<Card.Content class="py-4">
						<div class="flex items-start justify-between">
							<div class="flex-1">
								<div class="flex items-center gap-2 mb-1">
									<h3 class="font-medium">{notification.name}</h3>
									{#if notification.is_active}
										<Badge variant="default" class="bg-green-500">Active</Badge>
									{:else}
										<Badge variant="secondary">Inactive</Badge>
									{/if}
								</div>
								{#if notification.description}
									<p class="text-sm text-muted-foreground mb-2">{notification.description}</p>
								{/if}
								<div class="flex items-center gap-4 text-xs text-muted-foreground">
									<span class="flex items-center gap-1">
										<Zap class="h-3 w-3" />
										{getEventLabel(notification.trigger_event)}
									</span>
									{#if notification.connection}
										<span>
											{notification.connection.provider === 'slack' ? '🔵' : '🟣'}
											{notification.connection.name}
										</span>
									{/if}
									{#if notification.channel}
										<span class="flex items-center gap-1">
											<Hash class="h-3 w-3" />
											{notification.channel.name}
										</span>
									{/if}
									<span>{notification.triggered_count} sent</span>
								</div>
							</div>
							<div class="flex items-center gap-1">
								<Button
									variant="ghost"
									size="sm"
									onclick={() => testNotification(notification)}
									disabled={testing === notification.id}
								>
									{#if testing === notification.id}
										<RefreshCw class="h-4 w-4 animate-spin" />
									{:else}
										<Play class="h-4 w-4" />
									{/if}
								</Button>
								<Button variant="ghost" size="sm" onclick={() => toggleActive(notification)}>
									{#if notification.is_active}
										<BellOff class="h-4 w-4" />
									{:else}
										<Bell class="h-4 w-4" />
									{/if}
								</Button>
								<Button variant="ghost" size="sm" onclick={() => duplicateNotification(notification)}>
									<Copy class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="sm" onclick={() => editNotification(notification)}>
									<Edit class="h-4 w-4" />
								</Button>
								<Button
									variant="ghost"
									size="sm"
									class="text-destructive"
									onclick={() => deleteNotification(notification.id)}
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
</div>

<!-- Editor Dialog -->
<Dialog.Root bind:open={showEditor}>
	<Dialog.Content class="sm:max-w-xl">
		<Dialog.Header>
			<Dialog.Title>
				{editingNotification ? 'Edit Notification' : 'Create Notification'}
			</Dialog.Title>
			<Dialog.Description>
				Configure when and what message to send to your team chat
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4 max-h-[60vh] overflow-y-auto">
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="name">Name</Label>
					<Input id="name" bind:value={form.name} placeholder="Deal Won Alert" />
				</div>
				<div class="space-y-2">
					<Label>Connection</Label>
					<Select.Root
						type="single"
						value={form.connection_id ? String(form.connection_id) : ''}
						onValueChange={(v) => {
							if (v) form.connection_id = parseInt(v);
						}}
					>
						<Select.Trigger>
							<span>
								{#if form.connection_id}
									{connections.find((c) => c.id === form.connection_id)?.name ?? 'Select...'}
								{:else}
									Select connection...
								{/if}
							</span>
						</Select.Trigger>
						<Select.Content>
							{#each connections as conn (conn.id)}
								<Select.Item value={String(conn.id)}>
									{conn.provider === 'slack' ? '🔵' : '🟣'}
									{conn.name}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="description">Description (Optional)</Label>
				<Input
					id="description"
					bind:value={form.description}
					placeholder="Notifies the team when a deal is won"
				/>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label>Trigger Event</Label>
					<Select.Root
						type="single"
						value={form.trigger_event}
						onValueChange={(v) => {
							if (v) form.trigger_event = v;
						}}
					>
						<Select.Trigger>
							<span>{form.trigger_event ? getEventLabel(form.trigger_event) : 'Select event...'}</span>
						</Select.Trigger>
						<Select.Content>
							{#each events as event (event.value)}
								<Select.Item value={event.value}>{event.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
				<div class="space-y-2">
					<Label>Channel</Label>
					<Select.Root
						type="single"
						value={form.channel_id ? String(form.channel_id) : ''}
						onValueChange={(v) => {
							if (v) form.channel_id = parseInt(v);
						}}
					>
						<Select.Trigger>
							<span>
								{#if form.channel_id}
									#{channels.find((c) => c.id === form.channel_id)?.name ?? 'Select...'}
								{:else}
									Select channel...
								{/if}
							</span>
						</Select.Trigger>
						<Select.Content>
							{#each channels as ch (ch.id)}
								<Select.Item value={String(ch.id)}>#{ch.name}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="trigger_module">Module (Optional)</Label>
				<Input id="trigger_module" bind:value={form.trigger_module} placeholder="deals" />
				<p class="text-xs text-muted-foreground">
					Filter to specific module (e.g., deals, leads, contacts)
				</p>
			</div>

			<div class="space-y-2">
				<Label for="message_template">Message Template</Label>
				<Textarea
					id="message_template"
					bind:value={form.message_template}
					placeholder="🎉 *Record* was just won for $10,000!
Owner: John Doe"
					rows={4}
				/>
				<p class="text-xs text-muted-foreground">
					Use {'{{field_name}}'} to insert record values. Supports Slack/Teams markdown.
				</p>
			</div>

			<div class="flex items-center justify-between">
				<div class="space-y-0.5">
					<Label>Include @mentions</Label>
					<p class="text-xs text-muted-foreground">Mention users based on a record field</p>
				</div>
				<Switch bind:checked={form.include_mentions} />
			</div>

			{#if form.include_mentions}
				<div class="space-y-2">
					<Label for="mention_field">Mention Field</Label>
					<Input id="mention_field" bind:value={form.mention_field} placeholder="owner_id" />
					<p class="text-xs text-muted-foreground">
						Field containing user ID to @mention (requires user mapping)
					</p>
				</div>
			{/if}

			<div class="flex items-center justify-between">
				<div class="space-y-0.5">
					<Label>Active</Label>
					<p class="text-xs text-muted-foreground">Enable or disable this notification</p>
				</div>
				<Switch bind:checked={form.is_active} />
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showEditor = false)}>Cancel</Button>
			<Button
				onclick={saveNotification}
				disabled={!form.name || !form.connection_id || !form.trigger_event || !form.message_template}
			>
				<CheckCircle class="mr-2 h-4 w-4" />
				{editingNotification ? 'Update' : 'Create'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
