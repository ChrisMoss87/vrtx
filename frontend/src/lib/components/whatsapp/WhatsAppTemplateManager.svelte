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
	import {
		whatsappTemplatesApi,
		whatsappConnectionsApi,
		type WhatsappTemplate,
		type WhatsappConnection,
		type TemplateComponent
	} from '$lib/api/whatsapp';
	import { Plus, RefreshCw, Trash2, Send, Eye, Loader2, FileText, CheckCircle, XCircle, Clock, AlertCircle } from 'lucide-svelte';

	let loading = $state(true);
	let templates = $state<WhatsappTemplate[]>([]);
	let connections = $state<WhatsappConnection[]>([]);
	let showCreateDialog = $state(false);
	let showPreviewDialog = $state(false);
	let previewContent = $state<{ header: string | null; body: string; footer: string | null; buttons: unknown[] } | null>(null);
	let selectedTemplate = $state<WhatsappTemplate | null>(null);
	let saving = $state(false);
	let syncing = $state<number | null>(null);

	// Form state
	let connectionId = $state('');
	let templateName = $state('');
	let language = $state('en');
	let category = $state<string>('UTILITY');
	let bodyText = $state('');
	let footerText = $state('');

	const categoryOptions = [
		{ value: 'UTILITY', label: 'Utility' },
		{ value: 'MARKETING', label: 'Marketing' },
		{ value: 'AUTHENTICATION', label: 'Authentication' }
	];

	async function loadTemplates() {
		loading = true;
		try {
			templates = await whatsappTemplatesApi.list();
		} catch (err) {
			console.error('Failed to load templates:', err);
		}
		loading = false;
	}

	async function loadConnections() {
		try {
			connections = await whatsappConnectionsApi.list();
		} catch (err) {
			console.error('Failed to load connections:', err);
		}
	}

	function openCreateDialog() {
		selectedTemplate = null;
		connectionId = connections[0]?.id.toString() || '';
		templateName = '';
		language = 'en';
		category = 'UTILITY';
		bodyText = '';
		footerText = '';
		showCreateDialog = true;
	}

	async function handleSave() {
		if (!connectionId || !templateName.trim() || !bodyText.trim()) return;

		saving = true;
		try {
			const components: TemplateComponent[] = [
				{ type: 'BODY', text: bodyText.trim() }
			];

			if (footerText.trim()) {
				components.push({ type: 'FOOTER', text: footerText.trim() });
			}

			await whatsappTemplatesApi.create({
				connection_id: parseInt(connectionId),
				name: templateName.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_'),
				language,
				category: category as 'UTILITY' | 'MARKETING' | 'AUTHENTICATION',
				components,
				submit_to_meta: true
			});

			showCreateDialog = false;
			loadTemplates();
		} catch (err) {
			console.error('Failed to create template:', err);
		}
		saving = false;
	}

	async function handleDelete(template: WhatsappTemplate) {
		if (!confirm(`Are you sure you want to delete "${template.name}"?`)) return;

		try {
			await whatsappTemplatesApi.delete(template.id);
			loadTemplates();
		} catch (err) {
			console.error('Failed to delete template:', err);
		}
	}

	async function handleSyncStatus(template: WhatsappTemplate) {
		syncing = template.id;
		try {
			await whatsappTemplatesApi.syncStatus(template.id);
			loadTemplates();
		} catch (err) {
			console.error('Failed to sync status:', err);
		}
		syncing = null;
	}

	async function handleSubmit(template: WhatsappTemplate) {
		try {
			await whatsappTemplatesApi.submit(template.id);
			loadTemplates();
		} catch (err) {
			console.error('Failed to submit template:', err);
		}
	}

	async function showPreview(template: WhatsappTemplate) {
		selectedTemplate = template;
		try {
			previewContent = await whatsappTemplatesApi.preview(template.id);
			showPreviewDialog = true;
		} catch (err) {
			console.error('Failed to load preview:', err);
		}
	}

	function getStatusBadge(status: string): { variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: typeof CheckCircle } {
		switch (status) {
			case 'APPROVED':
				return { variant: 'default', icon: CheckCircle };
			case 'PENDING':
				return { variant: 'secondary', icon: Clock };
			case 'REJECTED':
				return { variant: 'destructive', icon: XCircle };
			default:
				return { variant: 'outline', icon: AlertCircle };
		}
	}

	function extractVariables(text: string): string[] {
		const matches = text.match(/\{\{(\d+)\}\}/g);
		return matches ? [...new Set(matches)] : [];
	}

	onMount(() => {
		loadConnections();
		loadTemplates();
	});
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>Message Templates</CardTitle>
				<CardDescription>
					Manage WhatsApp message templates for automated and out-of-window messaging
				</CardDescription>
			</div>
			<Button onclick={openCreateDialog} disabled={connections.length === 0}>
				<Plus class="h-4 w-4 mr-2" />
				Create Template
			</Button>
		</div>
	</CardHeader>
	<CardContent>
		{#if loading}
			<div class="flex items-center justify-center h-32">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if templates.length === 0}
			<div class="flex flex-col items-center justify-center h-32 text-muted-foreground">
				<FileText class="h-8 w-8 mb-2 opacity-50" />
				<p class="text-sm">No templates created yet</p>
			</div>
		{:else}
			<div class="space-y-3">
				{#each templates as template}
					{@const statusInfo = getStatusBadge(template.status)}
					<div class="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50">
						<div class="flex-1 min-w-0">
							<div class="flex items-center gap-2">
								<h4 class="font-medium">{template.name}</h4>
								<Badge variant={statusInfo.variant}>
									<svelte:component this={statusInfo.icon} class="h-3 w-3 mr-1" />
									{template.status}
								</Badge>
								<Badge variant="outline">{template.category}</Badge>
								<Badge variant="outline">{template.language}</Badge>
							</div>
							{#if template.connection}
								<p class="text-sm text-muted-foreground mt-1">
									Connection: {template.connection.name}
								</p>
							{/if}
							{#if template.rejection_reason}
								<p class="text-sm text-destructive mt-1">
									Rejected: {template.rejection_reason}
								</p>
							{/if}
							{#if template.components}
								{@const body = template.components.find(c => c.type === 'BODY')}
								{#if body?.text}
									<p class="text-sm text-muted-foreground mt-1 line-clamp-2">{body.text}</p>
								{/if}
							{/if}
						</div>
						<div class="flex items-center gap-1 ml-4">
							<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => showPreview(template)}>
								<Eye class="h-4 w-4" />
							</Button>
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8"
								onclick={() => handleSyncStatus(template)}
								disabled={syncing === template.id}
							>
								<RefreshCw class="h-4 w-4 {syncing === template.id ? 'animate-spin' : ''}" />
							</Button>
							{#if template.status === 'PENDING' || template.status === 'REJECTED'}
								<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => handleSubmit(template)}>
									<Send class="h-4 w-4" />
								</Button>
							{/if}
							<Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" onclick={() => handleDelete(template)}>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>

<!-- Create Template Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Create Message Template</Dialog.Title>
			<Dialog.Description>
				Create a new template for WhatsApp messaging. Templates must be approved by Meta.
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
				<Label>Connection *</Label>
				<Select.Root type="single" bind:value={connectionId}>
					<Select.Trigger>
						{connections.find(c => c.id.toString() === connectionId)?.name || 'Select connection...'}
					</Select.Trigger>
					<Select.Content>
						{#each connections as conn}
							<Select.Item value={conn.id.toString()} label={conn.name}>{conn.name}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="templateName">Template Name *</Label>
					<Input
						id="templateName"
						bind:value={templateName}
						placeholder="order_confirmation"
					/>
					<p class="text-xs text-muted-foreground">Lowercase, underscores only</p>
				</div>

				<div class="space-y-2">
					<Label>Category *</Label>
					<Select.Root type="single" bind:value={category}>
						<Select.Trigger>
							{categoryOptions.find(c => c.value === category)?.label || 'Select...'}
						</Select.Trigger>
						<Select.Content>
							{#each categoryOptions as opt}
								<Select.Item value={opt.value} label={opt.label}>{opt.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="bodyText">Body Text *</Label>
				<Textarea
					id="bodyText"
					bind:value={bodyText}
					placeholder={'Hello {{1}}, your order #{{2}} has been confirmed.'}
					rows={4}
				/>
				<p class="text-xs text-muted-foreground">
					Use {'{{1}}'}, {'{{2}}'}, etc. for variables
				</p>
				{#if bodyText}
					{@const vars = extractVariables(bodyText)}
					{#if vars.length > 0}
						<p class="text-xs text-muted-foreground">
							Variables: {vars.join(', ')}
						</p>
					{/if}
				{/if}
			</div>

			<div class="space-y-2">
				<Label for="footerText">Footer (Optional)</Label>
				<Input id="footerText" bind:value={footerText} placeholder="Thank you for your business" />
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={handleSave} disabled={saving || !connectionId || !templateName.trim() || !bodyText.trim()}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				Create & Submit
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Preview Dialog -->
<Dialog.Root bind:open={showPreviewDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Template Preview</Dialog.Title>
		</Dialog.Header>

		{#if previewContent && selectedTemplate}
			<div class="bg-muted p-4 rounded-lg">
				<div class="bg-green-600 text-white px-4 py-3 rounded-t-lg -mx-4 -mt-4 mb-3">
					<div class="text-sm font-medium">{selectedTemplate.name}</div>
				</div>
				{#if previewContent.header}
					<p class="font-medium mb-2">{previewContent.header}</p>
				{/if}
				<p class="text-sm whitespace-pre-wrap">{previewContent.body}</p>
				{#if previewContent.footer}
					<p class="text-xs text-muted-foreground mt-2">{previewContent.footer}</p>
				{/if}
			</div>
		{/if}

		<Dialog.Footer>
			<Button onclick={() => (showPreviewDialog = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
