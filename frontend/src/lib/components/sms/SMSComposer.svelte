<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { smsConnectionsApi, smsTemplatesApi, smsMessagesApi, type SmsConnection, type SmsTemplate } from '$lib/api/sms';
	import { Send, Loader2, FileText } from 'lucide-svelte';

	interface Props {
		moduleApiName?: string;
		recordId?: number;
		defaultPhone?: string;
		onSent?: () => void;
	}

	let { moduleApiName, recordId, defaultPhone = '', onSent }: Props = $props();

	let loading = $state(true);
	let sending = $state(false);
	let connections = $state<SmsConnection[]>([]);
	let templates = $state<SmsTemplate[]>([]);

	// Form state
	let connectionId = $state('');
	let templateId = $state('');
	let toNumber = $state(defaultPhone);
	let content = $state('');

	const characterCount = $derived(content.length);
	const segmentCount = $derived(calculateSegments(content));

	function calculateSegments(text: string): number {
		const length = text.length;
		if (length === 0) return 0;
		return length <= 160 ? 1 : Math.ceil(length / 153);
	}

	async function loadData() {
		loading = true;
		try {
			const [conns, tmpls] = await Promise.all([
				smsConnectionsApi.list(),
				smsTemplatesApi.list({ active_only: true })
			]);
			connections = conns.filter(c => c.is_active);
			templates = tmpls;

			if (connections.length > 0 && !connectionId) {
				connectionId = connections[0].id.toString();
			}
		} catch (err) {
			console.error('Failed to load SMS data:', err);
		}
		loading = false;
	}

	function handleTemplateSelect(id: string) {
		templateId = id;
		const template = templates.find(t => t.id.toString() === id);
		if (template) {
			content = template.content;
		}
	}

	async function handleSend() {
		if (!connectionId || !toNumber.trim() || !content.trim()) return;

		sending = true;
		try {
			await smsMessagesApi.send({
				connection_id: parseInt(connectionId),
				to: toNumber.trim(),
				content: content.trim(),
				template_id: templateId ? parseInt(templateId) : undefined,
				module_record_id: recordId,
				module_api_name: moduleApiName
			});

			// Clear form
			content = '';
			templateId = '';
			if (!defaultPhone) toNumber = '';

			onSent?.();
		} catch (err) {
			console.error('Failed to send SMS:', err);
		}
		sending = false;
	}

	onMount(() => {
		loadData();
	});

	$effect(() => {
		if (defaultPhone) {
			toNumber = defaultPhone;
		}
	});
</script>

<Card>
	<CardHeader>
		<CardTitle>Send SMS</CardTitle>
		<CardDescription>
			Send a text message to a contact
		</CardDescription>
	</CardHeader>
	<CardContent>
		{#if loading}
			<div class="flex items-center justify-center h-32">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if connections.length === 0}
			<div class="text-center text-muted-foreground py-8">
				<p>No active SMS connections available.</p>
				<p class="text-sm">Please configure an SMS connection first.</p>
			</div>
		{:else}
			<form onsubmit={(e) => { e.preventDefault(); handleSend(); }} class="space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label>From</Label>
						<Select.Root type="single" bind:value={connectionId}>
							<Select.Trigger>
								{connections.find(c => c.id.toString() === connectionId)?.phone_number || 'Select...'}
							</Select.Trigger>
							<Select.Content>
								{#each connections as conn}
									<Select.Item value={conn.id.toString()} label={conn.phone_number}>
										{conn.name} ({conn.phone_number})
									</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
					<div class="space-y-2">
						<Label for="toNumber">To *</Label>
						<Input id="toNumber" bind:value={toNumber} placeholder="+1234567890" />
					</div>
				</div>

				{#if templates.length > 0}
					<div class="space-y-2">
						<Label>Template (Optional)</Label>
						<Select.Root type="single" value={templateId} onValueChange={handleTemplateSelect}>
							<Select.Trigger>
								<FileText class="h-4 w-4 mr-2" />
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
				{/if}

				<div class="space-y-2">
					<Label for="content">Message *</Label>
					<Textarea
						id="content"
						bind:value={content}
						placeholder="Enter your message..."
						rows={4}
					/>
					<div class="flex items-center justify-between text-xs text-muted-foreground">
						<span>{characterCount} / 160 characters</span>
						<span>{segmentCount} segment{segmentCount !== 1 ? 's' : ''}</span>
					</div>
				</div>

				<Button type="submit" disabled={sending || !connectionId || !toNumber.trim() || !content.trim()} class="w-full">
					{#if sending}
						<Loader2 class="h-4 w-4 mr-2 animate-spin" />
						Sending...
					{:else}
						<Send class="h-4 w-4 mr-2" />
						Send Message
					{/if}
				</Button>
			</form>
		{/if}
	</CardContent>
</Card>
