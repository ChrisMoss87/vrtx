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
	import { smsTemplatesApi, type SmsTemplate } from '$lib/api/sms';
	import { Plus, Trash2, Copy, FileText, Loader2, Eye } from 'lucide-svelte';

	let loading = $state(true);
	let templates = $state<SmsTemplate[]>([]);
	let showCreateDialog = $state(false);
	let showPreviewDialog = $state(false);
	let editingTemplate = $state<SmsTemplate | undefined>(undefined);
	let saving = $state(false);
	let previewData = $state<{
		original: string;
		rendered: string;
		character_count: number;
		segment_count: number;
		merge_fields: string[];
	} | null>(null);

	// Form state
	let name = $state('');
	let content = $state('');
	let category = $state<string>('transactional');

	const categoryOptions = [
		{ value: 'transactional', label: 'Transactional' },
		{ value: 'marketing', label: 'Marketing' },
		{ value: 'support', label: 'Support' }
	];

	const characterCount = $derived(content.length);
	const segmentCount = $derived(calculateSegments(content));
	const mergeFields = $derived(extractMergeFields(content));

	function calculateSegments(text: string): number {
		const length = text.length;
		if (length === 0) return 0;
		// Simple GSM-7 calculation (160/153 chars per segment)
		return length <= 160 ? 1 : Math.ceil(length / 153);
	}

	function extractMergeFields(text: string): string[] {
		const matches = text.match(/\{\{(\w+)\}\}/g);
		return matches ? [...new Set(matches)] : [];
	}

	async function loadTemplates() {
		loading = true;
		try {
			templates = await smsTemplatesApi.list();
		} catch (err) {
			console.error('Failed to load templates:', err);
		}
		loading = false;
	}

	function openCreateDialog() {
		editingTemplate = undefined;
		name = '';
		content = '';
		category = 'transactional';
		showCreateDialog = true;
	}

	function openEditDialog(template: SmsTemplate) {
		editingTemplate = template;
		name = template.name;
		content = template.content;
		category = template.category || 'transactional';
		showCreateDialog = true;
	}

	async function handleSave() {
		if (!name.trim() || !content.trim()) return;

		saving = true;
		try {
			if (editingTemplate) {
				await smsTemplatesApi.update(editingTemplate.id, {
					name: name.trim(),
					content: content.trim(),
					category: category as 'marketing' | 'transactional' | 'support'
				});
			} else {
				await smsTemplatesApi.create({
					name: name.trim(),
					content: content.trim(),
					category: category as 'marketing' | 'transactional' | 'support'
				});
			}

			showCreateDialog = false;
			loadTemplates();
		} catch (err) {
			console.error('Failed to save template:', err);
		}
		saving = false;
	}

	async function handleDelete(template: SmsTemplate) {
		if (!confirm(`Are you sure you want to delete "${template.name}"?`)) return;

		try {
			await smsTemplatesApi.delete(template.id);
			loadTemplates();
		} catch (err) {
			console.error('Failed to delete template:', err);
		}
	}

	async function handleDuplicate(template: SmsTemplate) {
		try {
			await smsTemplatesApi.duplicate(template.id);
			loadTemplates();
		} catch (err) {
			console.error('Failed to duplicate template:', err);
		}
	}

	async function showPreview(template: SmsTemplate) {
		try {
			previewData = await smsTemplatesApi.preview(template.id);
			editingTemplate = template;
			showPreviewDialog = true;
		} catch (err) {
			console.error('Failed to load preview:', err);
		}
	}

	function insertMergeField(field: string) {
		content += `{{${field}}}`;
	}

	onMount(() => {
		loadTemplates();
	});
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>SMS Templates</CardTitle>
				<CardDescription>
					Create reusable message templates with merge fields
				</CardDescription>
			</div>
			<Button onclick={openCreateDialog}>
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
					<div class="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50">
						<div class="flex-1 min-w-0">
							<div class="flex items-center gap-2">
								<h4 class="font-medium">{template.name}</h4>
								{#if template.category}
									<Badge variant="outline">{template.category}</Badge>
								{/if}
								{#if !template.is_active}
									<Badge variant="secondary">Inactive</Badge>
								{/if}
							</div>
							<p class="text-sm text-muted-foreground mt-1 line-clamp-2">{template.content}</p>
							<div class="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
								<span>{template.character_count} chars</span>
								<span>{template.segment_count} segment{template.segment_count !== 1 ? 's' : ''}</span>
								<span>{template.usage_count} uses</span>
								{#if template.merge_fields && template.merge_fields.length > 0}
									<span>Fields: {template.merge_fields.join(', ')}</span>
								{/if}
							</div>
						</div>
						<div class="flex items-center gap-1 ml-4">
							<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => showPreview(template)}>
								<Eye class="h-4 w-4" />
							</Button>
							<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => handleDuplicate(template)}>
								<Copy class="h-4 w-4" />
							</Button>
							<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => openEditDialog(template)}>
								<FileText class="h-4 w-4" />
							</Button>
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

<!-- Create/Edit Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>{editingTemplate ? 'Edit' : 'Create'} SMS Template</Dialog.Title>
			<Dialog.Description>
				Create a reusable message template. Use {'{{field_name}}'} for merge fields.
			</Dialog.Description>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleSave(); }} class="space-y-4">
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="name">Template Name *</Label>
					<Input id="name" bind:value={name} placeholder="Order Confirmation" />
				</div>
				<div class="space-y-2">
					<Label>Category</Label>
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
				<Label for="content">Message Content *</Label>
				<Textarea
					id="content"
					bind:value={content}
					placeholder={'Hi {{first_name}}, your order #{{order_id}} has been confirmed!'}
					rows={5}
				/>
				<div class="flex items-center justify-between text-xs text-muted-foreground">
					<span>{characterCount} characters &bull; {segmentCount} segment{segmentCount !== 1 ? 's' : ''}</span>
					{#if mergeFields.length > 0}
						<span>Merge fields: {mergeFields.join(', ')}</span>
					{/if}
				</div>
			</div>

			<div class="space-y-2">
				<Label>Quick Insert</Label>
				<div class="flex flex-wrap gap-2">
					{#each ['first_name', 'last_name', 'company', 'phone', 'email'] as field}
						<Button type="button" variant="outline" size="sm" onclick={() => insertMergeField(field)}>
							{`{{${field}}}`}
						</Button>
					{/each}
				</div>
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={handleSave} disabled={saving || !name.trim() || !content.trim()}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				{editingTemplate ? 'Save Changes' : 'Create Template'}
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

		{#if previewData && editingTemplate}
			<div class="space-y-4">
				<div class="bg-muted p-4 rounded-lg">
					<p class="text-sm font-medium mb-2">{editingTemplate.name}</p>
					<p class="text-sm whitespace-pre-wrap">{previewData.rendered}</p>
				</div>
				<div class="grid grid-cols-2 gap-4 text-sm">
					<div>
						<span class="text-muted-foreground">Characters:</span> {previewData.character_count}
					</div>
					<div>
						<span class="text-muted-foreground">Segments:</span> {previewData.segment_count}
					</div>
				</div>
				{#if previewData.merge_fields.length > 0}
					<div class="text-sm">
						<span class="text-muted-foreground">Merge fields:</span> {previewData.merge_fields.join(', ')}
					</div>
				{/if}
			</div>
		{/if}

		<Dialog.Footer>
			<Button onclick={() => (showPreviewDialog = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
