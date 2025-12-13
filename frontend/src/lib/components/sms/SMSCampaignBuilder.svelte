<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Badge } from '$lib/components/ui/badge';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Progress } from '$lib/components/ui/progress';
	import {
		smsCampaignsApi,
		smsConnectionsApi,
		smsTemplatesApi,
		type SmsCampaign,
		type SmsConnection,
		type SmsTemplate,
		type SmsCampaignStats
	} from '$lib/api/sms';
	import { getActiveModules, type Module } from '$lib/api/modules';
	import {
		Plus,
		Trash2,
		Play,
		Pause,
		XCircle,
		Loader2,
		Users,
		Send,
		CheckCircle,
		Clock,
		BarChart2,
		Eye
	} from 'lucide-svelte';

	let loading = $state(true);
	let campaigns = $state<SmsCampaign[]>([]);
	let connections = $state<SmsConnection[]>([]);
	let templates = $state<SmsTemplate[]>([]);
	let modules = $state<Module[]>([]);
	let showCreateDialog = $state(false);
	let showStatsDialog = $state(false);
	let selectedCampaign = $state<SmsCampaign | null>(null);
	let campaignStats = $state<SmsCampaignStats | null>(null);
	let saving = $state(false);

	// Form state
	let name = $state('');
	let description = $state('');
	let connectionId = $state('');
	let templateId = $state('');
	let messageContent = $state('');
	let targetModule = $state('');
	let phoneField = $state('phone');

	async function loadData() {
		loading = true;
		try {
			const [campaignRes, conns, tmpls, mods] = await Promise.all([
				smsCampaignsApi.list(),
				smsConnectionsApi.list(),
				smsTemplatesApi.list({ active_only: true }),
				getActiveModules()
			]);
			campaigns = campaignRes.data;
			connections = conns.filter(c => c.is_active);
			templates = tmpls;
			modules = mods;

			if (connections.length > 0 && !connectionId) {
				connectionId = connections[0].id.toString();
			}
		} catch (err) {
			console.error('Failed to load data:', err);
		}
		loading = false;
	}

	function openCreateDialog() {
		selectedCampaign = null;
		name = '';
		description = '';
		connectionId = connections[0]?.id.toString() || '';
		templateId = '';
		messageContent = '';
		targetModule = '';
		phoneField = 'phone';
		showCreateDialog = true;
	}

	function openEditDialog(campaign: SmsCampaign) {
		selectedCampaign = campaign;
		name = campaign.name;
		description = campaign.description || '';
		connectionId = campaign.connection_id.toString();
		templateId = campaign.template_id?.toString() || '';
		messageContent = campaign.message_content || '';
		targetModule = campaign.target_module || '';
		phoneField = campaign.phone_field || 'phone';
		showCreateDialog = true;
	}

	async function handleSave() {
		if (!name.trim() || !connectionId) return;

		saving = true;
		try {
			const data = {
				name: name.trim(),
				description: description.trim() || undefined,
				connection_id: parseInt(connectionId),
				template_id: templateId ? parseInt(templateId) : undefined,
				message_content: messageContent.trim() || undefined,
				target_module: targetModule || undefined,
				phone_field: phoneField
			};

			if (selectedCampaign) {
				await smsCampaignsApi.update(selectedCampaign.id, data);
			} else {
				await smsCampaignsApi.create(data);
			}

			showCreateDialog = false;
			loadData();
		} catch (err) {
			console.error('Failed to save campaign:', err);
		}
		saving = false;
	}

	async function handleDelete(campaign: SmsCampaign) {
		if (!confirm(`Are you sure you want to delete "${campaign.name}"?`)) return;

		try {
			await smsCampaignsApi.delete(campaign.id);
			loadData();
		} catch (err) {
			console.error('Failed to delete campaign:', err);
		}
	}

	async function handleSendNow(campaign: SmsCampaign) {
		if (!confirm(`Send campaign "${campaign.name}" now?`)) return;

		try {
			await smsCampaignsApi.sendNow(campaign.id);
			loadData();
		} catch (err) {
			console.error('Failed to send campaign:', err);
		}
	}

	async function handlePause(campaign: SmsCampaign) {
		try {
			await smsCampaignsApi.pause(campaign.id);
			loadData();
		} catch (err) {
			console.error('Failed to pause campaign:', err);
		}
	}

	async function handleCancel(campaign: SmsCampaign) {
		if (!confirm(`Cancel campaign "${campaign.name}"?`)) return;

		try {
			await smsCampaignsApi.cancel(campaign.id);
			loadData();
		} catch (err) {
			console.error('Failed to cancel campaign:', err);
		}
	}

	async function viewStats(campaign: SmsCampaign) {
		try {
			const result = await smsCampaignsApi.get(campaign.id);
			selectedCampaign = result.data;
			campaignStats = result.stats;
			showStatsDialog = true;
		} catch (err) {
			console.error('Failed to load stats:', err);
		}
	}

	function handleTemplateSelect(id: string) {
		templateId = id;
		const template = templates.find(t => t.id.toString() === id);
		if (template) {
			messageContent = template.content;
		}
	}

	function getStatusBadge(status: string): { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string } {
		switch (status) {
			case 'draft':
				return { variant: 'secondary', label: 'Draft' };
			case 'scheduled':
				return { variant: 'outline', label: 'Scheduled' };
			case 'sending':
				return { variant: 'default', label: 'Sending' };
			case 'sent':
				return { variant: 'default', label: 'Completed' };
			case 'paused':
				return { variant: 'secondary', label: 'Paused' };
			case 'cancelled':
				return { variant: 'destructive', label: 'Cancelled' };
			default:
				return { variant: 'outline', label: status };
		}
	}

	onMount(() => {
		loadData();
	});
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>SMS Campaigns</CardTitle>
				<CardDescription>
					Create and manage bulk SMS campaigns
				</CardDescription>
			</div>
			<Button onclick={openCreateDialog} disabled={connections.length === 0}>
				<Plus class="h-4 w-4 mr-2" />
				Create Campaign
			</Button>
		</div>
	</CardHeader>
	<CardContent>
		{#if loading}
			<div class="flex items-center justify-center h-32">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if campaigns.length === 0}
			<div class="flex flex-col items-center justify-center h-32 text-muted-foreground">
				<Send class="h-8 w-8 mb-2 opacity-50" />
				<p class="text-sm">No campaigns created yet</p>
			</div>
		{:else}
			<div class="space-y-3">
				{#each campaigns as campaign}
					{@const statusInfo = getStatusBadge(campaign.status)}
					<div class="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50">
						<div class="flex-1 min-w-0">
							<div class="flex items-center gap-2">
								<h4 class="font-medium">{campaign.name}</h4>
								<Badge variant={statusInfo.variant}>{statusInfo.label}</Badge>
							</div>
							{#if campaign.description}
								<p class="text-sm text-muted-foreground mt-1">{campaign.description}</p>
							{/if}
							<div class="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
								<span class="flex items-center gap-1">
									<Users class="h-3 w-3" />
									{campaign.total_recipients} recipients
								</span>
								<span class="flex items-center gap-1">
									<Send class="h-3 w-3" />
									{campaign.sent_count} sent
								</span>
								<span class="flex items-center gap-1">
									<CheckCircle class="h-3 w-3" />
									{campaign.delivered_count} delivered
								</span>
							</div>
							{#if campaign.status === 'sending'}
								<Progress value={campaign.total_recipients > 0 ? (campaign.sent_count / campaign.total_recipients) * 100 : 0} class="mt-2 h-1" />
							{/if}
						</div>
						<div class="flex items-center gap-1 ml-4">
							<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => viewStats(campaign)}>
								<BarChart2 class="h-4 w-4" />
							</Button>
							{#if campaign.status === 'draft'}
								<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => openEditDialog(campaign)}>
									<Eye class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="icon" class="h-8 w-8 text-green-600" onclick={() => handleSendNow(campaign)}>
									<Play class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" onclick={() => handleDelete(campaign)}>
									<Trash2 class="h-4 w-4" />
								</Button>
							{:else if campaign.status === 'sending'}
								<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => handlePause(campaign)}>
									<Pause class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" onclick={() => handleCancel(campaign)}>
									<XCircle class="h-4 w-4" />
								</Button>
							{:else if campaign.status === 'scheduled' || campaign.status === 'paused'}
								<Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" onclick={() => handleCancel(campaign)}>
									<XCircle class="h-4 w-4" />
								</Button>
							{/if}
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
			<Dialog.Title>{selectedCampaign ? 'Edit' : 'Create'} SMS Campaign</Dialog.Title>
			<Dialog.Description>
				Configure your bulk SMS campaign settings.
			</Dialog.Description>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleSave(); }} class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Campaign Name *</Label>
				<Input id="name" bind:value={name} placeholder="Holiday Sale Announcement" />
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Input id="description" bind:value={description} placeholder="Describe your campaign..." />
			</div>

			<div class="space-y-2">
				<Label>From Connection *</Label>
				<Select.Root type="single" bind:value={connectionId}>
					<Select.Trigger>
						{connections.find(c => c.id.toString() === connectionId)?.name || 'Select connection...'}
					</Select.Trigger>
					<Select.Content>
						{#each connections as conn}
							<Select.Item value={conn.id.toString()} label={conn.name}>
								{conn.name} ({conn.phone_number})
							</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<div class="space-y-2">
				<Label>Template (Optional)</Label>
				<Select.Root type="single" value={templateId} onValueChange={handleTemplateSelect}>
					<Select.Trigger>
						{templates.find(t => t.id.toString() === templateId)?.name || 'Select template...'}
					</Select.Trigger>
					<Select.Content>
						{#each templates as template}
							<Select.Item value={template.id.toString()} label={template.name}>
								{template.name}
							</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<div class="space-y-2">
				<Label for="messageContent">Message Content *</Label>
				<Textarea
					id="messageContent"
					bind:value={messageContent}
					placeholder={'Hi {{first_name}}, check out our holiday sale!'}
					rows={4}
				/>
				<p class="text-xs text-muted-foreground">{messageContent.length} characters</p>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label>Target Module</Label>
					<Select.Root type="single" bind:value={targetModule}>
						<Select.Trigger>
							{modules.find(m => m.api_name === targetModule)?.name || 'Select module...'}
						</Select.Trigger>
						<Select.Content>
							{#each modules as mod}
								<Select.Item value={mod.api_name} label={mod.name}>{mod.name}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
				<div class="space-y-2">
					<Label for="phoneField">Phone Field</Label>
					<Input id="phoneField" bind:value={phoneField} placeholder="phone" />
				</div>
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={handleSave} disabled={saving || !name.trim() || !connectionId || !messageContent.trim()}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				{selectedCampaign ? 'Save Changes' : 'Create Campaign'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Stats Dialog -->
<Dialog.Root bind:open={showStatsDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Campaign Statistics</Dialog.Title>
			{#if selectedCampaign}
				<Dialog.Description>{selectedCampaign.name}</Dialog.Description>
			{/if}
		</Dialog.Header>

		{#if campaignStats}
			<div class="space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div class="p-3 bg-muted rounded-lg">
						<p class="text-2xl font-bold">{campaignStats.total_recipients}</p>
						<p class="text-sm text-muted-foreground">Total Recipients</p>
					</div>
					<div class="p-3 bg-muted rounded-lg">
						<p class="text-2xl font-bold">{campaignStats.sent_count}</p>
						<p class="text-sm text-muted-foreground">Messages Sent</p>
					</div>
					<div class="p-3 bg-green-50 rounded-lg">
						<p class="text-2xl font-bold text-green-600">{campaignStats.delivered_count}</p>
						<p class="text-sm text-muted-foreground">Delivered</p>
					</div>
					<div class="p-3 bg-red-50 rounded-lg">
						<p class="text-2xl font-bold text-red-600">{campaignStats.failed_count}</p>
						<p class="text-sm text-muted-foreground">Failed</p>
					</div>
				</div>

				<div class="space-y-2">
					<div class="flex items-center justify-between text-sm">
						<span>Delivery Rate</span>
						<span class="font-medium">{campaignStats.delivery_rate}%</span>
					</div>
					<Progress value={campaignStats.delivery_rate} class="h-2" />
				</div>

				<div class="grid grid-cols-2 gap-4 text-sm">
					<div>
						<span class="text-muted-foreground">Opted Out:</span> {campaignStats.opted_out_count}
					</div>
					<div>
						<span class="text-muted-foreground">Replies:</span> {campaignStats.reply_count}
					</div>
					<div>
						<span class="text-muted-foreground">Progress:</span> {campaignStats.progress}%
					</div>
					<div>
						<span class="text-muted-foreground">Failure Rate:</span> {campaignStats.failure_rate}%
					</div>
				</div>
			</div>
		{/if}

		<Dialog.Footer>
			<Button onclick={() => (showStatsDialog = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
