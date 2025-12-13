<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Table from '$lib/components/ui/table';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import {
		Plus,
		Search,
		MoreHorizontal,
		Pencil,
		Trash2,
		Copy,
		Eye,
		Mail,
		Loader2
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getWorkflowEmailTemplates,
		deleteWorkflowEmailTemplate,
		duplicateWorkflowEmailTemplate,
		getWorkflowEmailTemplateCategories,
		type WorkflowEmailTemplate
	} from '$lib/api/workflow-email-templates';
	import EmailTemplateEditor from '$lib/components/workflow-builder/EmailTemplateEditor.svelte';

	let templates = $state<WorkflowEmailTemplate[]>([]);
	let categories = $state<string[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let categoryFilter = $state<string>('all');

	// Editor dialog state
	let editorOpen = $state(false);
	let editingTemplate = $state<WorkflowEmailTemplate | null>(null);

	// Delete confirmation dialog
	let deleteDialogOpen = $state(false);
	let templateToDelete = $state<WorkflowEmailTemplate | null>(null);
	let deleting = $state(false);

	// Preview dialog
	let previewDialogOpen = $state(false);
	let previewTemplate = $state<WorkflowEmailTemplate | null>(null);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [templateData, categoryData] = await Promise.all([
				getWorkflowEmailTemplates({
					search: searchQuery || undefined,
					category: categoryFilter !== 'all' ? categoryFilter : undefined
				}),
				getWorkflowEmailTemplateCategories()
			]);
			templates = templateData;
			categories = categoryData;
		} catch (error) {
			console.error('Failed to load templates:', error);
			toast.error('Failed to load email templates');
		} finally {
			loading = false;
		}
	}

	function openEditor(template?: WorkflowEmailTemplate) {
		editingTemplate = template || null;
		editorOpen = true;
	}

	function closeEditor() {
		editorOpen = false;
		editingTemplate = null;
	}

	async function handleSaved() {
		closeEditor();
		await loadData();
		toast.success(editingTemplate ? 'Template updated' : 'Template created');
	}

	function confirmDelete(template: WorkflowEmailTemplate) {
		templateToDelete = template;
		deleteDialogOpen = true;
	}

	async function handleDelete() {
		if (!templateToDelete) return;

		deleting = true;
		try {
			await deleteWorkflowEmailTemplate(templateToDelete.id);
			toast.success('Template deleted');
			deleteDialogOpen = false;
			templateToDelete = null;
			await loadData();
		} catch (error) {
			console.error('Failed to delete template:', error);
			toast.error('Failed to delete template');
		} finally {
			deleting = false;
		}
	}

	async function handleDuplicate(template: WorkflowEmailTemplate) {
		try {
			await duplicateWorkflowEmailTemplate(template.id);
			toast.success('Template duplicated');
			await loadData();
		} catch (error) {
			console.error('Failed to duplicate template:', error);
			toast.error('Failed to duplicate template');
		}
	}

	function openPreview(template: WorkflowEmailTemplate) {
		previewTemplate = template;
		previewDialogOpen = true;
	}

	async function handleSearch() {
		await loadData();
	}

	async function handleCategoryChange(value: string) {
		categoryFilter = value;
		await loadData();
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}
</script>

<svelte:head>
	<title>Workflow Email Templates | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Workflow Email Templates</h1>
			<p class="text-muted-foreground">
				Manage email templates used in workflow automation
			</p>
		</div>
		<Button onclick={() => openEditor()}>
			<Plus class="mr-2 h-4 w-4" />
			New Template
		</Button>
	</div>

	<!-- Filters -->
	<Card.Root class="mb-6">
		<Card.Content class="pt-6">
			<div class="flex flex-wrap gap-4">
				<div class="flex flex-1 items-center gap-2">
					<Search class="h-4 w-4 text-muted-foreground" />
					<Input
						type="search"
						placeholder="Search templates..."
						class="max-w-sm"
						bind:value={searchQuery}
						onkeydown={(e) => e.key === 'Enter' && handleSearch()}
					/>
					<Button variant="secondary" onclick={handleSearch}>Search</Button>
				</div>
				<Select.Root type="single" value={categoryFilter} onValueChange={handleCategoryChange}>
					<Select.Trigger class="w-[180px]">
						{categoryFilter === 'all' ? 'All Categories' : categoryFilter}
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="all">All Categories</Select.Item>
						{#each categories as category}
							<Select.Item value={category}>{category}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Templates Table -->
	<Card.Root>
		<Card.Content class="p-0">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else if templates.length === 0}
				<div class="flex flex-col items-center justify-center py-12">
					<Mail class="mb-4 h-12 w-12 text-muted-foreground" />
					<h3 class="mb-2 text-lg font-medium">No templates found</h3>
					<p class="mb-4 text-muted-foreground">
						{searchQuery || categoryFilter !== 'all'
							? 'Try adjusting your search or filters'
							: 'Create your first email template to get started'}
					</p>
					{#if !searchQuery && categoryFilter === 'all'}
						<Button onclick={() => openEditor()}>
							<Plus class="mr-2 h-4 w-4" />
							Create Template
						</Button>
					{/if}
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Name</Table.Head>
							<Table.Head>Subject</Table.Head>
							<Table.Head>Category</Table.Head>
							<Table.Head>Updated</Table.Head>
							<Table.Head class="w-[100px]"></Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each templates as template}
							<Table.Row>
								<Table.Cell>
									<div class="flex items-center gap-2">
										<span class="font-medium">{template.name}</span>
										{#if template.is_system}
											<Badge variant="secondary">System</Badge>
										{/if}
									</div>
									{#if template.description}
										<p class="text-sm text-muted-foreground line-clamp-1">
											{template.description}
										</p>
									{/if}
								</Table.Cell>
								<Table.Cell>
									<span class="text-sm">{template.subject}</span>
								</Table.Cell>
								<Table.Cell>
									{#if template.category}
										<Badge variant="outline">{template.category}</Badge>
									{:else}
										<span class="text-muted-foreground">-</span>
									{/if}
								</Table.Cell>
								<Table.Cell>
									<span class="text-sm text-muted-foreground">
										{formatDate(template.updated_at)}
									</span>
								</Table.Cell>
								<Table.Cell>
									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											{#snippet child({ props })}
												<Button variant="ghost" size="icon" class="h-8 w-8" {...props}>
													<MoreHorizontal class="h-4 w-4" />
												</Button>
											{/snippet}
										</DropdownMenu.Trigger>
										<DropdownMenu.Content align="end">
											<DropdownMenu.Item onclick={() => openPreview(template)}>
												<Eye class="mr-2 h-4 w-4" />
												Preview
											</DropdownMenu.Item>
											<DropdownMenu.Item
												onclick={() => openEditor(template)}
												disabled={template.is_system}
											>
												<Pencil class="mr-2 h-4 w-4" />
												Edit
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => handleDuplicate(template)}>
												<Copy class="mr-2 h-4 w-4" />
												Duplicate
											</DropdownMenu.Item>
											<DropdownMenu.Separator />
											<DropdownMenu.Item
												class="text-destructive focus:text-destructive"
												onclick={() => confirmDelete(template)}
												disabled={template.is_system}
											>
												<Trash2 class="mr-2 h-4 w-4" />
												Delete
											</DropdownMenu.Item>
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			{/if}
		</Card.Content>
	</Card.Root>
</div>

<!-- Editor Dialog -->
<Dialog.Root bind:open={editorOpen}>
	<Dialog.Content class="max-w-4xl max-h-[90vh] overflow-y-auto">
		<Dialog.Header>
			<Dialog.Title>
				{editingTemplate ? 'Edit Email Template' : 'Create Email Template'}
			</Dialog.Title>
			<Dialog.Description>
				{editingTemplate
					? 'Modify the email template settings and content'
					: 'Create a new email template for workflow automation'}
			</Dialog.Description>
		</Dialog.Header>
		<EmailTemplateEditor
			template={editingTemplate}
			onSave={handleSaved}
			onCancel={closeEditor}
		/>
	</Dialog.Content>
</Dialog.Root>

<!-- Delete Confirmation Dialog -->
<Dialog.Root bind:open={deleteDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Delete Template</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to delete "{templateToDelete?.name}"? This action cannot be undone.
			</Dialog.Description>
		</Dialog.Header>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (deleteDialogOpen = false)} disabled={deleting}>
				Cancel
			</Button>
			<Button variant="destructive" onclick={handleDelete} disabled={deleting}>
				{#if deleting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Delete
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Preview Dialog -->
<Dialog.Root bind:open={previewDialogOpen}>
	<Dialog.Content class="max-w-3xl max-h-[90vh] overflow-y-auto">
		<Dialog.Header>
			<Dialog.Title>Preview: {previewTemplate?.name}</Dialog.Title>
		</Dialog.Header>
		{#if previewTemplate}
			<div class="space-y-4">
				<div>
					<span class="text-sm font-medium text-muted-foreground">Subject</span>
					<p class="mt-1 rounded-md border bg-muted/50 p-3">{previewTemplate.subject}</p>
				</div>
				<div>
					<span class="text-sm font-medium text-muted-foreground">Body</span>
					<div
						class="mt-1 rounded-md border bg-white p-4 prose prose-sm max-w-none"
					>
						{@html previewTemplate.body_html}
					</div>
				</div>
			</div>
		{/if}
		<Dialog.Footer>
			<Button onclick={() => (previewDialogOpen = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
