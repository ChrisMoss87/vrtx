<script lang="ts">
	import {
		Plus,
		Trash2,
		Edit,
		Copy,
		Eye,
		Search,
		ArrowLeft,
		FileText,
		RefreshCw
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import RichTextEditor from '$lib/components/editor/RichTextEditor.svelte';
	import {
		emailTemplatesApi,
		type EmailTemplate,
		type CreateTemplateData
	} from '$lib/api/email';
	import { cn } from '$lib/utils';

	// State
	let templates = $state<EmailTemplate[]>([]);
	let categories = $state<string[]>([]);
	let isLoading = $state(true);
	let showDialog = $state(false);
	let showPreview = $state(false);
	let editingTemplate = $state<EmailTemplate | null>(null);
	let previewTemplate = $state<EmailTemplate | null>(null);
	let previewContent = $state<{ subject: string; body_html: string; body_text: string } | null>(null);
	let isSaving = $state(false);
	let searchQuery = $state('');
	let filterCategory = $state<string | undefined>(undefined);

	// Form state
	let formData = $state<CreateTemplateData>({
		name: '',
		description: '',
		type: 'user',
		subject: '',
		body_html: '',
		body_text: '',
		category: '',
		tags: [],
		is_active: true,
		is_default: false
	});

	// Load templates on mount
	$effect(() => {
		loadTemplates();
		loadCategories();
	});

	async function loadTemplates() {
		isLoading = true;
		try {
			const response = await emailTemplatesApi.list({
				search: searchQuery || undefined,
				category: filterCategory,
				per_page: 100
			});
			templates = response.data;
		} catch (error) {
			console.error('Failed to load templates:', error);
		} finally {
			isLoading = false;
		}
	}

	async function loadCategories() {
		try {
			categories = await emailTemplatesApi.getCategories();
		} catch (error) {
			console.error('Failed to load categories:', error);
		}
	}

	function openDialog(template?: EmailTemplate) {
		if (template) {
			editingTemplate = template;
			formData = {
				name: template.name,
				description: template.description ?? '',
				type: template.type,
				module_id: template.module_id ?? undefined,
				subject: template.subject,
				body_html: template.body_html,
				body_text: template.body_text ?? '',
				category: template.category ?? '',
				tags: template.tags,
				is_active: template.is_active,
				is_default: template.is_default
			};
		} else {
			editingTemplate = null;
			formData = {
				name: '',
				description: '',
				type: 'user',
				subject: '',
				body_html: '',
				body_text: '',
				category: '',
				tags: [],
				is_active: true,
				is_default: false
			};
		}
		showDialog = true;
	}

	async function saveTemplate() {
		isSaving = true;
		try {
			if (editingTemplate) {
				await emailTemplatesApi.update(editingTemplate.id, formData);
			} else {
				await emailTemplatesApi.create(formData);
			}
			showDialog = false;
			await loadTemplates();
			await loadCategories();
		} catch (error) {
			console.error('Failed to save template:', error);
		} finally {
			isSaving = false;
		}
	}

	async function deleteTemplate(template: EmailTemplate) {
		if (!confirm('Are you sure you want to delete this template?')) return;

		try {
			await emailTemplatesApi.delete(template.id);
			await loadTemplates();
		} catch (error) {
			console.error('Failed to delete template:', error);
		}
	}

	async function duplicateTemplate(template: EmailTemplate) {
		try {
			await emailTemplatesApi.duplicate(template.id);
			await loadTemplates();
		} catch (error) {
			console.error('Failed to duplicate template:', error);
		}
	}

	async function openPreview(template: EmailTemplate) {
		previewTemplate = template;
		try {
			const response = await emailTemplatesApi.preview(template.id);
			previewContent = response.data;
			showPreview = true;
		} catch (error) {
			console.error('Failed to preview template:', error);
		}
	}

	function handleSearch() {
		loadTemplates();
	}

	const typeOptions = [
		{ value: 'user', label: 'User Template' },
		{ value: 'system', label: 'System Template' },
		{ value: 'workflow', label: 'Workflow Template' }
	];
</script>

<div class="container max-w-6xl py-6">
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" href="/email">
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-semibold">Email Templates</h1>
				<p class="text-muted-foreground">Create and manage reusable email templates</p>
			</div>
		</div>
		<Button onclick={() => openDialog()}>
			<Plus class="mr-2 h-4 w-4" />
			New Template
		</Button>
	</div>

	<!-- Filters -->
	<div class="mb-6 flex items-center gap-4">
		<div class="relative flex-1">
			<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
			<Input
				bind:value={searchQuery}
				placeholder="Search templates..."
				class="pl-9"
				onkeydown={(e) => e.key === 'Enter' && handleSearch()}
			/>
		</div>
		<Select.Root
			type="single"
			value={filterCategory}
			onValueChange={(v) => {
				filterCategory = v;
				loadTemplates();
			}}
		>
			<Select.Trigger class="w-48">
				{filterCategory ?? 'All categories'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="">All categories</Select.Item>
				{#each categories as category}
					<Select.Item value={category}>{category}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	{#if isLoading}
		<div class="flex items-center justify-center py-12">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if templates.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<FileText class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="mb-2 text-lg font-medium">No templates</h3>
				<p class="mb-4 text-muted-foreground">Create your first email template to speed up your workflow</p>
				<Button onclick={() => openDialog()}>
					<Plus class="mr-2 h-4 w-4" />
					New Template
				</Button>
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each templates as template}
				<Card.Root class="flex flex-col">
					<Card.Header class="pb-3">
						<div class="flex items-start justify-between">
							<div class="flex-1">
								<Card.Title class="line-clamp-1 text-base">{template.name}</Card.Title>
								{#if template.description}
									<Card.Description class="line-clamp-2 text-xs">
										{template.description}
									</Card.Description>
								{/if}
							</div>
							{#if !template.is_active}
								<Badge variant="outline" class="text-xs">Inactive</Badge>
							{/if}
						</div>
					</Card.Header>
					<Card.Content class="flex-1 pb-3">
						<div class="space-y-2">
							<div class="text-sm">
								<span class="text-muted-foreground">Subject:</span>
								<span class="ml-1 line-clamp-1">{template.subject}</span>
							</div>
							<div class="flex flex-wrap gap-1">
								<Badge variant="secondary" class="text-xs">{template.type}</Badge>
								{#if template.category}
									<Badge variant="outline" class="text-xs">{template.category}</Badge>
								{/if}
							</div>
							<div class="text-xs text-muted-foreground">
								Used {template.usage_count} time{template.usage_count !== 1 ? 's' : ''}
							</div>
						</div>
					</Card.Content>
					<Card.Footer class="border-t pt-3">
						<div class="flex w-full items-center justify-between">
							<Button variant="ghost" size="sm" onclick={() => openPreview(template)}>
								<Eye class="mr-1 h-3 w-3" />
								Preview
							</Button>
							<div class="flex items-center gap-1">
								<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => duplicateTemplate(template)}>
									<Copy class="h-3 w-3" />
								</Button>
								<Button variant="ghost" size="icon" class="h-8 w-8" onclick={() => openDialog(template)}>
									<Edit class="h-3 w-3" />
								</Button>
								<Button
									variant="ghost"
									size="icon"
									class="h-8 w-8 text-destructive hover:text-destructive"
									onclick={() => deleteTemplate(template)}
								>
									<Trash2 class="h-3 w-3" />
								</Button>
							</div>
						</div>
					</Card.Footer>
				</Card.Root>
			{/each}
		</div>
	{/if}
</div>

<!-- Template Dialog -->
<Dialog.Root bind:open={showDialog}>
	<Dialog.Content class="max-w-3xl max-h-[90vh] overflow-y-auto">
		<Dialog.Header>
			<Dialog.Title>{editingTemplate ? 'Edit Template' : 'New Template'}</Dialog.Title>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="name">Template Name</Label>
					<Input id="name" bind:value={formData.name} placeholder="Welcome Email" />
				</div>
				<div class="space-y-2">
					<Label for="type">Type</Label>
					<Select.Root
						type="single"
						value={formData.type}
						onValueChange={(v) => v && (formData.type = v as typeof formData.type)}
					>
						<Select.Trigger>
							{typeOptions.find(t => t.value === formData.type)?.label ?? 'Select type'}
						</Select.Trigger>
						<Select.Content>
							{#each typeOptions as option}
								<Select.Item value={option.value}>{option.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Input id="description" bind:value={formData.description} placeholder="Brief description..." />
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="category">Category</Label>
					<Input id="category" bind:value={formData.category} placeholder="Sales, Support, etc." />
				</div>
				<div class="flex items-end gap-4">
					<div class="flex items-center gap-2">
						<Switch
							id="is_active"
							checked={formData.is_active}
							onCheckedChange={(v) => (formData.is_active = v)}
						/>
						<Label for="is_active">Active</Label>
					</div>
					<div class="flex items-center gap-2">
						<Switch
							id="is_default"
							checked={formData.is_default}
							onCheckedChange={(v) => (formData.is_default = v)}
						/>
						<Label for="is_default">Default</Label>
					</div>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="subject">Subject Line</Label>
				<Input
					id="subject"
					bind:value={formData.subject}
					placeholder="Use {'{'}variable{'}'} for dynamic content"
				/>
				<p class="text-xs text-muted-foreground">
					Available variables: {'{user.name}'}, {'{user.email}'}, {'{date.today}'}, {'{company.name}'}
				</p>
			</div>

			<div class="space-y-2">
				<Label>Email Body</Label>
				<Tabs.Root value="visual">
					<Tabs.List>
						<Tabs.Trigger value="visual">Visual Editor</Tabs.Trigger>
						<Tabs.Trigger value="html">HTML</Tabs.Trigger>
						<Tabs.Trigger value="text">Plain Text</Tabs.Trigger>
					</Tabs.List>
					<Tabs.Content value="visual" class="mt-2">
						<RichTextEditor
							bind:content={formData.body_html}
							placeholder="Write your email template..."
							minHeight="200px"
							maxHeight="300px"
						/>
					</Tabs.Content>
					<Tabs.Content value="html" class="mt-2">
						<Textarea
							bind:value={formData.body_html}
							placeholder="<p>Your HTML content...</p>"
							rows={10}
							class="font-mono text-sm"
						/>
					</Tabs.Content>
					<Tabs.Content value="text" class="mt-2">
						<Textarea
							bind:value={formData.body_text}
							placeholder="Plain text version (auto-generated if empty)"
							rows={10}
						/>
					</Tabs.Content>
				</Tabs.Root>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showDialog = false)}>Cancel</Button>
			<Button onclick={saveTemplate} disabled={isSaving || !formData.name || !formData.subject}>
				{isSaving ? 'Saving...' : editingTemplate ? 'Update Template' : 'Create Template'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Preview Dialog -->
<Dialog.Root bind:open={showPreview}>
	<Dialog.Content class="max-w-2xl">
		<Dialog.Header>
			<Dialog.Title>Template Preview</Dialog.Title>
			<Dialog.Description>{previewTemplate?.name}</Dialog.Description>
		</Dialog.Header>

		{#if previewContent}
			<div class="space-y-4 py-4">
				<div class="rounded-lg border p-4">
					<div class="mb-2 text-sm text-muted-foreground">Subject</div>
					<div class="font-medium">{previewContent.subject}</div>
				</div>
				<div class="rounded-lg border p-4">
					<div class="mb-2 text-sm text-muted-foreground">Body</div>
					<div class="prose prose-sm dark:prose-invert max-w-none">
						{@html previewContent.body_html}
					</div>
				</div>
			</div>
		{/if}

		<Dialog.Footer>
			<Button onclick={() => (showPreview = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
